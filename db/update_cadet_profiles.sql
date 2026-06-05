-- Create registration fields view and add missing indexes
-- All required fields are already present in cadet_profiles table

USE rotc_db;

-- Add indexes for better performance (only if they don't exist)
ALTER TABLE cadet_profiles 
ADD INDEX IF NOT EXISTS idx_cadet_id (cadet_id),
ADD INDEX IF NOT EXISTS idx_rank (rank),
ADD INDEX IF NOT EXISTS idx_platoon (platoon),
ADD INDEX IF NOT EXISTS idx_company (company);

-- Create a view that shows all cadet profile fields
CREATE OR REPLACE VIEW cadet_profiles_view AS
SELECT 
    cp.id,
    cp.user_id,
    cp.cadet_id,
    CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) AS full_name,
    cp.first_name,
    cp.last_name,
    cp.middle_name,
    cp.rank,
    cp.company,
    cp.platoon,
    cp.squad,
    cp.year_level,
    cp.course,
    cp.contact_number,
    cp.facebook_profile,
    cp.emergency_contact,
    cp.address,
    cp.date_of_birth,
    cp.blood_type,
    cp.medical_conditions,
    cp.created_at,
    cp.updated_at
FROM cadet_profiles cp;

-- Show the updated table structure
SELECT 'Cadet profiles view created successfully!' AS message;
SELECT 'All cadet profile fields are now available in cadet_profiles_view' AS status;