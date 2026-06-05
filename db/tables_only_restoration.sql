-- Missing Tables Creation Script (Tables Only)
-- This script creates ALL missing tables without INSERT statements

USE rotc_db;

-- 1. ATTENDANCE_LOGS TABLE
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_profile_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `status` enum('present','absent','late','excused') NOT NULL,
  `logged_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cadet_profile_id` (`cadet_profile_id`),
  KEY `logged_by_user_id` (`logged_by_user_id`),
  CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`cadet_profile_id`) REFERENCES `cadet_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`logged_by_user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. MISSING_ID_REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `missing_id_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cadet_id` INT NOT NULL,
    `reason` ENUM('lost', 'damaged', 'stolen', 'confiscated', 'other') NOT NULL,
    `reason_details` TEXT,
    `request_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expiry_date` DATETIME NOT NULL,
    `status` ENUM('active', 'expired') DEFAULT 'active',
    `qr_code_data` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
    INDEX `idx_cadet_id` (`cadet_id`),
    INDEX `idx_status_expiry` (`status`, `expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SECURITY_AUDIT_LOGS TABLE
CREATE TABLE IF NOT EXISTS `security_audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `user_id` INT,
    `user_type` VARCHAR(50),
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_action` (`action`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ATTENDANCE_RECORDS TABLE
CREATE TABLE IF NOT EXISTS `attendance_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cadet_id` INT NOT NULL,
    `cadet_name` VARCHAR(255) NOT NULL,
    `student_id` VARCHAR(50) NOT NULL,
    `school_year` VARCHAR(20) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `event_name` VARCHAR(255) NOT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `recorded_by` INT DEFAULT 1,
    `status` ENUM('present', 'absent', 'late') DEFAULT 'present',
    `notes` TEXT,
    INDEX `idx_cadet_id` (`cadet_id`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_event` (`event_name`),
    INDEX `idx_school_year_semester` (`school_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. DUTY_SESSIONS TABLE
CREATE TABLE IF NOT EXISTS `duty_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `duty_officer_id` INT NOT NULL,
    `start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `end_time` TIMESTAMP NULL,
    `status` ENUM('active', 'completed') DEFAULT 'active',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`duty_officer_id`) REFERENCES `officers`(`id`) ON DELETE CASCADE,
    INDEX `idx_duty_officer` (`duty_officer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. INVENTORY_ITEMS TABLE
CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(50) UNIQUE NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `total_quantity` INT NOT NULL DEFAULT 0,
    `available_quantity` INT NOT NULL DEFAULT 0,
    `borrowed_quantity` INT NOT NULL DEFAULT 0,
    `unit` VARCHAR(20) DEFAULT 'pcs',
    `location` VARCHAR(100),
    `condition_status` ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    `minimum_stock` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_item_code` (`item_code`),
    INDEX `idx_category` (`category`),
    INDEX `idx_condition` (`condition_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. TRANSACTIONS TABLE
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` VARCHAR(50) UNIQUE NOT NULL,
    `type` ENUM('borrow', 'return', 'supply') NOT NULL,
    `duty_officer_id` INT NOT NULL,
    `borrower_name` VARCHAR(100),
    `borrower_id` VARCHAR(50),
    `borrower_contact` VARCHAR(15),
    `purpose` TEXT,
    `expected_return_date` DATE,
    `actual_return_date` DATE NULL,
    `status` ENUM('pending', 'approved', 'completed', 'overdue', 'cancelled') DEFAULT 'pending',
    `notes` TEXT,
    `digital_signature` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`duty_officer_id`) REFERENCES `officers`(`id`) ON DELETE CASCADE,
    INDEX `idx_transaction_id` (`transaction_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_duty_officer` (`duty_officer_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. TRANSACTION_ITEMS TABLE
CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `condition_before` ENUM('excellent', 'good', 'fair', 'poor'),
    `condition_after` ENUM('excellent', 'good', 'fair', 'poor'),
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. BORROWED_ITEMS TABLE
CREATE TABLE IF NOT EXISTS `borrowed_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `borrowed_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expected_return_date` DATE NOT NULL,
    `status` ENUM('active', 'returned', 'overdue') DEFAULT 'active',
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_item` (`item_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expected_return` (`expected_return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. RESUPPLY_REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `resupply_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` VARCHAR(50) UNIQUE NOT NULL,
    `duty_officer_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'completed', 'cancelled') DEFAULT 'pending',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`duty_officer_id`) REFERENCES `officers`(`id`) ON DELETE CASCADE,
    INDEX `idx_request_id` (`request_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_duty_officer` (`duty_officer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. RESUPPLY_ITEMS TABLE
CREATE TABLE IF NOT EXISTS `resupply_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `resupply_request_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `requested_quantity` INT NOT NULL,
    `approved_quantity` INT DEFAULT 0,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`resupply_request_id`) REFERENCES `resupply_requests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
    INDEX `idx_resupply_request` (`resupply_request_id`),
    INDEX `idx_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. ATTENDANCE_SUMMARY TABLE
CREATE TABLE IF NOT EXISTS `attendance_summary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cadet_id` INT NOT NULL,
    `school_year` VARCHAR(20) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `total_events` INT DEFAULT 0,
    `present_count` INT DEFAULT 0,
    `absent_count` INT DEFAULT 0,
    `late_count` INT DEFAULT 0,
    `attendance_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
    INDEX `idx_cadet_id` (`cadet_id`),
    INDEX `idx_school_year_semester` (`school_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. TRAINING_SCHEDULES TABLE
CREATE TABLE IF NOT EXISTS `training_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `training_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `location` VARCHAR(255),
    `instructor` VARCHAR(100),
    `max_participants` INT DEFAULT NULL,
    `status` ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_training_date` (`training_date`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;