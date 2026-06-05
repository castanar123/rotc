-- Fix Basic Role and Clean Rifle Duplicates
-- This script adds 'basic' to role ENUM and properly cleans rifle duplicates

USE rotc_db;

-- Update the role ENUM to include 'basic' value
ALTER TABLE users 
MODIFY COLUMN role ENUM(
    'admin',
    'instructor', 
    '1cl',
    '2cl',
    'commandant',
    'cadet',
    'basic_cadet',
    'basic',
    'student',
    'user',
    'senior_cadet',
    'junior_cadet'
) NOT NULL DEFAULT 'cadet';

-- Clean up all duplicate rifle serial numbers
-- First, delete all rifles with TEST-RIFLE-001 serial number
DELETE FROM rifle_assignments WHERE rifle_id IN (
    SELECT id FROM rifles WHERE serial_number = 'TEST-RIFLE-001'
);

DELETE FROM rifles WHERE serial_number = 'TEST-RIFLE-001';

-- Clean up any other potential duplicates
CREATE TEMPORARY TABLE temp_rifles AS 
SELECT MIN(id) as keep_id, serial_number 
FROM rifles 
GROUP BY serial_number 
HAVING COUNT(*) > 1;

-- Delete rifle assignments for duplicate rifles
DELETE ra FROM rifle_assignments ra
INNER JOIN rifles r ON ra.rifle_id = r.id
WHERE r.serial_number IN (
    SELECT serial_number FROM temp_rifles
) AND r.id NOT IN (
    SELECT keep_id FROM temp_rifles
);

-- Delete duplicate rifles
DELETE r FROM rifles r
INNER JOIN temp_rifles tr ON r.serial_number = tr.serial_number
WHERE r.id != tr.keep_id;

DROP TEMPORARY TABLE temp_rifles;

-- Verify the changes
SELECT 'users role enum updated:' as info;
SELECT column_type FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'users' AND column_name = 'role';

SELECT 'rifle duplicates check:' as info;
SELECT serial_number, COUNT(*) as count 
FROM rifles 
GROUP BY serial_number 
HAVING COUNT(*) > 1;

SELECT 'total rifles:' as info;
SELECT COUNT(*) as total_rifles FROM rifles;