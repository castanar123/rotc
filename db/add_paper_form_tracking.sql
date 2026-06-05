-- Add paper form submission tracking to users table
-- This field will track whether a cadet has submitted their physical paper form

USE rotc_cadet_management;

-- Add paper_form_submitted field to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `paper_form_submitted` TINYINT(1) DEFAULT 0 COMMENT 'Tracks if cadet has submitted physical paper form (0=not submitted, 1=submitted)';

-- Add paper_form_submitted_date field to track when the form was submitted
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `paper_form_submitted_date` DATETIME DEFAULT NULL COMMENT 'Date when physical paper form was submitted';

-- Add paper_form_notes field for additional tracking information
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `paper_form_notes` TEXT DEFAULT NULL COMMENT 'Additional notes about paper form submission status';

-- Create index for better performance when querying pending paper forms
CREATE INDEX IF NOT EXISTS `idx_users_paper_form_status` ON `users` (`paper_form_submitted`, `approval_status`);

-- Show the updated table structure
DESCRIBE users;

-- Create a view for pending paper form submissions
CREATE OR REPLACE VIEW `pending_paper_forms_view` AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.full_name,
    u.first_name,
    u.last_name,
    u.student_id,
    u.course,
    u.year_level,
    u.contact_number,
    u.approval_status,
    u.created_at as registration_date,
    u.paper_form_submitted,
    u.paper_form_submitted_date,
    u.paper_form_notes,
    cp.platoon,
    cp.section,
    DATEDIFF(NOW(), u.created_at) as days_since_registration
FROM users u
LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
WHERE u.approval_status = 'pending' 
AND u.paper_form_submitted = 0
AND u.role IN ('basic_cadet', 'cadet', 'basic-cadet')
ORDER BY u.created_at ASC;

-- Show sample data from the new view
SELECT 'Paper form tracking fields added successfully!' as message;
SELECT COUNT(*) as pending_paper_forms_count FROM pending_paper_forms_view;