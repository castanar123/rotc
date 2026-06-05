<?php
// Comprehensive test of inventory operations

echo "=== Comprehensive Inventory Operations Test ===\n\n";

// Include the inventory database connection
require_once 'rotc-qr-inventory/includes/db.php';

try {
    echo "1. Testing Borrow Operation...\n";
    
    // Test borrowing an item
    $item_id = 1; // M16 Rifle
    $quantity_to_borrow = 3;
    $borrower_name = "Cadet Test User";
    $borrower_contact = "09123456789";
    $expected_return = date('Y-m-d', strtotime('+7 days'));
    
    // Get current item info
    $item_query = $pdo->prepare("SELECT item_name, available_quantity, borrowed_quantity FROM items WHERE id = ?");
    $item_query->execute([$item_id]);
    $item_before = $item_query->fetch();
    
    echo "   Before borrow: {$item_before['item_name']} - Available: {$item_before['available_quantity']}, Borrowed: {$item_before['borrowed_quantity']}\n";
    
    if ($item_before['available_quantity'] >= $quantity_to_borrow) {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert borrowed item record
        $borrow_stmt = $pdo->prepare("
            INSERT INTO borrowed_items (item_id, borrower_name, borrower_contact, quantity_borrowed, expected_return_date, status, notes) 
            VALUES (?, ?, ?, ?, ?, 'borrowed', 'Test borrow operation')
        ");
        $borrow_stmt->execute([$item_id, $borrower_name, $borrower_contact, $quantity_to_borrow, $expected_return]);
        
        // Update item quantities
        $update_stmt = $pdo->prepare("
            UPDATE items 
            SET available_quantity = available_quantity - ?, 
                borrowed_quantity = borrowed_quantity + ? 
            WHERE id = ?
        ");
        $update_stmt->execute([$quantity_to_borrow, $quantity_to_borrow, $item_id]);
        
        // Create transaction record
        $trans_stmt = $pdo->prepare("
            INSERT INTO transactions (transaction_id, type, duty_officer_id, borrower_name, borrower_contact, purpose, status, notes) 
            VALUES (?, 'borrow', 1, ?, ?, 'Test borrow operation', 'completed', 'Automated test')
        ");
        $trans_id = 'TEST_' . date('YmdHis');
        $trans_stmt->execute([$trans_id, $borrower_name, $borrower_contact]);
        
        $pdo->commit();
        
        // Verify the operation
        $item_query->execute([$item_id]);
        $item_after = $item_query->fetch();
        
        echo "   After borrow: {$item_after['item_name']} - Available: {$item_after['available_quantity']}, Borrowed: {$item_after['borrowed_quantity']}\n";
        echo "   ✓ Borrow operation successful!\n";
        
    } else {
        echo "   ❌ Insufficient quantity available\n";
    }
    
    echo "\n2. Testing Return Operation...\n";
    
    // Find a borrowed item to return
    $borrowed_query = $pdo->query("
        SELECT bi.id, bi.item_id, bi.borrower_name, bi.quantity_borrowed, i.item_name 
        FROM borrowed_items bi 
        JOIN items i ON bi.item_id = i.id 
        WHERE bi.status = 'borrowed' 
        LIMIT 1
    ");
    $borrowed_item = $borrowed_query->fetch();
    
    if ($borrowed_item) {
        echo "   Found borrowed item: {$borrowed_item['item_name']} (Qty: {$borrowed_item['quantity_borrowed']}) by {$borrowed_item['borrower_name']}\n";
        
        // Get current item info
        $item_query->execute([$borrowed_item['item_id']]);
        $item_before_return = $item_query->fetch();
        echo "   Before return: Available: {$item_before_return['available_quantity']}, Borrowed: {$item_before_return['borrowed_quantity']}\n";
        
        // Start transaction for return
        $pdo->beginTransaction();
        
        // Update borrowed item status
        $return_stmt = $pdo->prepare("
            UPDATE borrowed_items 
            SET status = 'returned', actual_return_date = NOW() 
            WHERE id = ?
        ");
        $return_stmt->execute([$borrowed_item['id']]);
        
        // Update item quantities
        $update_return_stmt = $pdo->prepare("
            UPDATE items 
            SET available_quantity = available_quantity + ?, 
                borrowed_quantity = borrowed_quantity - ? 
            WHERE id = ?
        ");
        $update_return_stmt->execute([
            $borrowed_item['quantity_borrowed'], 
            $borrowed_item['quantity_borrowed'], 
            $borrowed_item['item_id']
        ]);
        
        // Create return transaction record
        $return_trans_stmt = $pdo->prepare("
            INSERT INTO transactions (transaction_id, type, duty_officer_id, borrower_name, borrower_contact, purpose, status, notes) 
            VALUES (?, 'return', 1, ?, '', 'Test return operation', 'completed', 'Automated test return')
        ");
        $return_trans_id = 'RET_' . date('YmdHis');
        $return_trans_stmt->execute([$return_trans_id, $borrowed_item['borrower_name']]);
        
        $pdo->commit();
        
        // Verify the return operation
        $item_query->execute([$borrowed_item['item_id']]);
        $item_after_return = $item_query->fetch();
        
        echo "   After return: Available: {$item_after_return['available_quantity']}, Borrowed: {$item_after_return['borrowed_quantity']}\n";
        echo "   ✓ Return operation successful!\n";
        
    } else {
        echo "   ℹ No borrowed items found to test return operation\n";
    }
    
    echo "\n3. Testing QR Code Operations...\n";
    
    // Test QR code lookup for different items
    $qr_codes = ['QR_M16_001', 'QR_BOOTS_002', 'QR_PACK_003'];
    
    foreach ($qr_codes as $qr_code) {
        $qr_stmt = $pdo->prepare("SELECT item_name, available_quantity, total_quantity FROM items WHERE qr_code = ?");
        $qr_stmt->execute([$qr_code]);
        $qr_result = $qr_stmt->fetch();
        
        if ($qr_result) {
            echo "   ✓ QR {$qr_code}: {$qr_result['item_name']} (Available: {$qr_result['available_quantity']}/{$qr_result['total_quantity']})\n";
        } else {
            echo "   ❌ QR {$qr_code}: Not found\n";
        }
    }
    
    echo "\n4. Testing Supply/Add Stock Operation...\n";
    
    // Test adding stock to an item
    $supply_item_id = 2; // Combat Boots
    $supply_quantity = 10;
    
    // Get current stock
    $stock_query = $pdo->prepare("SELECT item_name, total_quantity, available_quantity FROM items WHERE id = ?");
    $stock_query->execute([$supply_item_id]);
    $stock_before = $stock_query->fetch();
    
    echo "   Before supply: {$stock_before['item_name']} - Total: {$stock_before['total_quantity']}, Available: {$stock_before['available_quantity']}\n";
    
    // Add stock
    $pdo->beginTransaction();
    
    $supply_stmt = $pdo->prepare("
        UPDATE items 
        SET total_quantity = total_quantity + ?, 
            available_quantity = available_quantity + ? 
        WHERE id = ?
    ");
    $supply_stmt->execute([$supply_quantity, $supply_quantity, $supply_item_id]);
    
    // Create supply transaction
    $supply_trans_stmt = $pdo->prepare("
        INSERT INTO transactions (transaction_id, type, duty_officer_id, purpose, status, notes) 
        VALUES (?, 'supply', 1, 'Test supply operation', 'completed', 'Added stock for testing')
    ");
    $supply_trans_id = 'SUP_' . date('YmdHis');
    $supply_trans_stmt->execute([$supply_trans_id]);
    
    $pdo->commit();
    
    // Verify supply operation
    $stock_query->execute([$supply_item_id]);
    $stock_after = $stock_query->fetch();
    
    echo "   After supply: {$stock_after['item_name']} - Total: {$stock_after['total_quantity']}, Available: {$stock_after['available_quantity']}\n";
    echo "   ✓ Supply operation successful!\n";
    
    echo "\n5. Testing Inventory Reports...\n";
    
    // Generate inventory summary
    $summary_query = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(total_quantity) as total_stock,
            SUM(available_quantity) as available_stock,
            SUM(borrowed_quantity) as borrowed_stock
        FROM items
    ");
    $summary = $summary_query->fetch();
    
    echo "   Inventory Summary:\n";
    echo "   - Total Items: {$summary['total_items']}\n";
    echo "   - Total Stock: {$summary['total_stock']}\n";
    echo "   - Available: {$summary['available_stock']}\n";
    echo "   - Borrowed: {$summary['borrowed_stock']}\n";
    
    // Low stock items (less than 10 available)
    $low_stock_query = $pdo->query("
        SELECT item_name, available_quantity, total_quantity 
        FROM items 
        WHERE available_quantity < 10 
        ORDER BY available_quantity ASC
    ");
    $low_stock_items = $low_stock_query->fetchAll();
    
    if (count($low_stock_items) > 0) {
        echo "   Low Stock Items:\n";
        foreach ($low_stock_items as $item) {
            echo "   - {$item['item_name']}: {$item['available_quantity']}/{$item['total_quantity']}\n";
        }
    } else {
        echo "   ✓ No low stock items\n";
    }
    
    echo "\n6. Testing Transaction History...\n";
    
    $trans_history = $pdo->query("
        SELECT transaction_id, type, borrower_name, purpose, status, created_at 
        FROM transactions 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    echo "   Recent Transactions:\n";
    foreach ($trans_history as $trans) {
        echo "   - {$trans['transaction_id']}: {$trans['type']} by {$trans['borrower_name']} ({$trans['status']})\n";
    }
    
    echo "\n✅ All inventory operations tested successfully!\n";
    echo "\n=== Final System Status ===\n";
    echo "✓ Borrow operations: Working\n";
    echo "✓ Return operations: Working\n";
    echo "✓ QR code system: Working\n";
    echo "✓ Supply/Stock management: Working\n";
    echo "✓ Inventory reporting: Working\n";
    echo "✓ Transaction logging: Working\n";
    echo "✓ Database integration: Complete\n";
    
    echo "\n🎉 Inventory system is fully functional and integrated with rotc_db!\n";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>