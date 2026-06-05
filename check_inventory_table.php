<?php
// Check if inventory table exists in rotc_db

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING INVENTORY TABLE IN ROTC_DB ===\n";
    
    // Check if inventory table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Inventory table exists\n\n";
        
        // Show table structure
        echo "INVENTORY TABLE STRUCTURE:\n";
        $stmt = $pdo->query("DESCRIBE inventory");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
        }
        
        echo "\n=== SAMPLE DATA ===\n";
        $stmt = $pdo->query("SELECT * FROM inventory LIMIT 3");
        $items = $stmt->fetchAll();
        
        if ($items) {
            foreach ($items as $item) {
                echo "Item ID: {$item['id']}\n";
                foreach ($item as $key => $value) {
                    echo "  {$key}: {$value}\n";
                }
                echo "\n";
            }
        } else {
            echo "No items found in inventory table\n";
        }
        
    } else {
        echo "✗ Inventory table does NOT exist\n";
        
        echo "\nAvailable tables in rotc_db:\n";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        foreach ($tables as $table) {
            echo "- {$table[0]}\n";
        }
        
        echo "\n=== CHECKING FOR SIMILAR TABLES ===\n";
        foreach ($tables as $table) {
            if (strpos(strtolower($table[0]), 'item') !== false || strpos(strtolower($table[0]), 'inventory') !== false) {
                echo "Found similar table: {$table[0]}\n";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>