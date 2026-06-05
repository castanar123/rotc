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

try {
    // Get all students from cadet_profiles table
    $stmt = $pdo->query("
        SELECT 
            student_id, 
            CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) as name
        FROM cadet_profiles 
        WHERE student_id IS NOT NULL AND student_id != ''
        ORDER BY first_name, last_name ASC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    
} catch (PDOException $e) {
    error_log("Get students error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>