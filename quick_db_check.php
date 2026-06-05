<?php
require_once 'includes/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Quick Database Check</h2>";
echo "<style>table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;}th{background:#f2f2f2;}</style>";

try {
    // Check if users table exists and get all users
    $stmt = $pdo->query("SELECT id, username, role, status, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "<h3>All Users in Database (" . count($users) . " total):</h3>";
    
    if (empty($users)) {
        echo "<p style='color:red;'>❌ NO USERS FOUND IN DATABASE!</p>";
        echo "<p>The database appears to be empty. You need to add users first.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        
        $basic_cadet_active = 0;
        $basic_cadet_any = 0;
        $active_any = 0;
        
        foreach ($users as $user) {
            $highlight = '';
            if ($user['role'] == 'basic_cadet' && $user['status'] == 'active') {
                $highlight = 'style="background-color: #d4edda;"';
                $basic_cadet_active++;
            }
            if ($user['role'] == 'basic_cadet') $basic_cadet_any++;
            if ($user['status'] == 'active') $active_any++;
            
            echo "<tr $highlight>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Summary:</h3>";
        echo "<ul>";
        echo "<li>Users with role 'basic_cadet' AND status 'active': <strong>$basic_cadet_active</strong> (This is what admin dashboard should show)</li>";
        echo "<li>Users with role 'basic_cadet' (any status): <strong>$basic_cadet_any</strong></li>";
        echo "<li>Users with status 'active' (any role): <strong>$active_any</strong></li>";
        echo "</ul>";
        
        if ($basic_cadet_active == 0) {
            echo "<h3 style='color:red;'>❌ PROBLEM IDENTIFIED:</h3>";
            echo "<p>No users have BOTH role='basic_cadet' AND status='active'.</p>";
            
            if ($basic_cadet_any > 0) {
                echo "<p>✅ Solution: Update existing basic_cadet users to have status='active'</p>";
            } else {
                echo "<p>✅ Solution: Create users with role='basic_cadet' and status='active'</p>";
            }
        } else {
            echo "<h3 style='color:green;'>✅ Data looks correct!</h3>";
            echo "<p>There should be $basic_cadet_active basic cadets showing in admin dashboard.</p>";
        }
    }
    
    // Show all unique roles
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC");
    $roles = $stmt->fetchAll();
    
    echo "<h3>All Roles in Database:</h3>";
    echo "<ul>";
    foreach ($roles as $role) {
        echo "<li>{$role['role']}: {$role['count']} users</li>";
    }
    echo "</ul>";
    
    // Show all unique statuses
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status ORDER BY count DESC");
    $statuses = $stmt->fetchAll();
    
    echo "<h3>All Statuses in Database:</h3>";
    echo "<ul>";
    foreach ($statuses as $status) {
        echo "<li>{$status['status']}: {$status['count']} users</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>