<?php
// Debug data in database
require_once 'db.php';

header('Content-Type: application/json');

try {
    $result = [];
    
    // Check cadet_profiles data
    $stmt = $pdo->prepare("SELECT cp.*, u.role, u.status as user_status FROM cadet_profiles cp LEFT JOIN users u ON cp.user_id = u.id LIMIT 10");
    $stmt->execute();
    $result['cadet_profiles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check users data
    $stmt = $pdo->prepare("SELECT id, username, role, status FROM users LIMIT 10");
    $stmt->execute();
    $result['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check attendance data
    $stmt = $pdo->prepare("SELECT * FROM attendance LIMIT 10");
    $stmt->execute();
    $result['attendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check specific query that should return cadets
    $stmt = $pdo->prepare("
        SELECT 
            cp.student_id,
            cp.first_name,
            cp.last_name,
            cp.gender,
            cp.platoon,
            cp.status as cadet_status,
            u.role,
            u.status as user_status
        FROM cadet_profiles cp
        LEFT JOIN users u ON cp.user_id = u.id
        WHERE u.role IN ('basic_cadet', '2cl', '1cl') 
            AND u.status = 'active'
            AND cp.status = 'Active'
    ");
    $stmt->execute();
    $result['active_cadets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check what roles and statuses exist
    $stmt = $pdo->prepare("SELECT DISTINCT role FROM users");
    $stmt->execute();
    $result['available_roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("SELECT DISTINCT status FROM users");
    $stmt->execute();
    $result['available_user_statuses'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("SELECT DISTINCT status FROM cadet_profiles");
    $stmt->execute();
    $result['available_cadet_statuses'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $result = [
        'error' => $e->getMessage()
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>