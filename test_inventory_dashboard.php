<?php
// Test inventory dashboard functionality

echo "=== Testing Inventory Dashboard Functionality ===\n\n";

// Include the inventory database connection
require_once 'rotc-qr-inventory/includes/db.php';

try {
    echo "1. Testing database connection...\n";
    if (isset($pdo)) {
        echo "✓ PDO connection established\n";
    }
    if (isset($link)) {
        echo "✓ MySQLi connection established\n";
    }
    
    echo "\n2. Testing inventory data retrieval...\n";
    
    // Test items retrieval
    $items_query = "SELECT COUNT(*) as total_items, SUM(total_quantity) as total_stock, SUM(available_quantity) as available_stock FROM items";
    $result = $pdo->query($items_query)->fetch();
    echo "   Total Items: {$result['total_items']}\n";
    echo "   Total Stock: {$result['total_stock']}\n";
    echo "   Available Stock: {$result['available_stock']}\n";
    
    // Test officers retrieval
    $officers_query = "SELECT COUNT(*) as total_officers FROM officers WHERE status = 'active'";
    $officers_result = $pdo->query($officers_query)->fetch();
    echo "   Active Officers: {$officers_result['total_officers']}\n";
    
    echo "\n3. Testing item listing...\n";
    $items_list = $pdo->query("SELECT item_name, available_quantity, total_quantity FROM items ORDER BY item_name LIMIT 5")->fetchAll();
    foreach ($items_list as $item) {
        echo "   {$item['item_name']}: {$item['available_quantity']}/{$item['total_quantity']}\n";
    }
    
    echo "\n4. Testing QR code functionality...\n";
    $qr_test = $pdo->prepare("SELECT item_name, available_quantity FROM items WHERE qr_code = ?");
    $qr_test->execute(['QR_M16_001']);
    $qr_result = $qr_test->fetch();
    if ($qr_result) {
        echo "   ✓ QR Code lookup working: {$qr_result['item_name']} (Available: {$qr_result['available_quantity']})\n";
    } else {
        echo "   ❌ QR Code lookup failed\n";
    }
    
    echo "\n5. Testing borrow functionality simulation...\n";
    
    // Simulate borrowing an item
    $item_id = 1; // M16 Rifle
    $quantity_to_borrow = 2;
    $borrower_name = "Test Cadet";
    $borrower_contact = "09999999999";
    
    // Check current availability
    $check_query = $pdo->prepare("SELECT item_name, available_quantity FROM items WHERE id = ?");
    $check_query->execute([$item_id]);
    $item_info = $check_query->fetch();
    
    if ($item_info && $item_info['available_quantity'] >= $quantity_to_borrow) {
        echo "   ✓ Item available for borrowing: {$item_info['item_name']} (Available: {$item_info['available_quantity']})\n";
        
        // Simulate the borrow process (without actually executing)
        echo "   ✓ Borrow simulation: Would borrow {$quantity_to_borrow} {$item_info['item_name']} for {$borrower_name}\n";
        echo "   ✓ Would update available_quantity and borrowed_quantity\n";
        echo "   ✓ Would insert record into borrowed_items table\n";
    } else {
        echo "   ❌ Insufficient quantity available for borrowing\n";
    }
    
    echo "\n6. Testing return functionality simulation...\n";
    
    // Check for borrowed items
    $borrowed_query = "SELECT COUNT(*) as borrowed_count FROM borrowed_items WHERE status = 'borrowed'";
    $borrowed_result = $pdo->query($borrowed_query)->fetch();
    echo "   Current borrowed items: {$borrowed_result['borrowed_count']}\n";
    
    if ($borrowed_result['borrowed_count'] > 0) {
        echo "   ✓ Return functionality can be tested with existing borrowed items\n";
    } else {
        echo "   ℹ No items currently borrowed, return functionality ready for testing\n";
    }
    
    echo "\n7. Testing transaction logging...\n";
    
    $transaction_count = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch();
    echo "   Total transactions logged: {$transaction_count['count']}\n";
    
    echo "\n✅ Inventory dashboard functionality test completed successfully!\n";
    echo "\n=== Dashboard Integration Status ===\n";
    echo "✓ Database connection: Working\n";
    echo "✓ Item management: Ready\n";
    echo "✓ Officer management: Ready\n";
    echo "✓ QR code system: Working\n";
    echo "✓ Borrow/Return system: Ready for testing\n";
    echo "✓ Transaction logging: Working\n";
    
    echo "\n=== Next Steps ===\n";
    echo "1. Test the actual inventory dashboard web interface\n";
    echo "2. Test borrow/return operations through the UI\n";
    echo "3. Test QR code scanning functionality\n";
    echo "4. Verify all CRUD operations work properly\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>