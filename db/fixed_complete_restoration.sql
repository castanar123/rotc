-- Fixed Complete ROTC Database Restoration Script
-- This script creates ALL discovered tables from various schema files

USE rotc_db;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- ========================================
-- CORE SYSTEM TABLES (from updated_rotc_schema.sql)
-- ========================================

-- Training Days Table
CREATE TABLE IF NOT EXISTS `training_days` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `td_id` int(11) NOT NULL,
  `label` varchar(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_td_id` (`td_id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quizzes Table
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `time_limit` int(11) DEFAULT NULL COMMENT 'Time limit in minutes',
  `max_attempts` int(11) DEFAULT 1,
  `passing_score` decimal(5,2) DEFAULT 70.00,
  `is_active` boolean DEFAULT TRUE,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz Questions Table
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
  `points` decimal(5,2) DEFAULT 1.00,
  `order_index` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz Options Table
CREATE TABLE IF NOT EXISTS `quiz_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` boolean DEFAULT FALSE,
  `order_index` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz Attempts Table
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `max_score` decimal(5,2) DEFAULT 0.00,
  `percentage` decimal(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time taken in seconds',
  `attempt_number` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz Answers Table
CREATE TABLE IF NOT EXISTS `quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `is_correct` boolean DEFAULT FALSE,
  `points_earned` decimal(5,2) DEFAULT 0.00,
  `answered_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`selected_option_id`) REFERENCES `quiz_options`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz Scores Table
CREATE TABLE IF NOT EXISTS `quiz_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `quiz_name` varchar(255) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `percentage` decimal(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
  `semester` varchar(50) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents Table
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'general',
  `is_public` boolean DEFAULT FALSE,
  `uploaded_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cadet Documents Table
CREATE TABLE IF NOT EXISTS `cadet_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `access_granted_by` int(11) NOT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cadet_document` (`cadet_id`, `document_id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`access_granted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text NOT NULL,
  `description` text,
  `category` varchar(50) DEFAULT 'general',
  `is_editable` boolean DEFAULT TRUE,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification Reads Table
CREATE TABLE IF NOT EXISTS `notification_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_announcement` (`user_id`, `announcement_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Logs Table
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
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_table` (`table_name`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Sessions Table
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `session_id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Two Factor Authentication Table
CREATE TABLE IF NOT EXISTS `two_factor_auth` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `secret_key` VARCHAR(255) NOT NULL,
    `backup_codes` TEXT,
    `is_verified` BOOLEAN DEFAULT FALSE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Two Factor Backup Codes Table
CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
  `backup_code_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`backup_code_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_used_at` (`used_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Security Logs Table
CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `metadata` JSON,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Security Settings Table
CREATE TABLE IF NOT EXISTS `security_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backup Jobs Table
CREATE TABLE IF NOT EXISTS `backup_jobs` (
    `backup_id` VARCHAR(64) PRIMARY KEY,
    `created_by` INT NOT NULL,
    `backup_type` ENUM('full', 'database', 'files', 'selective') NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `file_path` VARCHAR(500),
    `file_size` BIGINT DEFAULT 0,
    `checksum` VARCHAR(64),
    `backup_config` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backup Files Table
CREATE TABLE IF NOT EXISTS `backup_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `backup_id` VARCHAR(64) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`backup_id`) REFERENCES `backup_jobs`(`backup_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alert Notifications Table
CREATE TABLE IF NOT EXISTS `alert_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `log_id` INT NOT NULL,
    `alert_type` VARCHAR(50) NOT NULL,
    `notification_method` ENUM('email', 'sms', 'dashboard') NOT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `sent_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`log_id`) REFERENCES `security_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registration Requests Table
CREATE TABLE IF NOT EXISTS `registration_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `request_type` enum('new_registration','profile_update','role_change') NOT NULL DEFAULT 'new_registration',
  `status` enum('pending','approved','rejected','under_review') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registration Documents Table
CREATE TABLE IF NOT EXISTS `registration_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `document_type` enum('photo','signature','id_copy','birth_certificate','other') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`registration_id`) REFERENCES `registration_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registration Status Log Table
CREATE TABLE IF NOT EXISTS `registration_status_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`registration_id`) REFERENCES `registration_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- QR Attendance Sessions Table
CREATE TABLE IF NOT EXISTS `qr_attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_name` varchar(255) NOT NULL,
  `td` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `is_active` boolean DEFAULT TRUE,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`td`) REFERENCES `training_days`(`td_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- QR Codes Table
CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL UNIQUE,
  `type` enum('attendance','rifle','item','general') NOT NULL DEFAULT 'general',
  `reference_id` int(11) DEFAULT NULL,
  `data` json DEFAULT NULL,
  `is_active` boolean DEFAULT TRUE,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Borrower Temp IDs Table
CREATE TABLE IF NOT EXISTS `borrower_temp_ids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(50) NOT NULL UNIQUE,
  `cadet_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `is_used` boolean DEFAULT FALSE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Borrowers Table
CREATE TABLE IF NOT EXISTS `borrowers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `borrower_type` enum('cadet','officer','external') NOT NULL DEFAULT 'cadet',
  `contact_info` json DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rifle Borrowings Table
CREATE TABLE IF NOT EXISTS `rifle_borrowings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rifle_id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `borrowed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expected_return` datetime NOT NULL,
  `returned_at` datetime DEFAULT NULL,
  `status` enum('active','returned','overdue','lost') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `approved_by` int(11) NOT NULL,
  `returned_to` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`rifle_id`) REFERENCES `rifles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`borrower_id`) REFERENCES `borrowers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`returned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dummy QR Codes Table
CREATE TABLE IF NOT EXISTS `dummy_qr_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qr_data` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Records Table (alternative structure)
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `td` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('present','late','absent') NOT NULL DEFAULT 'present',
  `recorded_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training Schedules Table
CREATE TABLE IF NOT EXISTS `training_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `training_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`instructor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Advance ROTC Signups Table
CREATE TABLE IF NOT EXISTS `advance_rotc_signups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st','2nd') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `application_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Platoons Table
CREATE TABLE IF NOT EXISTS `platoons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `leader_id` int(11) DEFAULT NULL,
  `max_members` int(11) DEFAULT 30,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scanner Sessions Table
CREATE TABLE IF NOT EXISTS `scanner_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_name` varchar(255) NOT NULL,
  `session_type` enum('attendance','inventory','general') NOT NULL DEFAULT 'attendance',
  `started_by` int(11) NOT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `scanned_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`started_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Summary Table
CREATE TABLE IF NOT EXISTS `attendance_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `total_sessions` int(11) DEFAULT 0,
  `present_count` int(11) DEFAULT 0,
  `late_count` int(11) DEFAULT 0,
  `absent_count` int(11) DEFAULT 0,
  `attendance_percentage` decimal(5,2) GENERATED ALWAYS AS ((present_count + late_count) / total_sessions * 100) STORED,
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cadet_semester` (`cadet_id`, `semester`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Officers Table
CREATE TABLE IF NOT EXISTS `officers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `rank` varchar(50) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `commission_date` date DEFAULT NULL,
  `status` enum('active','inactive','retired') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table (for items)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- CREATE INDEXES FOR PERFORMANCE (FIXED)
-- ========================================

-- User-related indexes (removed status index since column doesn't exist)
CREATE INDEX IF NOT EXISTS `idx_users_email` ON `users`(`email`);
CREATE INDEX IF NOT EXISTS `idx_users_role` ON `users`(`role`);

-- Cadet profiles indexes
CREATE INDEX IF NOT EXISTS `idx_cadet_profiles_student_id` ON `cadet_profiles`(`student_id`);

-- Attendance indexes
CREATE INDEX IF NOT EXISTS `idx_attendance_log_date` ON `attendance`(`log_date`);
CREATE INDEX IF NOT EXISTS `idx_attendance_td_semester` ON `attendance`(`td`, `semester`);

-- Quiz-related indexes
CREATE INDEX IF NOT EXISTS `idx_quiz_scores_cadet_semester` ON `quiz_scores`(`cadet_id`, `semester`, `academic_year`);
CREATE INDEX IF NOT EXISTS `idx_quiz_scores_quiz_name` ON `quiz_scores`(`quiz_name`);

-- Security indexes
CREATE INDEX IF NOT EXISTS `idx_sessions_user_id` ON `user_sessions`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_sessions_expires_at` ON `user_sessions`(`expires_at`);
CREATE INDEX IF NOT EXISTS `idx_security_logs_event_type` ON `security_logs`(`event_type`);
CREATE INDEX IF NOT EXISTS `idx_security_logs_created_at` ON `security_logs`(`created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_security_logs_severity` ON `security_logs`(`severity`);

-- Registration indexes
CREATE INDEX IF NOT EXISTS `idx_registration_status` ON `registration_requests`(`status`);
CREATE INDEX IF NOT EXISTS `idx_registration_submitted_at` ON `registration_requests`(`submitted_at`);
CREATE INDEX IF NOT EXISTS `idx_registration_request_type` ON `registration_requests`(`request_type`);
CREATE INDEX IF NOT EXISTS `idx_registration_documents_type` ON `registration_documents`(`document_type`);

-- QR and attendance indexes
CREATE INDEX IF NOT EXISTS `idx_qr_attendance_session_date` ON `qr_attendance_sessions`(`session_date`);
CREATE INDEX IF NOT EXISTS `idx_qr_attendance_active_sessions` ON `qr_attendance_sessions`(`is_active`, `session_date`);
CREATE INDEX IF NOT EXISTS `idx_qr_codes_type` ON `qr_codes`(`type`);
CREATE INDEX IF NOT EXISTS `idx_qr_codes_reference` ON `qr_codes`(`type`, `reference_id`);

-- Backup indexes
CREATE INDEX IF NOT EXISTS `idx_backup_jobs_created_by` ON `backup_jobs`(`created_by`);
CREATE INDEX IF NOT EXISTS `idx_backup_jobs_status` ON `backup_jobs`(`status`);
CREATE INDEX IF NOT EXISTS `idx_backup_jobs_created_at` ON `backup_jobs`(`created_at` DESC);

-- Alert indexes
CREATE INDEX IF NOT EXISTS `idx_alert_notifications_status` ON `alert_notifications`(`status`);
CREATE INDEX IF NOT EXISTS `idx_alert_notifications_created_at` ON `alert_notifications`(`created_at` DESC);

-- ========================================
-- CREATE VIEWS FOR COMPATIBILITY
-- ========================================

-- Students view for QR system compatibility
CREATE OR REPLACE VIEW `students` AS
SELECT 
  cp.id,
  cp.student_id,
  CONCAT(cp.first_name, ' ', IFNULL(cp.middle_name, ''), ' ', cp.last_name) as name,
  NOW() as created_at
FROM cadet_profiles cp
WHERE cp.status IN ('Active', 'active');

-- ========================================
-- INSERT DEFAULT DATA
-- ========================================

-- Insert default training days
INSERT IGNORE INTO `training_days` (`td_id`, `label`) VALUES
(1, '1st TD'),
(2, '2nd TD'),
(3, '3rd TD'),
(4, '4th TD'),
(5, '5th TD'),
(6, '6th TD'),
(7, '7th TD'),
(8, '8th TD'),
(9, '9th TD'),
(10, '10th TD'),
(11, '11th TD'),
(12, '12th TD'),
(13, '13th TD'),
(14, '14th TD'),
(15, '15th TD');

-- Insert default system settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`, `category`) VALUES
('site_name', 'ROTC Management System', 'Name of the ROTC system', 'general'),
('max_file_upload_size', '10485760', 'Maximum file upload size in bytes (10MB)', 'files'),
('session_timeout', '3600', 'Session timeout in seconds', 'security'),
('backup_retention_days', '30', 'Number of days to retain backups', 'backup'),
('attendance_grace_period', '15', 'Grace period for late attendance in minutes', 'attendance'),
('quiz_time_limit_default', '60', 'Default quiz time limit in minutes', 'quiz'),
('notification_email', 'admin@rotc.edu', 'Email for system notifications', 'notifications');

-- Insert default security settings
INSERT IGNORE INTO `security_settings` (`setting_key`, `setting_value`, `description`) VALUES
('max_login_attempts', '5', 'Maximum failed login attempts before account lockout'),
('lockout_duration', '1800', 'Account lockout duration in seconds'),
('session_timeout', '1800', 'Session timeout in seconds'),
('backup_retention_days', '30', 'Number of days to retain daily backups'),
('password_min_length', '12', 'Minimum password length requirement'),
('two_factor_required', 'false', 'Require two-factor authentication for all users'),
('backup_encryption_enabled', 'true', 'Enable encryption for backup files'),
('daily_backup_time', '02:00', 'Time for daily automated backups (HH:MM format)');

-- Insert default categories
INSERT IGNORE INTO `categories` (`name`, `description`) VALUES
('Weapons', 'Military weapons and firearms'),
('Equipment', 'Training and field equipment'),
('Uniforms', 'Military uniforms and accessories'),
('Supplies', 'General supplies and materials'),
('Electronics', 'Electronic devices and equipment');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Fixed Complete ROTC database restoration completed successfully!' as message;
SELECT COUNT(*) as total_tables_created FROM information_schema.tables WHERE table_schema = 'rotc_db';