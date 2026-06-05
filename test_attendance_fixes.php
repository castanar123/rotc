<?php
session_start();
require_once 'includes/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Attendance Fixes Validation Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; } .error { color: red; } .success { color: green; } .warning { color: orange; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    // Test 1: Verify cadet_profiles and attendance relationship
    echo "<div class='section'>";
    echo "<h2>Test 1: Cadet Profiles and Attendance Relationship</h2>";
    
    // Find cadet profile with attendance data
    $stmt = $pdo->query("
        SELECT cp.id as cadet_id, cp.user_id, cp.first_name, cp.last_name, 
               COUNT(a.id) as attendance_count
        FROM cadet_profiles cp
        LEFT JOIN attendance a ON cp.id = a.cadet_id
        GROUP BY cp.id
        HAVING attendance_count > 0
        LIMIT 5
    ");
    $cadet_with_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cadet_with_attendance) > 0) {
        echo "<p class='success'>Found cadets with attendance data:</p>";
        echo "<table>";
        echo "<tr><th>Cadet ID</th><th>User ID</th><th>Name</th><th>Attendance Count</th></tr>";
        foreach ($cadet_with_attendance as $cadet) {
            echo "<tr>";
            echo "<td>" . $cadet['cadet_id'] . "</td>";
            echo "<td>" . ($cadet['user_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']) . "</td>";
            echo "<td>" . $cadet['attendance_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No cadets found with attendance data!</p>";
    }
    echo "</div>";
    
    // Test 2: Test fixed attendance queries
    echo "<div class='section'>";
    echo "<h2>Test 2: Fixed Attendance Queries</h2>";
    
    if (count($cadet_with_attendance) > 0) {
        $test_cadet = $cadet_with_attendance[0];
        $cadet_id = $test_cadet['cadet_id'];
        
        echo "<p>Testing with Cadet ID: <strong>$cadet_id</strong> (" . htmlspecialchars($test_cadet['first_name'] . ' ' . $test_cadet['last_name']) . ")</p>";
        
        // Test attendance statistics query
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
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>Attendance Statistics:</p>";
        echo "<ul>";
        echo "<li>Total Days: " . $stats['total_days'] . "</li>";
        echo "<li>Present Days: " . $stats['present_days'] . "</li>";
        echo "<li>Absent Days: " . $stats['absent_days'] . "</li>";
        echo "<li>Attendance Rate: " . $stats['attendance_rate'] . "%</li>";
        echo "</ul>";
        
        // Test recent attendance query
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
            WHERE a.cadet_id = ?
            ORDER BY a.log_date DESC, a.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$cadet_id]);
        $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>Recent Attendance Records (" . count($recent_attendance) . " found):</p>";
        if (count($recent_attendance) > 0) {
            echo "<table>";
            echo "<tr><th>Date</th><th>Status</th><th>Time In</th><th>Remarks</th></tr>";
            foreach ($recent_attendance as $record) {
                echo "<tr>";
                echo "<td>" . $record['date'] . "</td>";
                echo "<td>" . $record['status'] . "</td>";
                echo "<td>" . $record['time_in'] . "</td>";
                echo "<td>" . htmlspecialchars($record['remarks'] ?? '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    echo "</div>";
    
    // Test 3: Test attendance_logs query fix
    echo "<div class='section'>";
    echo "<h2>Test 3: Attendance Logs Query Fix</h2>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance_logs");
        $logs_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<p>Total attendance_logs records: <strong>$logs_count</strong></p>";
        
        if ($logs_count > 0) {
            // Test the fixed query
            $stmt = $pdo->prepare("
                SELECT al.*, cp.first_name, cp.last_name, cp.student_id 
                FROM attendance_logs al 
                JOIN cadet_profiles cp ON al.cadet_profile_id = cp.id 
                WHERE al.cadet_profile_id = ? 
                ORDER BY al.event_date DESC
                LIMIT 5
            ");
            
            if (count($cadet_with_attendance) > 0) {
                $stmt->execute([$cadet_with_attendance[0]['cadet_id']]);
                $logs_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<p class='success'>Fixed attendance_logs query executed successfully!</p>";
                echo "<p>Records found: " . count($logs_result) . "</p>";
            }
        } else {
            echo "<p class='warning'>No attendance_logs records found - table is empty.</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Attendance logs query error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Test 4: Session simulation test
    echo "<div class='section'>";
    echo "<h2>Test 4: Session Simulation Test</h2>";
    
    if (count($cadet_with_attendance) > 0) {
        $test_cadet = $cadet_with_attendance[0];
        
        // Simulate session for testing
        $_SESSION['user_id'] = $test_cadet['user_id'];
        $_SESSION['loggedin'] = true;
        $_SESSION['role'] = 'basic_cadet';
        
        echo "<p>Simulating session with User ID: " . $test_cadet['user_id'] . "</p>";
        
        // Test cadet profile lookup
        $stmt = $pdo->prepare("SELECT id, student_number as student_id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile_data = $stmt->fetch();
        
        if ($profile_data) {
            echo "<p class='success'>Cadet profile found successfully!</p>";
            echo "<ul>";
            echo "<li>Profile ID: " . $profile_data['id'] . "</li>";
            echo "<li>Student ID: " . $profile_data['student_id'] . "</li>";
            echo "<li>Full Name: " . htmlspecialchars($profile_data['full_name']) . "</li>";
            echo "<li>Platoon: " . htmlspecialchars($profile_data['platoon'] ?? 'Unassigned') . "</li>";
            echo "</ul>";
        } else {
            echo "<p class='warning'>No cadet profile found for user ID: " . $_SESSION['user_id'] . "</p>";
        }
        
        // Clear session
        unset($_SESSION['user_id']);
        unset($_SESSION['loggedin']);
        unset($_SESSION['role']);
    }
    echo "</div>";
    
    // Test 5: Overall system health check
    echo "<div class='section'>";
    echo "<h2>Test 5: System Health Check</h2>";
    
    $health_checks = [];
    
    // Check if attendance table has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance");
    $attendance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $health_checks[] = [
        'check' => 'Attendance table has data',
        'status' => $attendance_count > 0 ? 'PASS' : 'FAIL',
        'details' => "$attendance_count records found"
    ];
    
    // Check if cadet_profiles table has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_profiles");
    $profiles_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $health_checks[] = [
        'check' => 'Cadet profiles table has data',
        'status' => $profiles_count > 0 ? 'PASS' : 'FAIL',
        'details' => "$profiles_count records found"
    ];
    
    // Check if JOIN between attendance and cadet_profiles works
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM attendance a 
        JOIN cadet_profiles cp ON a.cadet_id = cp.id
    ");
    $join_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $health_checks[] = [
        'check' => 'Attendance-CadetProfiles JOIN works',
        'status' => $join_count > 0 ? 'PASS' : 'FAIL',
        'details' => "$join_count joined records found"
    ];
    
    echo "<table>";
    echo "<tr><th>Health Check</th><th>Status</th><th>Details</th></tr>";
    foreach ($health_checks as $check) {
        $status_class = $check['status'] === 'PASS' ? 'success' : 'error';
        echo "<tr>";
        echo "<td>" . $check['check'] . "</td>";
        echo "<td class='$status_class'>" . $check['status'] . "</td>";
        echo "<td>" . $check['details'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2>Test Error</h2>";
    echo "<p class='error'>Error during testing: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<p><a href='cadet_attendance.php'>Test Cadet Attendance Page</a> | <a href='debug_cadet_attendance.php'>Run Debug Script</a></p>";
?>