-- Create items table with categories for ROTC inventory system
-- Migration: Create items table with categories
-- Date: 2024-01-15

-- Create items table
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category ENUM('Consumable', 'Non-Consumable', 'Semi-Expendable', 'Capital Assets', 'Disposable') NOT NULL,
    description TEXT,
    quantity_available INT DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'pcs',
    location VARCHAR(255),
    status ENUM('Available', 'Out of Stock', 'Maintenance') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample items for each category

-- 1. Consumable (Expendable) Supplies
INSERT INTO items (item_code, item_name, category, description, quantity_available, unit, location) VALUES
('CONS001', 'A4 Paper', 'Consumable', 'White bond paper for documents and reports', 500, 'sheets', 'Supply Cabinet A'),
('CONS002', 'Black Ink Cartridge', 'Consumable', 'Printer ink cartridge for HP LaserJet', 25, 'pcs', 'Supply Cabinet A'),
('CONS003', 'AA Batteries', 'Consumable', 'Alkaline batteries for equipment', 100, 'pcs', 'Supply Cabinet B'),
('CONS004', 'Cleaning Materials', 'Consumable', 'Multi-purpose cleaning supplies', 15, 'bottles', 'Janitor Closet'),
('CONS005', 'Training Ammunition', 'Consumable', 'Blank ammunition for training exercises', 200, 'rounds', 'Armory - Secured');

-- 2. Non-Consumable (Non-Expendable / Durable) Supplies
INSERT INTO items (item_code, item_name, category, description, quantity_available, unit, location) VALUES
('NONC001', 'Ballpoint Pen', 'Non-Consumable', 'Blue ink ballpoint pen for writing', 50, 'pcs', 'Supply Cabinet A'),
('NONC002', 'Heavy Duty Stapler', 'Non-Consumable', 'Metal stapler for binding documents', 8, 'pcs', 'Supply Cabinet A'),
('NONC003', 'Scissors', 'Non-Consumable', 'Stainless steel scissors for cutting', 12, 'pcs', 'Supply Cabinet A'),
('NONC004', 'Metal Ruler', 'Non-Consumable', '30cm stainless steel ruler', 20, 'pcs', 'Supply Cabinet A'),
('NONC005', 'Training Rifle', 'Non-Consumable', 'M16A1 training rifle for drill exercises', 30, 'pcs', 'Armory - Secured'),
('NONC006', 'Plastic Chair', 'Non-Consumable', 'Stackable plastic chairs for events', 100, 'pcs', 'Storage Room B'),
('NONC007', 'Hand Tools Set', 'Non-Consumable', 'Basic maintenance tools kit', 5, 'sets', 'Maintenance Room');

-- 3. Semi-Expendable Supplies
INSERT INTO items (item_code, item_name, category, description, quantity_available, unit, location) VALUES
('SEMI001', 'USB Flash Drive 16GB', 'Semi-Expendable', '16GB USB storage device', 25, 'pcs', 'IT Storage'),
('SEMI002', 'Umbrella', 'Semi-Expendable', 'Compact folding umbrella', 15, 'pcs', 'Supply Cabinet C'),
('SEMI003', 'Basic Calculator', 'Semi-Expendable', 'Solar powered calculator', 20, 'pcs', 'Supply Cabinet A'),
('SEMI004', 'Document Folder', 'Semi-Expendable', 'Plastic document organizer folder', 40, 'pcs', 'Supply Cabinet A'),
('SEMI005', 'Lanyard', 'Semi-Expendable', 'ID card lanyard with clip', 60, 'pcs', 'Supply Cabinet C');

-- 4. Capital Assets / Equipment
INSERT INTO items (item_code, item_name, category, description, quantity_available, unit, location) VALUES
('CAPT001', 'Desktop Computer', 'Capital Assets', 'Dell OptiPlex desktop computer', 8, 'units', 'Computer Lab'),
('CAPT002', 'LCD Projector', 'Capital Assets', 'Epson multimedia projector', 3, 'units', 'AV Equipment Room'),
('CAPT003', 'Laser Printer', 'Capital Assets', 'HP LaserJet Pro printer', 2, 'units', 'Admin Office'),
('CAPT004', 'Service Vehicle', 'Capital Assets', 'Toyota Hilux pickup truck', 1, 'unit', 'Motor Pool'),
('CAPT005', 'Sound System', 'Capital Assets', 'Portable PA system with microphones', 2, 'sets', 'AV Equipment Room');

-- 5. Disposable Supplies
INSERT INTO items (item_code, item_name, category, description, quantity_available, unit, location) VALUES
('DISP001', 'Surgical Gloves', 'Disposable', 'Latex-free disposable gloves', 200, 'pairs', 'Medical Kit'),
('DISP002', 'Face Masks', 'Disposable', 'Disposable surgical face masks', 500, 'pcs', 'Medical Kit'),
('DISP003', 'Plastic Cups', 'Disposable', 'Disposable plastic drinking cups', 300, 'pcs', 'Supply Cabinet C'),
('DISP004', 'COVID Test Kits', 'Disposable', 'Rapid antigen test kits', 50, 'kits', 'Medical Kit'),
('DISP005', 'Paper Towels', 'Disposable', 'Disposable paper towels for cleaning', 25, 'rolls', 'Janitor Closet');

-- Permissions will be handled by application level security

-- Create index for better performance
CREATE INDEX idx_items_category ON items(category);
CREATE INDEX idx_items_status ON items(status);
CREATE INDEX idx_items_name ON items(item_name);