<?php
require_once 'includes/db.php';

// Don't include session.php as it redirects - we'll simulate session manually
// require_once 'includes/session.php';

// Start session manually
session_start();

// Based on database analysis:
// - cadet_id 11 has 4 attendance records (user_id 24 in cadet_profiles)
// - cadet_id 18 has 1 attendance record (user_id 28 in cadet_profiles)
// - user_id 11 doesn't exist in cadet_profiles but should use fallback

echo "<h2>Testing Correct Session Scenarios</h2>";

// Test 1: user_id 24 (should map to cadet_id 11 with 4 records)
echo "<h3>Test 1: user_id 24 (maps to cadet_id 11)</h3>";
$_SESSION['user_id'] = 24;
$_SESSION['role'] = 'cadet';

include_once 'cadet_attendance_new.php';

$stats = getCadetAttendanceStats(24);
echo "<p>Stats for user_id 24: " . json_encode($stats) . "</p>";

$recent = getCadetRecentAttendance(24, 5);
echo "<p>Recent attendance count: " . count($recent) . "</p>";
echo "<pre>" . json_encode($recent, JSON_PRETTY_PRINT) . "</pre>";

// Test 2: user_id 28 (should map to cadet_id 18 with 1 record)
echo "<h3>Test 2: user_id 28 (maps to cadet_id 18)</h3>";
$stats = getCadetAttendanceStats(28);
echo "<p>Stats for user_id 28: " . json_encode($stats) . "</p>";

$recent = getCadetRecentAttendance(28, 5);
echo "<p>Recent attendance count: " . count($recent) . "</p>";
echo "<pre>" . json_encode($recent, JSON_PRETTY_PRINT) . "</pre>";

// Test 3: user_id 11 (no profile, should use fallback to cadet_id 11)
echo "<h3>Test 3: user_id 11 (no profile, fallback to cadet_id 11)</h3>";
$stats = getCadetAttendanceStats(11);
echo "<p>Stats for user_id 11: " . json_encode($stats) . "</p>";

$recent = getCadetRecentAttendance(11, 5);
echo "<p>Recent attendance count: " . count($recent) . "</p>";
echo "<pre>" . json_encode($recent, JSON_PRETTY_PRINT) . "</pre>";

// Test AJAX endpoint with correct session
echo "<h3>Test 4: AJAX endpoint with user_id 24</h3>";
$_SESSION['user_id'] = 24;
$_GET['ajax'] = 'true';
$_GET['action'] = 'get_stats';

ob_start();
// Simulate the AJAX call
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_stats':
                $stats = getCadetAttendanceStats($_SESSION['user_id']);
                echo json_encode($stats);
                break;
        }
    }
    exit;
}
$ajax_output = ob_get_clean();
echo "<p>AJAX Response: $ajax_output</p>";
?>