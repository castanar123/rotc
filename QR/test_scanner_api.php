<?php
/**
 * Test script to simulate scanner API calls and debug server communication issues
 */

echo "<h1>Scanner API Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";

// Test 1: Check if record_attendance.php is accessible
echo "<h2>Test 1: API Endpoint Accessibility</h2>";
$url = 'http://localhost:8080/record_attendance.php';
echo "<p class='info'>Testing URL: $url</p>";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

$response = @file_get_contents($url, false, $context);
if ($response !== false) {
    echo "<p class='success'>✓ API endpoint is accessible</p>";
} else {
    echo "<p class='error'>✗ API endpoint is not accessible</p>";
    echo "<p>Error: " . error_get_last()['message'] . "</p>";
}

// Test 2: Simulate the exact POST request from scanner.js
echo "<h2>Test 2: Simulating Scanner POST Request</h2>";

$test_data = [
    'student_id' => '20230999',
    'name' => 'Test Student',
    'td' => '1',
    'semester' => '1',
    'timestamp' => date('c') // ISO 8601 format
];

echo "<p class='info'>Sending POST data:</p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

$json_data = json_encode($test_data);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                   "Content-Length: " . strlen($json_data) . "\r\n",
        'content' => $json_data,
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response !== false) {
    echo "<p class='success'>✓ POST request successful</p>";
    echo "<p class='info'>Response:</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $response_data = json_decode($response, true);
    if ($response_data) {
        if (isset($response_data['success']) && $response_data['success']) {
            echo "<p class='success'>✓ API returned success response</p>";
        } else {
            echo "<p class='error'>✗ API returned error: " . ($response_data['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p class='error'>✗ Invalid JSON response</p>";
    }
} else {
    echo "<p class='error'>✗ POST request failed</p>";
    $error = error_get_last();
    if ($error) {
        echo "<p>Error: " . $error['message'] . "</p>";
    }
    
    // Check HTTP response headers
    if (isset($http_response_header)) {
        echo "<p class='info'>HTTP Response Headers:</p>";
        echo "<pre>" . implode("\n", $http_response_header) . "</pre>";
    }
}

// Test 3: Check database connection from record_attendance.php perspective
echo "<h2>Test 3: Database Connection Test</h2>";

try {
    require_once 'db.php';
    echo "<p class='success'>✓ Database connection file loaded</p>";
    
    // Test database connection
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='success'>✓ Database connection working (" . $result['count'] . " students in database)</p>";
    
    // Test attendance table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='success'>✓ Attendance table accessible (" . $result['count'] . " records)</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test 4: Check for PHP errors in record_attendance.php
echo "<h2>Test 4: PHP Error Check</h2>";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<p class='info'>Checking for PHP syntax errors in record_attendance.php...</p>";

$output = [];
$return_var = 0;
exec('php -l record_attendance.php 2>&1', $output, $return_var);

if ($return_var === 0) {
    echo "<p class='success'>✓ No PHP syntax errors found</p>";
} else {
    echo "<p class='error'>✗ PHP syntax errors found:</p>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
}

// Test 5: Check server logs
echo "<h2>Test 5: Server Configuration</h2>";

echo "<p class='info'>PHP Version: " . phpversion() . "</p>";
echo "<p class='info'>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p class='info'>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p class='info'>Script Path: " . __FILE__ . "</p>";

// Check if CORS headers are working
echo "<p class='info'>Testing CORS headers...</p>";
if (function_exists('apache_response_headers')) {
    $headers = apache_response_headers();
    if (isset($headers['Access-Control-Allow-Origin'])) {
        echo "<p class='success'>✓ CORS headers are set</p>";
    } else {
        echo "<p class='error'>✗ CORS headers may not be set properly</p>";
    }
} else {
    echo "<p class='info'>Cannot check CORS headers (not running on Apache)</p>";
}

echo "<h2>Recommendations</h2>";
echo "<ul>";
echo "<li>If POST request fails, check if the PHP server is running on localhost:8080</li>";
echo "<li>If database errors occur, verify database credentials in db.php</li>";
echo "<li>If CORS errors occur in browser, check browser console for specific error messages</li>";
echo "<li>Check browser Network tab to see the actual HTTP request/response</li>";
echo "</ul>";

echo "<p><a href='scanner.html'>← Back to Scanner</a> | <a href='test_attendance.php'>Database Test</a></p>";
?>