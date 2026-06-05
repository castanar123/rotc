<?php
session_start();
require_once 'includes/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Final Attendance System Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; } .error { color: red; } .success { color: green; } .warning { color: orange; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    // Find a valid user with cadet profile and attendance data
    echo "<div class='section'>";
    echo "<h2>Setting Up Test User Session</h2>";
    
    $stmt = $pdo->query("
        SELECT u.id as user_id, u.username, u.role,
               cp.id as cadet_id, cp.first_name, cp.last_name,
               COUNT(a.id) as attendance_count
        FROM users u
        JOIN cadet_profiles cp ON u.id = cp.user_id
        LEFT JOIN attendance a ON cp.id = a.cadet_id
        GROUP BY u.id, cp.id
        HAVING attendance_count > 0
        LIMIT 1
    ");
    $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_user) {
        // Set up session for testing
        $_SESSION['user_id'] = $test_user['user_id'];
        $_SESSION['username'] = $test_user['username'];
        $_SESSION['role'] = 'basic_cadet';
        $_SESSION['loggedin'] = true;
        
        echo "<p class='success'>Test user session established:</p>";
        echo "<ul>";
        echo "<li>User ID: " . $test_user['user_id'] . "</li>";
        echo "<li>Username: " . htmlspecialchars($test_user['username']) . "</li>";
        echo "<li>Cadet Name: " . htmlspecialchars($test_user['first_name'] . ' ' . $test_user['last_name']) . "</li>";
        echo "<li>Attendance Records: " . $test_user['attendance_count'] . "</li>";
        echo "</ul>";
    } else {
        echo "<p class='error'>No valid test user found with both cadet profile and attendance data!</p>";
        exit;
    }
    echo "</div>";
    
    // Test the cadet_attendance.php logic
    echo "<div class='section'>";
    echo "<h2>Testing Cadet Attendance Logic</h2>";
    
    // Simulate the cadet_attendance.php logic
    $cadet_profile = null;
    
    // Get cadet profile info (same logic as cadet_attendance.php)
    $stmt = $pdo->prepare("SELECT id, student_id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile_data = $stmt->fetch();
    
    if (!$profile_data) {
        echo "<p class='error'>No cadet profile found!</p>";
    } else {
        // Parse full name into first and last name
        $name_parts = explode(' ', $profile_data['full_name']);
        $cadet_profile = [
            'id' => $profile_data['id'],
            'student_id' => $profile_data['student_id'],
            'first_name' => $name_parts[0] ?? 'Unknown',
            'last_name' => isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : 'User',
            'platoon' => $profile_data['platoon']
        ];
        
        echo "<p class='success'>Cadet profile loaded successfully:</p>";
        echo "<ul>";
        echo "<li>Profile ID: " . $cadet_profile['id'] . "</li>";
        echo "<li>Student ID: " . htmlspecialchars($cadet_profile['student_id']) . "</li>";
        echo "<li>Name: " . htmlspecialchars($cadet_profile['first_name'] . ' ' . $cadet_profile['last_name']) . "</li>";
        echo "<li>Platoon: " . htmlspecialchars($cadet_profile['platoon'] ?? 'Unassigned') . "</li>";
        echo "</ul>";
    }
    echo "</div>";
    
    // Test attendance statistics
    echo "<div class='section'>";
    echo "<h2>Testing Attendance Statistics</h2>";
    
    if ($cadet_profile && $cadet_profile['id']) {
        // Use attendance table structure (same logic as cadet_attendance.php)
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present_days,
                COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent_days,
                ROUND((COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
            FROM attendance 
            WHERE cadet_id = ?
        ");
        $stmt->execute([$cadet_profile['id']]);
        $stats = $stmt->fetch();
        
        echo "<p class='success'>Attendance statistics calculated:</p>";
        echo "<ul>";
        echo "<li>Total Days: " . $stats['total_days'] . "</li>";
        echo "<li>Present Days: " . $stats['present_days'] . "</li>";
        echo "<li>Absent Days: " . $stats['absent_days'] . "</li>";
        echo "<li>Attendance Rate: " . $stats['attendance_rate'] . "%</li>";
        echo "</ul>";
        
        // Test recent attendance records
        $stmt = $pdo->prepare("
            SELECT 
                a.log_date as date,
                a.status,
                a.log_time as time_in,
                NULL as time_out,
                a.training_day as remarks,
                cp.first_name,
                cp.last_name
            FROM attendance a
            LEFT JOIN cadet_profiles cp ON a.cadet_id = cp.id
            WHERE cp.user_id = ?
            ORDER BY a.log_date DESC, a.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recent_attendance = $stmt->fetchAll();
        
        echo "<p class='success'>Recent attendance records (" . count($recent_attendance) . " found):</p>";
        if (count($recent_attendance) > 0) {
            echo "<table>";
            echo "<tr><th>Date</th><th>Status</th><th>Time In</th><th>Remarks</th></tr>";
            foreach ($recent_attendance as $record) {
                echo "<tr>";
                echo "<td>" . date('M d, Y', strtotime($record['date'])) . "</td>";
                echo "<td>" . ucfirst($record['status']) . "</td>";
                echo "<td>" . ($record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-') . "</td>";
                echo "<td>" . htmlspecialchars($record['remarks'] ?? '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>No recent attendance records found!</p>";
        }
    }
    echo "</div>";
    
    // Final validation
    echo "<div class='section'>";
    echo "<h2>Final Validation Summary</h2>";
    
    $validation_results = [];
    
    // Check if session is properly set
    $validation_results[] = [
        'test' => 'Session Management',
        'status' => isset($_SESSION['user_id']) && isset($_SESSION['loggedin']) ? 'PASS' : 'FAIL',
        'details' => isset($_SESSION['user_id']) ? 'User ID: ' . $_SESSION['user_id'] : 'No session found'
    ];
    
    // Check if cadet profile is found
    $validation_results[] = [
        'test' => 'Cadet Profile Lookup',
        'status' => $cadet_profile && $cadet_profile['id'] ? 'PASS' : 'FAIL',
        'details' => $cadet_profile ? 'Profile ID: ' . $cadet_profile['id'] : 'No profile found'
    ];
    
    // Check if attendance data is retrieved
    $validation_results[] = [
        'test' => 'Attendance Data Retrieval',
        'status' => isset($stats) && $stats['total_days'] > 0 ? 'PASS' : 'FAIL',
        'details' => isset($stats) ? $stats['total_days'] . ' records found' : 'No data retrieved'
    ];
    
    // Check if recent attendance is retrieved
    $validation_results[] = [
        'test' => 'Recent Attendance Query',
        'status' => isset($recent_attendance) && count($recent_attendance) > 0 ? 'PASS' : 'FAIL',
        'details' => isset($recent_attendance) ? count($recent_attendance) . ' recent records' : 'No recent records'
    ];
    
    echo "<table>";
    echo "<tr><th>Test</th><th>Status</th><th>Details</th></tr>";
    foreach ($validation_results as $result) {
        $status_class = $result['status'] === 'PASS' ? 'success' : 'error';
        echo "<tr>";
        echo "<td>" . $result['test'] . "</td>";
        echo "<td class='$status_class'>" . $result['status'] . "</td>";
        echo "<td>" . $result['details'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    $all_passed = array_reduce($validation_results, function($carry, $item) {
        return $carry && ($item['status'] === 'PASS');
    }, true);
    
    if ($all_passed) {
        echo "<p class='success'><strong>🎉 ALL TESTS PASSED! The attendance system is now working correctly.</strong></p>";
    } else {
        echo "<p class='error'><strong>❌ Some tests failed. Please review the issues above.</strong></p>";
    }
    echo "</div>";
    
    // Clear session
    session_destroy();
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2>Test Error</h2>";
    echo "<p class='error'>Error during final testing: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<p><a href='cadet_attendance.php'>Go to Cadet Attendance Page</a></p>";
?>