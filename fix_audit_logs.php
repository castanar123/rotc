<?php
require_once 'includes/db.php';

echo "<h2>Fixing audit_logs Table</h2>";

try {
    // First, get existing user IDs
    $stmt = $pdo->query("SELECT id FROM users LIMIT 5");
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Available user IDs: " . implode(', ', $user_ids) . "</p>";
    
    if (!empty($user_ids)) {
        // Insert sample audit log entries with valid user IDs
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $sample_logs = [
            ['action' => 'login', 'details' => 'User logged in'],
            ['action' => 'dashboard_view', 'details' => 'Viewed admin dashboard'],
            ['action' => 'profile_update', 'details' => 'Updated profile information'],
            ['action' => 'user_approval', 'details' => 'Approved user registration'],
            ['action' => 'attendance_scan', 'details' => 'QR code attendance scan']
        ];
        
        foreach ($sample_logs as $i => $log) {
            $user_id = isset($user_ids[$i]) ? $user_ids[$i] : $user_ids[0];
            $stmt->execute([
                $user_id,
                $log['action'],
                $log['details'],
                '127.0.0.1',
                'Mozilla/5.0 (Sample User Agent)'
            ]);
        }
        
        echo "<p style='color: green;'>✓ Sample audit log entries inserted with valid user IDs!</p>";
    }
    
    // Test the audit_logs query from admin dashboard
    $stmt = $pdo->query("
        SELECT al.*, u.username, cp.first_name, cp.last_name
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        ORDER BY al.created_at DESC 
        LIMIT 5
    ");
    
    $recent_activities = $stmt->fetchAll();
    
    echo "<h3>Recent Activities Test:</h3>";
    if (!empty($recent_activities)) {
        echo "<table border='1'><tr><th>User</th><th>Action</th><th>Details</th><th>Time</th></tr>";
        foreach ($recent_activities as $activity) {
            $user_name = $activity['first_name'] && $activity['last_name'] 
                ? $activity['first_name'] . ' ' . $activity['last_name']
                : ($activity['username'] ?: 'Unknown User');
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user_name) . "</td>";
            echo "<td>" . htmlspecialchars($activity['action']) . "</td>";
            echo "<td>" . htmlspecialchars($activity['details']) . "</td>";
            echo "<td>" . htmlspecialchars($activity['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p style='color: green;'>✓ Recent activities query working!</p>";
    } else {
        echo "<p style='color: orange;'>No recent activities found.</p>";
    }
    
    // Test the exact query from admin dashboard
    echo "<h3>Testing Admin Dashboard Query:</h3>";
    $stmt = $pdo->query("
        SELECT al.*, cp.full_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $dashboard_activities = $stmt->fetchAll();
    
    echo "<p style='color: green;'>✓ Admin dashboard query returned " . count($dashboard_activities) . " activities</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: {$e->getMessage()}</p>";
}
?>