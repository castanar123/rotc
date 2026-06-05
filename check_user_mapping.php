<?php
require_once 'includes/db.php';
session_start();

echo "<h2>User Session and Mapping Check</h2>";

// Check current session
echo "<h3>Current Session:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check user mapping
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    echo "<h3>User ID $user_id Mapping:</h3>";
    
    // Check cadet_profiles mapping
    $stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if ($profile) {
        echo "<p>Cadet Profile Found:</p>";
        echo "<pre>";
        print_r($profile);
        echo "</pre>";
        
        $cadet_profile_id = $profile['id'];
        
        // Check attendance for this cadet
        echo "<h3>Attendance for Cadet Profile ID $cadet_profile_id:</h3>";
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
        $stmt->execute([$cadet_profile_id]);
        $attendance_count = $stmt->fetch()['total'];
        echo "<p>Total attendance records: $attendance_count</p>";
        
        if ($attendance_count > 0) {
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE cadet_id = ? ORDER BY log_date DESC LIMIT 3");
            $stmt->execute([$cadet_profile_id]);
            $attendance = $stmt->fetchAll();
            echo "<table border='1'><tr><th>Date</th><th>Status</th><th>TD</th><th>Semester</th></tr>";
            foreach ($attendance as $record) {
                echo "<tr><td>{$record['log_date']}</td><td>{$record['status']}</td><td>{$record['td']}</td><td>{$record['semester']}</td></tr>";
            }
            echo "</table>";
        }
        
        // Check grades for this cadet
        echo "<h3>Grades for Cadet Profile ID $cadet_profile_id:</h3>";
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM grades WHERE cadet_id = ?");
        $stmt->execute([$cadet_profile_id]);
        $grades_count = $stmt->fetch()['total'];
        echo "<p>Total grades records: $grades_count</p>";
        
    } else {
        echo "<p>No cadet profile found for user_id $user_id</p>";
    }
} else {
    echo "<p>No user_id in session</p>";
}

// Let's also check what cadet_ids have data
echo "<h3>Cadet IDs with Attendance Data:</h3>";
$stmt = $pdo->query("SELECT cadet_id, COUNT(*) as count FROM attendance GROUP BY cadet_id");
$cadet_data = $stmt->fetchAll();
echo "<table border='1'><tr><th>Cadet ID</th><th>Attendance Count</th></tr>";
foreach ($cadet_data as $data) {
    echo "<tr><td>{$data['cadet_id']}</td><td>{$data['count']}</td></tr>";
}
echo "</table>";
?>