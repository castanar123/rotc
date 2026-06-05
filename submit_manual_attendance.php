<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'commandant'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $attendance_date = trim($_POST['attendance_date'] ?? '');
    $td = intval($_POST['td'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        exit;
    }
    
    if (empty($attendance_date)) {
        echo json_encode(['success' => false, 'message' => 'Attendance date is required']);
        exit;
    }
    
    if ($td < 1 || $td > 15) {
        echo json_encode(['success' => false, 'message' => 'Invalid training day']);
        exit;
    }
    
    if ($semester < 1 || $semester > 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid semester']);
        exit;
    }
    
    try {
        // Verify student exists
        $stmt = $pdo->prepare("SELECT name FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // Check if attendance already exists for this date, TD, and semester
        $stmt = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND DATE(timestamp) = ? AND td = ? AND semester = ?
        ");
        $stmt->execute([$student_id, $attendance_date, $td, $semester]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo json_encode([
                'success' => false, 
                'message' => 'Attendance already recorded for ' . $student['name'] . ' on this date for TD ' . $td . ', Semester ' . $semester
            ]);
            exit;
        }
        
        // Record attendance with manual status
        $timestamp = $attendance_date . ' ' . date('H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, td, semester, timestamp, status) 
            VALUES (?, ?, ?, ?, 'present')
        ");
        $stmt->execute([$student_id, $td, $semester, $timestamp]);
        
        // Log the manual entry activity if audit_logs table exists
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, ip_address, user_agent, old_values, new_values) 
                VALUES (?, 'manual_attendance', ?, ?, NULL, ?)
            ");
            $activity_data = json_encode([
                'student_id' => $student_id,
                'student_name' => $student['name'],
                'date' => $attendance_date,
                'td' => $td,
                'semester' => $semester,
                'notes' => $notes,
                'recorded_by' => $_SESSION['user_id']
            ]);
            $stmt->execute([
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $activity_data
            ]);
        } catch (PDOException $e) {
            // Ignore if audit_logs table doesn't exist
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance recorded successfully for ' . $student['name']
        ]);
        
    } catch (PDOException $e) {
        error_log("Manual attendance submission error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>