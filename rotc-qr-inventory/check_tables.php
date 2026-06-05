<?php
require_once 'includes/db.php';

echo "Available tables in the database:\n";
try {
    $stmt = $pdo->query('SHOW TABLES');
    while($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}

echo "\nChecking if inventory table exists:\n";
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM inventory LIMIT 1');
    echo "Inventory table exists and has " . $stmt->fetchColumn() . " records\n";
} catch(Exception $e) {
    echo 'Inventory table error: ' . $e->getMessage() . "\n";
}
?>