-- Fix Username Field Default Value Issue
-- This script makes username nullable and adds proper default handling

USE rotc_db;

-- Make username field nullable and add default value
ALTER TABLE users 
MODIFY COLUMN username VARCHAR(50) NULL DEFAULT NULL;

-- Remove unique constraint temporarily to fix duplicates
ALTER TABLE users DROP INDEX username;

-- Update any NULL usernames with email-based usernames
UPDATE users 
SET username = CONCAT(SUBSTRING_INDEX(email, '@', 1), '_', id)
WHERE username IS NULL OR username = '';

-- Re-add unique constraint
ALTER TABLE users ADD UNIQUE KEY unique_username (username);

-- Fix rifle serial number duplicates completely
SET @counter = 0;
UPDATE rifles 
SET serial_number = CONCAT('RIFLE-', LPAD((@counter := @counter + 1), 6, '0'))
ORDER BY id;

-- Verify the changes
SELECT 'username field info:' as info;
SELECT column_name, is_nullable, column_default 
FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'users' AND column_name = 'username';

SELECT 'sample usernames:' as info;
SELECT id, email, username FROM users LIMIT 5;

SELECT 'rifle serial numbers:' as info;
SELECT id, serial_number FROM