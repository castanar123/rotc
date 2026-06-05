<?php
require_once 'includes/db.php';

echo "Checking transactions table structure...\n";

try {
    $stmt = $pdo->query('DESCRIBE transactions');
    echo "Transactions table columns:\n";
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\nSample transactions data:\n";
    $stmt = $pdo->query('SELECT * FROM transactions LIMIT 3');
    while($row = $stmt->fetch()) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>