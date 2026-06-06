<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
check_login();

// Access control: Admin only for editing
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
    exit;
}

try {
    $attendanceId = (int)$_POST['id'];
    
    // Prefer unified attendance_records
    $query = "SELECT ar.id, ar.cadet_id, ar.event_name, ar.recorded_at, ar.semester, COALESCE(ar.status,'present') AS status,
                     cp.first_name, cp.last_name, cp.student_id,
                     CONCAT(cp.first_name, ' ', cp.last_name) AS full_name
              FROM attendance_records ar
              JOIN cadet_profiles cp ON ar.cadet_id = cp.id
              WHERE ar.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$attendanceId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
        exit;
    }
    
    // Parse pieces and format for editing
    $dt = strtotime($record['recorded_at']);
    $tdParsed = '';
    if (!empty($record['event_name']) && preg_match('/(\d+)\s*TD/i', $record['event_name'], $m)) {
        $tdParsed = $m[1];
    }
    $formattedRecord = [
        'id' => $record['id'],
        'cadet_id' => $record['cadet_id'],
        'cadet_name' => $record['full_name'],
        'student_id' => $record['student_id'],
        'event_name' => $record['event_name'],
        'time' => date('H:i:s', $dt),
        'date' => date('Y-m-d', $dt),
        'td' => $tdParsed,
        'semester' => $record['semester'],
        'status' => $record['status']
    ];
    
    echo json_encode([
        'success' => true,
        'record' => $formattedRecord
    ]);
    
} catch (Exception $e) {
    error_log("Get attendance record error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
