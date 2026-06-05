-- Fix Users Table and Other Missing Columns
-- This script adds missing columns to users table and fixes other issues

USE rotc_db;

-- Add missing columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS course VARCHAR(100);

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS year_level VARCHAR(20);

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20);

-- Add emergency_contact_name to cadet_profiles (alias for emergency_contact)
ALTER TABLE cadet_profiles 
ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255);

-- Update emergency_contact_name with emergency_contact data
UPDATE cadet_profiles 
SET emergency_contact_name = emergency_contact 
WHERE emergency_contact_name IS NULL AND emergency_contact IS NOT NULL;

-- Add default value for description in security_logs
ALTER TABLE security_logs 
MODIFY COLUMN description TEXT DEFAULT 'No description provided';

-- Clean up duplicate rifle serial numbers
SET @row_number = 0;
UPDATE rifles 
SET serial_number = CONCAT(serial_number, '-', (@row_number := @row_number + 1))
WHERE serial_number = 'TEST-RIFLE-001' AND id > (
    SELECT min_id FROM (
        SELECT MIN(id) as min_id 
        FROM rifles 
        WHERE serial_number = 'TEST-RIFLE-001'
    ) as temp
);

-- Add indexes for new columns
CREATE INDEX IF NOT EXISTS idx_users_course ON users(course);
CREATE INDEX IF NOT EXISTS idx_users_year_level ON users(year_level);
CREATE INDEX IF NOT EXISTS idx_users_contact_number ON users(contact_number);
CREATE INDEX IF NOT EXISTS idx_cadet_profiles_emergency_contact_name ON cadet_profiles(emergency_contact_name);

-- Update existing users with default values
UPDATE users 
SET course = 'Computer Science' 
WHERE course IS NULL OR course = '';

UPDATE users 
SET year_level = '1st Year' 
WHERE year_level IS NULL OR year_level = '';

UPDATE users 
SET contact_number = '09000000000' 
WHERE contact_number IS NULL OR contact_number = '';

-- Verify the changes
SELECT 'users table columns:' as info;
SELECT column_name FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'users' 
AND column_name IN ('course', 'year_level', 'contact_number');

SELECT 'cadet_profiles emergency_contact_name:' as info;
SELECT COUNT(*) as emergency_contact_name_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'cadet_profiles' AND column_name = 'emergency_contact_name';

SELECT 'security_logs description default:' as info;
SELECT column_default FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'security_logs' AND column_name = 'description';