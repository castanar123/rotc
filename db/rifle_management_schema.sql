-- Rifle Management System Database Schema
-- Execute this script to create the required tables

USE rotc_management; -- Replace with your database name if different

-- Create rifles table
CREATE TABLE IF NOT EXISTS rifles (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rifle_number VARCHAR(50) NOT NULL UNIQUE,
    qr_code_path VARCHAR(255) DEFAULT NULL,
    status ENUM('available', 'assigned', 'maintenance', 'lost', 'damaged') DEFAULT 'available',
    condition_notes TEXT DEFAULT NULL,
    last_maintenance DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_rifle_number (rifle_number),
    INDEX idx_rifle_status (status),
    INDEX idx_rifle_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create rifle assignments table
CREATE TABLE IF NOT EXISTS rifle_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rifle_id INT(11) NOT NULL,
    cadet_id INT(11) NOT NULL,
    assigned_by INT(11) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_return TIMESTAMP NULL DEFAULT NULL,
    returned_at TIMESTAMP NULL DEFAULT NULL,
    returned_by INT(11) NULL DEFAULT NULL,
    status ENUM('active', 'returned', 'overdue', 'lost', 'damaged') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
    FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_assignment_rifle (rifle_id),
    INDEX idx_assignment_cadet (cadet_id),
    INDEX idx_assignment_status (status),
    INDEX idx_assignment_dates (assigned_at, returned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create rifle logs table
CREATE TABLE IF NOT EXISTS rifle_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rifle_id INT(11) NOT NULL,
    cadet_id INT(11) NULL DEFAULT NULL,
    action ENUM('created', 'assigned', 'returned', 'maintenance', 'lost', 'damaged', 'repaired') NOT NULL,
    performed_by INT(11) NOT NULL,
    details TEXT DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
    FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_log_rifle (rifle_id),
    INDEX idx_log_action (action),
    INDEX idx_log_timestamp (timestamp),
    INDEX idx_log_performer (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table to track external rifle QR codes separately
CREATE TABLE IF NOT EXISTS rifle_external_qrs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rifle_id INT(11) NULL DEFAULT NULL,
    rifle_number VARCHAR(50) NOT NULL,
    qr_path VARCHAR(255) NOT NULL,
    payload_json TEXT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE SET NULL,
    INDEX idx_extqr_rifle_number (rifle_number),
    INDEX idx_extqr_generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample rifle data
INSERT INTO rifles (rifle_number, status, condition_notes) VALUES
('R001', 'available', 'New rifle - excellent condition'),
('R002', 'available', 'Good condition - minor wear'),
('R003', 'maintenance', 'Requires cleaning and inspection'),
('R004', 'available', 'Recently serviced - excellent condition'),
('R005', 'available', 'Good condition - ready for use'),
('R006', 'available', 'Excellent condition - new stock'),
('R007', 'available', 'Good condition - ready for training'),
('R008', 'maintenance', 'Scheduled maintenance required'),
('R009', 'available', 'Good condition - recently cleaned'),
('R010', 'available', 'Excellent condition - inspection passed');

-- Log the creation of sample rifles
INSERT INTO rifle_logs (rifle_id, action, performed_by, details) 
SELECT id, 'created', 1, CONCAT('Rifle ', rifle_number, ' added to inventory')
FROM rifles;

-- Create indexes for better performance
CREATE INDEX idx_rifles_qr_path ON rifles(qr_code_path);
CREATE INDEX idx_assignments_expected_return ON rifle_assignments(expected_return);
CREATE INDEX idx_logs_cadet_action ON rifle_logs(cadet_id, action);

-- Display success message
SELECT 'Rifle Management System database schema created successfully!' as message;
SELECT COUNT(*) as total_rifles_created FROM rifles;
SELECT COUNT(*) as total_logs_created FROM rifle_logs;