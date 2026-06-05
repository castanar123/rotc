<?php
// Simple test to simulate a logged-in cadet and test dashboard data
require_once 'includes/db.php';

echo "<h2>Cadet Dashboard Login Test</h2>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; } .error { border-left-color: #dc3545; } .success { border-left-color: #28a745; }</style>";

// Check if we have any users in the system
echo "<div class='debug'>";
echo "<h3>Available Users in System:</h3>";
try {
    $stmt = $pdo->query("SELECT id, username, role, full_name FROM users WHERE role IN ('cadet', 'basic_cadet') LIMIT 10");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<div class='error'>No cadet users found in the system!</div>";
    } else {
        echo "<div class='success'>Found " . count($users) . " cadet users:</div>";
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}, Name: {$user['full_name']}<br>";
        }
        
        // Test with the first cadet user
        $test_user = $users[0];
        echo "<br><strong>Testing with user: {$test_user['username']} (ID: {$test_user['id']})</strong><br>";
        
        // Simulate session
        session_start();
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $test_user['id'];
        $_SESSION['username'] = $test_user['username'];
        $_SESSION['role'] = $test_user['role'];
        $_SESSION['full_name'] = $test_user['full_name'];
        
        echo "<div class='success'>Session simulated successfully!</div>";
        
        // Test cadet profile lookup
        $stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ?");
        $stmt->execute([$test_user['id']]);
        $cadet_profile = $stmt->fetch();
        
        if ($cadet_profile) {
            echo "<div class='success'>Cadet profile found for this user!</div>";
            echo "Profile ID: {$cadet_profile['id']}<br>";
            echo "Student ID: " . ($cadet_profile['student_id'] ?? 'Not set') . "<br>";
            echo "Name: " . ($cadet_profile['first_name'] ?? '') . " " . ($cadet_profile['last_name'] ?? '') . "<br>";
            
            $cadet_profile_id = $cadet_profile['id'];
            
            // Test attendance data
            echo "<h4>Testing Attendance Data:</h4>";
            
            // Check attendance_logs table
            $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
            if ($table_check->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance_logs WHERE cadet_profile_id = ?");
                $stmt->execute([$cadet_profile_id]);
                $attendance_count = $stmt->fetch()['total'];
                echo "Attendance logs count: $attendance_count<br>";
                
                if ($attendance_count == 0) {
                    echo "<div class='error'>No attendance records found for this cadet in attendance_logs table</div>";
                }
            }
            
            // Check attendance table
            $table_check = $pdo->query("SHOW TABLES LIKE 'attendance'");
            if ($table_check->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
                $stmt->execute([$cadet_profile_id]);
                $attendance_count = $stmt->fetch()['total'];
                echo "Attendance table count: $attendance_count<br>";
                
                if ($attendance_count == 0) {
                    echo "<div class='error'>No attendance records found for this cadet in attendance table</div>";
                }
            }
            
            // Test grades data
            echo "<h4>Testing Grades Data:</h4>";
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM grades WHERE cadet_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $grades_count = $stmt->fetch()['total'];
            echo "Grades count: $grades_count<br>";
            
            if ($grades_count == 0) {
                echo "<div class='error'>No grades found for this cadet</div>";
            }
            
        } else {
            echo "<div class='error'>No cadet profile found for user ID: {$test_user['id']}</div>";
            echo "<strong>This is likely the main issue!</strong> Users exist but don't have corresponding cadet profiles.<br>";
            
            // Check if cadet_profiles table exists and has data
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM cadet_profiles");
            $profile_count = $stmt->fetch()['total'];
            echo "Total cadet profiles in database: $profile_count<br>";
            
            if ($profile_count == 0) {
                echo "<div class='error'>The cadet_profiles table is empty! This explains why the dashboard shows no data.</div>";
            } else {
                echo "<div class='error'>Cadet profiles exist but are not linked to this user. Check user_id relationships.</div>";
                
                // Show some cadet profiles
                $stmt = $pdo->query("SELECT id, user_id, student_id, first_name, last_name FROM cadet_profiles LIMIT 5");
                $profiles = $stmt->fetchAll();
                echo "<strong>Sample cadet profiles:</strong><br>";
                foreach ($profiles as $profile) {
                    echo "- Profile ID: {$profile['id']}, User ID: {$profile['user_id']}, Student ID: {$profile['student_id']}, Name: {$profile['first_name']} {$profile['last_name']}<br>";
                }
            }
        }
    }
} catch (PDOException $e) {
    echo "<div class='error'>Database error: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='debug success'><h3>Test Complete</h3>";
echo "<p>If cadet profiles are missing or not linked to users, that's the root cause of the dashboard showing zeros.</p>";
echo "<p><a href='cadet_dashboard.php'>Test Cadet Dashboard Now</a> (with simulated session)</p>";
echo "</div>";
?>