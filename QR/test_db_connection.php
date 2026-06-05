<?php
// Test database connection and table structure
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Test database connection
    if (isset($db_connection_failed) && $db_connection_failed) {
        throw new Exception('Database connection failed');
    }
    
    $result = [
        'success' => true,
        'connection' => 'OK',
        'tables' => []
    ];
    
    // Check required tables
    $required_tables = ['cadet_profiles', 'attendance', 'users', 'training_days', 'scanner_sessions'];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->rowCount() > 0;
        
        $result['tables'][$table] = [
            'exists' => $exists,
            'status' => $exists ? 'OK' : 'MISSING'
        ];
        
        if ($exists) {
            // Get row count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['tables'][$table]['count'] = $count['count'];
        }
    }
    
    // Test specific query that dashboard uses
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_strength,
            COUNT(a.id) as total_present
        FROM cadet_profiles s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN attendance a ON s.student_id = a.student_id 
            AND a.td = ? 
            AND a.semester = ? 
            AND DATE(a.timestamp) = ?
        WHERE u.role IN ('basic_cadet', '2cl', '1cl') 
            AND u.status = 'approved'
            AND s.status = 'Active'
    ");
    $stmt->execute([1, 1, '2025-08-08']);
    $test_query = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['test_query'] = [
        'success' => true,
        'total_strength' => $test_query['total_strength'],
        'total_present' => $test_query['total_present']
    ];
    
} catch (Exception $e) {
    $result = [
        'success' => false,
        'error' => $e->getMessage(),
        'connection' => 'FAILED'
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>