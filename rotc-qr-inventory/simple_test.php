<?php
require_once 'includes/db.php';

echo "=== INVENTORY SYSTEM TEST ===\n";

// Test 1: Check items table
echo "\n1. Checking items in inventory:\n";
$stmt = $pdo->query("SELECT item_name, available_quantity, borrowed_quantity FROM items LIMIT 5");
while($row = $stmt->fetch()) {
    echo "- {$row['item_name']}: Available={$row['available_quantity']}, Borrowed={$row['borrowed_quantity']}\n";
}

// Test 2: Check if we can add a supply item
echo "\n2. Testing supply addition:\n";
try {
    $pdo->exec("INSERT INTO items (item_name, total_quantity, available_quantity, borrowed_quantity, unit) VALUES ('Test Item', 5, 5, 0, 'pcs')");
    echo "✓ Supply item added successfully\n";
} catch (Exception $e) {
    echo "✗ Supply failed: " . $e->getMessage() . "\n";
}

// Test 3: Check if we can borrow an item
echo "\n3. Testing borrow operation:\n";
try {
    $pdo->exec("UPDATE items SET available_quantity = available_quantity - 1, borrowed_quantity = borrowed_quantity + 1 WHERE item_name = 'Test Item'");
    echo "✓ Borrow operation successful\n";
} catch (Exception $e) {
    echo "✗ Borrow failed: " . $e->getMessage() . "\n";
}

// Test 4: Check if we can return an item
echo "\n4. Testing return operation:\n";
try {
    $pdo->exec("UPDATE items SET available_quantity = available_quantity + 1, borrowed_quantity = borrowed_quantity - 1 WHERE item_name = 'Test Item'");
    echo "✓ Return operation successful\n";
} catch (Exception $e) {
    echo "✗ Return failed: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>