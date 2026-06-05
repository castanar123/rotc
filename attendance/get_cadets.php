<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Get all cadets with their information
    $stmt = $pdo->prepare("
        SELECT 
            cp.id as cadet_id,
            CONCAT(cp.first_name, ' ', cp.last_name) as full_name,
            cp.student_id
        FROM cadet_profiles cp
        ORDER BY cp.last_name, cp.first_name
    ");
    
    $stmt->execute();
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'cadets' => $cadets
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>