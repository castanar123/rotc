<?php
require_once 'includes/db.php';

try {
    echo "Checking rifles table structure...\n\n";
    
    $stmt = $pdo->query('DESCRIBE rifles');
    $columns = $stmt->fetchAll();
    
    echo "Rifles table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\nTotal columns: " . count($columns) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>