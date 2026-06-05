-- Create borrower_temp_ids table for reusable QR borrower ID system
-- This table manages temporary borrower IDs that can be recycled

USE rotc_db;

CREATE TABLE IF NOT EXISTS `borrower_temp_ids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(20) NOT NULL UNIQUE COMMENT 'Temporary borrower ID (e.g., TEMP_000001)',
  `status` enum('available','in_use','disabled') NOT NULL DEFAULT 'available' COMMENT 'Status of the temp ID',
  `current_borrower_name` varchar(255) DEFAULT NULL COMMENT 'Name of current borrower if in_use',
  `current_borrower_course` varchar(100) DEFAULT NULL COMMENT 'Course of current borrower if in_use',
  `current_borrower_section` varchar(50) DEFAULT NULL COMMENT 'Section of current borrower if in_use',
  `current_borrower_contact` varchar(20) DEFAULT NULL COMMENT 'Contact of current borrower if in_use',
  `last_used_at` datetime DEFAULT NULL COMMENT 'Last time this ID was used',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_temp_id` (`temp_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_last_used` (`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Manages reusable temporary borrower IDs for QR system';

-- Insert some initial temp IDs
INSERT IGNORE INTO `borrower_temp_ids` (`temp_id`, `status`) VALUES 
('TEMP_000001', 'available'),
('TEMP_000002', 'available'),
('TEMP_000003', 'available'),
('TEMP_000004', 'available'),
('TEMP_000005', 'available');

SELECT 'borrower_temp_ids table created successfully!' as result;