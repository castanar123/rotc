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

try {
    // Parse QR data - expecting format: "USER_ID:TIMESTAMP", "USER_ID", or "PROFILE_ID"
    $parts = explode(':', $qr_data);
    $id_value = intval($parts[0]);
    
    if ($id_value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID in QR code']);
        exit;
    }
    
    // First try to find user by user_id
    $stmt = $pdo->prepare("SELECT u.id, cp.first_name, cp.last_name, u.role FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.id = ?");
    $stmt->execute([$id_value]);
    $user = $stmt->fetch();
    
    // If not found, try to find user by profile_id (for permanent QR codes)
    if (!$user) {
        $stmt = $pdo->prepare("SELECT u.id, cp.first_name, cp.last_name, u.role FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE cp.id = ?");
        $stmt->execute([$id_value]);
        $user = $stmt->fetch();
    }
    
    if (!$user) {
        // Try to get user info from cadet_profiles table for better error message
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM cadet_profiles WHERE id = ?");
        $stmt->execute([$id_value]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            echo json_encode(['success' => false, 'message' => 'Profile found but user account not linked for ' . $profile['first_name'] . ' ' . $profile['last_name']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid QR code - ID not found in system']);
        }
        exit;
    }
    
    $user_id = $user['id'];
    
    // Check if attendance already recorded today
    $stmt = $pdo->prepare("
        SELECT id FROM attendance_logs 
        WHERE user_id = ? AND DATE(timestamp) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo json_encode([
            'success' => false, 
            'message' => 'Attendance already recorded for ' . $user['first_name'] . ' ' . $user['last_name'] . ' today'
        ]);
        exit;
    }
    
    // Record attendance
    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs (user_id, timestamp, method, recorded_by) 
        VALUES (?, NOW(), 'qr_scan', ?)
    ");
    $stmt->execute([$user_id, $_SESSION['user_id']]);
    
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