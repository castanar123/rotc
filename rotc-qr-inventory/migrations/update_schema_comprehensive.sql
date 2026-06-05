-- Comprehensive Database Schema Update for ROTC QR Inventory System
-- This migration addresses all new requirements

USE rotc_qr_inventory;

-- Create borrowers table for fixed borrower names with PIN authentication
CREATE TABLE IF NOT EXISTS borrowers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    pin VARCHAR(6) NOT NULL,
    rank_position VARCHAR(50),
    unit VARCHAR(50),
    contact_number VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    is_guest BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_is_guest (is_guest)
);

-- Update items table to add missing columns
ALTER TABLE items 
ADD COLUMN IF NOT EXISTS can_be_returned BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS minimum_stock INT DEFAULT 0;

-- Update category enum to match requirements
ALTER TABLE items MODIFY COLUMN category ENUM('Consumable', 'Disposable', 'Non-consumable', 'Semi-expendable', 'Capital') NOT NULL;

-- Update items to set can_be_returned based on category
UPDATE items SET can_be_returned = FALSE WHERE category IN ('Consumable', 'Disposable');
UPDATE items SET can_be_returned = TRUE WHERE category IN ('Non-consumable', 'Semi-expendable', 'Capital');

-- Add borrower_id column to transactions table if it doesn't exist
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS borrower_id INT;

-- Insert sample borrowers with PINs
INSERT IGNORE INTO borrowers (name, pin, rank_position, unit, status) VALUES
('Cadet John Smith', '123456', 'Cadet Captain', 'Alpha Company', 'active'),
('Cadet Maria Garcia', '234567', 'Cadet Lieutenant', 'Bravo Company', 'active'),
('Cadet Robert Johnson', '345678', 'Cadet Sergeant', 'Charlie Company', 'active'),
('Cadet Sarah Wilson', '456789', 'Cadet Corporal', 'Delta Company', 'active'),
('Cadet Michael Brown', '567890', 'Cadet Private', 'Echo Company', 'active');

-- Clear existing items and insert new categorized items
DELETE FROM items;

-- Insert Consumable items (cannot be returned)
INSERT INTO items (item_code, item_name, category, quantity_available, unit, can_be_returned) VALUES
('CONS001', 'A4 Paper', 'Consumable', 500, 'sheets', FALSE),
('CONS002', 'Ballpoint Pen', 'Consumable', 100, 'pcs', FALSE),
('CONS003', 'Pencil', 'Consumable', 80, 'pcs', FALSE),
('CONS004', 'Eraser', 'Consumable', 50, 'pcs', FALSE),
('CONS005', 'Notebook', 'Consumable', 75, 'pcs', FALSE),
('CONS006', 'Marker', 'Consumable', 40, 'pcs', FALSE),
('CONS007', 'Correction Fluid', 'Consumable', 25, 'bottles', FALSE);

-- Insert Disposable items (cannot be returned)
INSERT INTO items (item_code, item_name, category, quantity_available, unit, can_be_returned) VALUES
('DISP001', 'Face Mask', 'Disposable', 200, 'pcs', FALSE),
('DISP002', 'Disposable Gloves', 'Disposable', 150, 'pairs', FALSE),
('DISP003', 'Paper Cups', 'Disposable', 300, 'pcs', FALSE),
('DISP004', 'Tissue Paper', 'Disposable', 60, 'packs', FALSE),
('DISP005', 'Plastic Bags', 'Disposable', 100, 'pcs', FALSE);

-- Insert Non-consumable items (must be returned)
INSERT INTO items (item_code, item_name, category, quantity_available, unit, can_be_returned) VALUES
('NONC001', 'Stapler', 'Non-consumable', 15, 'pcs', TRUE),
('NONC002', 'Hole Puncher', 'Non-consumable', 10, 'pcs', TRUE),
('NONC003', 'Scissors', 'Non-consumable', 20, 'pcs', TRUE),
('NONC004', 'Ruler', 'Non-consumable', 25, 'pcs', TRUE),
('NONC005', 'Calculator', 'Non-consumable', 12, 'pcs', TRUE),
('NONC006', 'Clipboard', 'Non-consumable', 18, 'pcs', TRUE);

-- Insert Semi-expendable items (must be returned)
INSERT INTO items (item_code, item_name, category, quantity_available, unit, can_be_returned) VALUES
('SEMI001', 'Training Manual', 'Semi-expendable', 30, 'pcs', TRUE),
('SEMI002', 'Field Gear Set', 'Semi-expendable', 25, 'sets', TRUE),
('SEMI003', 'Compass', 'Semi-expendable', 20, 'pcs', TRUE),
('SEMI004', 'Whistle', 'Semi-expendable', 35, 'pcs', TRUE),
('SEMI005', 'Flashlight', 'Semi-expendable', 22, 'pcs', TRUE),
('SEMI006', 'First Aid Kit', 'Semi-expendable', 15, 'kits', TRUE);

-- Insert Capital items (must be returned)
INSERT INTO items (item_code, item_name, category, quantity_available, unit, can_be_returned) VALUES
('CAPT001', 'Laptop Computer', 'Capital', 8, 'units', TRUE),
('CAPT002', 'Projector', 'Capital', 5, 'units', TRUE),
('CAPT003', 'Digital Camera', 'Capital', 6, 'units', TRUE),
('CAPT004', 'Radio Equipment', 'Capital', 10, 'sets', TRUE),
('CAPT005', 'GPS Device', 'Capital', 7, 'units', TRUE);

-- Create resupply_requests table for quick/multiple resupply functionality
CREATE TABLE IF NOT EXISTS resupply_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    duty_officer_id INT NOT NULL,
    status ENUM('pending', 'approved', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (duty_officer_id) REFERENCES officers(id) ON DELETE CASCADE,
    INDEX idx_request_id (request_id),
    INDEX idx_status (status),
    INDEX idx_duty_officer (duty_officer_id)
);

-- Create resupply_items table
CREATE TABLE IF NOT EXISTS resupply_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resupply_request_id INT NOT NULL,
    item_id INT NOT NULL,
    requested_quantity INT NOT NULL,
    approved_quantity INT DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resupply_request_id) REFERENCES resupply_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_resupply_request (resupply_request_id),
    INDEX idx_item (item_id)
);

COMMIT;