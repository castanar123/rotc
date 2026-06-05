-- Complete Database Fix Script
-- This script fixes all missing columns and table issues identified

USE rotc_db;

-- Disable foreign key checks for safe modifications
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Fix users table - Add approval_status column for admin approval workflow
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER `status`;

-- 2. Fix cadet_profiles table - Add all missing columns
ALTER TABLE `cadet_profiles` 
ADD COLUMN IF NOT EXISTS `student_id` VARCHAR(20) AFTER `user_id`,
ADD COLUMN IF NOT EXISTS `first_name` VARCHAR(100) AFTER `student_id`,
ADD COLUMN IF NOT EXISTS `last_name` VARCHAR(100) AFTER `first_name`,
ADD COLUMN IF NOT EXISTS `middle_name` VARCHAR(100) AFTER `last_name`,
ADD COLUMN IF NOT EXISTS `gender` ENUM('male', 'female') DEFAULT 'male' AFTER `middle_name`,
ADD COLUMN IF NOT EXISTS `address` TEXT AFTER `contact_number`,
ADD COLUMN IF NOT EXISTS `religion` VARCHAR(100) AFTER `address`,
ADD COLUMN IF NOT EXISTS `birth_date` DATE AFTER `religion`,
ADD COLUMN IF NOT EXISTS `place_of_birth` VARCHAR(255) AFTER `birth_date`,
ADD COLUMN IF NOT EXISTS `height` DECIMAL(5,2) AFTER `place_of_birth`,
ADD COLUMN IF NOT EXISTS `weight` DECIMAL(5,2) AFTER `height`,
ADD COLUMN IF NOT EXISTS `skin_color` VARCHAR(50) AFTER `weight`,
ADD COLUMN IF NOT EXISTS `blood_type` VARCHAR(10) AFTER `skin_color`,
ADD COLUMN IF NOT EXISTS `father_name` VARCHAR(255) AFTER `blood_type`,
ADD COLUMN IF NOT EXISTS `father_occupation` VARCHAR(255) AFTER `father_name`,
ADD COLUMN IF NOT EXISTS `mother_name` VARCHAR(255) AFTER `father_occupation`,
ADD COLUMN IF NOT EXISTS `mother_occupation` VARCHAR(255) AFTER `mother_name`,
ADD COLUMN IF NOT EXISTS `guardian_name` VARCHAR(255) AFTER `mother_occupation`,
ADD COLUMN IF NOT EXISTS `guardian_contact` VARCHAR(20) AFTER `guardian_name`,
ADD COLUMN IF NOT EXISTS `guardian_relationship` VARCHAR(100) AFTER `guardian_contact`,
ADD COLUMN IF NOT EXISTS `guardian_address` TEXT AFTER `guardian_relationship`,
ADD COLUMN IF NOT EXISTS `emergency_contact_name` VARCHAR(255) AFTER `guardian_address`,
ADD COLUMN IF NOT EXISTS `emergency_contact_number` VARCHAR(20) AFTER `emergency_contact_name`,
ADD COLUMN IF NOT EXISTS `emergency_contact_relationship` VARCHAR(100) AFTER `emergency_contact_number`,
ADD COLUMN IF NOT EXISTS `emergency_contact_email` VARCHAR(255) AFTER `emergency_contact_relationship`,
ADD COLUMN IF NOT EXISTS `beneficiary_name` VARCHAR(255) AFTER `emergency_contact_email`,
ADD COLUMN IF NOT EXISTS `beneficiary_relationship` VARCHAR(100) AFTER `beneficiary_name`,
ADD COLUMN IF NOT EXISTS `beneficiary_address` TEXT AFTER `beneficiary_relationship`,
ADD COLUMN IF NOT EXISTS `province_city` VARCHAR(255) AFTER `beneficiary_address`,
ADD COLUMN IF NOT EXISTS `region` VARCHAR(255) AFTER `province_city`,
ADD COLUMN IF NOT EXISTS `semester` VARCHAR(50) AFTER `region`,
ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(20) AFTER `semester`,
ADD COLUMN IF NOT EXISTS `ms_level` INT DEFAULT 1 AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `section` VARCHAR(50) AFTER `ms_level`,
ADD COLUMN IF NOT EXISTS `photo_path` VARCHAR(500) AFTER `section`,
ADD COLUMN IF NOT EXISTS `qr_code_path` VARCHAR(500) AFTER `photo_path`;

-- Add unique constraint for student_id if it doesn't exist
ALTER TABLE `cadet_profiles` 
ADD UNIQUE KEY IF NOT EXISTS `uk_student_id` (`student_id`);

-- 3. Fix rifles table - Ensure all required columns exist
ALTER TABLE `rifles` 
ADD COLUMN IF NOT EXISTS `serial_number` VARCHAR(100) AFTER `rifle_number`,
ADD COLUMN IF NOT EXISTS `model` VARCHAR(100) AFTER `serial_number`,
ADD COLUMN IF NOT EXISTS `condition_status` ENUM('excellent', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good' AFTER `model`,
ADD COLUMN IF NOT EXISTS `notes` TEXT AFTER `condition_status`;

-- Ensure status column exists with correct values
ALTER TABLE `rifles` 
MODIFY COLUMN `status` ENUM('available', 'assigned', 'maintenance', 'lost', 'damaged') DEFAULT 'available';

-- 4. Ensure rifle_assignments table has assigned_at column
ALTER TABLE `rifle_assignments` 
MODIFY COLUMN `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 5. Create attendance_events table if it doesn't exist (for QR attendance)
CREATE TABLE IF NOT EXISTS `attendance_events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_name` VARCHAR(255) NOT NULL,
  `event_date` DATE NOT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `qr_code` VARCHAR(255) DEFAULT NULL,
  `qr_code_path` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_event_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create attendance_logs table if it doesn't exist
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` INT(11) NOT NULL,
  `event_id` INT(11) DEFAULT NULL,
  `attendance_date` DATE NOT NULL,
  `time_in` TIME DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `status` ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
  `qr_scanned` BOOLEAN DEFAULT FALSE,
  `scan_time` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `recorded_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`event_id`) REFERENCES `attendance_events`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `uk_cadet_event_date` (`cadet_id`, `event_id`, `attendance_date`),
  INDEX `idx_attendance_date` (`attendance_date`),
  INDEX `idx_attendance_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Create borrowed_items table if it doesn't exist
CREATE TABLE IF NOT EXISTS `borrowed_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(255) NOT NULL,
  `item_type` VARCHAR(100) DEFAULT NULL,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('available', 'borrowed', 'maintenance', 'lost', 'damaged') DEFAULT 'available',
  `condition_notes` TEXT DEFAULT NULL,
  `qr_code_path` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_item_status` (`status`),
  INDEX `idx_item_type` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Create item_assignments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `item_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_id` INT(11) NOT NULL,
  `cadet_id` INT(11) NOT NULL,
  `assigned_by` INT(11) NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expected_return` TIMESTAMP NULL DEFAULT NULL,
  `returned_at` TIMESTAMP NULL DEFAULT NULL,
  `returned_by` INT(11) NULL DEFAULT NULL,
  `status` ENUM('active', 'returned', 'overdue', 'lost', 'damaged') DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`item_id`) REFERENCES `borrowed_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`returned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_assignment_item` (`item_id`),
  INDEX `idx_assignment_cadet` (`cadet_id`),
  INDEX `idx_assignment_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Create security_logs table if it doesn't exist
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_security_action` (`action`),
  INDEX `idx_security_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Create two_factor_auth table if it doesn't exist
CREATE TABLE IF NOT EXISTS `two_factor_auth` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `secret_key` VARCHAR(255) NOT NULL,
  `is_enabled` BOOLEAN DEFAULT FALSE,
  `backup_codes` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_user_2fa` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Update existing data to set approval_status for existing users
UPDATE `users` SET `approval_status` = 'approved' WHERE `status` = 'active' AND `approval_status` = 'pending';

-- 12. Insert default admin user if not exists
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `status`, `approval_status`) 
VALUES ('admin', 'admin@rotc.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 'approved');

-- 13. Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_users_approval` ON `users`(`approval_status`);
CREATE INDEX IF NOT EXISTS `idx_cadet_beneficiary` ON `cadet_profiles`(`beneficiary_name`);
CREATE INDEX IF NOT EXISTS `idx_cadet_province` ON `cadet_profiles`(`province_city`);
CREATE INDEX IF NOT EXISTS `idx_cadet_guardian` ON `cadet_profiles`(`guardian_name`);
CREATE INDEX IF NOT EXISTS `idx_rifles_qr` ON `rifles`(`qr_code_path`);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Display completion message
SELECT 'Database structure fix completed successfully!' AS message;
SELECT COUNT(*) AS total_tables FROM information_schema.tables WHERE table_schema = 'rotc_db';

-- Show table structures for verification
SELECT 
    TABLE_NAME,
    COUNT(*) AS column_count
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'rotc_db' 
GROUP BY TABLE_NAME 
ORDER BY TABLE_NAME;