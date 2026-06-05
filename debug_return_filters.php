<?php
require_once 'rotc-qr-inventory/includes/db.php';

echo "=== DEBUGGING RETURN FILTERS AND BORROWED ITEMS ===\n\n";

try {
    // Test the borrowed items API directly
    echo "1. Testing borrowed items query without filters...\n";
    
    // Simulate the API call
    $_GET['action'] = 'get_borrowed';
    $_GET['category'] = '';
    $_GET['borrower_id'] = '';
    
    // Include the API file to test
    ob_start();
    include 'rotc-qr-inventory/api/borrowed_items.php';
    $output = ob_get_clean();
    
    echo "API Response: " . $output . "\n\n";
    
    // Test with category filter
    echo "2. Testing with category filter 'Equipment'...\n";
    $_GET['category'] = 'Equipment';
    
    ob_start();
    include 'rotc-qr-inventory/api/borrowed_items.php';
    $output = ob_get_clean();
    
    echo "API Response with category filter: " . $output . "\n\n";
    
    // Check what borrowed items exist in database
    echo "3. Checking database for borrowed items...\n";
    
    // Check transactions table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'borrowed'");
    $transactionCount = $stmt->fetch()['count'];
    echo "Transactions with status 'borrowed': {$transactionCount}\n";
    
    if ($transactionCount > 0) {
        $stmt = $pdo->query("SELECT id, borrower_id, purpose, created_at FROM transactions WHERE status = 'borrowed' LIMIT 3");
        $transactions = $stmt->fetchAll();
        foreach ($transactions as $t) {
            echo "  Transaction ID {$t['id']}: Borrower {$t['borrower_id']}, Purpose: {$t['purpose']}, Date: {$t['created_at']}\n";
        }
    }
    
    // Check borrowed_items table
    echo "\nChecking borrowed_items table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'borrowed_items'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM borrowed_items");
        $borrowedCount = $stmt->fetch()['count'];
        echo "Records in borrowed_items: {$borrowedCount}\n";
        
        if ($borrowedCount > 0) {
            $stmt = $pdo->query("SELECT * FROM borrowed_items LIMIT 3");
            $borrowed = $stmt->fetchAll();
            foreach ($borrowed as $b) {
                echo "  Borrowed Item ID {$b['id']}: Item {$b['item_id']}, Quantity: " . ($b['quantity_borrowed'] ?? $b['quantity'] ?? 'N/A') . "\n";
            }
        }
    } else {
        echo "borrowed_items table does not exist\n";
    }
    
    // Check items table for category column
    echo "\nChecking items table structure...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'items'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM items");
        $columns = $stmt->fetchAll();
        $hasCategory = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'category') {
                $hasCategory = true;
                break;
            }
        }
        echo "Items table has 'category' column: " . ($hasCategory ? 'YES' : 'NO') . "\n";
        
        // Check inventory_items table
        $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_items'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SHOW COLUMNS FROM inventory_items");
            $columns = $stmt->fetchAll();
            $hasItemCategory = false;
            foreach ($columns as $col) {
                if ($col['Field'] === 'item_category') {
                    $hasItemCategory = true;
                    break;
                }
            }
            echo "Inventory_items table has 'item_category' column: " . ($hasItemCategory ? 'YES' : 'NO') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
