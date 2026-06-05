<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // Get recent attendance from attendance_records (joined for names/roles)
    $stmt = $pdo->query("
        SELECT a.id, a.recorded_at AS timestamp, cp.id AS cadet_profile_id,
               u.first_name, u.last_name, u.role
        FROM attendance_records a
        JOIN cadet_profiles cp ON a.cadet_id = cp.id
        JOIN users u ON cp.user_id = u.id
        WHERE u.approval_status = 'approved' AND u.status = 'active'
        ORDER BY a.recorded_at DESC
        LIMIT 20
    ");
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for JSON response
    $formatted_logs = [];
    foreach ($recent_logs as $log) {
        $formatted_logs[] = [
            'id' => $log['id'],
            'user_id' => $log['cadet_profile_id'],
            'first_name' => $log['first_name'],
            'last_name' => $log['last_name'],
            'role' => $log['role'],
            'timestamp' => $log['timestamp'],
            'method' => 'qr_scan'
        ];
    }
    
    echo json_encode($formatted_logs);
    
} catch (PDOException $e) {
    error_log("Get recent logs error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Get recent logs error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while fetching logs']);
}
?>