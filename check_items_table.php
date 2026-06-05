<?php
require_once 'includes/db.php';

echo "=== CHECKING ITEMS TABLE STRUCTURE ===\n\n";

try {
    // Check if items table exists and its structure
    $stmt = $pdo->query("SHOW TABLES LIKE 'items'");
    if ($stmt->rowCount() > 0) {
        echo "✓ 'items' table exists\n";
        
        // Show columns
        $stmt = $pdo->query("SHOW COLUMNS FROM items");
        $columns = $stmt->fetchAll();
        echo "Columns in 'items' table:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "❌ 'items' table does not exist\n";
    }
    
    // Check inventory_items table
    echo "\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_items'");
    if ($stmt->rowCount() > 0) {
        echo "✓ 'inventory_items' table exists\n";
        
        // Show columns
        $stmt = $pdo->query("SHOW COLUMNS FROM inventory_items");
        $columns = $stmt->fetchAll();
        echo "Columns in 'inventory_items' table:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        
        // Check if it has data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory_items");
        $count = $stmt->fetch()['count'];
        echo "Records in inventory_items: {$count}\n";
    } else {
        echo "❌ 'inventory_items' table does not exist\n";
    }
    
    // Check which table is actually being used
    echo "\n=== CHECKING WHICH TABLE HAS DATA ===\n";
    
    $tables_to_check = ['items', 'inventory_items'];
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
            $count = $stmt->fetch()['count'];
            echo "{$table}: {$count} records\n";
            
            if ($count > 0) {
                // Show sample data
                $stmt = $pdo->query("SELECT * FROM `{$table}` LIMIT 3");
                $samples = $stmt->fetchAll();
                echo "Sample data from {$table}:\n";
                foreach ($samples as $sample) {
                    echo "  ID: {$sample['id']} | Name: " . ($sample['item_name'] ?? 'N/A') . " | Category: " . ($sample['category'] ?? 'N/A') . "\n";
                }
            }
        } catch (Exception $e) {
            echo "{$table}: Error - {$e->getMessage()}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECK COMPLETE ===\n";
?>
