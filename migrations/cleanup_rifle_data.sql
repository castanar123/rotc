-- Cleanup script for rifle data
-- Created: 2025-08-20
-- Purpose: Clean up existing rifle data and set proper rifle types

-- First, let's see what we have before cleanup
SELECT 'BEFORE CLEANUP - Current rifle data:' as info;
SELECT 
    `id`, 
    `rifle_number`, 
    `rifle_type`, 
    `status`, 
    `condition_notes`,
    `created_at`
FROM `rifles` 
ORDER BY `id`;

-- Remove any duplicate rifle numbers (keep the oldest entry)
DELETE r1 FROM `rifles` r1
INNER JOIN `rifles` r2 
WHERE r1.`id` > r2.`id` 
AND r1.`rifle_number` = r2.`rifle_number`;

-- Set rifle types based on rifle number patterns
-- Rifles with 'R' prefix (R001, R002, etc.) = mechanical rifle
UPDATE `rifles` 
SET `rifle_type` = 'mechanical rifle' 
WHERE `rifle_number` LIKE 'R%';

-- Rifles with numeric only patterns (5454, 102, etc.) = wooden rifle
UPDATE `rifles` 
SET `rifle_type` = 'wooden rifle' 
WHERE `rifle_number` REGEXP '^[0-9]+$';

-- Rifles with 'TEST' prefix = mechanical rifle (for testing)
UPDATE `rifles` 
SET `rifle_type` = 'mechanical rifle' 
WHERE `rifle_number` LIKE 'TEST%';

-- Any remaining rifles without type = mechanical rifle (default)
UPDATE `rifles` 
SET `rifle_type` = 'mechanical rifle' 
WHERE `rifle_type` IS NULL;

-- Clean up any rifles with invalid status
UPDATE `rifles` 
SET `status` = 'available' 
WHERE `status` NOT IN ('available', 'borrowed', 'maintenance', 'lost', 'damaged');

-- Remove any rifles with empty or invalid rifle numbers
DELETE FROM `rifles` 
WHERE `rifle_number` IS NULL 
OR `rifle_number` = '' 
OR LENGTH(TRIM(`rifle_number`)) = 0;

-- Show results after cleanup
SELECT 'AFTER CLEANUP - Updated rifle data:' as info;
SELECT 
    `id`, 
    `rifle_number`, 
    `rifle_type`, 
    `status`, 
    `condition_notes`,
    `created_at`
FROM `rifles` 
ORDER BY `rifle_type`, `rifle_number`;

-- Show summary by rifle type
SELECT 'SUMMARY - Rifles by type:' as info;
SELECT 
    `rifle_type`,
    COUNT(*) as total_rifles,
    SUM(CASE WHEN `status` = 'available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN `status` = 'borrowed' THEN 1 ELSE 0 END) as borrowed,
    SUM(CASE WHEN `status` = 'maintenance' THEN 1 ELSE 0 END) as maintenance
FROM `rifles` 
GROUP BY `rifle_type`
ORDER BY `rifle_type`;