-- Create missing_id_requests table
CREATE TABLE IF NOT EXISTS missing_id_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cadet_id INT NOT NULL,
    reason ENUM('lost', 'damaged', 'stolen', 'confiscated', 'other') NOT NULL,
    reason_details TEXT,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME NOT NULL,
    status ENUM('active', 'expired') DEFAULT 'active',
    qr_code_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE
);

-- Add index for better performance
CREATE INDEX idx_cadet_id ON missing_id_requests(cadet_id);
CREATE INDEX idx_status_expiry ON missing_id_requests(status, expiry_date);