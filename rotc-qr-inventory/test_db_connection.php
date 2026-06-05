<?php
require_once 'includes/db.php';

echo "Database connection test: ";
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM items');
    echo "SUCCESS - Items table has " . $stmt->fetchColumn() . " records\n";
    
    // Test inventory table
    $stmt = $pdo->query('SELECT COUNT(*) FROM inventory');
    echo "Inventory table has " . $stmt->fetchColumn() . " records\n";
    
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>