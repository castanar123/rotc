<?php
echo "=== ROTC Database Complete Restoration ===\n\n";

try {
    // Connect to MySQL
    $mysql = new PDO('mysql:host=localhost', 'root', 'root');
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Connect to SQLite
    $sqlite = new PDO('sqlite:data/rotc_db.sqlite');
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop and recreate database completely
    echo "1. Recreating rotc_db database...\n";
    $mysql->exec('DROP DATABASE IF EXISTS rotc_db');
    $mysql->exec('CREATE DATABASE rotc_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $mysql->exec('USE rotc_db');
    
    echo "2. Creating all tables...\n";
    
    // Create users table
    $mysql->exec("
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        full_name VARCHAR(255),
        role ENUM('admin', 'officer', 'cadet', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        two_factor_enabled BOOLEAN DEFAULT FALSE,
        two_factor_secret VARCHAR(32)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create cadet_profiles table
    $mysql->exec("
    CREATE TABLE cadet_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        cadet_id VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        rank VARCHAR(50),
        company VARCHAR(10),
        platoon VARCHAR(10),
        squad VARCHAR(10),
        year_level ENUM('1st Year', '2nd Year', '3rd Year', '4th Year'),
        course VARCHAR(100),
        contact_number VARCHAR(20),
        emergency_contact VARCHAR(255),
        address TEXT,
        date_of_birth DATE,
        blood_type VARCHAR(5),
        medical_conditions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create items table
    $mysql->exec("
    CREATE TABLE items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        description TEXT,
        total_quantity INT NOT NULL DEFAULT 0,
        available_quantity INT NOT NULL DEFAULT 0,
        borrowed_quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        location VARCHAR(100),
        condition_status ENUM('excellent', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good',
        qr_code VARCHAR(255) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create borrowed_items table
    $mysql->exec("
    CREATE TABLE borrowed_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        borrower_name VARCHAR(255) NOT NULL,
        borrower_contact VARCHAR(255),
        quantity_borrowed INT NOT NULL,
        borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expected_return_date DATE,
        actual_return_date TIMESTAMP NULL,
        status ENUM('borrowed', 'returned', 'overdue', 'lost') DEFAULT 'borrowed',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create rifles table
    $mysql->exec("
    CREATE TABLE rifles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(50) UNIQUE NOT NULL,
        model VARCHAR(100) NOT NULL,
        manufacturer VARCHAR(100),
        caliber VARCHAR(20),
        condition_status ENUM('excellent', 'good', 'fair', 'poor', 'out_of_service') DEFAULT 'good',
        location VARCHAR(100),
        last_maintenance DATE,
        next_maintenance DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create rifle_assignments table
    $mysql->exec("
    CREATE TABLE rifle_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rifle_id INT NOT NULL,
        cadet_id INT,
        assigned_by INT,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        return_date TIMESTAMP NULL,
        purpose VARCHAR(255),
        status ENUM('active', 'returned', 'overdue') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
        FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create rifle_logs table
    $mysql->exec("
    CREATE TABLE rifle_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rifle_id INT NOT NULL,
        action ENUM('assigned', 'returned', 'maintenance', 'inspection', 'repair', 'other') NOT NULL,
        performed_by INT,
        description TEXT,
        action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
        FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create announcements table
    $mysql->exec("
    CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        author_id INT,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        target_audience ENUM('all', 'cadets', 'officers', 'admin') DEFAULT 'all',
        is_active BOOLEAN DEFAULT TRUE,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create attendance table
    $mysql->exec("
    CREATE TABLE attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cadet_id INT NOT NULL,
        date DATE NOT NULL,
        time_in TIME,
        time_out TIME,
        status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
        notes TEXT,
        recorded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_cadet_date (cadet_id, date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create grades table
    $mysql->exec("
    CREATE TABLE grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cadet_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        grade_type ENUM('quiz', 'exam', 'project', 'assignment', 'final') NOT NULL,
        score DECIMAL(5,2) NOT NULL,
        max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        percentage DECIMAL(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
        date_recorded DATE NOT NULL,
        semester VARCHAR(20),
        academic_year VARCHAR(10),
        notes TEXT,
        recorded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "3. Importing data from SQLite...\n";
    
    // Import users
    $users = $sqlite->query('SELECT * FROM users')->fetchAll();
    $stmt = $mysql->prepare('INSERT INTO users (username, password, email, full_name, role, created_at, updated_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($users as $user) {
        $stmt->execute([
            $user['username'],
            $user['password'],
            $user['email'],
            $user['full_name'],
            $user['role'],
            $user['created_at'],
            $user['updated_at'],
            $user['is_active'] ?? 1
        ]);
    }
    echo "   - Imported " . count($users) . " users\n";
    
    // Import items
    $items = $sqlite->query('SELECT * FROM items')->fetchAll();
    $stmt = $mysql->prepare('INSERT INTO items (item_name, description, total_quantity, available_quantity, borrowed_quantity, unit, location, condition_status, qr_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $stmt->execute([
            $item['item_name'],
            $item['description'],
            $item['total_quantity'],
            $item['available_quantity'],
            $item['borrowed_quantity'],
            $item['unit'],
            $item['location'],
            $item['condition_status'],
            $item['qr_code'],
            $item['created_at'],
            $item['updated_at']
        ]);
    }
    echo "   - Imported " . count($items) . " items\n";
    
    // Import borrowed_items
    $borrowed = $sqlite->query('SELECT * FROM borrowed_items')->fetchAll();
    $stmt = $mysql->prepare('INSERT INTO borrowed_items (item_id, borrower_name, borrower_contact, quantity_borrowed, borrow_date, expected_return_date, actual_return_date, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($borrowed as $borrow) {
        $stmt->execute([
            $borrow['item_id'],
            $borrow['borrower_name'],
            $borrow['borrower_contact'],
            $borrow['quantity_borrowed'],
            $borrow['borrow_date'],
            $borrow['expected_return_date'],
            $borrow['actual_return_date'],
            $borrow['status'],
            $borrow['notes'],
            $borrow['created_at'],
            $borrow['updated_at']
        ]);
    }
    echo "   - Imported " . count($borrowed) . " borrowed items\n";
    
    echo "4. Adding sample rifle data...\n";
    
    // Insert sample rifles
    $rifles = [
        ['M16A2-001', 'M16A2', 'Colt', '5.56mm', 'good', 'Armory A1'],
        ['M16A2-002', 'M16A2', 'Colt', '5.56mm', 'excellent', 'Armory A1'],
        ['M4A1-001', 'M4A1', 'Colt', '5.56mm', 'good', 'Armory A2'],
        ['M4A1-002', 'M4A1', 'Colt', '5.56mm', 'fair', 'Armory A2'],
        ['M14-001', 'M14', 'Springfield', '7.62mm', 'good', 'Armory B1']
    ];
    
    $stmt = $mysql->prepare('INSERT INTO rifles (serial_number, model, manufacturer, caliber, condition_status, location) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rifles as $rifle) {
        $stmt->execute($rifle);
    }
    echo "   - Added " . count($rifles) . " sample rifles\n";
    
    echo "\n=== Database Restoration Complete! ===\n";
    echo "\nSummary:\n";
    echo "- Users: " . count($users) . "\n";
    echo "- Items: " . count($items) . "\n";
    echo "- Borrowed Items: " . count($borrowed) . "\n";
    echo "- Rifles: " . count($rifles) . "\n";
    echo "- All tables created with proper relationships\n";
    echo "\nThe rotc_db database has been fully restored!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>