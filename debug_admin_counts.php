<?php
// Debug script to check why admin_dashboard.php shows 0 for cadet counts
require_once 'includes/db.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Admin Dashboard Debug</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; }\n.success { color: green; }\n.error { color: red; }\n.warning { color: orange; }\n.info { color: blue; }\ntable { border-collapse: collapse; width: 100%; margin: 10px 0; }\nth, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\nth { background-color: #f2f2f2; }\n.query-box { background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; }\n</style>\n</head>\n<body>";

echo "<h1>Admin Dashboard Debug Report</h1>";
echo "<p>Generated at: " . date('Y-m-d H:i:s') . "</p>";

try {
    // 1. Test Database Connection
    echo "<h2>1. Database Connection Test</h2>";
    if ($pdo) {
        echo "<p class='success'>✓ Database connection successful</p>";
        echo "<p class='info'>Database: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed</p>";
        exit;
    }
    
    // 2. Check if required tables exist
    echo "<h2>2. Required Tables Check</h2>";
    $required_tables = ['users', 'cadet_profiles', 'attendance', 'audit_logs'];
    
    foreach ($required_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "<p class='success'>✓ Table '$table' exists with $count records</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Table '$table' error: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. Check users table structure and data
    echo "<h2>3. Users Table Analysis</h2>";
    
    // Show table structure
    try {
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        echo "<h3>Users Table Structure:</h3>";
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p class='error'>Error getting users table structure: " . $e->getMessage() . "</p>";
    }
    
    // Check all users
    echo "<h3>All Users in Database:</h3>";
    $stmt = $pdo->query("SELECT id, username, role, status, created_at FROM users ORDER BY id");
    $all_users = $stmt->fetchAll();
    
    if (empty($all_users)) {
        echo "<p class='warning'>⚠ No users found in database!</p>";
    } else {
        echo "<table><tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        foreach ($all_users as $user) {
            $class = ($user['role'] == 'basic_cadet' && $user['status'] == 'active') ? 'success' : '';
            echo "<tr class='$class'><td>{$user['id']}</td><td>{$user['username']}</td><td>{$user['role']}</td><td>{$user['status']}</td><td>{$user['created_at']}</td></tr>";
        }
        echo "</table>";
    }
    
    // 4. Test specific queries from admin_dashboard.php
    echo "<h2>4. Admin Dashboard Queries Test</h2>";
    
    // Query 1: Basic cadets count
    echo "<h3>Query 1: Basic Cadets Count</h3>";
    $query1 = "SELECT COUNT(*) as total FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic_cadet' AND u.status = 'active'";
    echo "<div class='query-box'>$query1</div>";
    
    try {
        $stmt = $pdo->query($query1);
        $result1 = $stmt->fetch()['total'];
        echo "<p class='info'>Result: $result1 basic cadets</p>";
        
        if ($result1 == 0) {
            echo "<p class='warning'>⚠ Zero basic cadets found. Let's check why:</p>";
            
            // Check users with basic_cadet role (any status)
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet'");
            $basic_any_status = $stmt->fetch()['total'];
            echo "<p>- Users with role 'basic_cadet' (any status): $basic_any_status</p>";
            
            // Check active users (any role)
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
            $active_any_role = $stmt->fetch()['total'];
            echo "<p>- Users with status 'active' (any role): $active_any_role</p>";
            
            // Show all roles in database
            $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $roles = $stmt->fetchAll();
            echo "<p>- All roles in database:</p><ul>";
            foreach ($roles as $role) {
                echo "<li>{$role['role']}: {$role['count']} users</li>";
            }
            echo "</ul>";
            
            // Show all statuses in database
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
            $statuses = $stmt->fetchAll();
            echo "<p>- All statuses in database:</p><ul>";
            foreach ($statuses as $status) {
                echo "<li>{$status['status']}: {$status['count']} users</li>";
            }
            echo "</ul>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Query 1 failed: " . $e->getMessage() . "</p>";
    }
    
    // Query 2: Officers count
    echo "<h3>Query 2: Officers Count</h3>";
    $query2 = "SELECT COUNT(*) as total FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role IN ('1cl', 'commandant') AND (cp.status = 'Active' OR cp.status IS NULL)";
    echo "<div class='query-box'>$query2</div>";
    
    try {
        $stmt = $pdo->query($query2);
        $result2 = $stmt->fetch()['total'];
        echo "<p class='info'>Result: $result2 officers</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>Query 2 failed: " . $e->getMessage() . "</p>";
    }
    
    // Query 3: Total users
    echo "<h3>Query 3: Total Users</h3>";
    $query3 = "SELECT COUNT(*) as total FROM users";
    echo "<div class='query-box'>$query3</div>";
    
    try {
        $stmt = $pdo->query($query3);
        $result3 = $stmt->fetch()['total'];
        echo "<p class='info'>Result: $result3 total users</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>Query 3 failed: " . $e->getMessage() . "</p>";
    }
    
    // 5. Check cadet_profiles table
    echo "<h2>5. Cadet Profiles Analysis</h2>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_profiles");
        $cp_count = $stmt->fetch()['count'];
        echo "<p class='info'>Total cadet profiles: $cp_count</p>";
        
        if ($cp_count > 0) {
            // Show sample cadet profiles
            $stmt = $pdo->query("SELECT cp.*, u.username, u.role, u.status FROM cadet_profiles cp LEFT JOIN users u ON cp.user_id = u.id LIMIT 10");
            $profiles = $stmt->fetchAll();
            
            echo "<h3>Sample Cadet Profiles:</h3>";
            echo "<table><tr><th>ID</th><th>User ID</th><th>Username</th><th>Role</th><th>Status</th><th>First Name</th><th>Last Name</th><th>Student ID</th></tr>";
            foreach ($profiles as $profile) {
                echo "<tr><td>{$profile['id']}</td><td>{$profile['user_id']}</td><td>{$profile['username']}</td><td>{$profile['role']}</td><td>{$profile['status']}</td><td>{$profile['first_name']}</td><td>{$profile['last_name']}</td><td>{$profile['student_id']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Cadet profiles check failed: " . $e->getMessage() . "</p>";
    }
    
    // 6. Check attendance data
    echo "<h2>6. Attendance Data Check</h2>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance");
        $att_count = $stmt->fetch()['count'];
        echo "<p class='info'>Total attendance records: $att_count</p>";
        
        if ($att_count > 0) {
            // Today's attendance
            $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) as present FROM attendance WHERE DATE(timestamp) = CURDATE()");
            $today_att = $stmt->fetch()['present'];
            echo "<p class='info'>Today's attendance: $today_att students</p>";
            
            // Recent attendance
            $stmt = $pdo->query("SELECT * FROM attendance ORDER BY timestamp DESC LIMIT 5");
            $recent_att = $stmt->fetchAll();
            
            echo "<h3>Recent Attendance Records:</h3>";
            echo "<table><tr><th>ID</th><th>Student ID</th><th>Timestamp</th></tr>";
            foreach ($recent_att as $att) {
                echo "<tr><td>{$att['id']}</td><td>{$att['student_id']}</td><td>{$att['timestamp']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Attendance check failed: " . $e->getMessage() . "</p>";
    }
    
    // 7. Recommendations
    echo "<h2>7. Recommendations</h2>";
    
    if (empty($all_users)) {
        echo "<p class='error'>🔴 CRITICAL: No users in database. You need to add users first.</p>";
    } elseif ($result1 == 0) {
        echo "<p class='warning'>🟡 WARNING: No basic cadets with active status found.</p>";
        echo "<p>Possible solutions:</p>";
        echo "<ul>";
        echo "<li>Add users with role 'basic_cadet' and status 'active'</li>";
        echo "<li>Update existing users' status to 'active'</li>";
        echo "<li>Check if the role should be 'cadet' instead of 'basic_cadet'</li>";
        echo "</ul>";
    } else {
        echo "<p class='success'>✓ Database appears to be working correctly.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Fatal error: " . $e->getMessage() . "</p>";
    echo "<p class='error'>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "</body></html>";
?>