<?php
// Create missing tables for ROTC system
require_once 'includes/db.php';

echo "Creating missing tables...\n";

try {
    // Create attendance_records table
    $sql_attendance = "
    CREATE TABLE IF NOT EXISTS `attendance_records` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cadet_id` int(11) NOT NULL,
      `training_day_id` int(11) DEFAULT NULL,
      `date` date NOT NULL,
      `time_in` time DEFAULT NULL,
      `time_out` time DEFAULT NULL,
      `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
      `remarks` text DEFAULT NULL,
      `recorded_by` int(11) NOT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`),
      FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($link->query($sql_attendance) === TRUE) {
        echo "✅ Table 'attendance_records' created successfully\n";
    } else {
        echo "❌ Error creating attendance_records table: " . $link->error . "\n";
    }
    
    // Create qr_codes table
    $sql_qr = "
    CREATE TABLE IF NOT EXISTS `qr_codes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `code` varchar(255) NOT NULL UNIQUE,
      `type` enum('attendance','event','general') NOT NULL DEFAULT 'attendance',
      `data` text DEFAULT NULL,
      `expires_at` datetime DEFAULT NULL,
      `is_active` boolean NOT NULL DEFAULT TRUE,
      `usage_count` int(11) NOT NULL DEFAULT 0,
      `max_usage` int(11) DEFAULT NULL,
      `created_by` int(11) NOT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($link->query($sql_qr) === TRUE) {
        echo "✅ Table 'qr_codes' created successfully\n";
    } else {
        echo "❌ Error creating qr_codes table: " . $link->error . "\n";
    }
    
    // Create training_days table (referenced by attendance_records)
    $sql_training = "
    CREATE TABLE IF NOT EXISTS `training_days` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `date` date NOT NULL,
      `start_time` time DEFAULT NULL,
      `end_time` time DEFAULT NULL,
      `description` text DEFAULT NULL,
      `status` enum('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
      `created_by` int(11) NOT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($link->query($sql_training) === TRUE) {
        echo "✅ Table 'training_days' created successfully\n";
    } else {
        echo "❌ Error creating training_days table: " . $link->error . "\n";
    }
    
    echo "\n🎉 Missing tables creation complete!\n";
    
    // Verify tables exist
    echo "\nVerifying tables:\n";
    $tables_to_check = ['users', 'cadet_profiles', 'rifles', 'rifle_assignments', 'attendance_records', 'missing_id_requests', 'qr_codes', 'training_days'];
    
    foreach ($tables_to_check as $table) {
        $result = $link->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' is missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$link->close();
echo "\n🎉 Database setup complete! You can now access your application.\n";
?>