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
    // Get recent attendance logs
    $stmt = $pdo->query("
        SELECT al.*, cp.first_name, cp.last_name, u.role 
        FROM attendance_logs al 
        JOIN users u ON al.user_id = u.id 
        JOIN cadet_profiles cp ON u.id = cp.user_id
        ORDER BY al.timestamp DESC 
        LIMIT 20
    ");
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for JSON response
    $formatted_logs = [];
    foreach ($recent_logs as $log) {
        $formatted_logs[] = [
            'id' => $log['id'],
            'user_id' => $log['user_id'],
            'first_name' => $log['first_name'],
            'last_name' => $log['last_name'],
            'role' => $log['role'],
            'timestamp' => $log['timestamp'],
            'method' => $log['method'] ?? 'qr_scan'
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