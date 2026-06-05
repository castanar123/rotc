<?php
session_start();
require_once 'includes/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Cadet Attendance Debug Script</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; } .error { color: red; } .success { color: green; } .warning { color: orange; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

// Check session
echo "<div class='section'>";
echo "<h2>1. Session Information</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='success'>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p class='success'>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    echo "<p class='success'>Username: " . ($_SESSION['username'] ?? 'Not set') . "</p>";
} else {
    echo "<p class='warning'>No user session found! Running in debug mode.</p>";
    echo "<p>For full testing, please log in first. Continuing with general database checks...</p>";
    // Set a test user ID for debugging purposes
    $debug_user_id = 11; // Use a known cadet ID from the database
    echo "<p class='warning'>Using debug user ID: $debug_user_id for testing queries.</p>";
}
echo "</div>";

try {
    // Test database connection
    echo "<div class='section'>";
    echo "<h2>2. Database Connection Test</h2>";
    if (isset($pdo)) {
        echo "<p class='success'>Database connection successful!</p>";
        echo "<p>Database: " . DB_NAME . "</p>";
        echo "<p>Server: " . DB_SERVER . "</p>";
    } else {
        throw new Exception('PDO connection not available');
    }
    echo "</div>";

    // Check attendance table structure
    echo "<div class='section'>";
    echo "<h2>3. Attendance Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
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
    echo "</div>";

    // Check if attendance_logs table exists
    echo "<div class='section'>";
    echo "<h2>4. Attendance Logs Table Check</h2>";
    try {
        $stmt = $pdo->query("DESCRIBE attendance_logs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p class='success'>attendance_logs table exists!</p>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
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
    } catch (PDOException $e) {
        echo "<p class='warning'>attendance_logs table does not exist. Error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Count total attendance records
    echo "<div class='section'>";
    echo "<h2>5. Total Attendance Records</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Total attendance records: <strong>$total</strong></p>";
    
    // Check attendance_logs if it exists
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance_logs");
        $total_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>Total attendance_logs records: <strong>$total_logs</strong></p>";
    } catch (PDOException $e) {
        echo "<p class='warning'>Could not count attendance_logs: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Show sample attendance data
    echo "<div class='section'>";
    echo "<h2>6. Sample Attendance Data</h2>";
    $stmt = $pdo->query("SELECT * FROM attendance ORDER BY created_at DESC LIMIT 10");
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($attendance_data) > 0) {
        echo "<table>";
        echo "<tr>";
        foreach (array_keys($attendance_data[0]) as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        foreach ($attendance_data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No attendance records found!</p>";
    }
    echo "</div>";

    // Test cadet-specific attendance query
    $test_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($debug_user_id) ? $debug_user_id : 11);
    
    if (isset($_SESSION['user_id']) || isset($debug_user_id)) {
        echo "<div class='section'>";
        echo "<h2>7. Current User's Attendance Records</h2>";
        $user_id = $test_user_id;
        
        // Test the exact query from cadet_attendance.php
        $query = "SELECT a.*, cp.first_name, cp.last_name, cp.student_id 
                  FROM attendance a 
                  JOIN cadet_profiles cp ON a.cadet_id = cp.id 
                  WHERE cp.user_id = ? 
                  ORDER BY a.log_date DESC";
        
        echo "<p><strong>Query being executed:</strong></p>";
        echo "<pre>" . htmlspecialchars($query) . "</pre>";
        echo "<p><strong>User ID:</strong> $user_id</p>";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $user_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Records found: <strong>" . count($user_attendance) . "</strong></p>";
        
        if (count($user_attendance) > 0) {
            echo "<table>";
            echo "<tr>";
            foreach (array_keys($user_attendance[0]) as $header) {
                echo "<th>$header</th>";
            }
            echo "</tr>";
            foreach ($user_attendance as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>No attendance records found for current user!</p>";
            
            // Check if user exists in users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                echo "<p class='success'>User exists in database:</p>";
                echo "<pre>" . print_r($user_data, true) . "</pre>";
            } else {
                echo "<p class='error'>User not found in database!</p>";
            }
        }
        echo "</div>";
        
        // Test alternative attendance_logs query if table exists
        echo "<div class='section'>";
        echo "<h2>8. Alternative Attendance Logs Query</h2>";
        try {
            $query_logs = "SELECT al.*, cp.first_name, cp.last_name, cp.student_id 
                          FROM attendance_logs al 
                          JOIN cadet_profiles cp ON al.cadet_profile_id = cp.id 
                          WHERE al.cadet_profile_id = ? 
                          ORDER BY al.event_date DESC";
            
            echo "<p><strong>Query being executed:</strong></p>";
            echo "<pre>" . htmlspecialchars($query_logs) . "</pre>";
            echo "<p><strong>Test User ID:</strong> $user_id</p>";
            
            $stmt = $pdo->prepare($query_logs);
            $stmt->execute([$user_id]);
            $user_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p>Records found in attendance_logs: <strong>" . count($user_logs) . "</strong></p>";
            
            if (count($user_logs) > 0) {
                echo "<table>";
                echo "<tr>";
                foreach (array_keys($user_logs[0]) as $header) {
                    echo "<th>$header</th>";
                }
                echo "</tr>";
                foreach (array_slice($user_logs, 0, 5) as $row) { // Show only first 5 records
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='warning'>No records found in attendance_logs for current user!</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='warning'>Could not query attendance_logs: " . $e->getMessage() . "</p>";
        }
        echo "</div>";
    }
    
    // Check cadet_profiles table structure
    echo "<div class='section'>";
    echo "<h2>9. Cadet Profiles Table Structure</h2>";
    try {
        $stmt = $pdo->query("DESCRIBE cadet_profiles");
        $cadet_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($cadet_columns as $column) {
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
    } catch (PDOException $e) {
        echo "<p class='error'>Could not describe cadet_profiles table: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Check users table structure
    echo "<div class='section'>";
    echo "<h2>10. Users Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE users");
    $user_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($user_columns as $column) {
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
    echo "</div>";
    
    // Test JOIN query
    echo "<div class='section'>";
    echo "<h2>11. JOIN Query Test</h2>";
    $join_query = "SELECT a.id, a.log_date, a.log_time, a.status, 
                          cp.first_name, cp.last_name, u.username, u.role 
                   FROM attendance a 
                   JOIN cadet_profiles cp ON a.cadet_id = cp.id 
                   JOIN users u ON cp.user_id = u.id 
                   LIMIT 5";
    
    echo "<p><strong>Testing JOIN query:</strong></p>";
    echo "<pre>" . htmlspecialchars($join_query) . "</pre>";
    
    try {
        $stmt = $pdo->query($join_query);
        $join_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($join_results) > 0) {
            echo "<p class='success'>JOIN query successful! Found " . count($join_results) . " records.</p>";
            echo "<table>";
            echo "<tr>";
            foreach (array_keys($join_results[0]) as $header) {
                echo "<th>$header</th>";
            }
            echo "</tr>";
            foreach ($join_results as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>JOIN query returned no results!</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>JOIN query failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='section'>";
    echo "<h2>Database Error</h2>";
    echo "<p class='error'>Connection failed: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>12. Recommendations</h2>";
echo "<ul>";
echo "<li>If no attendance records are found, check if attendance data is being inserted correctly</li>";
echo "<li>If JOIN queries fail, verify that user_id foreign key relationships are correct</li>";
echo "<li>If session is not set, make sure user is logged in</li>";
echo "<li>Check cadet_attendance.php for any PHP errors or incorrect variable names</li>";
echo "<li>Verify that the attendance table has the correct structure and data</li>";
echo "</ul>";
echo "</div>";

echo "<p><a href='cadet_attendance.php'>Go back to Cadet Attendance</a></p>";
?>