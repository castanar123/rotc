-- Create borrowers table for recycled QR borrower ID system
CREATE TABLE IF NOT EXISTS borrowers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    temp_id VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    course VARCHAR(255) NOT NULL,
    contact VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_temp_id (temp_id),
    INDEX idx_status (status),
    INDEX idx_temp_id_status (temp_id, status)
);

-- Update rifle_assignments table to include borrower_id and additional fields
ALTER TABLE rifle_assignments 
ADD COLUMN IF NOT EXISTS borrower_id INT,
ADD COLUMN IF NOT EXISTS returned_by INT,
ADD COLUMN IF NOT EXISTS returned_at TIMESTAMP NULL,
ADD INDEX IF NOT EXISTS idx_borrower_id (borrower_id),
ADD INDEX IF NOT EXISTS idx_status (status),
ADD FOREIGN KEY IF NOT EXISTS fk_borrower (borrower_id) REFERENCES borrowers(id) ON DELETE SET NULL;

-- Create index for better performance on common queries
CREATE INDEX IF NOT EXISTS idx_rifle_assignments_active ON rifle_assignments (borrower_id, status) WHERE status = 'active';

-- Insert some sample temp IDs for testing (these would normally be QR codes)
INSERT IGNORE INTO borrowers (temp_id, name, course, contact, status) VALUES 
('TEMP001', 'Sample Borrower 1', 'BSIT', '09123456789', 'inactive'),
('TEMP002', 'Sample Borrower 2', 'BSCS', '09987654321', 'inactive'),
('TEMP003', 'Sample Borrower 3', 'BSIS', '09111222333', 'inactive');