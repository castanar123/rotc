-- Fix Remaining Column Issues in ROTC Database
-- This script adds the final missing columns and creates aliases where needed

USE rotc_db;

-- Add student_id column to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS student_id VARCHAR(50) UNIQUE;

-- Add birth_place column to cadet_profiles table (in addition to place_of_birth)
ALTER TABLE cadet_profiles 
ADD COLUMN IF NOT EXISTS birth_place VARCHAR(255);

-- Add timestamp column to security_logs table (in addition to created_at)
ALTER TABLE security_logs 
ADD COLUMN IF NOT EXISTS timestamp DATETIME DEFAULT CURRENT_TIMESTAMP;

-- Update birth_place with place_of_birth data where birth_place is null
UPDATE cadet_profiles 
SET birth_place = place_of_birth 
WHERE birth_place IS NULL AND place_of_birth IS NOT NULL;

-- Update timestamp with created_at data where timestamp is null
UPDATE security_logs 
SET timestamp = created_at 
WHERE timestamp IS NULL AND created_at IS NOT NULL;

-- Create triggers to keep the duplicate columns in sync
DELIMITER //

CREATE TRIGGER IF NOT EXISTS sync_birth_place_insert
AFTER INSERT ON cadet_profiles
FOR EACH ROW
BEGIN
    IF NEW.birth_place IS NOT NULL AND NEW.place_of_birth IS NULL THEN
        UPDATE cadet_profiles SET place_of_birth = NEW.birth_place WHERE id = NEW.id;
    ELSEIF NEW.place_of_birth IS NOT NULL AND NEW.birth_place IS NULL THEN
        UPDATE cadet_profiles SET birth_place = NEW.place_of_birth WHERE id = NEW.id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS sync_birth_place_update
AFTER UPDATE ON cadet_profiles
FOR EACH ROW
BEGIN
    IF NEW.birth_place != OLD.birth_place AND NEW.birth_place IS NOT NULL THEN
        UPDATE cadet_profiles SET place_of_birth = NEW.birth_place WHERE id = NEW.id;
    ELSEIF NEW.place_of_birth != OLD.place_of_birth AND NEW.place_of_birth IS NOT NULL THEN
        UPDATE cadet_profiles SET birth_place = NEW.place_of_birth WHERE id = NEW.id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS sync_timestamp_insert
AFTER INSERT ON security_logs
FOR EACH ROW
BEGIN
    IF NEW.timestamp IS NOT NULL AND NEW.created_at IS NULL THEN
        UPDATE security_logs SET created_at = NEW.timestamp WHERE id = NEW.id;
    ELSEIF NEW.created_at IS NOT NULL AND NEW.timestamp IS NULL THEN
        UPDATE security_logs SET timestamp = NEW.created_at WHERE id = NEW.id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS sync_timestamp_update
AFTER UPDATE ON security_logs
FOR EACH ROW
BEGIN
    IF NEW.timestamp != OLD.timestamp AND NEW.timestamp IS NOT NULL THEN
        UPDATE security_logs SET created_at = NEW.timestamp WHERE id = NEW.id;
    ELSEIF NEW.created_at != OLD.created_at AND NEW.created_at IS NOT NULL THEN
        UPDATE security_logs SET timestamp = NEW.created_at WHERE id = NEW.id;
    END IF;
END//

DELIMITER ;

-- Add indexes for the new columns
CREATE INDEX IF NOT EXISTS idx_users_student_id ON users(student_id);
CREATE INDEX IF NOT EXISTS idx_cadet_profiles_birth_place ON cadet_profiles(birth_place);
CREATE INDEX IF NOT EXISTS idx_security_logs_timestamp ON security_logs(timestamp);

-- Verify the changes
SELECT 'users table now has student_id:' as info;
SELECT COUNT(*) as student_id_column_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'users' AND column_name = 'student_id';

SELECT 'cadet_profiles table now has birth_place:' as info;
SELECT COUNT(*) as birth_place_column_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'cadet_profiles' AND column_name = 'birth_place';

SELECT 'security_logs table now has timestamp:' as info;
SELECT COUNT(*) as timestamp_column_exists FROM information_schema.columns 
WHERE table_schema = 'rotc_db' AND table_name = 'security_logs' AND column_name = 'timestamp';