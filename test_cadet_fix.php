<?php
session_start();
require_once 'includes/db.php';

// Simulate cadet user session (user ID 11)
$_SESSION['user_id'] = 11;
$_SESSION['username'] = 'cadet11';
$_SESSION['full_name'] = 'Test Cadet';
$_SESSION['role'] = 'cadet';
$_SESSION['loggedin'] = true; // This is required by the authorization logic

echo "<h2>Testing Cadet Attendance Fix</h2>";
echo "<p>Session User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Session Role: " . $_SESSION['role'] . "</p>";
echo "<hr>";

// Test AJAX endpoint
echo "<h3>Testing AJAX Endpoint</h3>";
echo "<p>Making request to cadet_attendance.php?ajax=true</p>";

// Capture output from cadet_attendance.php with AJAX parameter
$_GET['ajax'] = 'true';
ob_start();
include 'cadet_attendance.php';
$ajax_output = ob_get_clean();

echo "<pre>AJAX Response: " . htmlspecialchars($ajax_output) . "</pre>";

// Try to decode JSON
$json_data = json_decode($ajax_output, true);
if ($json_data) {
    echo "<h4>JSON Decoded Successfully:</h4>";
    echo "<ul>";
    echo "<li>Success: " . ($json_data['success'] ? 'true' : 'false') . "</li>";
    if (isset($json_data['stats'])) {
        echo "<li>Total Days: " . $json_data['stats']['total_days'] . "</li>";
        echo "<li>Present Days: " . $json_data['stats']['present_days'] . "</li>";
        echo "<li>Absent Days: " . $json_data['stats']['absent_days'] . "</li>";
        echo "<li>Attendance Rate: " . $json_data['stats']['attendance_rate'] . "%</li>";
    }
    if (isset($json_data['recent_attendance'])) {
        echo "<li>Recent Attendance Records: " . count($json_data['recent_attendance']) . "</li>";
    }
    if (isset($json_data['profile'])) {
        echo "<li>Profile - Student ID: " . $json_data['profile']['student_id'] . "</li>";
        echo "<li>Profile - Full Name: " . $json_data['profile']['full_name'] . "</li>";
        echo "<li>Profile - Platoon: " . $json_data['profile']['platoon'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>Failed to decode JSON response</p>";
}

echo "<hr>";
echo "<h3>Direct Database Check</h3>";

try {
    // Check if attendance data exists for cadet_id 11
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE cadet_id = ?");
    $stmt->execute([11]);
    $attendance_count = $stmt->fetch()['count'];
    echo "<p>Attendance records for cadet_id 11: " . $attendance_count . "</p>";
    
    // Check if cadet_profiles record exists for user_id 11
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([11]);
    $profile_count = $stmt->fetch()['count'];
    echo "<p>Cadet profiles records for user_id 11: " . $profile_count . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='cadet_attendance.php'>View Cadet Attendance Page</a></p>";
?>