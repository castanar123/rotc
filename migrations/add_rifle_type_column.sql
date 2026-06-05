-- Migration script to add rifle_type column to rifles table
-- Created: 2025-08-20
-- Purpose: Add rifle_type ENUM column with constraints for 'mechanical rifle' and 'wooden rifle'

-- Add rifle_type column with ENUM constraint
ALTER TABLE `rifles` 
ADD COLUMN `rifle_type` ENUM('mechanical rifle', 'wooden rifle') NOT NULL DEFAULT 'mechanical rifle' 
AFTER `rifle_number`;

-- Add index for rifle_type for better query performance
ALTER TABLE `rifles` 
ADD INDEX `idx_rifle_type` (`rifle_type`);

-- Update existing rifles with default type (mechanical rifle)
-- This sets all existing rifles to 'mechanical rifle' as default
UPDATE `rifles` 
SET `rifle_type` = 'mechanical rifle' 
WHERE `rifle_type` IS NULL OR `rifle_type` = '';

-- Verify the changes
SELECT 
    `id`, 
    `rifle_number`, 
    `rifle_type`, 
    `status`, 
    `condition_notes`
FROM `rifles` 
ORDER BY `id`;

-- Show table structure to confirm changes
DESCRIBE `rifles`;