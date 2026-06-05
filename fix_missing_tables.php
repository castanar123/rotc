<?php
require_once 'includes/db.php';

echo "<h1>Fixing Missing Database Tables</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

try {
    // Create audit_logs table if it doesn't exist
    echo "<h2>Creating audit_logs table...</h2>";
    $sql_audit = "
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
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_audit);
    echo "<p class='success'>✓ audit_logs table created successfully</p>";
    
    // Create advance_rotc_signups table if it doesn't exist
    echo "<h2>Creating advance_rotc_signups table...</h2>";
    $sql_rotc = "
        CREATE TABLE IF NOT EXISTS `advance_rotc_signups` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `full_name` varchar(255) NOT NULL,
            `course` varchar(255) NOT NULL,
            `facebook_link` varchar(500) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_course` (`course`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_rotc);
    echo "<p class='success'>✓ advance_rotc_signups table created successfully</p>";
    
    // Create attendance table if it doesn't exist
    echo "<h2>Creating attendance table...</h2>";
    $sql_attendance = "
        CREATE TABLE IF NOT EXISTS `attendance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` varchar(50) NOT NULL,
            `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
            `td` int(11) DEFAULT NULL,
            `semester` int(11) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_student_id` (`student_id`),
            INDEX `idx_timestamp` (`timestamp`),
            INDEX `idx_td_semester` (`td`, `semester`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_attendance);
    echo "<p class='success'>✓ attendance table created successfully</p>";
    
    // Insert some sample data for testing
    echo "<h2>Adding sample data...</h2>";
    
    // Sample audit logs
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO audit_logs (user_id, action, details, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    
    $sample_logs = [
        [1, 'login', 'User logged in successfully', '127.0.0.1'],
        [1, 'dashboard_view', 'Accessed admin dashboard', '127.0.0.1'],
        [2, 'user_approval', 'Approved user registration', '127.0.0.1'],
        [1, 'system_check', 'Performed system diagnostics', '127.0.0.1']
    ];
    
    foreach ($sample_logs as $log) {
        $stmt->execute($log);
    }
    echo "<p class='success'>✓ Sample audit logs added</p>";
    
    // Sample advance ROTC signups
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO advance_rotc_signups (full_name, course, facebook_link) 
        VALUES (?, ?, ?)
    ");
    
    $sample_signups = [
        ['John Doe', 'Computer Science', 'https://facebook.com/johndoe'],
        ['Jane Smith', 'Engineering', 'https://facebook.com/janesmith'],
        ['Mike Johnson', 'Business Administration', 'https://facebook.com/mikejohnson']
    ];
    
    foreach ($sample_signups as $signup) {
        $stmt->execute($signup);
    }
    echo "<p class='success'>✓ Sample advance ROTC signups added</p>";
    
    // Sample attendance records
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO attendance (student_id, td, semester, notes) 
        VALUES (?, ?, ?, ?)
    ");
    
    $sample_attendance = [
        ['2024-001', 1, 1, 'Present for TD1'],
        ['2024-002', 1, 1, 'Present for TD1'],
        ['2024-003', 1, 1, 'Present for TD1'],
        ['2024-001', 2, 1, 'Present for TD2'],
        ['2024-002', 2, 1, 'Present for TD2']
    ];
    
    foreach ($sample_attendance as $record) {
        $stmt->execute($record);
    }
    echo "<p class='success'>✓ Sample attendance records added</p>";
    
    echo "<h2>✅ All tables and sample data created successfully!</h2>";
    echo "<p class='info'>The admin dashboard should now display data properly.</p>";
    echo "<p><a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
?>