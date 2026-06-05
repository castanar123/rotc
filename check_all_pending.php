<?php
// Check all pending users in the database
require_once 'includes/db.php';

global $link;

echo "<h2>🔍 Checking All Pending Users in Database</h2>\n";

// Check all users with pending status
$all_pending_query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.approval_status,
        u.created_at,
        u.role,
        cp.full_name,
        cp.student_number,
        cp.year_level,
        cp.course
    FROM users u
    LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.approval_status = 'pending'
    ORDER BY u.created_at DESC
";

$result = $link->query($all_pending_query);

if ($result) {
    $total_pending = $result->num_rows;
    echo "<h3>📊 Total Pending Users Found: <strong style='color: orange;'>$total_pending</strong></h3>\n";
    
    if ($total_pending > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Student #</th>";
        echo "<th>Role</th><th>Status</th><th>Created</th>";
        echo "</tr>\n";
        
        $count = 0;
        while ($user = $result->fetch_assoc()) {
            $count++;
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>" . ($user['full_name'] ?? 'N/A') . "</td>";
            echo "<td>" . ($user['student_number'] ?? 'N/A') . "</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td style='color: orange; font-weight: bold;'>{$user['approval_status']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<h3>📈 Breakdown by Role</h3>\n";
        
        // Get breakdown by role
        $role_breakdown_query = "
            SELECT 
                role,
                COUNT(*) as count
            FROM users 
            WHERE approval_status = 'pending'
            GROUP BY role
        ";
        
        $role_result = $link->query($role_breakdown_query);
        if ($role_result) {
            echo "<ul>\n";
            while ($role_data = $role_result->fetch_assoc()) {
                echo "<li><strong>{$role_data['role']}</strong>: {$role_data['count']} pending</li>\n";
            }
            echo "</ul>\n";
        }
        
    } else {
        echo "<p>No pending users found in the database.</p>\n";
    }
} else {
    echo "<p style='color: red;'>❌ Error querying database: " . $link->error . "</p>\n";
}

// Also check the enrollment tracking system filter
echo "<h3>🔍 Checking Enrollment Tracking Filter</h3>\n";

$cadet_only_query = "
    SELECT 
        COUNT(*) as total_cadets_pending
    FROM users u
    WHERE u.role = 'cadet' AND u.approval_status = 'pending'
";

$cadet_result = $link->query($cadet_only_query);
if ($cadet_result) {
    $cadet_data = $cadet_result->fetch_assoc();
    echo "<p>📋 Cadets with pending status: <strong>{$cadet_data['total_cadets_pending']}</strong></p>\n";
    echo "<p>💡 <em>Note: The enrollment tracking system may be filtering to show only 'cadet' role users.</em></p>\n";
}

$link->close();
?>