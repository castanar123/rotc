-- Add Facebook profile field to cadet_profiles table
-- Migration to add facebook_profile column

USE rotc_db;

-- Add facebook_profile column to cadet_profiles table
ALTER TABLE `cadet_profiles` 
ADD COLUMN `facebook_profile` VARCHAR(255) DEFAULT NULL 
AFTER `contact_number`;

-- Add index for better performance
ALTER TABLE `cadet_profiles` 
ADD INDEX `idx_facebook_profile` (`facebook_profile`);

SELECT 'Facebook profile field added successfully to cadet_profiles table' AS status;