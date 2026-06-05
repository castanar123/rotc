<?php
require_once 'includes/db.php';

echo "=== Checking attendance_logs table structure ===\n\n";

try {
    $stmt = $pdo->query("DESCRIBE attendance_logs");
    $columns = $stmt->fetchAll();
    
    echo "Columns in attendance_logs table:\n";
    foreach ($columns as $column) {
        echo "  {$column['Field']} - {$column['Type']}\n";
    }
    
    echo "\n=== Sample data from attendance_logs ===\n";
    $stmt = $pdo->query("SELECT * FROM attendance_logs LIMIT 3");
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "No attendance logs found\n";
    } else {
        foreach ($logs as $log) {
            echo "Log ID: {$log['id']}\n";
            foreach ($log as $key => $value) {
                if (!is_numeric($key)) {
                    echo "  {$key}: {$value}\n";
                }
            }
            echo "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}