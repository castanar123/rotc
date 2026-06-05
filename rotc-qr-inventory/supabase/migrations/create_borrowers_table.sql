-- Create borrowers table for managing borrower information
CREATE TABLE IF NOT EXISTS borrowers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrower_name VARCHAR(255) NOT NULL,
    borrower_id VARCHAR(100) UNIQUE,
    pin VARCHAR(6),
    is_guest BOOLEAN DEFAULT FALSE,
    contact_info VARCHAR(255),
    department VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert some default borrowers
INSERT INTO borrowers (borrower_name, borrower_id, pin, is_guest, department) VALUES
('John Doe', 'ROTC001', '123456', FALSE, 'ROTC Unit'),
('Jane Smith', 'ROTC002', '654321', FALSE, 'ROTC Unit'),
('Mike Johnson', 'ROTC003', '111111', FALSE, 'ROTC Unit'),
('Guest User', 'GUEST', NULL, TRUE, 'Temporary');

-- Update borrowed_items table to reference borrowers
ALTER TABLE borrowed_items 
ADD COLUMN borrower_table_id INT,
ADD FOREIGN KEY (borrower_table_id) REFERENCES borrowers(id);

-- Grant permissions to anon and authenticated roles
GRANT SELECT, INSERT, UPDATE ON borrowers TO anon;
GRANT ALL