<?php
/**
 * Debug script to test record_attendance.php directly
 */

echo "<h1>Debug Attendance Recording</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testing record_attendance.php directly</h2>";

// Simulate POST data
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Create test JSON input
$test_data = [
    'student_id' => '20230888',
    'name' => 'Debug Test Student',
    'td' => 1,
    'semester' => 1
];

// Simulate the JSON input that record_attendance.php expects
$json_input = json_encode($test_data);
file_put_contents('php://input', $json_input);

echo "<p class='info'>Simulating POST request with data:</p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Attempting to include record_attendance.php...</h3>";

try {
    // Capture output
    ob_start();
    
    // Mock the php://input for testing
    $GLOBALS['mock_input'] = $json_input;
    
    // Include the file
    include 'record_attendance.php';
    
    $output = ob_get_clean();
    
    echo "<p class='success'>✓ record_attendance.php executed successfully</p>";
    echo "<p class='info'>Output:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to decode as JSON
    $json_response = json_decode($output, true);
    if ($json_response) {
        echo "<p class='success'>✓ Valid JSON response received</p>";
        if (isset($json_response['success']) && $json_response['success']) {
            echo "<p class='success'>✓ Attendance recorded successfully</p>";
        } else {
            echo "<p class='error'>✗ API returned error: " . ($json_response['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p class='error'>✗ Invalid JSON response</p>";
    }
    
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "<p class='error'>✗ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='info'>Output before error:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
} catch (Error $e) {
    $output = ob_get_clean();
    echo "<p class='error'>✗ Fatal error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='info'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "<p class='info'>Output before error:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}

echo "<p><a href='scanner.html'>← Back to Scanner</a></p>";
?>