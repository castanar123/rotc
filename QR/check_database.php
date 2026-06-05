<?php
/**
 * Database verification script
 * Checks if the database and tables exist and are properly configured
 */

require_once 'db.php';

echo "<h1>Database Verification Report</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";

try {
    // Check database connection
    echo "<h2>Database Connection</h2>";
    if ($pdo) {
        echo "<p class='success'>✓ Database connection successful</p>";
        
        // Get database name
        $stmt = $pdo->query("SELECT DATABASE() as db_name");
        $db_info = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Connected to database: <strong>" . $db_info['db_name'] . "</strong></p>";
    } else {
        echo "<p class='error'>✗ Database connection failed</p>";
        exit;
    }
    
    // Check if tables exist
    echo "<h2>Table Structure</h2>";
    $tables = ['students', 'attendance', 'training_days', 'scanner_sessions'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Table: $table</h3>";
            echo "<p class='success'>✓ Table exists</p>";
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>" . $column['Field'] . "</td>";
                echo "<td>" . $column['Type'] . "</td>";
                echo "<td>" . $column['Null'] . "</td>";
                echo "<td>" . $column['Key'] . "</td>";
                echo "<td>" . $column['Default'] . "</td>";
                echo "<td>" . $column['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check record count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p>Records in table: <strong>" . $count['count'] . "</strong></p>";
            
        } catch (PDOException $e) {
            echo "<h3>Table: $table</h3>";
            echo "<p class='error'>✗ Table does not exist or error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check recent attendance records
    echo "<h2>Recent Attendance Records</h2>";
    try {
        $stmt = $pdo->query("SELECT a.*, s.name FROM attendance a LEFT JOIN students s ON a.student_id = s.student_id ORDER BY a.timestamp DESC LIMIT 10");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($records) > 0) {
            echo "<p class='success'>✓ Found " . count($records) . " recent attendance records</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Student ID</th><th>Name</th><th>TD</th><th>Semester</th><th>Timestamp</th><th>Status</th></tr>";
            
            foreach ($records as $record) {
                echo "<tr>";
                echo "<td>" . $record['id'] . "</td>";
                echo "<td>" . $record['student_id'] . "</td>";
                echo "<td>" . ($record['name'] ?? 'Unknown') . "</td>";
                echo "<td>" . $record['td'] . "</td>";
                echo "<td>" . $record['semester'] . "</td>";
                echo "<td>" . $record['timestamp'] . "</td>";
                echo "<td>" . $record['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠ No attendance records found</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error checking attendance records: " . $e->getMessage() . "</p>";
    }
    
    // Check students table
    echo "<h2>Students Table</h2>";
    try {
        $stmt = $pdo->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 10");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($students) > 0) {
            echo "<p class='success'>✓ Found " . count($students) . " students</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Student ID</th><th>Name</th><th>Gender</th><th>Platoon</th><th>Created At</th></tr>";
            
            foreach ($students as $student) {
                echo "<tr>";
                echo "<td>" . $student['id'] . "</td>";
                echo "<td>" . $student['student_id'] . "</td>";
                echo "<td>" . $student['name'] . "</td>";
                echo "<td>" . ($student['gender'] ?? 'N/A') . "</td>";
                echo "<td>" . ($student['platoon'] ?? 'N/A') . "</td>";
                echo "<td>" . $student['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠ No students found</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error checking students: " . $e->getMessage() . "</p>";
    }
    
    // Test database write operation
    echo "<h2>Database Write Test</h2>";
    try {
        // Try to insert a test record
        $test_student_id = 'TEST_' . time();
        $stmt = $pdo->prepare("INSERT INTO students (student_id, name) VALUES (?, ?)");
        $result = $stmt->execute([$test_student_id, 'Test Student']);
        
        if ($result) {
            echo "<p class='success'>✓ Database write test successful</p>";
            
            // Clean up test record
            $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->execute([$test_student_id]);
            echo "<p>Test record cleaned up</p>";
        } else {
            echo "<p class='error'>✗ Database write test failed</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Database write test error: " . $e->getMessage() . "</p>";
    }
    
    // Check database permissions
    echo "<h2>Database Permissions</h2>";
    try {
        $stmt = $pdo->query("SHOW GRANTS");
        $grants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>✓ Database permissions check successful</p>";
        echo "<table>";
        echo "<tr><th>Grants</th></tr>";
        
        foreach ($grants as $grant) {
            foreach ($grant as $permission) {
                echo "<tr><td>" . htmlspecialchars($permission) . "</td></tr>";
            }
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p class='warning'>⚠ Could not check database permissions: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ General error: " . $e->getMessage() . "</p>";
}

echo "<h2>Recommendations</h2>";
echo "<ul>";
echo "<li>If tables are missing, run the database.sql script to create them</li>";
echo "<li>If attendance records are not being saved, check the record_attendance.php file</li>";
echo "<li>Ensure XAMPP MySQL service is running</li>";
echo "<li>Check that the database name in db.php matches your actual database</li>";
echo "</ul>";

echo "<p><a href='home.html'>← Back to Home</a></p>";
?>