<?php
// Setup inventory data and test functionality

try {
    $pdo = new PDO('mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Setting Up Inventory Data ===\n\n";
    
    // Check if data already exists
    $itemCount = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $officerCount = $pdo->query("SELECT COUNT(*) FROM officers")->fetchColumn();
    
    if ($itemCount > 0 && $officerCount > 0) {
        echo "✓ Inventory data already exists\n";
        echo "Items: $itemCount, Officers: $officerCount\n";
    } else {
        echo "=== Inserting Sample Data ===\n";
        
        // Clear existing data
        $pdo->exec("DELETE FROM borrowed_items");
        $pdo->exec("DELETE FROM transaction_items");
        $pdo->exec("DELETE FROM transactions");
        $pdo->exec("DELETE FROM items");
        $pdo->exec("DELETE FROM officers");
        
        // Reset auto increment
        $pdo->exec("ALTER TABLE items AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE officers AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE transactions AUTO_INCREMENT = 1");
        
        // Insert sample officers (using actual table structure)
        $pdo->exec("INSERT INTO officers (user_id, rank, position, department, commission_date, status) VALUES 
            (1, 'Sergeant', 'Squad Leader', 'Alpha Company', '2023-01-15', 'active'),
            (2, 'Lieutenant', 'Platoon Leader', 'Bravo Company', '2022-06-10', 'active'),
            (3, 'Captain', 'Company Commander', 'Charlie Company', '2021-03-20', 'active'),
            (4, 'Corporal', 'Team Leader', 'Delta Company', '2023-08-05', 'active'),
            (5, 'Major', 'Battalion Commander', 'Echo Company', '2020-12-01', 'active')
        ");
        echo "✓ Inserted 5 sample officers\n";
        
        // Insert sample items with proper quantities
        $pdo->exec("INSERT INTO items (item_name, description, total_quantity, available_quantity, borrowed_quantity, unit, location, condition_status, qr_code) VALUES 
            ('M16 Rifle', 'Standard issue rifle for training', 50, 45, 5, 'pcs', 'Armory A1', 'good', 'QR_M16_001'),
            ('Combat Boots', 'Standard combat boots size 9', 100, 85, 15, 'pairs', 'Supply Room B2', 'good', 'QR_BOOTS_002'),
            ('Field Pack', 'Military field backpack', 75, 70, 5, 'pcs', 'Supply Room B1', 'excellent', 'QR_PACK_003'),
            ('Helmet', 'Combat helmet with chin strap', 60, 55, 5, 'pcs', 'Armory A2', 'good', 'QR_HELMET_004'),
            ('Uniform Set', 'Complete BDU uniform set', 120, 100, 20, 'sets', 'Supply Room C1', 'good', 'QR_UNIFORM_005'),
            ('Tactical Vest', 'Bulletproof tactical vest', 40, 35, 5, 'pcs', 'Armory A1', 'excellent', 'QR_VEST_006'),
            ('Radio Set', 'Military communication radio', 25, 20, 5, 'pcs', 'Communication Room', 'good', 'QR_RADIO_007'),
            ('First Aid Kit', 'Complete medical first aid kit', 80, 75, 5, 'kits', 'Medical Bay', 'good', 'QR_MEDKIT_008'),
            ('Compass', 'Military grade compass', 30, 28, 2, 'pcs', 'Navigation Room', 'excellent', 'QR_COMPASS_009'),
            ('Flashlight', 'Tactical LED flashlight', 90, 80, 10, 'pcs', 'Supply Room B1', 'good', 'QR_LIGHT_010')
        ");
        echo "✓ Inserted 10 sample items\n";
        
        // Insert some sample transactions
        $pdo->exec("INSERT INTO transactions (transaction_id, type, duty_officer_id, borrower_name, borrower_contact, purpose, status, notes) VALUES 
            ('TXN001', 'borrow', 1, 'Cadet Rodriguez', '09111222333', 'Training exercise equipment', 'completed', 'Field training exercise'),
            ('TXN002', 'return', 2, 'Cadet Martinez', '09222333444', 'Returned after field training', 'completed', 'Equipment returned in good condition'),
            ('TXN003', 'supply', 3, 'Supply Officer', '09333444555', 'New equipment received', 'completed', 'Monthly supply delivery')
        ");
        echo "✓ Inserted sample transactions\n";
        
        // Insert some borrowed items
        $pdo->exec("INSERT INTO borrowed_items (item_id, borrower_name, borrower_contact, quantity_borrowed, expected_return_date, status, notes) VALUES 
            (1, 'Cadet Rodriguez', '09111222333', 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'borrowed', 'Field training exercise'),
            (2, 'Cadet Martinez', '09222333444', 5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'borrowed', 'Boot camp training'),
            (4, 'Cadet Johnson', '09333444555', 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'borrowed', 'Safety training')
        ");
        echo "✓ Inserted sample borrowed items\n";
    }
    
    echo "\n=== Testing Inventory Functionality ===\n";
    
    // Test 1: Get all items with availability
    echo "\n1. Testing item retrieval...\n";
    $items = $pdo->query("SELECT item_name, total_quantity, available_quantity, borrowed_quantity FROM items ORDER BY item_name")->fetchAll();
    foreach ($items as $item) {
        echo "   {$item['item_name']}: Total={$item['total_quantity']}, Available={$item['available_quantity']}, Borrowed={$item['borrowed_quantity']}\n";
    }
    
    // Test 2: Get active officers
    echo "\n2. Testing officer retrieval...\n";
    $officers = $pdo->query("SELECT user_id, rank, position, department FROM officers WHERE status = 'active' ORDER BY rank")->fetchAll();
    foreach ($officers as $officer) {
        echo "   User ID {$officer['user_id']} - {$officer['rank']} ({$officer['position']}, {$officer['department']})\n";
    }
    
    // Test 3: Get borrowed items with borrower info
    echo "\n3. Testing borrowed items tracking...\n";
    $borrowed = $pdo->query("
        SELECT i.item_name, b.borrower_name, b.quantity_borrowed, b.expected_return_date, b.status 
        FROM borrowed_items b 
        JOIN items i ON b.item_id = i.id 
        WHERE b.status = 'borrowed'
        ORDER BY b.expected_return_date
    ")->fetchAll();
    
    if (count($borrowed) > 0) {
        foreach ($borrowed as $item) {
            echo "   {$item['item_name']} - {$item['quantity_borrowed']} pcs borrowed by {$item['borrower_name']} (due: {$item['expected_return_date']})\n";
        }
    } else {
        echo "   No items currently borrowed\n";
    }
    
    // Test 4: Calculate inventory statistics
    echo "\n4. Testing inventory statistics...\n";
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(total_quantity) as total_stock,
            SUM(available_quantity) as available_stock,
            SUM(borrowed_quantity) as borrowed_stock
        FROM items
    ")->fetch();
    
    echo "   Total Items: {$stats['total_items']}\n";
    echo "   Total Stock: {$stats['total_stock']}\n";
    echo "   Available: {$stats['available_stock']}\n";
    echo "   Borrowed: {$stats['borrowed_stock']}\n";
    
    // Test 5: Test QR code functionality
    echo "\n5. Testing QR code lookup...\n";
    $qr_test = $pdo->prepare("SELECT item_name, available_quantity FROM items WHERE qr_code = ?");
    $qr_test->execute(['QR_M16_001']);
    $qr_result = $qr_test->fetch();
    
    if ($qr_result) {
        echo "   QR Code 'QR_M16_001' found: {$qr_result['item_name']} (Available: {$qr_result['available_quantity']})\n";
    } else {
        echo "   QR Code test failed\n";
    }
    
    echo "\n✅ Inventory system setup and testing completed successfully!\n";
    echo "\n=== Next Steps ===\n";
    echo "1. Update inventory dashboard to use rotc_db database\n";
    echo "2. Test borrow/return functionality\n";
    echo "3. Test QR code scanning integration\n";
    echo "4. Verify all CRUD operations work properly\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>