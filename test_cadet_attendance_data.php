<?php
// Test file to verify cadet attendance data fetching and display
session_start();

// Include database connection
require_once 'includes/db.php';

echo "<h1>Cadet Attendance Data Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; } .success { color: green; } .error { color: red; } .info { color: blue; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h2>1. Database Connection Test</h2>";
try {
    if (isset($pdo)) {
        echo "<p class='success'>✓ Database connection successful</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed - PDO not available</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection error: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Test 2: Session Information
echo "<div class='section'>";
echo "<h2>2. Session Information</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>User ID:</strong> " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "</p>";
echo "<p><strong>Role:</strong> " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "</p>";
echo "<p><strong>Username:</strong> " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'Not set') . "</p>";
echo "</div>";

// Test 3: Users Table Data
echo "<div class='section'>";
echo "<h2>3. Users Table Data</h2>";
try {
    $stmt = $pdo->query("SELECT user_id, username, role FROM users LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($users) {
        echo "<p class='success'>✓ Found " . count($users) . " users</p>";
        echo "<table><tr><th>User ID</th><th>Username</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr><td>{$user['user_id']}</td><td>{$user['username']}</td><td>{$user['role']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>✗ No users found</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error fetching users: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Cadet Profiles Table
echo "<div class='section'>";
echo "<h2>4. Cadet Profiles Table</h2>";
try {
    $stmt = $pdo->query("SELECT cadet_id, user_id, student_id FROM cadet_profiles LIMIT 10");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($profiles) {
        echo "<p class='success'>✓ Found " . count($profiles) . " cadet profiles</p>";
        echo "<table><tr><th>Cadet ID</th><th>User ID</th><th>Student ID</th></tr>";
        foreach ($profiles as $profile) {
            echo "<tr><td>{$profile['cadet_id']}</td><td>{$profile['user_id']}</td><td>{$profile['student_id']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ No cadet profiles found - will use fallback logic</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error fetching cadet profiles: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 5: Attendance Table Structure and Data
echo "<div class='section'>";
echo "<h2>5. Attendance Table</h2>";
try {
    // Check if attendance table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'attendance'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ Attendance table exists</p>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE attendance");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Table Structure:</h3>";
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM attendance LIMIT 10");
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($attendance) {
            echo "<h3>Sample Data (" . count($attendance) . " records):</h3>";
            echo "<table><tr>";
            foreach (array_keys($attendance[0]) as $header) {
                echo "<th>$header</th>";
            }
            echo "</tr>";
            foreach ($attendance as $record) {
                echo "<tr>";
                foreach ($record as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='info'>ℹ No attendance records found</p>";
        }
    } else {
        echo "<p class='error'>✗ Attendance table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error checking attendance table: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 6: Attendance Logs Table
echo "<div class='section'>";
echo "<h2>6. Attendance Logs Table</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ Attendance logs table exists</p>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE attendance_logs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Table Structure:</h3>";
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM attendance_logs LIMIT 10");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($logs) {
            echo "<h3>Sample Data (" . count($logs) . " records):</h3>";
            echo "<table><tr>";
            foreach (array_keys($logs[0]) as $header) {
                echo "<th>$header</th>";
            }
            echo "</tr>";
            foreach ($logs as $record) {
                echo "<tr>";
                foreach ($record as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='info'>ℹ No attendance log records found</p>";
        }
    } else {
        echo "<p class='info'>ℹ Attendance logs table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error checking attendance logs table: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 7: Simulate Cadet Data Fetching Logic
echo "<div class='section'>";
echo "<h2>7. Cadet Data Fetching Simulation</h2>";

// Simulate different user scenarios
$test_users = [11, 1, 2]; // Test with different user IDs

foreach ($test_users as $test_user_id) {
    echo "<h3>Testing User ID: $test_user_id</h3>";
    
    try {
        // Get user info
        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE user_id = ?");
        $stmt->execute([$test_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<p><strong>User:</strong> {$user['username']} (Role: {$user['role']})</p>";
            
            // Try to get cadet_id from cadet_profiles
            $stmt = $pdo->prepare("SELECT cadet_id FROM cadet_profiles WHERE user_id = ?");
            $stmt->execute([$test_user_id]);
            $cadet_profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $cadet_id = $cadet_profile ? $cadet_profile['cadet_id'] : $test_user_id;
            echo "<p><strong>Cadet ID:</strong> $cadet_id " . ($cadet_profile ? '(from cadet_profiles)' : '(fallback to user_id)') . "</p>";
            
            // Test attendance query
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
            $stmt->execute([$cadet_id]);
            $attendance_count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p><strong>Attendance Records:</strong> {$attendance_count['total']}</p>";
            
            // Test attendance logs query if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance_logs WHERE cadet_id = ?");
                $stmt->execute([$cadet_id]);
                $logs_count = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p><strong>Attendance Log Records:</strong> {$logs_count['total']}</p>";
            }
            
            // Show recent attendance records
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE cadet_id = ? ORDER BY date DESC LIMIT 5");
            $stmt->execute([$cadet_id]);
            $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($recent_attendance) {
                echo "<h4>Recent Attendance Records:</h4>";
                echo "<table><tr>";
                foreach (array_keys($recent_attendance[0]) as $header) {
                    echo "<th>$header</th>";
                }
                echo "</tr>";
                foreach ($recent_attendance as $record) {
                    echo "<tr>";
                    foreach ($record as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='info'>ℹ No recent attendance records for this cadet</p>";
            }
        } else {
            echo "<p class='error'>✗ User ID $test_user_id not found</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error testing user $test_user_id: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}
echo "</div>";

// Test 8: Session Simulation for Cadet
echo "<div class='section'>";
echo "<h2>8. Session Simulation Test</h2>";
echo "<p>Click the button below to simulate a cadet session and test the attendance page:</p>";
echo "<button onclick='simulateSession()'>Simulate Cadet Session (User ID 11)</button>";
echo "<div id='sessionResult'></div>";
echo "</div>";

echo "<script>
function simulateSession() {
    fetch('test_cadet_attendance_data.php?simulate_session=1', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=11&role=cadet&username=testcadet'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('sessionResult').innerHTML = '<p class=\"success\">Session simulated! You can now test <a href=\"cadet_attendance.php\" target=\"_blank\">cadet_attendance.php</a></p>';
    })
    .catch(error => {
        document.getElementById('sessionResult').innerHTML = '<p class=\"error\">Error: ' + error + '</p>';
    });
}
</script>";

// Handle session simulation
if (isset($_GET['simulate_session']) && $_POST) {
    $_SESSION['user_id'] = $_POST['user_id'];
    $_SESSION['role'] = $_POST['role'];
    $_SESSION['username'] = $_POST['username'];
    echo "Session variables set successfully";
    exit;
}

echo "<div class='section'>";
echo "<h2>Test Complete</h2>";
echo "<p>This test shows the current state of your database and helps identify why attendance data might not be displaying.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If no attendance records are found, you need to add test data</li>";
echo "<li>If records exist but don't display, check the cadet_id mapping logic</li>";
echo "<li>If session issues are found, verify login functionality</li>";
echo "</ul>";
echo "</div>";
?>