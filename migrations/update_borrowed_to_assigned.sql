-- Migration script to update 'borrowed' to 'assigned' terminology
-- Created: 2025-01-21
-- Purpose: Change rifle status and action enums from 'borrowed' to 'assigned'

USE rotc_db;

-- First, update any existing data that uses 'borrowed' status
UPDATE rifles SET status = 'assigned' WHERE status = 'borrowed';

-- Update rifle_logs actions from 'borrowed' to 'assigned'
UPDATE rifle_logs SET action = 'assigned' WHERE action = 'borrowed';

-- Alter the rifles table enum to replace 'borrowed' with 'assigned'
ALTER TABLE rifles MODIFY COLUMN status ENUM('available', 'assigned', 'maintenance', 'lost', 'damaged') DEFAULT 'available';

-- Alter the rifle_logs table enum to replace 'borrowed' with 'assigned'
ALTER TABLE rifle_logs MODIFY COLUMN action ENUM('created', 'assigned', 'returned', 'maintenance', 'lost', 'damaged', 'repaired') NOT NULL;

-- Display success message
SELECT 'Database schema updated successfully! Changed borrowed to assigned.' as message;
SELECT COUNT(*) as rifles_with_assigned_status FROM rifles WHERE status = 'assigned';
SELECT COUNT(*) as logs_with_assigned_action FROM rifle_logs WHERE action = 'assigned';