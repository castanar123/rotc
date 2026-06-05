<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Comparing inventory and inventory_items tables...\n\n";
    
    // Check inventory table
    echo "=== INVENTORY TABLE ===\n";
    $inventory_stmt = $pdo->query('DESCRIBE inventory');
    $inventory_columns = $inventory_stmt->fetchAll();
    
    echo "Columns:\n";
    foreach($inventory_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    $inventory_count_stmt = $pdo->query('SELECT COUNT(*) FROM inventory');
    $inventory_count = $inventory_count_stmt->fetchColumn();
    echo "Records: $inventory_count\n";
    
    if($inventory_count > 0) {
        echo "Sample data:\n";
        $sample_stmt = $pdo->query('SELECT * FROM inventory LIMIT 3');
        $samples = $sample_stmt->fetchAll();
        foreach($samples as $sample) {
            echo "- ID: {$sample['id']}, Item: {$sample['item_name']}\n";
        }
    }
    
    // Check inventory_items table
    echo "\n=== INVENTORY_ITEMS TABLE ===\n";
    $items_stmt = $pdo->query('DESCRIBE inventory_items');
    $items_columns = $items_stmt->fetchAll();
    
    echo "Columns:\n";
    foreach($items_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    $items_count_stmt = $pdo->query('SELECT COUNT(*) FROM inventory_items');
    $items_count = $items_count_stmt->fetchColumn();
    echo "Records: $items_count\n";
    
    if($items_count > 0) {
        echo "Sample data:\n";
        $items_sample_stmt = $pdo->query('SELECT * FROM inventory_items LIMIT 3');
        $items_samples = $items_sample_stmt->fetchAll();
        foreach($items_samples as $sample) {
            echo "- ID: {$sample['id']}, Name: {$sample['name']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>