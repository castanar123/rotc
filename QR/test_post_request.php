<?php
/**
 * Test actual HTTP POST request to record_attendance.php
 */

echo "<h1>Test POST Request to record_attendance.php</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";

// Test data
$test_data = [
    'student_id' => '20230777',
    'name' => 'HTTP Test Student',
    'td' => 1,
    'semester' => 1
];

echo "<p class='info'>Sending POST request with data:</p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Use cURL for a proper HTTP POST request
$url = 'http://localhost:8080/record_attendance.php';
$json_data = json_encode($test_data);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p class='error'>✗ cURL Error: $error</p>";
} else {
    echo "<p class='info'>HTTP Response Code: $http_code</p>";
    
    if ($http_code == 200 || $http_code == 201) {
        echo "<p class='success'>✓ Request successful</p>";
        echo "<p class='info'>Response:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        $json_response = json_decode($response, true);
        if ($json_response) {
            echo "<p class='success'>✓ Valid JSON response</p>";
            if (isset($json_response['success']) && $json_response['success']) {
                echo "<p class='success'>✓ Attendance recorded successfully!</p>";
                echo "<p class='info'>Message: " . ($json_response['message'] ?? 'No message') . "</p>";
            } else {
                echo "<p class='error'>✗ API returned error: " . ($json_response['message'] ?? 'Unknown error') . "</p>";
            }
        } else {
            echo "<p class='error'>✗ Invalid JSON response</p>";
        }
    } else {
        echo "<p class='error'>✗ HTTP Error: $http_code</p>";
        echo "<p class='info'>Response:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

// Test 2: Try with a student that already exists
echo "<hr><h2>Test 2: Existing Student</h2>";

$test_data2 = [
    'student_id' => '20230001',  // This should exist from our previous tests
    'name' => 'John Doe',
    'td' => 1,
    'semester' => 1
];

echo "<p class='info'>Testing with existing student:</p>";
echo "<pre>" . json_encode($test_data2, JSON_PRETTY_PRINT) . "</pre>";

$json_data2 = json_encode($test_data2);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data2)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response2 = curl_exec($ch);
$http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch);
curl_close($ch);

if ($error2) {
    echo "<p class='error'>✗ cURL Error: $error2</p>";
} else {
    echo "<p class='info'>HTTP Response Code: $http_code2</p>";
    
    if ($http_code2 == 200 || $http_code2 == 201) {
        echo "<p class='success'>✓ Request successful</p>";
        echo "<p class='info'>Response:</p>";
        echo "<pre>" . htmlspecialchars($response2) . "</pre>";
        
        $json_response2 = json_decode($response2, true);
        if ($json_response2) {
            echo "<p class='success'>✓ Valid JSON response</p>";
            if (isset($json_response2['success']) && $json_response2['success']) {
                echo "<p class='success'>✓ Response successful!</p>";
                echo "<p class='info'>Message: " . ($json_response2['message'] ?? 'No message') . "</p>";
            } else {
                echo "<p class='error'>✗ API returned error: " . ($json_response2['message'] ?? 'Unknown error') . "</p>";
            }
        }
    } else {
        echo "<p class='error'>✗ HTTP Error: $http_code2</p>";
        echo "<p class='info'>Response:</p>";
        echo "<pre>" . htmlspecialchars($response2) . "</pre>";
    }
}

echo "<p><a href='scanner.html'>← Back to Scanner</a> | <a href='test_attendance.php'>Database Test</a></p>";
?>