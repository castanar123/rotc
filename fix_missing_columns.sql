-- Fix Missing Columns in ROTC Database
-- This script adds all missing columns identified in the comprehensive testing

USE rotc_db;

-- Add missing columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NOT NULL DEFAULT '',
ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NOT NULL DEFAULT '',
ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';

-- Add missing columns to rifles table
ALTER TABLE rifles 
ADD COLUMN IF NOT EXISTS user_id INT NULL,
ADD COLUMN IF NOT EXISTS status ENUM('available', 'assigned', 'maintenance', 'retired') DEFAULT 'available';

-- Add missing columns to attendance table
ALTER TABLE attendance 
ADD COLUMN IF NOT EXISTS user_id INT NOT NULL;

-- Add missing columns to security_logs table
ALTER TABLE security_logs 
ADD COLUMN IF NOT EXISTS action VARCHAR(255) NOT NULL DEFAULT '';

-- Add foreign key constraints for better data integrity
ALTER TABLE rifles 
ADD CONSTRAINT fk_rifles_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE attendance 
ADD CONSTRAINT fk_attendance_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update existing data to have proper values
-- Set default approval status for existing users
UPDATE users SET approval_status = 'approved' WHERE approval_status IS NULL;

-- Set default rifle status
UPDATE rifles SET status = 'available' WHERE status IS NULL;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_approval_status ON users(approval_status);
CREATE INDEX IF NOT EXISTS idx_rifles_status ON rifles(status);
CREATE INDEX IF NOT EXISTS idx_rifles_user_id ON rifles(user_id);
CREATE INDEX IF NOT EXISTS idx_attendance_user_id ON attendance(user_id);
CREATE INDEX IF NOT EXISTS idx_security_logs_action ON security_logs(action);

-- Verify the changes
SELECT 'users table structure:' as info;
DESCRIBE users;

SELECT 'rifles table structure:' as info;
DESCRIBE rifles;

SELECT 'attendance table structure:' as info;
DESCRIBE attendance;

SELECT 'security_logs table structure:' as info;
DESCRIBE security_logs;