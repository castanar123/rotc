-- ROTC QR-Based Inventory Management System Database Setup
-- Create database and tables

CREATE DATABASE IF NOT EXISTS rotc_qr_inventory;
USE rotc_qr_inventory;

-- Officers table
CREATE TABLE officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    rank_position VARCHAR(50) NOT NULL,
    platoon VARCHAR(20) NOT NULL,
    contact_number VARCHAR(15),
    email VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_officer_id (officer_id),
    INDEX idx_platoon (platoon),
    INDEX idx_status (status)
);

-- Duty sessions table
CREATE TABLE duty_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    duty_officer_id INT NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (duty_officer_id) REFERENCES officers(id) ON DELETE CASCADE,
    INDEX idx_duty_officer (duty_officer_id),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time)
);

-- Inventory items table
CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    total_quantity INT NOT NULL DEFAULT 0,
    available_quantity INT NOT NULL DEFAULT 0,
    borrowed_quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'pcs',
    location VARCHAR(100),
    condition_status ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    minimum_stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_code (item_code),
    INDEX idx_category (category),
    INDEX idx_condition (condition_status)
);

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('borrow', 'return', 'supply') NOT NULL,
    duty_officer_id INT NOT NULL,
    borrower_name VARCHAR(100),
    borrower_id VARCHAR(50),
    borrower_contact VARCHAR(15),
    purpose TEXT,
    expected_return_date DATE,
    actual_return_date DATE NULL,
    status ENUM('pending', 'approved', 'completed', 'overdue', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    digital_signature TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (duty_officer_id) REFERENCES officers(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_duty_officer (duty_officer_id),
    INDEX idx_created_at (created_at)
);

-- Transaction items table
CREATE TABLE transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    condition_before ENUM('excellent', 'good', 'fair', 'poor'),
    condition_after ENUM('excellent', 'good', 'fair', 'poor'),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_transaction (transaction_id),
    INDEX idx_item (item_id)
);

-- Borrowed items table (for tracking active borrows)
CREATE TABLE borrowed_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    borrowed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_return_date DATE NOT NULL,
    status ENUM('active', 'returned', 'overdue') DEFAULT 'active',
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_transaction (transaction_id),
    INDEX idx_item (item_id),
    INDEX idx_status (status),
    INDEX idx_expected_return (expected_return_date)
);

-- QR codes table
CREATE TABLE qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    type ENUM('item', 'transaction') NOT NULL,
    reference_id INT NOT NULL,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'expired', 'used') DEFAULT 'active',
    INDEX idx_qr_code (qr_code),
    INDEX idx_type_ref (type, reference_id),
    INDEX idx_status (status)
);

-- Insert sample officers
INSERT INTO officers (officer_id, name, rank_position, platoon, contact_number, email) VALUES
('OFF001', 'John Doe', 'Cadet Captain', 'Alpha', '09123456789', 'john.doe@rotc.edu'),
('OFF002', 'Jane Smith', 'Cadet Lieutenant', 'Bravo', '09987654321', 'jane.smith@rotc.edu'),
('OFF003', 'Mike Johnson', 'Cadet Sergeant', 'Charlie', '09111222333', 'mike.johnson@rotc.edu');

-- Insert sample inventory items
INSERT INTO inventory_items (item_code, item_name, category, description, total_quantity, available_quantity, unit, location) VALUES
('RIFLE001', 'M16 Rifle', 'Weapons', 'Standard issue rifle for training', 50, 45, 'pcs', 'Armory A'),
('UNIFORM001', 'Combat Uniform Set', 'Clothing', 'Complete combat uniform with accessories', 100, 85, 'sets', 'Supply Room B'),
('BOOTS001', 'Combat Boots', 'Footwear', 'Standard issue combat boots', 80, 70, 'pairs', 'Supply Room B'),
('HELMET001', 'Combat Helmet', 'Protection', 'Protective helmet for field training', 60, 55, 'pcs', 'Armory A'),
('VEST001', 'Tactical Vest', 'Protection', 'Tactical vest with pouches', 40, 35, 'pcs', 'Armory A');

-- Grant privileges to root user (for XAMPP)
GRANT ALL PRIVILEGES ON rotc_qr_inventory.* TO 'root'@'localhost';
FLUSH PRIVILEGES;