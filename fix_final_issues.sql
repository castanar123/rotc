-- Fix Final Issues: Remove Problematic Triggers and Fix Attendance Column
-- This script removes the problematic cadet_profiles triggers and fixes attendance table

USE rotc_db;

-- Drop the problematic triggers that cause recursive updates
DROP TRIGGER IF EXISTS sync_birth_place_insert;
DROP TRIGGER IF EXISTS sync_birth_place_update;

-- Add 'date' column to attendance table as an alias or alternative to log_date
-- First check if 'date' column exists
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'rotc_db' 
AND table_name = 'attendance' 
AND column_name = 'date';

-- Add 'date' column if it doesn't exist
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE attendance ADD COLUMN date DATE NOT NULL DEFAULT (CURDATE())',
    'SELECT "date column already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records to sync date with log_date
UPDATE attendance SET date = log_date WHERE date IS NULL OR date = '0000-00-00';

-- Add time_in column if it doesn't exist (the test expects this)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'rotc_db' 
AND table_name = 'attendance' 
AND column_name = 'time_in';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE attendance ADD COLUMN time_in TIME NULL',
    'SELECT "time_in column already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add semester column if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'rotc_db' 
AND table_name = 'attendance' 
AND column_name = 'semester';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE attendance ADD COLUMN semester INT DEFAULT 1',
    'SELECT "semester column already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add academic_year column if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'rotc_db' 
AND table_name = 'attendance' 
AND column_name = 'academic_year';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE attendance ADD COLUMN academic_year VARCHAR(20) DEFAULT "2024-2025"',
    'SELECT "academic_year column already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the changes
SELECT 'Triggers removed:' as info;
SHOW TRIGGERS LIKE 'cadet_profiles';

SELECT 'Attendance table structure:' as info;
DESCRIBE attendance;

SELECT 'Test attendance insert:' as info;
SELECT 'Ready for testing' as status;