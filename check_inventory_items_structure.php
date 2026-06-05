<?php
// Check structure of inventory_items table

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== INVENTORY_ITEMS TABLE STRUCTURE ===\n";
    
    $stmt = $pdo->query("DESCRIBE inventory_items");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - Null: {$column['Null']} - Default: {$column['Default']}\n";
    }
    
    echo "\n=== SAMPLE DATA FROM INVENTORY_ITEMS ===\n";
    $stmt = $pdo->query("SELECT * FROM inventory_items LIMIT 3");
    $items = $stmt->fetchAll();
    
    if ($items) {
        foreach ($items as $item) {
            echo "Item ID: {$item['id']}\n";
            foreach ($item as $key => $value) {
                if (!is_numeric($key)) {
                    echo "  {$key}: {$value}\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "No items found in inventory_items table\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>