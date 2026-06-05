<?php
require_once 'includes/db.php';

echo "<h2>Creating Missing audit_logs Table</h2>";

try {
    // Create audit_logs table
    $sql = "
        CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action` varchar(255) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ audit_logs table created successfully!</p>";
    
    // Insert some sample audit log entries for testing
    $sample_logs = [
        ['user_id' => 1, 'action' => 'login', 'details' => 'User logged in', 'ip_address' => '127.0.0.1'],
        ['user_id' => 1, 'action' => 'dashboard_view', 'details' => 'Viewed admin dashboard', 'ip_address' => '127.0.0.1'],
        ['user_id' => 2, 'action' => 'profile_update', 'details' => 'Updated profile information', 'ip_address' => '127.0.0.1'],
        ['user_id' => 1, 'action' => 'user_approval', 'details' => 'Approved user registration', 'ip_address' => '127.0.0.1'],
        ['user_id' => 3, 'action' => 'attendance_scan', 'details' => 'QR code attendance scan', 'ip_address' => '127.0.0.1']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($sample_logs as $log) {
        $stmt->execute([
            $log['user_id'],
            $log['action'],
            $log['details'],
            $log['ip_address'],
            'Mozilla/5.0 (Sample User Agent)'
        ]);
    }
    
    echo "<p style='color: green;'>✓ Sample audit log entries inserted!</p>";
    
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
                : $activity['username'];
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
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: {$e->getMessage()}</p>";
}
?>