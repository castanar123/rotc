<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Syncing data from inventory to inventory_items...\n";
    
    // First, check if inventory_items is empty
    $count_stmt = $pdo->query('SELECT COUNT(*) FROM inventory_items');
    $count = $count_stmt->fetchColumn();
    
    if($count > 0) {
        echo "inventory_items already has $count records. Clearing first...\n";
        $pdo->exec('DELETE FROM inventory_items');
    }
    
    // Copy data from inventory to inventory_items
    $copy_stmt = $pdo->prepare("
        INSERT INTO inventory_items (
            item_code, item_name, category, description, 
            total_quantity, available_quantity, borrowed_quantity,
            unit, location, condition_status, minimum_stock, created_at, updated_at
        )
        SELECT 
            item_code, item_name, category, description,
            total_quantity, available_quantity, borrowed_quantity,
            unit, location, condition_status, 10 as minimum_stock, created_at, updated_at
        FROM inventory
    ");
    
    $copy_stmt->execute();
    $copied_count = $copy_stmt->rowCount();
    
    echo "✓ Copied $copied_count records from inventory to inventory_items\n";
    
    // Verify the copy
    $verify_stmt = $pdo->query('SELECT COUNT(*) FROM inventory_items');
    $verify_count = $verify_stmt->fetchColumn();
    
    echo "✓ inventory_items now has $verify_count records\n";
    
    // Show sample data
    echo "\nSample inventory_items data:\n";
    $sample_stmt = $pdo->query('SELECT id, item_name, total_quantity FROM inventory_items LIMIT 3');
    $samples = $sample_stmt->fetchAll();
    
    foreach($samples as $sample) {
        echo "- ID: {$sample['id']}, Item: {$sample['item_name']}, Qty: {$sample['total_quantity']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>