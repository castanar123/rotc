<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking inventory table structure...\n";
    
    $stmt = $pdo->query('DESCRIBE inventory');
    $columns = $stmt->fetchAll();
    
    echo "Inventory table columns:\n";
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Check if table has any data
    $count_stmt = $pdo->query('SELECT COUNT(*) FROM inventory');
    $count = $count_stmt->fetchColumn();
    echo "\nTotal records: $count\n";
    
    // Show sample data if exists
    if ($count > 0) {
        echo "\nSample data (first 3 records):\n";
        $sample_stmt = $pdo->query('SELECT * FROM inventory LIMIT 3');
        $samples = $sample_stmt->fetchAll();
        
        foreach($samples as $sample) {
            echo "Record ID: {$sample['id']}\n";
            foreach($sample as $key => $value) {
                if (!is_numeric($key)) {
                    echo "  $key: $value\n";
                }
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>