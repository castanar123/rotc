<?php
session_start();
require_once 'includes/db.php';

// Simulate logged-in session
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 7;
$_SESSION['cadet_profile_id'] = 4;
$_SESSION['role'] = 'cadet';

ob_start();

echo "<h2>Testing Recent Activities</h2>";
echo "Session user_id: " . $_SESSION['user_id'] . "<br>";
echo "Session cadet_profile_id: " . $_SESSION['cadet_profile_id'] . "<br><br>";

try {
    $user_id = $_SESSION['user_id'];
    $cadet_profile_id = $_SESSION['cadet_profile_id'];
    
    // Check if attendance_logs table exists and has data
    $use_attendance_logs = true;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
        $stmt->execute([$cadet_profile_id]);
        $logs_count = $stmt->fetch()['count'];
        echo "Found $logs_count records in attendance_logs table<br>";
        
        if ($logs_count > 0) {
            // Recent attendance activities from attendance_logs
            $stmt = $pdo->prepare("
                SELECT CONCAT('Attendance: ', COALESCE(event_name, 'Training')) as action, created_at as timestamp 
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$cadet_profile_id]);
            $recent_activities = $stmt->fetchAll();
            echo "Recent activities from attendance_logs: " . count($recent_activities) . " records<br>";
        } else {
            $use_attendance_logs = false;
        }
    } catch (Exception $e) {
        echo "Error with attendance_logs: " . $e->getMessage() . "<br>";
        $use_attendance_logs = false;
    }
    
    if (!$use_attendance_logs) {
        // Fallback to attendance table
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE cadet_id = ?");
        $stmt->execute([$cadet_profile_id]);
        $attendance_count = $stmt->fetch()['count'];
        echo "Found $attendance_count records in attendance table<br>";
        
        // Recent attendance activities from attendance table
        $stmt = $pdo->prepare("
            SELECT CONCAT('Attendance: ', COALESCE(training_day, 'Training')) as action, created_at as timestamp 
            FROM attendance 
            WHERE cadet_id = ?
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$cadet_profile_id]);
        $recent_activities = $stmt->fetchAll();
        echo "Recent activities from attendance: " . count($recent_activities) . " records<br>";
    }
    
    echo "<br><h3>Recent Activities Data:</h3>";
    if (empty($recent_activities)) {
        echo "<p style='color: red;'>No recent activities found!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Action</th><th>Timestamp</th><th>Formatted Date</th></tr>";
        foreach ($recent_activities as $activity) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($activity['action']) . "</td>";
            echo "<td>" . htmlspecialchars($activity['timestamp']) . "</td>";
            echo "<td>" . date('M j, H:i', strtotime($activity['timestamp'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test the HTML output that would be generated
    echo "<br><h3>HTML Output Test:</h3>";
    echo "<div class='activity-list'>";
    if (empty($recent_activities)) {
        echo "<p style='color: var(--text-secondary); text-align: center; padding: var(--spacing-xl);'>No recent activities found.</p>";
    } else {
        foreach (array_slice($recent_activities, 0, 5) as $activity) {
            echo "<div class='activity-item' style='padding: 10px; border-bottom: 1px solid #ccc; display: flex; justify-content: space-between; align-items: center;'>";
            echo "<div>";
            echo "<strong style='color: #333;'>" . htmlspecialchars($activity['action']) . "</strong>";
            echo "<p style='color: #666; margin: 5px 0 0 0; font-size: 0.9rem;'>Personal Activity</p>";
            echo "</div>";
            echo "<span style='color: #999; font-size: 0.85rem;'>" . date('M j, H:i', strtotime($activity['timestamp'])) . "</span>";
            echo "</div>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$output = ob_get_clean();
file_put_contents('activities_test_results.txt', $output);
echo "Test results saved to activities_test_results.txt";
?>