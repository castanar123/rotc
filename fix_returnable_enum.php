<?php
// Fix returnable field: Change from TINYINT(1) to ENUM and update existing data

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== UPDATING RETURNABLE FIELD TO ENUM ===\n";
    
    // Check current structure
    $stmt = $pdo->query("DESCRIBE items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasCanBeReturned = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'can_be_returned') {
            $hasCanBeReturned = true;
            echo "Current can_be_returned: {$col['Type']} {$col['Null']} {$col['Default']}\n";
            break;
        }
    }
    
    if ($hasCanBeReturned) {
        // Update existing data first: 1 -> 'returnable', 0 -> 'non-returnable'
        echo "Updating existing data...\n";
        $pdo->exec("UPDATE items SET can_be_returned = 'returnable' WHERE can_be_returned = 1 OR can_be_returned = '1'");
        $pdo->exec("UPDATE items SET can_be_returned = 'non-returnable' WHERE can_be_returned = 0 OR can_be_returned = '0'");
        
        // Change column type to ENUM
        echo "Changing column type to ENUM...\n";
        $pdo->exec("ALTER TABLE items MODIFY COLUMN can_be_returned ENUM('returnable', 'non-returnable') DEFAULT 'returnable'");
        echo "✓ Successfully changed can_be_returned to ENUM\n";
    } else {
        echo "Adding can_be_returned as ENUM column...\n";
        $pdo->exec("ALTER TABLE items ADD COLUMN can_be_returned ENUM('returnable', 'non-returnable') DEFAULT 'returnable'");
        echo "✓ Successfully added can_be_returned ENUM column\n";
    }
    
    // Verify the change
    echo "\n=== FINAL STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE items");
    while ($row = $stmt->fetch()) {
        if ($row['Field'] === 'can_be_returned') {
            echo "can_be_returned: {$row['Type']} {$row['Null']} {$row['Default']}\n";
        }
    }
    
    // Show sample data
    echo "\n=== SAMPLE DATA ===\n";
    $stmt = $pdo->query("SELECT item_name, can_be_returned FROM items LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "{$row['item_name']}: {$row['can_be_returned']}\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
