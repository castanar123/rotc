<?php
// Simple test to verify AJAX endpoints work with proper session
require_once 'includes/db.php';
session_start();

// Simulate a logged-in user
$_SESSION['user_id'] = 28;
$_SESSION['role'] = 'cadet';

echo "<h2>Testing Attendance AJAX Endpoints</h2>";
echo "<p>Session User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Session Role: " . $_SESSION['role'] . "</p>";

// Test get_stats endpoint
echo "<h3>Testing get_stats:</h3>";
$_GET['ajax'] = 'true';
$_GET['action'] = 'get_stats';

// Capture output from cadet_attendance_new.php AJAX handler
ob_start();
include 'cadet_attendance_new.php';
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Reset for next test
unset($_GET['action']);

// Test get_recent_attendance endpoint
echo "<h3>Testing get_recent_attendance:</h3>";
$_GET['action'] = 'get_recent_attendance';

ob_start();
include 'cadet_attendance_new.php';
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<h3>Direct Function Test:</h3>";
// Include functions directly
require_once 'cadet_attendance_new.php';

// Test functions directly
$stats = getCadetAttendanceStats(28);
echo "<p>Direct stats call: " . json_encode($stats) . "</p>";

$recent = getCadetRecentAttendance(28, 5);
echo "<p>Direct recent call: " . json_encode($recent) . "</p>";
?>