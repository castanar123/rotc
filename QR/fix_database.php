<?php
/**
 * Database fix script
 * Updates the database structure to fix attendance recording issues
 */

require_once 'db.php';

echo "<h1>Database Fix Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>";

try {
    echo "<h2>Fixing Database Structure</h2>";
    
    // Add missing columns to students table
    echo "<p>Adding gender and platoon columns to students table...</p>";
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS gender ENUM('male', 'female') NOT NULL DEFAULT 'male'");
        echo "<p class='success'>✓ Gender column added/verified</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p class='warning'>⚠ Gender column already exists</p>";
        } else {
            echo "<p class='error'>✗ Error adding gender column: " . $e->getMessage() . "</p>";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS platoon VARCHAR(20) NOT NULL DEFAULT 'Alpha'");
        echo "<p class='success'>✓ Platoon column added/verified</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p class='warning'>⚠ Platoon column already exists</p>";
        } else {
            echo "<p class='error'>✗ Error adding platoon column: " . $e->getMessage() . "</p>";
        }
    }
    
    // Create platoons table
    echo "<p>Creating platoons table...</p>";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS platoons (
                platoon_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(20) NOT NULL UNIQUE
            )
        ");
        echo "<p class='success'>✓ Platoons table created/verified</p>";
        
        // Insert default platoons
        $platoons = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'];
        foreach ($platoons as $platoon) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO platoons (name) VALUES (?)");
                $stmt->execute([$platoon]);
            } catch (PDOException $e) {
                // Ignore duplicate entries
            }
        }
        echo "<p class='success'>✓ Default platoons inserted</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error creating platoons table: " . $e->getMessage() . "</p>";
    }
    
    // Create scanner_sessions table
    echo "<p>Creating scanner_sessions table...</p>";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS scanner_sessions (
                session_id VARCHAR(64) PRIMARY KEY,
                td INT NOT NULL,
                semester INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                device_info VARCHAR(255),
                ip_address VARCHAR(45)
            )
        ");
        echo "<p class='success'>✓ Scanner sessions table created/verified</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error creating scanner_sessions table: " . $e->getMessage() . "</p>";
    }
    
    // Update existing students with default values
    echo "<p>Updating existing students with default gender and platoon...</p>";
    try {
        $stmt = $pdo->prepare("UPDATE students SET gender = 'male', platoon = 'Alpha' WHERE gender IS NULL OR platoon IS NULL");
        $stmt->execute();
        $updated = $stmt->rowCount();
        echo "<p class='success'>✓ Updated $updated student records with default values</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error updating students: " . $e->getMessage() . "</p>";
    }
    
    // Test attendance insertion
    echo "<h2>Testing Attendance Recording</h2>";
    try {
        // Test with a sample record
        $test_student_id = 'TEST_' . time();
        $test_name = 'Test Student';
        $test_td = 1;
        $test_semester = 1;
        
        // Insert test student
        $stmt = $pdo->prepare("INSERT INTO students (student_id, name, gender, platoon) VALUES (?, ?, 'male', 'Alpha')");
        $stmt->execute([$test_student_id, $test_name]);
        echo "<p class='success'>✓ Test student created</p>";
        
        // Insert test attendance
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, td, semester) VALUES (?, ?, ?)");
        $stmt->execute([$test_student_id, $test_td, $test_semester]);
        echo "<p class='success'>✓ Test attendance record created</p>";
        
        // Verify the record was inserted
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ?");
        $stmt->execute([$test_student_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            echo "<p class='success'>✓ Attendance record verified in database</p>";
            echo "<p>Record details: ID=" . $record['id'] . ", TD=" . $record['td'] . ", Semester=" . $record['semester'] . ", Timestamp=" . $record['timestamp'] . "</p>";
        } else {
            echo "<p class='error'>✗ Attendance record not found after insertion</p>";
        }
        
        // Clean up test records
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id = ?");
        $stmt->execute([$test_student_id]);
        
        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->execute([$test_student_id]);
        echo "<p>Test records cleaned up</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Attendance test failed: " . $e->getMessage() . "</p>";
    }
    
    // Check record_attendance.php functionality
    echo "<h2>Checking record_attendance.php</h2>";
    if (file_exists('record_attendance.php')) {
        echo "<p class='success'>✓ record_attendance.php file exists</p>";
        
        // Check if the file is accessible
        $content = file_get_contents('record_attendance.php');
        if (strpos($content, 'INSERT INTO attendance') !== false) {
            echo "<p class='success'>✓ record_attendance.php contains attendance insertion code</p>";
        } else {
            echo "<p class='warning'>⚠ record_attendance.php may not have proper insertion code</p>";
        }
    } else {
        echo "<p class='error'>✗ record_attendance.php file not found</p>";
    }
    
    echo "<h2>Database Fix Complete</h2>";
    echo "<p class='success'>✓ Database structure has been updated and tested</p>";
    echo "<p>You can now try scanning QR codes to test attendance recording.</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ General error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='check_database.php'>Run Database Verification</a> | <a href='home.html'>← Back to Home</a></p>";
?>