<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Structure of 'inventory' table:\n";
    $stmt = $pdo->query('DESCRIBE inventory');
    while($row = $stmt->fetch()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nSample data from 'inventory' table:\n";
    $stmt = $pdo->query('SELECT * FROM inventory LIMIT 5');
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        foreach($rows as $row) {
            print_r($row);
        }
    } else {
        echo "No data found in inventory table\n";
    }
    
    echo "\nStructure of 'items' table:\n";
    $stmt = $pdo->query('DESCRIBE items');
    while($row = $stmt->fetch()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nSample data from 'items' table:\n";
    $stmt = $pdo->query('SELECT * FROM items LIMIT 5');
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        foreach($rows as $row) {
            print_r($row);
        }
    } else {
        echo "No data found in items table\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>