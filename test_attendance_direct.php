<?php
// Direct test of the attendance logic without HTTP requests
require_once 'includes/session.php';
require_once 'includes/db.php';

// Simulate session for user 28
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 28;
$_SESSION['role'] = 'basic_cadet';
$_SESSION['username'] = 'test23';

echo "<h2>Direct Test of Cadet Attendance Logic</h2>";
echo "<p>Session User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Session Role: " . $_SESSION['role'] . "</p>";

try {
    // Get cadet profile info with fallback logic (same as in cadet_attendance_new.php)
    $stmt = $pdo->prepare("SELECT id, student_id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile_data = $stmt->fetch();
    
    echo "<h3>Cadet Profile Data:</h3>";
    if ($profile_data) {
        echo "<ul>";
        echo "<li>Cadet ID: " . $profile_data['id'] . "</li>";
        echo "<li>Student ID: " . $profile_data['student_id'] . "</li>";
        echo "<li>Full Name: " . $profile_data['full_name'] . "</li>";
        echo "<li>Platoon: " . $profile_data['platoon'] . "</li>";
        echo "</ul>";
        
        $cadet_id = $profile_data['id'];
    } else {
        echo "<p style='color: orange;'>No cadet profile found, using fallback logic</p>";
        $cadet_id = $_SESSION['user_id'];
    }
    
    echo "<h3>Using Cadet ID: $cadet_id for attendance lookup</h3>";
    
    // Check attendance data
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present_days,
            COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent_days,
            ROUND((COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
        FROM attendance 
        WHERE cadet_id = ?
    ");
    $stmt->execute([$cadet_id]);
    $stats = $stmt->fetch();
    
    echo "<h3>Attendance Statistics:</h3>";
    if ($stats && $stats['total_days'] > 0) {
        echo "<ul>";
        echo "<li>Total Days: " . $stats['total_days'] . "</li>";
        echo "<li>Present Days: " . $stats['present_days'] . "</li>";
        echo "<li>Absent Days: " . $stats['absent_days'] . "</li>";
        echo "<li>Attendance Rate: " . $stats['attendance_rate'] . "%</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>No attendance records found for cadet_id $cadet_id</p>";
    }
    
    // Get recent attendance records
    $stmt = $pdo->prepare("
        SELECT 
            a.log_date as date,
            a.status,
            a.log_time as time_in,
            a.training_day as remarks
        FROM attendance a
        WHERE a.cadet_id = ?
        ORDER BY a.log_date DESC, a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$cadet_id]);
    $recent_attendance = $stmt->fetchAll();
    
    echo "<h3>Recent Attendance Records:</h3>";
    if ($recent_attendance && count($recent_attendance) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Date</th><th>Status</th><th>Time In</th><th>Remarks</th></tr>";
        foreach ($recent_attendance as $record) {
            echo "<tr>";
            echo "<td>" . $record['date'] . "</td>";
            echo "<td>" . $record['status'] . "</td>";
            echo "<td>" . ($record['time_in'] ?: 'N/A') . "</td>";
            echo "<td>" . ($record['remarks'] ?: 'Training') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No recent attendance records found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>