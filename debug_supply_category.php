<?php
// Debug supply category and quantity issues

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUGGING SUPPLY CATEGORY ISSUES ===\n\n";
    
    // Check all categories in items table
    echo "1. ALL CATEGORIES IN ITEMS TABLE:\n";
    $stmt = $pdo->query("SELECT DISTINCT category, COUNT(*) as count FROM items WHERE category IS NOT NULL GROUP BY category ORDER BY count DESC");
    while ($row = $stmt->fetch()) {
        echo "   - '{$row['category']}': {$row['count']} items\n";
    }
    
    // Check recent items with their quantities
    echo "\n2. RECENT ITEMS WITH QUANTITIES:\n";
    $stmt = $pdo->query("SELECT item_name, category, total_quantity, available_quantity, unit FROM items ORDER BY id DESC LIMIT 10");
    while ($row = $stmt->fetch()) {
        echo "   - {$row['item_name']} ({$row['category']}): Total={$row['total_quantity']}, Available={$row['available_quantity']}, Unit={$row['unit']}\n";
    }
    
    // Test the supply.php endpoint directly
    echo "\n3. TESTING SUPPLY ENDPOINT:\n";
    $testData = [
        'item_name' => 'Debug Supply Item',
        'category' => 'supplies',
        'unit' => 'pieces',
        'quantity' => 25,
        'can_be_returned' => 'returnable'
    ];
    
    echo "Test data: " . json_encode($testData) . "\n";
    
    // Simulate the API call
    $url = "http://localhost/generate%20qr/rotc-qr-inventory/api/supply.php?action=add_supply_item";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    echo "Response: $response\n";
    
    // Check if the item was actually inserted
    echo "\n4. VERIFICATION AFTER INSERT:\n";
    $stmt = $pdo->prepare("SELECT * FROM items WHERE item_name = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(['Debug Supply Item']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "Item found: " . json_encode($result) . "\n";
    } else {
        echo "Item not found in database\n";
    }
    
    // Test get_items.php for supplies category
    echo "\n5. TESTING GET_ITEMS FOR SUPPLIES:\n";
    $url2 = "http://localhost/generate%20qr/rotc-qr-inventory/api/get_items.php?category=supplies";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $url2);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    echo "HTTP Status: $httpCode2\n";
    echo "Response length: " . strlen($response2) . "\n";
    
    $decoded = json_decode($response2, true);
    if ($decoded && isset($decoded['data'])) {
        echo "Items returned: " . count($decoded['data']) . "\n";
        if (count($decoded['data']) > 0) {
            echo "First item: " . json_encode($decoded['data'][0]) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
