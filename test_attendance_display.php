<?php
// Simple Attendance Data Display Test
// This script tests if attendance data can be fetched and displayed correctly

require_once 'includes/db.php';

echo "<h1>Attendance Data Display Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
</style>";

try {
    // Test 1: Basic database connection
    echo "<h2>Test 1: Database Connection</h2>";
    if ($pdo) {
        echo "<p class='success'>✓ Database connection successful!</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed!</p>";
        exit;
    }

    // Test 2: Check attendance table data
    echo "<h2>Test 2: Attendance Table Data</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance");
    $count = $stmt->fetch()['count'];
    echo "<p class='info'>Total attendance records: {$count}</p>";

    if ($count > 0) {
        echo "<h3>Sample Attendance Records:</h3>";
        $stmt = $pdo->query("SELECT * FROM attendance ORDER BY log_date DESC LIMIT 10");
        $records = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Cadet ID</th><th>Student ID</th><th>Date</th><th>Time</th><th>Status</th></tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>{$record['id']}</td>";
            echo "<td>{$record['cadet_id']}</td>";
            echo "<td>{$record['student_id']}</td>";
            echo "<td>{$record['log_date']}</td>";
            echo "<td>{$record['log_time']}</td>";
            echo "<td>{$record['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>No attendance records found!</p>";
    }

    // Test 3: Check cadet_profiles table
    echo "<h2>Test 3: Cadet Profiles Data</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_profiles");
    $count = $stmt->fetch()['count'];
    echo "<p class='info'>Total cadet profiles: {$count}</p>";

    if ($count > 0) {
        echo "<h3>Sample Cadet Profiles:</h3>";
        $stmt = $pdo->query("SELECT id, user_id, first_name, last_name, student_id FROM cadet_profiles LIMIT 10");
        $profiles = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>Profile ID</th><th>User ID</th><th>First Name</th><th>Last Name</th><th>Student ID</th></tr>";
        foreach ($profiles as $profile) {
            echo "<tr>";
            echo "<td>{$profile['id']}</td>";
            echo "<td>" . ($profile['user_id'] ?? 'NULL') . "</td>";
            echo "<td>{$profile['first_name']}</td>";
            echo "<td>{$profile['last_name']}</td>";
            echo "<td>{$profile['student_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test 4: JOIN query test
    echo "<h2>Test 4: JOIN Query Test</h2>";
    try {
        $stmt = $pdo->query("
            SELECT a.id, a.log_date, a.log_time, a.status, 
                   cp.first_name, cp.last_name, cp.student_id as profile_student_id
            FROM attendance a 
            JOIN cadet_profiles cp ON a.cadet_id = cp.id 
            ORDER BY a.log_date DESC 
            LIMIT 10
        ");
        $joinResults = $stmt->fetchAll();
        
        if ($joinResults) {
            echo "<p class='success'>✓ JOIN query successful! Found " . count($joinResults) . " records.</p>";
            echo "<h3>JOIN Query Results:</h3>";
            echo "<table>";
            echo "<tr><th>Attendance ID</th><th>Date</th><th>Time</th><th>Status</th><th>Cadet Name</th><th>Student ID</th></tr>";
            foreach ($joinResults as $result) {
                echo "<tr>";
                echo "<td>{$result['id']}</td>";
                echo "<td>{$result['log_date']}</td>";
                echo "<td>{$result['log_time']}</td>";
                echo "<td>{$result['status']}</td>";
                echo "<td>{$result['first_name']} {$result['last_name']}</td>";
                echo "<td>{$result['profile_student_id']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>✗ JOIN query returned no results!</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ JOIN query failed: " . $e->getMessage() . "</p>";
    }

    // Test 5: User-specific attendance test
    echo "<h2>Test 5: User-Specific Attendance Test</h2>";
    try {
        // Find a user with attendance records
        $stmt = $pdo->query("
            SELECT DISTINCT cp.user_id, cp.first_name, cp.last_name, COUNT(a.id) as attendance_count
            FROM cadet_profiles cp
            JOIN attendance a ON cp.id = a.cadet_id
            WHERE cp.user_id IS NOT NULL
            GROUP BY cp.user_id, cp.first_name, cp.last_name
            ORDER BY attendance_count DESC
            LIMIT 5
        ");
        $userStats = $stmt->fetchAll();
        
        if ($userStats) {
            echo "<p class='success'>✓ Found users with attendance records:</p>";
            echo "<table>";
            echo "<tr><th>User ID</th><th>Name</th><th>Attendance Count</th></tr>";
            foreach ($userStats as $stat) {
                echo "<tr>";
                echo "<td>{$stat['user_id']}</td>";
                echo "<td>{$stat['first_name']} {$stat['last_name']}</td>";
                echo "<td>{$stat['attendance_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>✗ No users found with attendance records!</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ User-specific query failed: " . $e->getMessage() . "</p>";
    }

    // Test 6: Check for orphaned records
    echo "<h2>Test 6: Data Integrity Check</h2>";
    try {
        // Check for attendance records without matching cadet profiles
        $stmt = $pdo->query("
            SELECT COUNT(*) as orphaned_count
            FROM attendance a
            LEFT JOIN cadet_profiles cp ON a.cadet_id = cp.id
            WHERE cp.id IS NULL
        ");
        $orphanedCount = $stmt->fetch()['orphaned_count'];
        
        if ($orphanedCount > 0) {
            echo "<p class='error'>⚠ Found {$orphanedCount} attendance records without matching cadet profiles!</p>";
        } else {
            echo "<p class='success'>✓ All attendance records have matching cadet profiles.</p>";
        }
        
        // Check for cadet profiles without user_id
        $stmt = $pdo->query("
            SELECT COUNT(*) as no_user_count
            FROM cadet_profiles
            WHERE user_id IS NULL
        ");
        $noUserCount = $stmt->fetch()['no_user_count'];
        
        if ($noUserCount > 0) {
            echo "<p class='error'>⚠ Found {$noUserCount} cadet profiles without user_id!</p>";
        } else {
            echo "<p class='success'>✓ All cadet profiles have user_id.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Data integrity check failed: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Test completed!</strong> If you see attendance data above, the database queries are working correctly.</p>";
echo "<p>If cadet_attendance.php still shows 0, the issue might be in session handling or user authentication logic.</p>";
echo "<p><a href='cadet_attendance.php'>← Back to Cadet Attendance</a></p>";
?>