<?php
require_once 'includes/db.php';

echo "<h1>Database Users Check</h1>";
echo "<style>body{font-family:Arial;margin:20px;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;}th{background:#f2f2f2;}.highlight{background:#ffffcc;}</style>";

try {
    // Get total count of users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total = $stmt->fetch()['total'];
    echo "<p><strong>Total users in database: $total</strong></p>";
    
    if ($total == 0) {
        echo "<h2 style='color:red;'>❌ DATABASE IS EMPTY!</h2>";
        echo "<p>No users found. The admin dashboard shows 0 because there are no users in the database.</p>";
        echo "<p><strong>Solution:</strong> You need to register some users first.</p>";
        echo "<p><a href='register.php'>Go to Registration Page</a></p>";
    } else {
        // Show all users
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        
        echo "<h2>All Users:</h2>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        
        $target_count = 0;
        foreach ($users as $user) {
            $class = '';
            if ($user['role'] == 'basic_cadet' && $user['status'] == 'active') {
                $class = 'highlight';
                $target_count++;
            }
            echo "<tr class='$class'>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h2>Analysis:</h2>";
        echo "<p>Users with role='basic_cadet' AND status='active' (highlighted): <strong>$target_count</strong></p>";
        echo "<p>This is what the admin dashboard should show for 'Basic Cadets'.</p>";
        
        if ($target_count == 0) {
            echo "<h3 style='color:red;'>Problem Found:</h3>";
            echo "<p>No users have both role='basic_cadet' AND status='active'.</p>";
            
            // Check what roles exist
            $stmt = $pdo->query("SELECT DISTINCT role FROM users");
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>Available roles: " . implode(', ', $roles) . "</p>";
            
            // Check what statuses exist
            $stmt = $pdo->query("SELECT DISTINCT status FROM users");
            $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>Available statuses: " . implode(', ', $statuses) . "</p>";
            
            echo "<h3>Quick Fix Options:</h3>";
            echo "<ol>";
            echo "<li>Update existing users to have status='active'</li>";
            echo "<li>Update existing users to have role='basic_cadet'</li>";
            echo "<li>Register new users with correct role and status</li>";
            echo "</ol>";
        }
        
        // Test the exact query from admin dashboard
        echo "<h2>Testing Admin Dashboard Query:</h2>";
        $query = "SELECT COUNT(*) as total FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic_cadet' AND u.status = 'active'";
        echo "<p><code>$query</code></p>";
        
        $stmt = $pdo->query($query);
        $result = $stmt->fetch()['total'];
        echo "<p><strong>Query Result: $result</strong></p>";
        
        if ($result == 0) {
            echo "<p style='color:red;'>This confirms why admin dashboard shows 0.</p>";
        } else {
            echo "<p style='color:green;'>Query should return $result to admin dashboard.</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr><p><a href='admin_dashboard.php'>Back to Admin Dashboard</a></p>";
?>