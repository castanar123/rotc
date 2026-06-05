<?php
// Debug what the borrowed_items.php API is returning

try {
    // Direct test of the borrowed_items API
    echo "=== TESTING BORROWED ITEMS API ===\n\n";
    
    // Test the API endpoint directly
    $url = "http://localhost/generate%20qr/rotc-qr-inventory/api/borrowed_items.php";
    
    echo "1. Testing API URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "2. HTTP Status: $httpCode\n";
    echo "3. Response:\n";
    echo $response;
    echo "\n\n";
    
    // Also test by including the file directly
    echo "4. DIRECT FILE TEST:\n";
    
    // Capture any output
    ob_start();
    
    try {
        include_once '../rotc-qr-inventory/api/borrowed_items.php';
    } catch (Exception $e) {
        echo "Include error: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_contents();
    ob_end_clean();
    
    echo "Direct include output:\n";
    echo $output;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
