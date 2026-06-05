<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Simulate a logged-in cadet session for testing
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 11; // Test user ID
$_SESSION['role'] = 'cadet';
$_SESSION['username'] = 'test_cadet';

echo "<h2>Testing Dashboard API Endpoints</h2>";

// Test 1: Dashboard Data API
echo "<h3>1. Testing api/dashboard_data.php</h3>";
$api_url = 'http://localhost:8080/api/dashboard_data.php';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id()
    ]
]);

$response = file_get_contents($api_url, false, $context);
if ($response !== false) {
    echo "<p><strong>Response received:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $json_data = json_decode($response, true);
    if ($json_data !== null) {
        echo "<p style='color: green;'><strong>✓ Valid JSON response!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Invalid JSON response!</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Failed to get response from API</strong></p>";
}

// Test 2: Cadet Attendance AJAX
echo "<h3>2. Testing cadet_attendance.php?ajax=true</h3>";
$ajax_url = 'http://localhost:8080/cadet_attendance.php?ajax=true';

$response2 = file_get_contents($ajax_url, false, $context);
if ($response2 !== false) {
    echo "<p><strong>Response received:</strong></p>";
    echo "<pre>" . htmlspecialchars($response2) . "</pre>";
    
    $json_data2 = json_decode($response2, true);
    if ($json_data2 !== null) {
        echo "<p style='color: green;'><strong>✓ Valid JSON response!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Invalid JSON response!</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Failed to get response from AJAX endpoint</strong></p>";
}

// Test 3: Regular cadet_attendance.php (should return HTML)
echo "<h3>3. Testing cadet_attendance.php (regular HTML)</h3>";
$html_url = 'http://localhost:8080/cadet_attendance.php';

$response3 = file_get_contents($html_url, false, $context);
if ($response3 !== false) {
    if (strpos($response3, '<!DOCTYPE html') !== false) {
        echo "<p style='color: green;'><strong>✓ Returns HTML as expected!</strong></p>";
        echo "<p>Response length: " . strlen($response3) . " characters</p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Does not return HTML!</strong></p>";
        echo "<pre>" . htmlspecialchars(substr($response3, 0, 500)) . "...</pre>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Failed to get response from HTML page</strong></p>";
}

echo "<h3>Summary</h3>";
echo "<p>The dashboard should now be able to:</p>";
echo "<ul>";
echo "<li>Get JSON data from api/dashboard_data.php for real-time updates</li>";
echo "<li>Get JSON data from cadet_attendance.php?ajax=true for attendance data</li>";
echo "<li>Display the regular HTML page when accessing cadet_attendance.php normally</li>";
echo "</ul>";
echo "<p><strong>This should fix the 'SyntaxError: Unexpected token' JSON parsing error!</strong></p>";

echo "<p><a href='cadet_dashboard.php'>← Back to Cadet Dashboard</a></p>";
?>