-- Fix attendance table log_date field to have default value
USE rotc_db;

-- Check current structure
SELECT 'Current attendance table structure:' as info;
DESCRIBE attendance;

-- Modify log_date to have default value
ALTER TABLE attendance MODIFY COLUMN log_date DATE NOT NULL DEFAULT (CURDATE());

-- Also ensure log_time has a default
ALTER TABLE attendance MODIFY COLUMN log_time TIME NOT NULL DEFAULT (CURTIME());

-- Verify the changes
SELECT 'Updated attendance table structure:' as info;
DESCRIBE attendance;

SELECT 'Ready for final test'