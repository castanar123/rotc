<?php
require_once 'includes/db.php';

echo "=== DATABASE REPAIR SCRIPT ===\n";
echo "Environment: " . (isProductionEnvironment() ? 'Production (SQLite)' : 'Local (MySQL)') . "\n";
echo "Database connection: " . (isset($pdo) ? 'SUCCESS' : 'FAILED') . "\n\n";

if (!isset($pdo)) {
    die("Failed to connect to database\n");
}

try {
    // Ensure users table has status column
    echo "=== CHECKING USERS TABLE ===\n";
    if (isProductionEnvironment()) {
        // SQLite
        $stmt = $pdo->query("PRAGMA table_info(users);");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasStatus = false;
        foreach ($columns as $col) {
            if (strtolower($col['name']) === 'status') {
                $hasStatus = true;
                break;
            }
        }
        
        if (!$hasStatus) {
            echo "Adding status column to users table...\n";
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active';");
            echo "Status column added to users table\n";
        } else {
            echo "Users table already has status column\n";
        }
    } else {
        // MySQL
        $stmt = $pdo->query("DESCRIBE users;");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasStatus = false;
        foreach ($columns as $col) {
            if (strtolower($col['Field']) === 'status') {
                $hasStatus = true;
                break;
            }
        }
        
        if (!$hasStatus) {
            echo "Adding status column to users table...\n";
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active';");
            echo "Status column added to users table\n";
        } else {
            echo "Users table already has status column\n";
        }
    }
    
    // Create missing_id_requests table if it doesn't exist
    echo "\n=== CHECKING MISSING_ID_REQUESTS TABLE ===\n";
    try {
        $stmt = $pdo->query("SELECT 1 FROM missing_id_requests LIMIT 1;");
        echo "missing_id_requests table exists\n";
    } catch (PDOException $e) {
        echo "Creating missing_id_requests table...\n";
        if (isProductionEnvironment()) {
            // SQLite
            $sql = "CREATE TABLE missing_id_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cadet_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'active',
                request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                expiry_date DATETIME,
                approved_by INTEGER,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            // MySQL
            $sql = "CREATE TABLE missing_id_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cadet_id INT NOT NULL,
                reason TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'active',
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expiry_date TIMESTAMP NULL,
                approved_by INT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            );";
        }
        $pdo->exec($sql);
        echo "missing_id_requests table created\n";
    }
    
    // Create reports table if it doesn't exist
    echo "\n=== CHECKING REPORTS TABLE ===\n";
    try {
        $stmt = $pdo->query("SELECT 1 FROM reports LIMIT 1;");
        echo "reports table exists\n";
    } catch (PDOException $e) {
        echo "Creating reports table...\n";
        if (isProductionEnvironment()) {
            // SQLite
            $sql = "CREATE TABLE reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                content TEXT,
                status VARCHAR(20) DEFAULT 'draft',
                generated_by INTEGER NOT NULL,
                date_from DATE,
                date_to DATE,
                filters TEXT,
                file_path VARCHAR(500),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            // MySQL
            $sql = "CREATE TABLE reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                content TEXT,
                status VARCHAR(20) DEFAULT 'draft',
                generated_by INT NOT NULL,
                date_from DATE,
                date_to DATE,
                filters TEXT,
                file_path VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (generated_by) REFERENCES users(id)
            );";
        }
        $pdo->exec($sql);
        echo "reports table created\n";
    }
    
    // Ensure attendance table exists and has proper structure
    echo "\n=== CHECKING ATTENDANCE TABLE ===\n";
    try {
        $stmt = $pdo->query("SELECT 1 FROM attendance LIMIT 1;");
        echo "attendance table exists\n";
        
        // Check if it has status column
        if (isProductionEnvironment()) {
            $stmt = $pdo->query("PRAGMA table_info(attendance);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasStatus = false;
            foreach ($columns as $col) {
                if (strtolower($col['name']) === 'status') {
                    $hasStatus = true;
                    break;
                }
            }
        } else {
            $stmt = $pdo->query("DESCRIBE attendance;");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasStatus = false;
            foreach ($columns as $col) {
                if (strtolower($col['Field']) === 'status') {
                    $hasStatus = true;
                    break;
                }
            }
        }
        
        if (!$hasStatus) {
            echo "Adding status column to attendance table...\n";
            $pdo->exec("ALTER TABLE attendance ADD COLUMN status VARCHAR(20) DEFAULT 'present';");
            echo "Status column added to attendance table\n";
        } else {
            echo "Attendance table already has status column\n";
        }
        
    } catch (PDOException $e) {
        echo "Creating attendance table...\n";
        if (isProductionEnvironment()) {
            // SQLite
            $sql = "CREATE TABLE attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cadet_id INTEGER NOT NULL,
                scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'present',
                location VARCHAR(100),
                scanner_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            // MySQL
            $sql = "CREATE TABLE attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cadet_id INT NOT NULL,
                scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'present',
                location VARCHAR(100),
                scanner_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cadet_id) REFERENCES cadet_profiles(id)
            );";
        }
        $pdo->exec($sql);
        echo "attendance table created\n";
    }
    
    // Test the problematic queries
    echo "\n=== TESTING PROBLEMATIC QUERIES ===\n";
    
    // Test QR/home.php query
    echo "Testing QR/home.php query...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
        $result = $stmt->fetch();
        echo "QR/home.php query: SUCCESS (" . $result['total'] . " pending users)\n";
    } catch (PDOException $e) {
        echo "QR/home.php query: ERROR - " . $e->getMessage() . "\n";
    }
    
    // Test QR/dashboard.php query
    echo "Testing QR/dashboard.php query...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
        $result = $stmt->fetch();
        echo "QR/dashboard.php query: SUCCESS (" . $result['total'] . " pending users)\n";
    } catch (PDOException $e) {
        echo "QR/dashboard.php query: ERROR - " . $e->getMessage() . "\n";
    }
    
    // Test admin/missing_ids.php query
    echo "Testing admin/missing_ids.php query...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM missing_id_requests WHERE status = 'active' AND expiry_date > NOW()");
        $result = $stmt->fetch();
        echo "admin/missing_ids.php query: SUCCESS (" . $result['total'] . " active requests)\n";
    } catch (PDOException $e) {
        echo "admin/missing_ids.php query: ERROR - " . $e->getMessage() . "\n";
    }
    
    // Test reports/view_report.php query
    echo "Testing reports/view_report.php query...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
        $result = $stmt->fetch();
        echo "reports/view_report.php query: SUCCESS (" . $result['total'] . " pending users)\n";
    } catch (PDOException $e) {
        echo "reports/view_report.php query: ERROR - " . $e->getMessage() . "\n";
    }
    
    echo "\n=== DATABASE REPAIR COMPLETED ===\n";
    
} catch (PDOException $e) {
    echo "Database repair error: " . $e->getMessage() . "\n";
}
?>