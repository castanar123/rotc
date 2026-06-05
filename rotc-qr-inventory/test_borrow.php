<?php
session_start();
require_once 'includes/db.php';

echo "Testing borrow functionality...\n";

// Set a dummy duty officer ID
$_SESSION['duty_officer_id'] = 1;

echo "\nTesting direct borrow operation...\n";

try {
    // Test borrowing an item
    $item_name = 'Test Rifle';
    $quantity = 2;
    $borrower_name = 'Test Cadet';
    $purpose = 'Training Exercise';
    
    // Check if item exists and has sufficient quantity
    $checkSql = "SELECT id, available_quantity FROM items WHERE item_name = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$item_name]);
    $item = $checkStmt->fetch();
    
    if (!$item) {
        throw new Exception("Item not found: $item_name");
    }
    
    if ($item['available_quantity'] < $quantity) {
        throw new Exception("Insufficient quantity. Available: {$item['available_quantity']}, Requested: $quantity");
    }
    
    echo "Item found with sufficient quantity. Proceeding with borrow...\n";
    
    // Update item quantities
    $updateSql = "UPDATE items SET available_quantity = available_quantity - ?, borrowed_quantity = borrowed_quantity + ? WHERE id = ?";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([$quantity, $quantity, $item['id']]);
    echo "Updated item quantities successfully.\n";
    
    // Create transaction record
    $transaction_id = 'BOR_' . date('YmdHis') . '_' . rand(1000, 9999);
    $transactionSql = "INSERT INTO transactions (transaction_id, type, duty_officer_id, borrower_name, purpose, status, notes, created_at, updated_at)
                      VALUES (?, 'borrow', ?, ?, ?, 'active', 'Test borrow transaction', NOW(), NOW())";
    $transactionStmt = $pdo->prepare($transactionSql);
    $transactionStmt->execute([$transaction_id, $_SESSION['duty_officer_id'], $borrower_name, $purpose]);
    $transaction_db_id = $pdo->lastInsertId();
    echo "Transaction record created successfully. ID: $transaction_db_id\n";
    
    // Create borrowed items record
    $borrowedSql = "INSERT INTO borrowed_items (transaction_id, item_id, quantity, borrowed_date, expected_return_date, status)
                   VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'borrowed')";
    $borrowedStmt = $pdo->prepare($borrowedSql);
    $borrowedStmt->execute([$transaction_db_id, $item['id'], $quantity]);
    echo "Borrowed items record created successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking items table after borrow...\n";
$stmt = $pdo->query("SELECT * FROM items WHERE item_name = 'Test Rifle'");
$item = $stmt->fetch();
if ($item) {
    echo "Item status after borrow:\n";
    echo "ID: " . $item['id'] . "\n";
    echo "Name: " . $item['item_name'] . "\n";
    echo "Total Quantity: " . $item['total_quantity'] . "\n";
    echo "Available Quantity: " . $item['available_quantity'] . "\n";
    echo "Borrowed Quantity: " . $item['borrowed_quantity'] . "\n";
}

echo "\nChecking borrowed_items table...\n";
$stmt = $pdo->query("SELECT * FROM borrowed_items ORDER BY id DESC LIMIT 1");
$borrowed = $stmt->fetch();
if ($borrowed) {
    echo "Latest borrowed item record:\n";
    print_r($borrowed);
}
?>