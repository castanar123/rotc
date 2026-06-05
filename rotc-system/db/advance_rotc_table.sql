-- Create advance_rotc_signups table for Advanced ROTC Program signups
-- This table stores information about students who sign up for the Advanced ROTC program

USE rotc_db;

-- Create advance_rotc_signups table
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

-- Insert some sample data for testing (optional)
-- INSERT INTO `advance_rotc_signups` (`full_name`, `course`, `facebook_link`) VALUES
-- ('John Doe', 'Computer Science', 'https://facebook.com/johndoe'),
-- ('Jane Smith', 'Engineering', 'https://facebook.com/janesmith'),
-- ('Mike Johnson', 'Business Administration', NULL);

SELECT 'advance_rotc_signups table created successfully!' as message;