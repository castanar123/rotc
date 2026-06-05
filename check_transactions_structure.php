<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking transactions table structure...\n";
    
    $stmt = $pdo->query('DESCRIBE transactions');
    $columns = $stmt->fetchAll();
    
    echo "Transactions table columns:\n";
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Check if table has any data
    $count_stmt = $pdo->query('SELECT COUNT(*) FROM transactions');
    $count = $count_stmt->fetchColumn();
    echo "\nTotal records: $count\n";
    
    // Also check transaction_items table
    echo "\nChecking transaction_items table structure...\n";
    
    $items_stmt = $pdo->query('DESCRIBE transaction_items');
    $items_columns = $items_stmt->fetchAll();
    
    echo "Transaction_items table columns:\n";
    foreach($items_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    $items_count_stmt = $pdo->query('SELECT COUNT(*) FROM transaction_items');
    $items_count = $items_count_stmt->fetchColumn();
    echo "\nTotal transaction_items records: $items_count\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}