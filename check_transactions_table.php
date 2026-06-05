<?php
// Check transactions table structure

try {
    $pdo = new PDO('mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Transactions Table Structure ===\n";
    $result = $pdo->query('DESCRIBE transactions');
    while ($row = $result->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\n=== Transaction Items Table Structure ===\n";
    $result = $pdo->query('DESCRIBE transaction_items');
    while ($row = $result->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\n=== Borrowed Items Table Structure ===\n";
    $result = $pdo->query('DESCRIBE borrowed_items');
    while ($row = $result->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>