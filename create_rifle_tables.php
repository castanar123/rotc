<?php
/**
 * Script to create rifle management tables
 * This script will execute the SQL commands to create the required tables
 */

require_once 'includes/db.php';

try {
    echo "<h2>Creating Rifle Management Tables</h2>";
    
    // Create rifles table
    echo "<h3>Creating rifles table...</h3>";
    $sql_rifles = "
    CREATE TABLE IF NOT EXISTS rifles (
        id INT(11) NOT NULL AUTO_INCREMENT,
        rifle_number VARCHAR(50) NOT NULL UNIQUE,
        qr_code_path VARCHAR(255) DEFAULT NULL,
        status ENUM('available', 'borrowed', 'maintenance', 'lost', 'damaged') DEFAULT 'available',
        condition_notes TEXT DEFAULT NULL,
        last_maintenance DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_rifle_number (rifle_number),
        INDEX idx_rifle_status (status),
        INDEX idx_rifle_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($link->query($sql_rifles)) {
        echo "<p style='color: green;'>✓ Rifles table created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating rifles table: " . $link->error . "</p>";
    }
    
    // Create rifle_assignments table
    echo "<h3>Creating rifle_assignments table...</h3>";
    $sql_assignments = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($link->query($sql_assignments)) {
        echo "<p style='color: green;'>✓ Rifle assignments table created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating rifle_assignments table: " . $link->error . "</p>";
    }
    
    // Create rifle_logs table
    echo "<h3>Creating rifle_logs table...</h3>";
    $sql_logs = "
    CREATE TABLE IF NOT EXISTS rifle_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        rifle_id INT(11) NOT NULL,
        cadet_id INT(11) NULL DEFAULT NULL,
        action ENUM('created', 'borrowed', 'returned', 'maintenance', 'lost', 'damaged', 'repaired') NOT NULL,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($link->query($sql_logs)) {
        echo "<p style='color: green;'>✓ Rifle logs table created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating rifle_logs table: " . $link->error . "</p>";
    }
    
    // Insert sample data
    echo "<h3>Inserting sample data...</h3>";
    $sample_rifles = [
        "('R001', 'available', 'New rifle - excellent condition')",
        "('R002', 'available', 'Good condition - minor wear')",
        "('R003', 'maintenance', 'Requires cleaning and inspection')",
        "('R004', 'available', 'Recently serviced - excellent condition')",
        "('R005', 'available', 'Good condition - ready for use')",
        "('R006', 'available', 'Excellent condition - new stock')",
        "('R007', 'available', 'Good condition - ready for training')",
        "('R008', 'maintenance', 'Scheduled maintenance required')",
        "('R009', 'available', 'Good condition - recently cleaned')",
        "('R010', 'available', 'Excellent condition - inspection passed')"
    ];
    
    $sql_insert = "INSERT IGNORE INTO rifles (rifle_number, status, condition_notes) VALUES " . implode(', ', $sample_rifles);
    
    if ($link->query($sql_insert)) {
        $affected = $link->affected_rows;
        echo "<p style='color: green;'>✓ Inserted $affected sample rifles</p>";
    } else {
        echo "<p style='color: red;'>✗ Error inserting sample rifles: " . $link->error . "</p>";
    }
    
    // Create logs for sample rifles
    $sql_logs_insert = "
    INSERT IGNORE INTO rifle_logs (rifle_id, action, performed_by, details) 
    SELECT id, 'created', 1, CONCAT('Rifle ', rifle_number, ' added to inventory')
    FROM rifles";
    
    if ($link->query($sql_logs_insert)) {
        $affected = $link->affected_rows;
        echo "<p style='color: green;'>✓ Created $affected log entries</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating log entries: " . $link->error . "</p>";
    }
    
    // Verify tables exist
    echo "<h3>Verifying Tables:</h3>";
    $tables = ['rifles', 'rifle_assignments', 'rifle_logs'];
    
    foreach ($tables as $table) {
        $result = $link->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            
            // Count records
            $countResult = $link->query("SELECT COUNT(*) as count FROM $table");
            if ($countResult) {
                $count = $countResult->fetch_assoc()['count'];
                echo "<p style='color: blue;'>  → Records in '$table': $count</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
        }
    }
    
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✓ Rifle management database setup completed!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$link->close();
?>