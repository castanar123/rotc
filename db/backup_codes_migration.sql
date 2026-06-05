-- Add backup codes table for 2FA
USE rotc_db;

-- Create security_settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS `security_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
  `backup_code_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`backup_code_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_used_at` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default security settings if not exists
INSERT IGNORE INTO `security_settings` (`setting_key`, `setting_value`, `description`) VALUES
('backup_retention_days', '30', 'Number of days to retain backup files'),
('max_failed_login_attempts', '5', 'Maximum failed login attempts before account lockout'),
('account_lockout_duration', '30', 'Account lockout duration in minutes'),
('session_timeout', '3600', 'Session timeout in seconds'),
('require_2fa_for_admin', '1', 'Require 2FA for admin accounts'),
('backup_encryption_enabled', '1', 'Enable backup file encryption'),
('security_log_retention_days', '90', 'Number of days to retain security logs');