-- Registration Management Tables Setup
-- This script creates tables for managing user registrations and approval workflow

USE rotc_db;

-- Create registration_requests table for managing pending registrations
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
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_submitted_at` (`submitted_at`),
  INDEX `idx_request_type` (`request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create registration_documents table for storing uploaded documents
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
  FOREIGN KEY (`registration_id`) REFERENCES `registration_requests`(`id`) ON DELETE CASCADE,
  INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create registration_status_log table for tracking status changes
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
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_registration_id` (`registration_id`),
  INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create view for pending registrations with user details
CREATE OR REPLACE VIEW `pending_registrations_view` AS
SELECT 
    rr.id as request_id,
    rr.request_type,
    rr.status,
    rr.submitted_at,
    rr.priority,
    u.id as user_id,
    u.username,
    u.email,
    u.role,
    cp.first_name,
    cp.last_name,
    cp.cadet_id,
    cp.course,
    cp.platoon,
    CONCAT(cp.first_name, ' ', cp.last_name) as full_name,
    DATEDIFF(NOW(), rr.submitted_at) as days_pending
FROM registration_requests rr
JOIN users u ON rr.user_id = u.id
LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
WHERE rr.status = 'pending'
ORDER BY rr.priority DESC, rr.submitted_at ASC;

-- Create view for registration statistics
CREATE OR REPLACE VIEW `registration_stats_view` AS
SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
    SUM(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week_count,
    SUM(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as this_month_count
FROM registration_requests;

-- Insert indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_users_role` ON `users` (`role`);
CREATE INDEX IF NOT EXISTS `idx_cadet_profiles_cadet_id` ON `cadet_profiles` (`cadet_id`);

-- Update existing users to be active if they have complete profiles
UPDATE users u 
JOIN cadet_profiles cp ON u.id = cp.user_id 
SET u.is_active = 1 
WHERE u.is_active = 0 AND cp.first_name IS NOT NULL AND cp.last_name IS NOT NULL;

-- Insert sample data for testing (optional)
-- This creates a pending registration request for demonstration
/*
INSERT INTO registration_requests (user_id, request_type, status, priority) 
SELECT id, 'new_registration', 'pending', 'normal' 
FROM users 
WHERE status = 'pending' 
LIMIT 1;
*/

SELECT 'Registration tables created successfully!' as message;