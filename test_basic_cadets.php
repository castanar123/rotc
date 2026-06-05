<?php
require_once 'includes/db.php';

echo "<h2>Testing Basic Cadet Count Fix</h2>";
echo "<hr>";

try {
    // Test the fixed query from admin_dashboard.php
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' 
        AND u.status = 'active'
    ");
    $basic_cadets = $stmt->fetch()['total'];
    echo "✓ Basic Cadets (with 'active' status): $basic_cadets<br>";
    
    // Test the old query for comparison
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' 
        AND u.status = 'approved'
    ");
    $basic_cadets_old = $stmt->fetch()['total'];
    echo "✓ Basic Cadets (with 'approved' status): $basic_cadets_old<br>";
    
    // Show all users with basic_cadet role
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.status, u.role, 
               cp.first_name, cp.last_name, cp.status as cadet_status
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet'
    ");
    $all_basic_cadets = $stmt->fetchAll();
    
    echo "<br><h3>All Basic Cadets in Database:</h3>";
    if (count($all_basic_cadets) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>User Status</th><th>Name</th><th>Cadet Status</th></tr>";
        foreach ($all_basic_cadets as $cadet) {
            echo "<tr>";
            echo "<td>{$cadet['id']}</td>";
            echo "<td>{$cadet['username']}</td>";
            echo "<td>{$cadet['status']}</td>";
            echo "<td>{$cadet['first_name']} {$cadet['last_name']}</td>";
            echo "<td>{$cadet['cadet_status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No basic cadets found in database.";
    }
    
    // Show attendance records
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) as present FROM attendance WHERE DATE(timestamp) = CURDATE()");
    $today_attendance = $stmt->fetch()['present'];
    echo "<br><h3>Today's Attendance: $today_attendance</h3>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
?>