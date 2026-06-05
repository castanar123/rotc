<?php
require_once 'includes/db.php';

echo "<h2>Testing Admin Dashboard Functionality</h2>";
echo "<hr>";

// Test 1: Summary Statistics
echo "<h3>1. Testing Summary Statistics</h3>";
try {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stmt->execute();
    $total_users = $stmt->fetch()['count'];
    echo "✓ Total Users: $total_users<br>";
    
    // Basic cadets
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'cadet'");
    $stmt->execute();
    $basic_cadets = $stmt->fetch()['count'];
    echo "✓ Basic Cadets: $basic_cadets<br>";
    
    // Officers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'officer'");
    $stmt->execute();
    $officers = $stmt->fetch()['count'];
    echo "✓ Officers: $officers<br>";
    
    // Command staff
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role = 'command_staff'");
    $stmt->execute();
    $command_staff = $stmt->fetch()['count'];
    echo "✓ Command Staff: $command_staff<br>";
    
    // Pending registrations
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
    $stmt->execute();
    $pending_registrations = $stmt->fetch()['count'];
    echo "✓ Pending Registrations: $pending_registrations<br>";
    
    // Advance ROTC applicants
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM advance_rotc_signups");
    $stmt->execute();
    $advance_rotc = $stmt->fetch()['count'];
    echo "✓ Advance ROTC Applicants: $advance_rotc<br>";
    
} catch (Exception $e) {
    echo "❌ Error in summary statistics: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 2: Recent Activities
echo "<h3>2. Testing Recent Activities</h3>";
try {
    // Test audit_logs query
    $stmt = $pdo->prepare("
        SELECT al.action, al.created_at, 
               CONCAT(cp.first_name, ' ', cp.last_name) AS full_name,
               u.username, cp.first_name, cp.last_name
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        ORDER BY al.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    if (count($activities) > 0) {
        echo "✓ Audit logs query working. Found " . count($activities) . " activities:<br>";
        foreach ($activities as $activity) {
            echo "&nbsp;&nbsp;- {$activity['action']} by {$activity['full_name']} at {$activity['created_at']}<br>";
        }
    } else {
        echo "⚠️ No activities found in audit_logs, testing fallback...<br>";
        
        // Test attendance fallback
        $stmt = $pdo->prepare("
            SELECT 'Attendance recorded' as action, a.created_at,
                   CONCAT(cp.first_name, ' ', cp.last_name) AS full_name,
                   u.username, cp.first_name, cp.last_name
            FROM attendance a 
            LEFT JOIN users u ON a.user_id = u.id 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            ORDER BY a.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll();
        
        if (count($activities) > 0) {
            echo "✓ Attendance fallback working. Found " . count($activities) . " activities:<br>";
            foreach ($activities as $activity) {
                echo "&nbsp;&nbsp;- {$activity['action']} by {$activity['full_name']} at {$activity['created_at']}<br>";
            }
        } else {
            echo "⚠️ No attendance records, testing final fallback...<br>";
            
            // Test users fallback
            $stmt = $pdo->prepare("
                SELECT 'User registered' as action, u.created_at,
                       CONCAT(cp.first_name, ' ', cp.last_name) AS full_name,
                       u.username, cp.first_name, cp.last_name
                FROM users u 
                LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
                WHERE u.status = 'active' 
                ORDER BY u.created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $activities = $stmt->fetchAll();
            
            echo "✓ Users fallback working. Found " . count($activities) . " activities:<br>";
            foreach ($activities as $activity) {
                echo "&nbsp;&nbsp;- {$activity['action']} by {$activity['full_name']} at {$activity['created_at']}<br>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error in recent activities: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 3: Pending Users for Approval
echo "<h3>3. Testing Pending Users for Approval</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at,
               CONCAT(cp.first_name, ' ', cp.last_name) AS full_name,
               cp.first_name, cp.last_name, cp.student_id, cp.course, cp.year_level
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.status = 'pending' 
        ORDER BY u.created_at ASC
    ");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();
    
    if (count($pending_users) > 0) {
        echo "✓ Found " . count($pending_users) . " pending users:<br>";
        foreach ($pending_users as $user) {
            echo "&nbsp;&nbsp;- {$user['full_name']} ({$user['username']}) - {$user['email']}<br>";
        }
    } else {
        echo "ℹ️ No pending users found.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error in pending users: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 4: Today's Attendance
echo "<h3>4. Testing Today's Attendance</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $today_attendance = $stmt->fetch()['count'];
    echo "✓ Today's Attendance: $today_attendance<br>";
    
} catch (Exception $e) {
    echo "❌ Error in today's attendance: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Dashboard Test Complete!</h3>";
echo "<p><a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
?>