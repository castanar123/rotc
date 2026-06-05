-- Fix Attendance Table cadet_id Issue
-- This script makes cadet_id nullable or adds proper default handling

USE rotc_db;

-- Make cadet_id nullable since we're using user_id instead
ALTER TABLE attendance 
MODIFY COLUMN cadet_id INT(11) NULL;

-- Set a default value for existing records where cadet_id is required
-- We'll sync cadet_id with user_id for consistency
UPDATE attendance 
SET cadet_id = user_id 
WHERE cadet_id IS NULL AND user_id IS NOT NULL;

-- Verify the changes
SELECT 'Attendance table structure updated:' as info;
DESCRIBE attendance;

SELECT 'Sample attendance records:' as info;
SELECT id, user_id, cadet_id, log_date, status 
FROM attendance 
LIMIT 3;

SELECT 'Ready for final test' as status;