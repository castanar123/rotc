-- Fix Role ENUM and Add Missing user_id Column
-- This script fixes role ENUM values and adds missing user_id to rifle_assignments

USE rotc_db;

-- Add user_id column to rifle_assignments table
ALTER TABLE rifle_assignments 
ADD COLUMN IF NOT EXISTS user_id INT(11);

-- Add foreign key constraint for user_id in rifle_assignments
ALTER TABLE rifle_assignments 
ADD CONSTRAINT fk_rifle_assignments_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Update the role ENUM to include more values that might be used in tests
ALTER TABLE users 
MODIFY COLUMN role ENUM(
    'admin',
    'instructor', 
    '1cl',
    '2cl',
    'commandant',
    'cadet',
    'basic_cadet',
    'student',
    'user',
    'senior_cadet',
    'junior_cadet'
) NOT NULL DEFAULT 'cadet';

-- Add index for the new user_id column
CREATE INDEX IF NOT EXISTS idx_rifle_assignments_user_id ON rifle_assignments(user_id);

-- Update existing rifle_assignments to have user_id based on assigned_by
UPDATE rifle_assignments 
SET user_id = assigned_by 
WHERE user_id IS NULL AND assigned_by IS NOT NULL;

-- Verify the changes
SELECT 'rifle_assignments user_id column:' as info;
SELECT COUNT(*) as user_id_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'rifle_assignments' AND column_name = 'user_id';

SELECT 'users role enum values:' as info;
SELECT column_type FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'users' AND column_name = 'role';

SELECT 'sample rifle_assignments:' as info;
SELECT id, rifle_id, cadet_id, user_id, assigned_by FROM rifle_assignments LIMIT 3;