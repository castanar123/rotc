<?php
// Test AJAX endpoints with proper POST requests

// Start session to simulate logged-in user
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin_test';
$_SESSION['role'] = 'admin';

function testEndpoint($action, $data = []) {
    $url = 'http://localhost:8000/rifle_management.php';
    
    $postData = array_merge(['action' => $action], $data);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "\n=== Testing $action ===\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    
    // Try to decode JSON
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "JSON Valid: YES\n";
        echo "Decoded: " . print_r($decoded, true) . "\n";
    } else {
        echo "JSON Valid: NO\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "First 200 chars: " . substr($response, 0, 200) . "\n";
    }
    echo "\n";
}

// Test various endpoints
testEndpoint('get_rifle_list', ['page' => 1, 'limit' => 5]);
testEndpoint('get_rifle_stats');
testEndpoint('generate_single_qr', ['rifle_id' => 1]);

echo "\nTest completed.\n";
?>