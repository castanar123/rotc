-- Comprehensive User Data for ROTC Management System
-- Adding 15 additional realistic user accounts with varied roles and 2FA settings

USE rotc_system;

-- Insert comprehensive user accounts
INSERT INTO users (username, password, email, full_name, role, is_active, two_factor_enabled, two_factor_secret, two_factor_backup, created_at, updated_at) VALUES

-- Administrative Staff
('commandant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'commandant@rotc.mil', 'Colonel James Mitchell', 'admin', 1, 1, 'JBSWY3DPEHPK3PXP', 'backup-codes-admin-001', NOW(), NOW()),
('deputy_cmd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'deputy@rotc.mil', 'Lieutenant Colonel Sarah Davis', 'admin', 1, 1, 'HXDMVJECJJWSRB3HWIZR4IFUGFTMXBOZ', 'backup-codes-admin-002', NOW(), NOW()),
('admin_clerk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'clerk@rotc.mil', 'Staff Sergeant Maria Rodriguez', 'admin', 1, 0, NULL, NULL, NOW(), NOW()),

-- Instructors and Training Staff
('instructor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor1@rotc.edu', 'Major Robert Thompson', 'instructor', 1, 1, 'KVKFKRCPNZQUYMLXOVYDSQKJKZDTSRLD', 'backup-codes-inst-001', NOW(), NOW()),
('instructor2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor2@rotc.edu', 'Captain Lisa Anderson', 'instructor', 1, 0, NULL, NULL, NOW(), NOW()),
('drill_sgt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'drill@rotc.edu', 'Sergeant First Class David Wilson', 'instructor', 1, 1, 'MFRGG2LTEBUW4IDPMYFA', 'backup-codes-drill-001', NOW(), NOW()),
('weapons_inst', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'weapons@rotc.edu', 'Master Sergeant Jennifer Lee', 'instructor', 1, 1, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'backup-codes-weapons-001', NOW(), NOW()),

-- Senior Cadets and Officers
('cadet_major', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cmajor@rotc.edu', 'Cadet Major Alexander Brown', 'senior_cadet', 1, 1, 'MFZWIZLNMFTA', 'backup-codes-cmajor-001', NOW(), NOW()),
('cadet_captain', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ccaptain@rotc.edu', 'Cadet Captain Emily Garcia', 'senior_cadet', 1, 0, NULL, NULL, NOW(), NOW()),
('cadet_1lt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'c1lt@rotc.edu', 'Cadet First Lieutenant Michael Chen', 'senior_cadet', 1, 1, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'backup-codes-c1lt-001', NOW(), NOW()),

-- Regular Cadets
('cadet_williams', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'williams@rotc.edu', 'Cadet Jessica Williams', 'cadet', 1, 0, NULL, NULL, NOW(), NOW()),
('cadet_martinez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'martinez@rotc.edu', 'Cadet Carlos Martinez', 'cadet', 1, 0, NULL, NULL, NOW(), NOW()),
('cadet_taylor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'taylor@rotc.edu', 'Cadet Ashley Taylor', 'cadet', 1, 1, 'JBSWY3DPEHPK3PXP', 'backup-codes-taylor-001', NOW(), NOW()),
('cadet_jackson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jackson@rotc.edu', 'Cadet Brandon Jackson', 'cadet', 1, 0, NULL, NULL, NOW(), NOW()),

-- Support Staff
('supply_officer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supply@rotc.mil', 'Sergeant Thomas White', 'staff', 1, 1, 'KVKFKRCPNZQUYMLXOVYDSQKJKZDTSRLD', 'backup-codes-supply-001', NOW(), NOW());

-- Update existing users to have proper two-factor settings for some accounts
UPDATE users SET 
    two_factor_enabled = 1, 
    two_factor_secret = 'JBSWY3DPEHPK3PXP', 
    two_factor_backup = 'backup-codes-admin-main'
WHERE username = 'admin';

UPDATE users SET 
    two_factor_enabled = 1, 
    two_factor_secret = 'MFRGG2LTEBUW4IDPMYFA', 
    two_factor_backup = 'backup-codes-officer-001'
WHERE username = 'officer2cl';

-- Display final user count
SELECT COUNT(*) as total_users FROM users;
SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY role;
SELECT two_factor_enabled, COUNT(*) as count FROM users GROUP BY two_factor_enabled;