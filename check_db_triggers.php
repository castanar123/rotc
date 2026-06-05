<?php
// Check for database triggers that might be affecting supply insertion

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING DATABASE TRIGGERS AND CONSTRAINTS ===\n\n";
    
    // Check for triggers on items table
    echo "1. TRIGGERS ON ITEMS TABLE:\n";
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'items'");
    $triggers = $stmt->fetchAll();
    if (empty($triggers)) {
        echo "   No triggers found.\n";
    } else {
        foreach ($triggers as $trigger) {
            echo "   - {$trigger['Trigger']}: {$trigger['Event']} {$trigger['Timing']}\n";
        }
    }
    
    // Check table constraints
    echo "\n2. TABLE CONSTRAINTS:\n";
    $stmt = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'rotc_db' AND TABLE_NAME = 'items' ORDER BY ORDINAL_POSITION");
    while ($row = $stmt->fetch()) {
        echo "   - {$row['COLUMN_NAME']}: {$row['COLUMN_TYPE']} NULL={$row['IS_NULLABLE']} DEFAULT={$row['COLUMN_DEFAULT']}\n";
    }
    
    // Check for any stored procedures
    echo "\n3. STORED PROCEDURES:\n";
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = 'rotc_db'");
    $procedures = $stmt->fetchAll();
    if (empty($procedures)) {
        echo "   No stored procedures found.\n";
    } else {
        foreach ($procedures as $proc) {
            echo "   - {$proc['Name']}\n";
        }
    }
    
    // Test a simple insert to see what happens
    echo "\n4. TEST SIMPLE INSERT:\n";
    $testName = "Direct Test " . time();
    echo "Inserting: name='$testName', category='supplies', total=25, available=25\n";
    
    $stmt = $pdo->prepare("INSERT INTO items (item_name, category, total_quantity, available_quantity) VALUES (?, ?, ?, ?)");
    $stmt->execute([$testName, 'supplies', 25, 25]);
    
    $insertId = $pdo->lastInsertId();
    echo "Insert ID: $insertId\n";
    
    // Check what was actually inserted
    $stmt = $pdo->prepare("SELECT item_name, category, total_quantity, available_quantity FROM items WHERE id = ?");
    $stmt->execute([$insertId]);
    $result = $stmt->fetch();
    
    echo "Result: name='{$result['item_name']}', category='{$result['category']}', total={$result['total_quantity']}, available={$result['available_quantity']}\n";
    
    // Check if there are any ENUMs on category
    echo "\n5. CATEGORY COLUMN DETAILS:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM items LIKE 'category'");
    $categoryInfo = $stmt->fetch();
    if ($categoryInfo) {
        echo "   Type: {$categoryInfo['Type']}\n";
        echo "   Null: {$categoryInfo['Null']}\n";
        echo "   Default: {$categoryInfo['Default']}\n";
        echo "   Extra: {$categoryInfo['Extra']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
