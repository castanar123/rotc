<?php
session_start();

// Simulate cadet user session (user ID 11)
$_SESSION['user_id'] = 11;
$_SESSION['username'] = 'cadet11';
$_SESSION['full_name'] = 'Test Cadet';
$_SESSION['role'] = 'cadet';
$_SESSION['loggedin'] = true;

echo "<h2>Final Authorization Test</h2>";
echo "<p>Session User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Session Role: " . $_SESSION['role'] . "</p>";
echo "<p>Session Logged In: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "</p>";
echo "<hr>";

// Test AJAX endpoint using cURL to avoid session conflicts
echo "<h3>Testing AJAX Endpoint via cURL</h3>";

$url = 'http://localhost:8080/cadet_attendance.php?ajax=true';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status Code: " . $http_code . "</p>";
echo "<p>Response:</p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode JSON
$json_data = json_decode($response, true);
if ($json_data) {
    echo "<h4>JSON Decoded Successfully:</h4>";
    if (isset($json_data['error'])) {
        echo "<p style='color: red;'>Error: " . $json_data['error'] . "</p>";
    } else {
        echo "<p style='color: green;'>Success! Data retrieved.</p>";
        if (isset($json_data['stats'])) {
            echo "<ul>";
            echo "<li>Total Days: " . $json_data['stats']['total_days'] . "</li>";
            echo "<li>Present Days: " . $json_data['stats']['present_days'] . "</li>";
            echo "<li>Absent Days: " . $json_data['stats']['absent_days'] . "</li>";
            echo "<li>Attendance Rate: " . $json_data['stats']['attendance_rate'] . "%</li>";
            echo "</ul>";
        }
    }
} else {
    echo "<p style='color: red;'>Failed to decode JSON response</p>";
}

echo "<hr>";
echo "<h3>Alternative Test: Direct Include with Session Check</h3>";

// Test by setting GET parameter and including the file
$_GET['ajax'] = 'true';

echo "<p>Testing direct include with AJAX parameter...</p>";

// Capture output
ob_start();
try {
    include 'cadet_attendance.php';
    $output = ob_get_clean();
    echo "<p>Include successful. Output:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $json_test = json_decode($output, true);
    if ($json_test) {
        if (isset($json_test['error'])) {
            echo "<p style='color: red;'>Still getting error: " . $json_test['error'] . "</p>";
        } else {
            echo "<p style='color: green;'>Authorization fixed! Data retrieved successfully.</p>";
        }
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='cadet_attendance.php'>View Cadet Attendance Page (Normal)</a></p>";
?>