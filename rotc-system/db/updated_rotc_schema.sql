-- Updated ROTC Database Schema with Registration Enhancements
-- Add email field to cadet_profiles and update status enum

USE rotc_db;

-- Email field and status already exist in cadet_profiles table
-- Users table uses 'role' column instead of 'status'

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_cadet_profiles_email` ON `cadet_profiles` (`email`);
CREATE INDEX IF NOT EXISTS `idx_cadet_profiles_student_number` ON `cadet_profiles` (`student_number`);
CREATE INDEX IF NOT EXISTS `idx_cadet_profiles_platoon` ON `cadet_profiles` (`platoon`);
CREATE INDEX IF NOT EXISTS `idx_cadet_profiles_status` ON `cadet_profiles` (`status`);
CREATE INDEX IF NOT EXISTS `idx_users_role` ON `users` (`role`);

-- Create training_days table for managing training schedules
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

-- Create quizzes table for academic assessments
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

-- Create quiz_questions table
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

-- Create quiz_options table for multiple choice questions
CREATE TABLE IF NOT EXISTS `quiz_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` boolean NOT NULL DEFAULT FALSE,
  `order_number` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create quiz_attempts table to track cadet quiz submissions
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

-- Create quiz_answers table to store cadet answers
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

-- Create grades table for comprehensive grading system
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

-- Create documents table for file management
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

-- Create cadet_documents table for cadet-specific file uploads
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

-- System_settings table already exists with different structure

-- Insert default system settings (matching existing table structure)
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

-- Update announcements table to include target audience
ALTER TABLE `announcements` 
ADD COLUMN IF NOT EXISTS `target_audience` enum('all','cadets','officers','admin','platoon_specific') NOT NULL DEFAULT 'all' AFTER `content`,
ADD COLUMN IF NOT EXISTS `target_platoon` varchar(50) DEFAULT NULL AFTER `target_audience`,
ADD COLUMN IF NOT EXISTS `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER `target_platoon`,
ADD COLUMN IF NOT EXISTS `expires_at` datetime DEFAULT NULL AFTER `priority`;

-- Create notification_reads table to track read announcements
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

-- Create audit_logs table for system activity tracking
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

COMMIT;