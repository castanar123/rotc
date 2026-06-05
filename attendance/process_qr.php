<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['qr_data'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid QR data']);
    exit;
}

$qr_data = $input['qr_data'];
$timestamp = isset($input['timestamp']) ? $input['timestamp'] : date('Y-m-d H:i:s');

// Helper: derive school year and semester from timestamp
function computeSchoolYearSemester($timestamp) {
    $dt = new DateTime($timestamp);
    $year = (int)$dt->format('Y');
    $month = (int)$dt->format('n');
    if ($month >= 8) {
        // 1st semester, SY current-year to next-year
        $schoolYear = sprintf('%d-%d', $year, $year + 1);
        $semester = '1';
    } else {
        // 2nd semester, SY prev-year to current-year
        $schoolYear = sprintf('%d-%d', $year - 1, $year);
        $semester = '2';
    }
    return [$schoolYear, $semester];
}

// Helper: ensure attendance_records exists (idempotent)
function ensureAttendanceRecordsTable(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cadet_id INT NULL,
        cadet_name VARCHAR(255) NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        school_year VARCHAR(20) NOT NULL,
        semester VARCHAR(20) NOT NULL,
        event_name VARCHAR(255) NOT NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        recorded_by INT DEFAULT 1,
        status ENUM('present', 'absent', 'late') DEFAULT 'present',
        notes TEXT,
        INDEX idx_cadet_id (cadet_id),
        INDEX idx_student_id (student_id),
        INDEX idx_event (event_name),
        INDEX idx_sy_sem (school_year, semester),
        INDEX idx_recorded_at (recorded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

try {
    // Parse QR data - expecting format: "USER_ID:TIMESTAMP", "USER_ID", or "PROFILE_ID"
    $parts = explode(':', $qr_data);
    $id_value = intval($parts[0]);
    
    if ($id_value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID in QR code']);
        exit;
    }
    
    // First try to find user by user_id (only approved users)
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ? AND approval_status = 'approved' AND status = 'active'");
    $stmt->execute([$id_value]);
    $user = $stmt->fetch();
    
    // If not found, try to find user by profile_id (for permanent QR codes, only approved users)
    if (!$user) {
        $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.role FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE cp.id = ? AND u.approval_status = 'approved' AND u.status = 'active'");
        $stmt->execute([$id_value]);
        $user = $stmt->fetch();
    }
    
    if (!$user) {
        // Check if user exists but is not approved
        $stmt = $pdo->prepare("SELECT first_name, last_name, approval_status, status FROM users WHERE id = ?");
        $stmt->execute([$id_value]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            if ($existing_user['approval_status'] !== 'approved') {
                echo json_encode(['success' => false, 'message' => 'User ' . $existing_user['first_name'] . ' ' . $existing_user['last_name'] . ' is not approved for attendance']);
            } else if ($existing_user['status'] !== 'active') {
                echo json_encode(['success' => false, 'message' => 'User ' . $existing_user['first_name'] . ' ' . $existing_user['last_name'] . ' is not active']);
            } else {
                echo json_encode(['success' => false, 'message' => 'User found but cannot record attendance']);
            }
        } else {
            // Try to get user info from cadet_profiles table for better error message
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM cadet_profiles WHERE id = ?");
            $stmt->execute([$id_value]);
            $profile = $stmt->fetch();
            
            if ($profile) {
                echo json_encode(['success' => false, 'message' => 'Profile found but user account not linked for ' . $profile['first_name'] . ' ' . $profile['last_name']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid QR code - ID not found in system']);
            }
        }
        exit;
    }
    
    $user_id = $user['id'];
    
    // Get cadet profile for this user
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, student_id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cadet_profile = $stmt->fetch();
    
    if (!$cadet_profile) {
        echo json_encode(['success' => false, 'message' => 'No cadet profile found for this user']);
        exit;
    }
    
    $cadet_profile_id = $cadet_profile['id'];
    $cadet_full_name = trim(($cadet_profile['first_name'] ?? '') . ' ' . ($cadet_profile['last_name'] ?? ''));
    $cadet_student_id = $cadet_profile['student_id'] ?? '';
    
    // Check if attendance already recorded today
    $stmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE cadet_profile_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$cadet_profile_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Attendance already recorded today']);
        exit;
    }
    
    // Record attendance
    $stmt = $pdo->prepare("INSERT INTO attendance_logs (cadet_profile_id, event_name, event_date, time_in, status, logged_by_user_id) VALUES (?, 'Daily Attendance', CURDATE(), CURTIME(), 'present', ?)");
    $stmt->execute([$cadet_profile_id, $user_id]);
    
    // Also mirror into attendance_records for analytics compatibility
    try {
        ensureAttendanceRecordsTable($pdo);
        [$school_year, $semester] = computeSchoolYearSemester($timestamp);
        $event_name = 'Daily Attendance';
        
        // Avoid duplicate record for the same cadet/date/event
        $dupCheck = $pdo->prepare("SELECT id FROM attendance_records 
                                   WHERE cadet_id = ? AND event_name = ? AND semester = ? AND DATE(recorded_at) = DATE(?)");
        $dupCheck->execute([$cadet_profile_id, $event_name, $semester, $timestamp]);
        if (!$dupCheck->fetch()) {
            $ins = $pdo->prepare("INSERT INTO attendance_records 
                (cadet_id, cadet_name, student_id, school_year, semester, event_name, recorded_at, recorded_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'present')");
            $ins->execute([
                $cadet_profile_id,
                $cadet_full_name !== '' ? $cadet_full_name : 'Unknown',
                $cadet_student_id !== '' ? $cadet_student_id : 'N/A',
                $school_year,
                $semester,
                $event_name,
                $timestamp,
                $_SESSION['user_id'] ?? $user_id
            ]);
        }
    } catch (Throwable $mirrorErr) {
        // Non-fatal: continue if attendance_records is unavailable
        error_log('attendance_records mirror failed: ' . $mirrorErr->getMessage());
    }
    
    // Log the activity
    $activity_stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, details, timestamp) 
        VALUES (?, 'attendance_recorded', ?, NOW())
    ");
    $activity_details = json_encode([
        'method' => 'qr_scan',
        'recorded_by' => $_SESSION['user_id'],
        'target_user' => $user_id,
        'target_name' => $user['first_name'] . ' ' . $user['last_name']
    ]);
    $activity_stmt->execute([$_SESSION['user_id'], $activity_details]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Attendance recorded successfully for ' . $user['first_name'] . ' ' . $user['last_name'],
        'user' => [
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'role' => $user['role']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("QR processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("QR processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing attendance']);
}
?>