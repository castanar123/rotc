-- Integrated ROTC Database Schema
-- Merging attendance_system into rotc_db for unified system

USE rotc_db;

-- Drop existing attendance table to recreate with enhanced structure
DROP TABLE IF EXISTS `attendance`;

-- Enhanced Attendance Table (combining both systems)
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL COMMENT 'For QR system compatibility',
  `log_date` date NOT NULL,
  `log_time` time NOT NULL,
  `training_day` varchar(255) DEFAULT NULL,
  `td` int(11) DEFAULT NULL COMMENT 'Training Day number for QR system',
  `semester` int(11) DEFAULT NULL COMMENT 'Semester for QR system',
  `status` enum('Present','Absent','Late','present','late','absent') NOT NULL DEFAULT 'Present',
  `recorded_by` int(11) DEFAULT NULL,
  `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'For QR system compatibility',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `cadet_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_log_date` (`log_date`),
  INDEX `idx_td_semester` (`td`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training Days Table (from QR system)
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

-- Insert default training days
INSERT INTO `training_days` (`td_id`, `label`) VALUES
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
(15, '15th TD')
ON DUPLICATE KEY UPDATE label=VALUES(label);

-- QR Attendance Sessions Table (for managing QR scanning sessions)
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
  FOREIGN KEY (`td`) REFERENCES `training_days`(`td_id`),
  INDEX `idx_session_date` (`session_date`),
  INDEX `idx_active_sessions` (`is_active`, `session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add student_id mapping for QR compatibility (check if index exists first)
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE table_schema = 'rotc_db' 
                    AND table_name = 'cadet_profiles' 
                    AND index_name = 'idx_student_id');

SET @sql = IF(@index_exists = 0, 
             'ALTER TABLE `cadet_profiles` ADD INDEX `idx_student_id` (`student_id`)', 
             'SELECT "Index already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create view for QR system compatibility
CREATE OR REPLACE VIEW `students` AS
SELECT 
  cp.id,
  cp.student_id,
  CONCAT(cp.first_name, ' ', IFNULL(cp.middle_name, ''), ' ', cp.last_name) as name,
  NOW() as created_at
FROM cadet_profiles cp
WHERE cp.status IN ('Active', 'active');

COMMIT;