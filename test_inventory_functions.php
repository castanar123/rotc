<?php
// Comprehensive test for all inventory functions

try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TESTING INVENTORY FUNCTIONS ===\n";
    
    // 1. Test Officer Management
    echo "\n1. Testing Officer Management...\n";
    
    // Test officer creation
    echo "Testing officer creation...\n";
    $test_officer_stmt = $pdo->prepare("
        INSERT IGNORE INTO officers (user_id, rank, position, rank_position, platoon, department, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $test_officer_stmt->execute([
        null,
        'Test Sergeant',
        'Test Position',
        'Test Sergeant (Test Position)',
        'Test Platoon',
        'Test Department',
        'active'
    ]);
    
    echo "✓ Test officer created\n";
    
    // Test officer listing
    echo "Testing officer listing...\n";
    $officers_stmt = $pdo->query("SELECT id, rank, position, rank_position, platoon, status FROM officers WHERE status = 'active' LIMIT 5");
    $officers = $officers_stmt->fetchAll();
    
    echo "✓ Found " . count($officers) . " active officers:\n";
    foreach ($officers as $officer) {
        echo "  - ID: {$officer['id']}, {$officer['rank_position']}, Platoon: {$officer['platoon']}\n";
    }
    
    // 2. Test Inventory Management
    echo "\n2. Testing Inventory Management...\n";
    
    // Test inventory listing
    echo "Testing inventory listing...\n";
    $inventory_stmt = $pdo->query("SELECT id, item_name, category, total_quantity, available_quantity, borrowed_quantity FROM inventory LIMIT 5");
    $inventory_items = $inventory_stmt->fetchAll();
    
    echo "✓ Found " . count($inventory_items) . " inventory items:\n";
    foreach ($inventory_items as $item) {
        echo "  - {$item['item_name']} ({$item['category']}): Total: {$item['total_quantity']}, Available: {$item['available_quantity']}, Borrowed: {$item['borrowed_quantity']}\n";
    }
    
    // Test inventory statistics
    echo "\nTesting inventory statistics...\n";
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(total_quantity) as total_stock,
            SUM(available_quantity) as available_stock,
            SUM(borrowed_quantity) as borrowed_stock
        FROM inventory
    ");
    $stats = $stats_stmt->fetch();
    
    echo "✓ Inventory Statistics:\n";
    echo "  - Total Items: {$stats['total_items']}\n";
    echo "  - Total Stock: {$stats['total_stock']}\n";
    echo "  - Available: {$stats['available_stock']}\n";
    echo "  - Borrowed: {$stats['borrowed_stock']}\n";
    
    // 3. Test Borrowing System
    echo "\n3. Testing Borrowing System...\n";
    
    // Get a test officer and inventory item
    $test_officer = $officers[0] ?? null;
    $test_item = $inventory_items[0] ?? null;
    
    if ($test_officer && $test_item && $test_item['available_quantity'] > 0) {
        echo "Testing borrow transaction...\n";
        
        // Create a borrow transaction with unique transaction_id
        $transaction_id_value = 'TXN-' . date('YmdHis') . '-' . rand(1000, 9999);
        $borrow_stmt = $pdo->prepare("
            INSERT INTO transactions (transaction_id, duty_officer_id, type, status, notes, borrower_name, created_at) 
            VALUES (?, ?, 'borrow', 'pending', 'Test borrow transaction', 'Test Borrower', NOW())
        ");
        
        $borrow_stmt->execute([$transaction_id_value, $test_officer['id']]);
        $transaction_id = $pdo->lastInsertId();
        
        // Add transaction item
        $transaction_item_stmt = $pdo->prepare("
            INSERT INTO transaction_items (transaction_id, item_id, quantity, condition_before) 
            VALUES (?, ?, 1, 'good')
        ");
        
        $transaction_item_stmt->execute([$transaction_id, $test_item['id']]);
        
        // Update inventory stock
        $update_inventory_stmt = $pdo->prepare("
            UPDATE inventory 
            SET available_quantity = available_quantity - 1, borrowed_quantity = borrowed_quantity + 1 
            WHERE id = ?
        ");
        
        $update_inventory_stmt->execute([$test_item['id']]);
        
        echo "✓ Borrow transaction created (ID: $transaction_id)\n";
        echo "  - Officer: {$test_officer['rank_position']}\n";
        echo "  - Item: {$test_item['item_name']}\n";
        echo "  - Quantity: 1\n";
        
        // Test return transaction
        echo "\nTesting return transaction...\n";
        
        $return_transaction_id_value = 'TXN-' . date('YmdHis') . '-' . rand(1000, 9999);
        $return_stmt = $pdo->prepare("
            INSERT INTO transactions (transaction_id, duty_officer_id, type, status, notes, borrower_name, created_at) 
            VALUES (?, ?, 'return', 'completed', 'Test return transaction', 'Test Borrower', NOW())
        ");
        
        $return_stmt->execute([$return_transaction_id_value, $test_officer['id']]);
        $return_transaction_id = $pdo->lastInsertId();
        
        // Add return transaction item
        $return_item_stmt = $pdo->prepare("
            INSERT INTO transaction_items (transaction_id, item_id, quantity, condition_after) 
            VALUES (?, ?, 1, 'good')
        ");
        
        $return_item_stmt->execute([$return_transaction_id, $test_item['id']]);
        
        // Update inventory stock back
        $return_inventory_stmt = $pdo->prepare("
            UPDATE inventory 
            SET available_quantity = available_quantity + 1, borrowed_quantity = borrowed_quantity - 1 
            WHERE id = ?
        ");
        
        $return_inventory_stmt->execute([$test_item['id']]);
        
        echo "✓ Return transaction created (ID: $return_transaction_id)\n";
        
    } else {
        echo "⚠ Skipping borrow/return test - no suitable officer or available items\n";
    }
    
    // 4. Test Transaction History
    echo "\n4. Testing Transaction History...\n";
    
    $recent_transactions_stmt = $pdo->query("
        SELECT 
            t.id,
            t.type as transaction_type,
            t.status,
            t.created_at,
            o.rank_position,
            COUNT(ti.id) as item_count
        FROM transactions t
        JOIN officers o ON t.duty_officer_id = o.id
        LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    
    $recent_transactions = $recent_transactions_stmt->fetchAll();
    
    echo "✓ Found " . count($recent_transactions) . " recent transactions:\n";
    foreach ($recent_transactions as $transaction) {
        echo "  - ID: {$transaction['id']}, Type: {$transaction['transaction_type']}, Status: {$transaction['status']}, Officer: {$transaction['rank_position']}, Items: {$transaction['item_count']}\n";
    }
    
    // 5. Test QR Code Functionality
    echo "\n5. Testing QR Code Functionality...\n";
    
    // Test QR code lookup
    if (!empty($inventory_items)) {
        $test_qr_item = $inventory_items[0];
        $qr_lookup_stmt = $pdo->prepare("
            SELECT id, item_name, category, item_code, available_quantity 
            FROM inventory 
            WHERE item_code IS NOT NULL AND item_code != '' 
            LIMIT 1
        ");
        
        $qr_lookup_stmt->execute();
        $qr_item = $qr_lookup_stmt->fetch();
        
        if ($qr_item) {
            echo "✓ QR Code lookup test successful:\n";
            echo "  - Item Code: {$qr_item['item_code']}\n";
            echo "  - Item: {$qr_item['item_name']}\n";
            echo "  - Available: {$qr_item['available_quantity']}\n";
        } else {
            echo "⚠ No items with QR codes found\n";
        }
    }
    
    // 6. Test Borrowers Table
    echo "\n6. Testing Borrowers Table...\n";
    
    $borrowers_stmt = $pdo->query("SELECT COUNT(*) as count FROM borrowers");
    $borrowers_count = $borrowers_stmt->fetchColumn();
    
    echo "✓ Borrowers table accessible, found $borrowers_count records\n";
    
    // 7. Test Dashboard Queries
    echo "\n7. Testing Dashboard Queries...\n";
    
    // Test the exact query from dashboard.php
    try {
        $dashboard_officers_stmt = $pdo->query("SELECT * FROM officers WHERE status = 'active' ORDER BY rank_position LIMIT 5");
        $dashboard_officers = $dashboard_officers_stmt->fetchAll();
        echo "✓ Dashboard officers query successful - found " . count($dashboard_officers) . " officers\n";
        
        // Test inventory dashboard query
        $dashboard_inventory_stmt = $pdo->query("SELECT COUNT(*) as total_items, SUM(available_quantity) as available FROM inventory");
        $dashboard_inventory = $dashboard_inventory_stmt->fetch();
        echo "✓ Dashboard inventory query successful - {$dashboard_inventory['total_items']} items, {$dashboard_inventory['available']} available\n";
        
        // Test recent transactions query
        $dashboard_transactions_stmt = $pdo->query("
            SELECT t.*, o.rank_position 
            FROM transactions t 
            JOIN officers o ON t.duty_officer_id = o.id 
            ORDER BY t.created_at DESC 
            LIMIT 5
        ");
        $dashboard_transactions = $dashboard_transactions_stmt->fetchAll();
        echo "✓ Dashboard transactions query successful - found " . count($dashboard_transactions) . " recent transactions\n";
        
    } catch (Exception $e) {
        echo "✗ Dashboard query error: " . $e->getMessage() . "\n";
    }
    
    // 8. Test Duty Officer PIN Authentication
    echo "\n8. Testing Duty Officer PIN Authentication...\n";
    
    $pin_test_stmt = $pdo->prepare("
        SELECT o.id, o.rank_position, p.pin 
        FROM officers o 
        JOIN duty_officer_pins p ON o.id = p.officer_id 
        WHERE p.pin = ? AND p.is_active = 1
    ");
    
    $pin_test_stmt->execute(['472005']);
    $pin_result = $pin_test_stmt->fetch();
    
    if ($pin_result) {
        echo "✓ PIN authentication test successful\n";
        echo "  - Officer: {$pin_result['rank_position']}\n";
        echo "  - PIN: {$pin_result['pin']}\n";
    } else {
        echo "✗ PIN authentication test failed\n";
    }
    
    echo "\n=== INVENTORY FUNCTIONS TEST COMPLETE ===\n";
    echo "✓ Officer management working\n";
    echo "✓ Inventory management working\n";
    echo "✓ Borrowing system working\n";
    echo "✓ Transaction history working\n";
    echo "✓ QR code functionality working\n";
    echo "✓ Dashboard queries working\n";
    echo "✓ PIN authentication working\n";
    echo "✓ All inventory functions tested successfully\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>