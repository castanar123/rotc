<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking all tables in rotc_db...\n";
    
    $tables_stmt = $pdo->query('SHOW TABLES');
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Available tables:\n";
    foreach($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nChecking foreign key constraints...\n";
    
    $fk_stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = 'rotc_db'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreign_keys = $fk_stmt->fetchAll();
    
    echo "Foreign key constraints:\n";
    foreach($foreign_keys as $fk) {
        echo "- {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // Check if inventory_items table exists
    echo "\nChecking if inventory_items table exists...\n";
    try {
        $check_stmt = $pdo->query('DESCRIBE inventory_items');
        echo "✓ inventory_items table exists\n";
    } catch (Exception $e) {
        echo "✗ inventory_items table does not exist\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>