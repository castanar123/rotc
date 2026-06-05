-- Fix Final Missing Columns in ROTC Database
-- This script adds all remaining missing columns identified in testing

USE rotc_db;

-- Add course column to cadet_profiles table
ALTER TABLE cadet_profiles 
ADD COLUMN IF NOT EXISTS course VARCHAR(100);

-- Add civil_status column to cadet_profiles table
ALTER TABLE cadet_profiles 
ADD COLUMN IF NOT EXISTS civil_status VARCHAR(50) DEFAULT 'Single';

-- Add section column to cadet_profiles table (if not exists)
ALTER TABLE cadet_profiles 
ADD COLUMN IF NOT EXISTS section VARCHAR(50);

-- Add default value for event_type in security_logs
ALTER TABLE security_logs 
MODIFY COLUMN event_type VARCHAR(50) NOT NULL DEFAULT 'general';

-- Add course column to rifle_assignments table (if referenced)
ALTER TABLE rifle_assignments 
ADD COLUMN IF NOT EXISTS course VARCHAR(100);

-- Clean up duplicate rifle serial numbers by adding unique suffix
UPDATE rifles r1 
JOIN (
    SELECT serial_number, 
           ROW_NUMBER() OVER (PARTITION BY serial_number ORDER BY id) as rn
    FROM rifles 
    WHERE serial_number IN (
        SELECT serial_number 
        FROM rifles 
        GROUP BY serial_number 
        HAVING COUNT(*) > 1
    )
) r2 ON r1.serial_number = r2.serial_number
SET r1.serial_number = CONCAT(r1.serial_number, '-', r2.rn)
WHERE r2.rn > 1;

-- Add indexes for the new columns
CREATE INDEX IF NOT EXISTS idx_cadet_profiles_course ON cadet_profiles(course);
CREATE INDEX IF NOT EXISTS idx_cadet_profiles_civil_status ON cadet_profiles(civil_status);
CREATE INDEX IF NOT EXISTS idx_cadet_profiles_section ON cadet_profiles(section);
CREATE INDEX IF NOT EXISTS idx_rifle_assignments_course ON rifle_assignments(course);

-- Update existing records with default values where needed
UPDATE cadet_profiles 
SET course = 'Computer Science' 
WHERE course IS NULL OR course = '';

UPDATE cadet_profiles 
SET civil_status = 'Single' 
WHERE civil_status IS NULL OR civil_status = '';

UPDATE cadet_profiles 
SET section = 'A' 
WHERE section IS NULL OR section = '';

-- Verify the changes
SELECT 'cadet_profiles table now has course column:' as info;
SELECT COUNT(*) as course_column_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'cadet_profiles' AND column_name = 'course';

SELECT 'cadet_profiles table now has civil_status column:' as info;
SELECT COUNT(*) as civil_status_column_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'cadet_profiles' AND column_name = 'civil_status';

SELECT 'security_logs event_type now has default value:' as info;
SELECT column_default FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'security_logs' AND column_name = 'event_type';

SELECT 'Checking for duplicate rifle serial numbers:' as info;
SELECT serial_number, COUNT(*) as count 
FROM rifles 
GROUP BY serial_number 
HAVING COUNT(*