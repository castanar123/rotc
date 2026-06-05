<?php
session_start();
require_once 'includes/db.php';

echo "Testing supply functionality...\n";

// Set a dummy duty officer ID
$_SESSION['duty_officer_id'] = 1;

echo "\nTesting direct supply insertion...\n";

try {
    // Test adding a supply item directly
    $item_name = 'Test Rifle';
    $quantity = 5;
    $unit = 'pcs';
    $description = 'M16A2 Training Rifle';
    $location = 'Armory A';
    
    // Check if item already exists
    $checkSql = "SELECT id, total_quantity, available_quantity FROM items WHERE item_name = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$item_name]);
    $existingItem = $checkStmt->fetch();
    
    if ($existingItem) {
        echo "Item already exists. Updating quantities...\n";
        $updateSql = "UPDATE items SET total_quantity = total_quantity + ?, available_quantity = available_quantity + ? WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$quantity, $quantity, $existingItem['id']]);
        echo "Updated item quantities successfully.\n";
    } else {
        echo "Creating new item...\n";
        $sql = "INSERT INTO items (item_name, description, total_quantity, available_quantity, borrowed_quantity, unit, location, condition_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, ?, ?, 'Good', NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_name, $description, $quantity, $quantity, $unit, $location]);
        echo "Created new item successfully.\n";
    }
    
    // Log the transaction
    $logSql = "INSERT INTO transactions (transaction_type, item_id, quantity, officer_id, officer_name, transaction_date, notes)
               VALUES ('supply', (SELECT id FROM items WHERE item_name = ?), ?, ?, 'Test Officer', NOW(), 'Test supply transaction')";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([$item_name, $quantity, $_SESSION['duty_officer_id']]);
    echo "Transaction logged successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking items table after supply...\n";
$stmt = $pdo->query("SELECT * FROM items WHERE item_name = 'Test Rifle'");
$item = $stmt->fetch();
if ($item) {
    echo "Item found in database:\n";
    echo "ID: " . $item['id'] . "\n";
    echo "Name: " . $item['item_name'] . "\n";
    echo "Total Quantity: " . $item['total_quantity'] . "\n";
    echo "Available Quantity: " . $item['available_quantity'] . "\n";
    echo "Unit: " . $item['unit'] . "\n";
} else {
    echo "Item not found in database.\n";
}
?>