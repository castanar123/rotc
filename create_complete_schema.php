<?php
require_once 'includes/db.php';

echo "<h1>Creating Complete ROTC Database Schema</h1>";
echo "<p>Setting up all required tables...</p>";

try {
    // Create cadet_profiles table
    $sql = "
    CREATE TABLE IF NOT EXISTS cadet_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        student_id VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        gender ENUM('Male', 'Female') NOT NULL,
        email VARCHAR(100),
        address TEXT,
        contact_number VARCHAR(20),
        course VARCHAR(100),
        section VARCHAR(50),
        religion VARCHAR(50),
        birthdate DATE,
        place_of_birth VARCHAR(100),
        height VARCHAR(20),
        weight VARCHAR(20),
        skin_color VARCHAR(50),
        blood_type VARCHAR(10),
        father VARCHAR(100),
        father_occupation VARCHAR(100),
        mother VARCHAR(100),
        mother_occupation VARCHAR(100),
        guardian VARCHAR(100),
        guardian_contact VARCHAR(20),
        guardian_relationship VARCHAR(50),
        guardian_address TEXT,
        platoon VARCHAR(50),
        status ENUM('Active', 'Inactive', 'Graduated', 'Dropped') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<p>✅ cadet_profiles table created</p>";
    
    // Create rifles table
    $sql = "
    CREATE TABLE IF NOT EXISTS rifles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(50) UNIQUE NOT NULL,
        model VARCHAR(50) NOT NULL,
        manufacturer VARCHAR(50),
        caliber VARCHAR(20),
        condition_status ENUM('excellent', 'good', 'fair', 'poor', 'needs_repair') DEFAULT 'good',
        location VARCHAR(100),
        last_maintenance DATE,
        next_maintenance DATE,
        status ENUM('available', 'assigned', 'maintenance', 'retired') DEFAULT 'available',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "<p>✅ rifles table created</p>";
    
    // Create rifle_assignments table
    $sql = "
    CREATE TABLE IF NOT EXISTS rifle_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rifle_id INT NOT NULL,
        cadet_profile_id INT NOT NULL,
        assigned_date DATE NOT NULL,
        return_date DATE,
        assigned_by INT NOT NULL,
        returned_by INT,
        course VARCHAR(100),
        purpose TEXT,
        status ENUM('active', 'returned', 'overdue') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
        FOREIGN KEY (cadet_profile_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id),
        FOREIGN KEY (returned_by) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    echo "<p>✅ rifle_assignments table created</p>";
    
    // Create items table
    $sql = "
    CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(100) NOT NULL,
        description TEXT,
        total_quantity INT NOT NULL DEFAULT 0,
        available_quantity INT NOT NULL DEFAULT 0,
        borrowed_quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        location VARCHAR(100),
        condition_status ENUM('excellent', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good',
        minimum_stock INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "<p>✅ items table created</p>";
    
    // Create borrowed_items table
    $sql = "
    CREATE TABLE IF NOT EXISTS borrowed_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        borrower_name VARCHAR(255) NOT NULL,
        borrower_contact VARCHAR(20),
        quantity_borrowed INT NOT NULL,
        borrow_date DATE NOT NULL,
        expected_return_date DATE,
        actual_return_date DATE,
        status ENUM('borrowed', 'returned', 'overdue', 'lost') DEFAULT 'borrowed',
        notes TEXT,
        approved_by INT,
        returned_to INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id),
        FOREIGN KEY (returned_to) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    echo "<p>✅ borrowed_items table created</p>";
    
    // Create attendance table
    $sql = "
    CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cadet_id INT NOT NULL,
        date DATE NOT NULL,
        time_in TIME,
        time_out TIME,
        status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
        notes TEXT,
        recorded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id),
        UNIQUE KEY unique_cadet_date (cadet_id, date)
    )";
    $pdo->exec($sql);
    echo "<p>✅ attendance table created</p>";
    
    // Create grades table
    $sql = "
    CREATE TABLE IF NOT EXISTS grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cadet_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        grade_type ENUM('quiz', 'exam', 'project', 'assignment', 'final') NOT NULL,
        score DECIMAL(5,2) NOT NULL,
        max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        percentage DECIMAL(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
        date_recorded DATE NOT NULL,
        recorded_by INT NOT NULL,
        semester VARCHAR(20),
        academic_year VARCHAR(10),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    echo "<p>✅ grades table created</p>";
    
    // Create announcements table
    $sql = "
    CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        author_id INT NOT NULL,
        target_audience ENUM('all', 'cadets', 'officers', 'admin') DEFAULT 'all',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
        publish_date DATETIME,
        expire_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    echo "<p>✅ announcements table created</p>";
    
    // Create rifle_logs table
    $sql = "
    CREATE TABLE IF NOT EXISTS rifle_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rifle_id INT NOT NULL,
        action ENUM('assigned', 'returned', 'maintenance', 'inspection', 'repair', 'retired') NOT NULL,
        performed_by INT NOT NULL,
        cadet_id INT,
        action_date DATETIME NOT NULL,
        notes TEXT,
        condition_before VARCHAR(50),
        condition_after VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
        FOREIGN KEY (performed_by) REFERENCES users(id),
        FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id)
    )";
    $pdo->exec($sql);
    echo "<p>✅ rifle_logs table created</p>";
    
    echo "<h2>✅ DATABASE SCHEMA CREATION COMPLETE!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>Tables Created Successfully:</h3>";
    echo "<ul>";
    echo "<li>✅ cadet_profiles - Student information and details</li>";
    echo "<li>✅ rifles - Rifle inventory and specifications</li>";
    echo "<li>✅ rifle_assignments - Rifle assignment tracking</li>";
    echo "<li>✅ items - General inventory items</li>";
    echo "<li>✅ borrowed_items - Item borrowing records</li>";
    echo "<li>✅ attendance - Daily attendance tracking</li>";
    echo "<li>✅ grades - Academic performance records</li>";
    echo "<li>✅ announcements - System announcements</li>";
    echo "<li>✅ rifle_logs - Rifle activity logs</li>";
    echo "</ul>";
    echo "<p><strong>Ready for data restoration!</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 20px; border-radius: 8px;'>";
    echo "<h2>❌ Error Creating Schema</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
    echo "</div>";
}
?>