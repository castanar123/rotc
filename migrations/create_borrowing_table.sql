-- Migration script to create rifle borrowing table
-- Created: 2025-01-22
-- Purpose: Add rifle borrowing functionality with QR code support

USE rotc_db;

-- Create rifle_borrowings table
CREATE TABLE IF NOT EXISTS rifle_borrowings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    borrower_name VARCHAR(255) NOT NULL,
    rifle_ids JSON NOT NULL, -- Store array of rifle IDs as JSON
    qr_code_id VARCHAR(50) NOT NULL, -- QR code identifier (dummy1, dummy2, dummy3)
    borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    return_date TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'returned', 'overdue') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL, -- User who processed the borrowing
    returned_by INT(11) DEFAULT NULL, -- User who processed the return
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_borrower_name (borrower_name),
    INDEX idx_status (status),
    INDEX idx_borrow_date (borrow_date),
    INDEX idx_qr_code_id (qr_code_id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create dummy QR codes table for borrowing
CREATE TABLE IF NOT EXISTS dummy_qr_codes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    qr_code_id VARCHAR(50) NOT NULL UNIQUE,
    qr_code_path VARCHAR(255) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_qr_code_id (qr_code_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert 3 dummy QR codes
INSERT IGNORE INTO dummy_qr_codes (qr_code_id, description) VALUES 
('BORROW_QR_001', 'Dummy QR Code 1 for Rifle Borrowing'),
('BORROW_QR_002', 'Dummy QR Code 2 for Rifle Borrowing'),
('BORROW_QR_003', 'Dummy QR Code 3 for Rifle Borrowing');

-- Add borrowing status to rifles table if not exists
ALTER TABLE rifles 
MODIFY COLUMN status ENUM('available', 'assigned', 'borrowed', 'maintenance', 'lost', 'damaged') DEFAULT 'available';

-- Create index for better performance on rifle status queries
CREATE INDEX IF NOT EXISTS idx_rifles_borrowed_status ON rifles(status) WHERE status = 'borrowed';

SELECT 'Rifle borrowing tables created successfully!' as result;