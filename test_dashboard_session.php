<?php
session_start();

// First, simulate login
$_SESSION['user_id'] = 7;
$_SESSION['cadet_profile_id'] = 4;
$_SESSION['username'] = 'test_cadet';
$_SESSION['role'] = 'cadet';

echo "<h2>Session Test</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Cadet Profile ID: " . ($_SESSION['cadet_profile_id'] ?? 'Not set') . "</p>";
echo "<p>Username: " . ($_SESSION['username'] ?? 'Not set') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";

// Now include the dashboard logic
echo "<hr><h2>Dashboard Content</h2>";

// Include database connection
require_once 'includes/db.php';

try {
    // PDO connection is already available from includes/db.php
    
    // Get cadet profile ID from session
    $cadet_profile_id = $_SESSION['cadet_profile_id'] ?? null;
    
    if (!$cadet_profile_id) {
        echo "<p style='color: red;'>No cadet profile ID in session!</p>";
        exit;
    }
    
    echo "<p>Using Cadet Profile ID: $cadet_profile_id</p>";
    
    // Test attendance query
    echo "<h3>Attendance Data:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
    $stmt->execute([$cadet_profile_id]);
    $attendance_count = $stmt->fetchColumn();
    echo "<p>Total Attendance Records: $attendance_count</p>";
    
    // Test monthly attendance
    echo "<h3>Monthly Attendance:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as monthly FROM attendance WHERE cadet_id = ? AND MONTH(log_date) = MONTH(CURDATE()) AND YEAR(log_date) = YEAR(CURDATE())");
    $stmt->execute([$cadet_profile_id]);
    $monthly_count = $stmt->fetchColumn();
    echo "<p>This Month Attendance: $monthly_count</p>";
    
    // Test grades
    echo "<h3>Grades Data:</h3>";
    $stmt = $pdo->prepare("SELECT AVG(total_grade) as avg_grade FROM grades WHERE cadet_id = ?");
    $stmt->execute([$cadet_profile_id]);
    $avg_grade = $stmt->fetchColumn();
    echo "<p>Average Grade: " . ($avg_grade ? round($avg_grade, 1) : '0') . "</p>";
    
    // Test announcements
    echo "<h3>Announcements Data:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as upcoming FROM announcements WHERE expires_at > NOW()");
    $stmt->execute();
    $upcoming_count = $stmt->fetchColumn();
    echo "<p>Upcoming Events: $upcoming_count</p>";
    
    // Test recent activities
    echo "<h3>Recent Activities:</h3>";
    $stmt = $pdo->prepare("SELECT log_date, status FROM attendance WHERE cadet_id = ? ORDER BY log_date DESC LIMIT 5");
    $stmt->execute([$cadet_profile_id]);
    $activities = $stmt->fetchAll();
    
    if ($activities) {
        echo "<ul>";
        foreach ($activities as $activity) {
            echo "<li>" . $activity['log_date'] . " - " . $activity['status'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No recent activities found.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}

echo "<hr><p><a href='cadet_dashboard.php'>Go to Actual Dashboard</a></p>";
?>