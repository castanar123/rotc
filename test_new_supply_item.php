<?php
// Test adding a completely new supply item to avoid "already exists" issue

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TESTING NEW SUPPLY ITEM CREATION ===\n\n";
    
    // Generate unique item name
    $uniqueName = "Test Supply " . date('YmdHis');
    
    $testData = [
        'item_name' => $uniqueName,
        'category' => 'supplies',
        'unit' => 'pieces',
        'quantity' => 30,
        'can_be_returned' => 'returnable'
    ];
    
    echo "1. Creating item with data: " . json_encode($testData) . "\n\n";
    
    // Test the API call
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
    
    echo "2. API Response:\n";
    echo "HTTP Status: $httpCode\n";
    echo "Response: $response\n\n";
    
    // Check what was actually inserted
    echo "3. Database verification:\n";
    $stmt = $pdo->prepare("SELECT * FROM items WHERE item_name = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$uniqueName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Item found in database:\n";
        echo "- ID: {$result['id']}\n";
        echo "- Name: {$result['item_name']}\n";
        echo "- Category: {$result['category']}\n";
        echo "- Total Quantity: {$result['total_quantity']}\n";
        echo "- Available Quantity: {$result['available_quantity']}\n";
        echo "- Unit: {$result['unit']}\n";
        echo "- Returnable: {$result['can_be_returned']}\n";
    } else {
        echo "Item NOT found in database!\n";
    }
    
    // Test direct SQL insert to compare
    echo "\n4. Testing direct SQL insert:\n";
    $directName = "Direct Insert " . date('YmdHis');
    $stmt = $pdo->prepare("INSERT INTO items (item_name, category, total_quantity, available_quantity, unit, can_be_returned) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$directName, 'supplies', 30, 30, 'pieces', 'returnable']);
    
    $newId = $pdo->lastInsertId();
    echo "Direct insert ID: $newId\n";
    
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$newId]);
    $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Direct insert result:\n";
    echo "- Category: {$directResult['category']}\n";
    echo "- Total: {$directResult['total_quantity']}\n";
    echo "- Available: {$directResult['available_quantity']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
