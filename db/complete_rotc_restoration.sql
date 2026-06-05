-- Complete ROTC Database Restoration Script
-- This script creates all discovered tables from various SQL files

USE rotc_db;

-- Drop existing tables to ensure clean restoration
SET FOREIGN_KEY_CHECKS = 0;

-- Core ROTC Management Tables (from rotc_db.sql)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','officer','cadet') NOT NULL DEFAULT 'cadet',
  `status` enum('pending','active','inactive') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cadet_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `facebook_profile` VARCHAR(255) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `platoon` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `status` enum('pending','Active','Inactive','Graduated') NOT NULL DEFAULT 'pending',
  `date_enrolled` date DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_cadet_profiles_email` (`email`),
  INDEX `idx_cadet_profiles_student_number` (`student_number`),
  INDEX `idx_cadet_profiles_platoon` (`platoon`),
  INDEX `idx_cadet_profiles_status` (`status`),
  INDEX `idx_facebook_profile` (`facebook_profile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `target_audience` enum('all','cadets','officers','admin','platoon_specific') NOT NULL DEFAULT 'all',
  `target_platoon` varchar(50) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `expires_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance` (
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
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`),
  UNIQUE KEY `unique_cadet_date` (`cadet_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `attendance_score` decimal(5,2) DEFAULT 0,
  `quiz_score` decimal(5,2) DEFAULT 0,
  `participation_score` decimal(5,2) DEFAULT 0,
  `conduct_score` decimal(5,2) DEFAULT 0,
  `midterm_grade` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `overall_grade` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  UNIQUE KEY `unique_cadet_semester` (`cadet_id`, `semester`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training and Schedule Tables (from integrated_rotc_schema.sql)
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

CREATE TABLE IF NOT EXISTS `qr_attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_day_id` int(11) NOT NULL,
  `qr_code` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`training_day_id`) REFERENCES `training_days`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz and Assessment Tables (from updated_rotc_schema.sql)
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `total_points` int(11) NOT NULL DEFAULT 0,
  `time_limit` int(11) DEFAULT NULL COMMENT 'Time limit in minutes',
  `deadline` datetime DEFAULT NULL,
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','essay') NOT NULL DEFAULT 'multiple_choice',
  `points` int(11) NOT NULL DEFAULT 1,
  `order_number` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` boolean NOT NULL DEFAULT FALSE,
  `order_number` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_points` int(11) NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time taken in minutes',
  `status` enum('in_progress','completed','submitted') NOT NULL DEFAULT 'in_progress',
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`),
  UNIQUE KEY `unique_quiz_attempt` (`quiz_id`, `cadet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `is_correct` boolean DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT 0,
  `answered_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`),
  FOREIGN KEY (`selected_option_id`) REFERENCES `quiz_options`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rifle Management Tables (from rifle_management_schema.sql)
CREATE TABLE IF NOT EXISTS `rifles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rifle_number` VARCHAR(50) NOT NULL UNIQUE,
    `qr_code_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('available', 'assigned', 'maintenance', 'lost', 'damaged') DEFAULT 'available',
    `condition_notes` TEXT DEFAULT NULL,
    `last_maintenance` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rifle_number` (`rifle_number`),
    INDEX `idx_rifle_status` (`status`),
    INDEX `idx_rifle_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rifle_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rifle_id` INT(11) NOT NULL,
    `cadet_id` INT(11) NOT NULL,
    `assigned_by` INT(11) NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expected_return` TIMESTAMP NULL DEFAULT NULL,
    `returned_at` TIMESTAMP NULL DEFAULT NULL,
    `returned_by` INT(11) NULL DEFAULT NULL,
    `status` ENUM('active', 'returned', 'overdue', 'lost', 'damaged') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`rifle_id`) REFERENCES `rifles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`returned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_assignment_rifle` (`rifle_id`),
    INDEX `idx_assignment_cadet` (`cadet_id`),
    INDEX `idx_assignment_status` (`status`),
    INDEX `idx_assignment_dates` (`assigned_at`, `returned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rifle_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rifle_id` INT(11) NOT NULL,
    `cadet_id` INT(11) NULL DEFAULT NULL,
    `action` ENUM('created', 'assigned', 'returned', 'maintenance', 'lost', 'damaged', 'repaired') NOT NULL,
    `performed_by` INT(11) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`rifle_id`) REFERENCES `rifles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_log_rifle` (`rifle_id`),
    INDEX `idx_log_action` (`action`),
    INDEX `idx_log_timestamp` (`timestamp`),
    INDEX `idx_log_performer` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Document Management Tables
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `category` enum('general','forms','assignments','announcements','policies') NOT NULL DEFAULT 'general',
  `access_level` enum('public','cadets','officers','admin') NOT NULL DEFAULT 'cadets',
  `uploaded_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cadet_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `document_type` enum('excuse_letter','medical_certificate','assignment','other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`),
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Configuration Tables
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_editable` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification and Audit Tables
CREATE TABLE IF NOT EXISTS `notification_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_announcement` (`user_id`, `announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_audit_logs_user_id` (`user_id`),
  INDEX `idx_audit_logs_action` (`action`),
  INDEX `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Advanced ROTC Signup Table (from advance_rotc_table.sql)
CREATE TABLE IF NOT EXISTS `advance_rotc_signups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL,
  `facebook_link` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_course` (`course`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- QR Attendance System Tables (from updated_database.sql)
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `gender` enum('male','female') NOT NULL DEFAULT 'male',
  `platoon` varchar(20) NOT NULL DEFAULT 'Alpha',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  INDEX `idx_students_platoon` (`platoon`),
  INDEX `idx_students_gender` (`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `platoons` (
    `platoon_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `scanner_sessions` (
    `session_id` VARCHAR(64) PRIMARY KEY,
    `td` INT NOT NULL,
    `semester` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_active` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `device_info` VARCHAR(255),
    `ip_address` VARCHAR(45)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Insert default data
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('current_semester', '1st Semester', 'Current academic semester'),
('current_academic_year', '2024-2025', 'Current academic year'),
('attendance_weight', '30', 'Attendance component weight in percentage'),
('quiz_weight', '25', 'Quiz component weight in percentage'),
('participation_weight', '25', 'Participation component weight in percentage'),
('conduct_weight', '20', 'Conduct component weight in percentage'),
('qr_expiration_minutes', '30', 'QR code expiration time in minutes'),
('max_file_upload_size', '10485760', 'Maximum file upload size in bytes (10MB)'),
('allowed_file_types', '["pdf","doc","docx","jpg","jpeg","png"]', 'Allowed file types for uploads');

INSERT IGNORE INTO `platoons` (`name`) VALUES
('Alpha'),
('Bravo'),
('Charlie'),
('Delta'),
('Echo');

INSERT IGNORE INTO `training_days` (`name`, `date`, `start_time`, `end_time`, `description`, `created_by`) VALUES
('Monday Training', '2024-01-08', '07:00:00', '09:00:00', 'Regular Monday training session', 1),
('Wednesday Training', '2024-01-10', '07:00:00', '09:00:00', 'Regular Wednesday training session', 1),
('Friday Training', '2024-01-12', '07:00:00', '09:00:00', 'Regular Friday training session', 1);

-- Insert sample rifle data
INSERT IGNORE INTO `rifles` (`serial_number`, `model`, `condition_status`, `notes`) VALUES
('R001', 'M16A1', 'excellent', 'New rifle - excellent condition'),
('R002', 'M16A1', 'good', 'Good condition - minor wear'),
('R003', 'M16A1', 'fair', 'Requires cleaning and inspection'),
('R004', 'M16A1', 'excellent', 'Recently serviced - excellent condition'),
('R005', 'M16A1', 'good', 'Good condition - ready for use'),
('R006', 'M16A1', 'excellent', 'Excellent condition - new stock'),
('R007', 'M16A1', 'good', 'Good condition - ready for training'),
('R008', 'M16A1', 'fair', 'Scheduled maintenance required'),
('R009', 'M16A1', 'good', 'Good condition - recently cleaned'),
('R010', 'M16A1', 'excellent', 'Excellent condition - inspection passed');

-- Note: students is a view, not a table, so we cannot insert data directly into it
-- Student data should be inserted into cadet_profiles table instead

SELECT 'Complete ROTC Database Restoration completed successfully!' as message;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'rotc_db';