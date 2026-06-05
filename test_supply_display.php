<?php
// Test supply items API to see what data is returned for display

try {
    // Test the supply items endpoint
    $url = "http://localhost/generate%20qr/rotc-qr-inventory/api/get_items.php?category=supplies";
    
    echo "=== TESTING SUPPLY ITEMS API ===\n";
    echo "URL: $url\n\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    echo "Response Length: " . strlen($response) . "\n";
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        echo "JSON Parse Error: " . json_last_error_msg() . "\n";
        echo "Raw response:\n$response\n";
    } else {
        echo "JSON Parse Success\n";
        echo "Items found: " . (isset($decoded['data']) ? count($decoded['data']) : 0) . "\n\n";
        
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            echo "SAMPLE ITEMS:\n";
            foreach (array_slice($decoded['data'], 0, 3) as $item) {
                echo "- Name: " . ($item['item_name'] ?? 'N/A') . "\n";
                echo "  Available: " . ($item['available_quantity'] ?? $item['quantity'] ?? 'N/A') . "\n";
                echo "  Total: " . ($item['total_quantity'] ?? 'N/A') . "\n";
                echo "  Unit: " . ($item['unit'] ?? 'N/A') . "\n";
                echo "  Category: " . ($item['category'] ?? 'N/A') . "\n---\n";
            }
        }
    }
    
    // Also test direct database query
    echo "\n=== DIRECT DATABASE CHECK ===\n";
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT item_name, total_quantity, available_quantity, category, unit FROM items WHERE category = 'supplies' ORDER BY id DESC LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['item_name']}: Total={$row['total_quantity']}, Available={$row['available_quantity']}, Unit={$row['unit']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
