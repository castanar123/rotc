<?php
require_once 'includes/db.php';

echo "<h1>Admin Dashboard Database Diagnostic</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;margin:10px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";

// 1. Test Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    if (isset($pdo)) {
        echo "<p class='success'>✓ PDO Connection: SUCCESS</p>";
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "<p class='info'>MySQL Version: $version</p>";
    } else {
        echo "<p class='error'>✗ PDO Connection: FAILED - \$pdo not defined</p>";
    }
    
    if (isset($link)) {
        echo "<p class='success'>✓ MySQLi Connection: SUCCESS</p>";
    } else {
        echo "<p class='error'>✗ MySQLi Connection: FAILED - \$link not defined</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database Connection Error: " . $e->getMessage() . "</p>";
}

// 2. List All Tables
echo "<h2>2. Database Tables</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p class='error'>✗ No tables found in database</p>";
    } else {
        echo "<p class='success'>✓ Found " . count($tables) . " tables:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error listing tables: " . $e->getMessage() . "</p>";
}

// 3. Check Required Tables
echo "<h2>3. Required Tables Check</h2>";
$required_tables = ['users', 'cadet_profiles', 'audit_logs', 'advance_rotc_signups', 'attendance'];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<p class='success'>✓ Table '$table': EXISTS ($count records)</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Table '$table': MISSING or ERROR - " . $e->getMessage() . "</p>";
    }
}

// 4. Test Dashboard Queries
echo "<h2>4. Dashboard Queries Test</h2>";

// Test each query from admin_dashboard.php
$queries = [
    'Total Users' => "SELECT COUNT(*) as total FROM users WHERE role IN ('basic_cadet', '2cl', '1cl')",
    'Basic Cadets' => "SELECT COUNT(*) as total FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic_cadet' AND (cp.status = 'Active' OR cp.status IS NULL)",
    'Officers' => "SELECT COUNT(*) as total FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role IN ('1cl', 'commandant') AND (cp.status = 'Active' OR cp.status IS NULL)",
    'Command Staff' => "SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'commandant')",
    'Pending Registrations' => "SELECT COUNT(*) as total FROM users WHERE status = 'pending'",
    'Advance ROTC' => "SELECT COUNT(*) as total FROM advance_rotc_signups",
    'Today Attendance' => "SELECT COUNT(DISTINCT student_id) as present FROM attendance WHERE DATE(timestamp) = CURDATE()",
    'Total Students' => "SELECT COUNT(*) as total FROM cadet_profiles"
];

foreach ($queries as $name => $query) {
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetch();
        $value = $result['total'] ?? $result['present'] ?? 0;
        echo "<p class='success'>✓ $name: $value</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ $name: ERROR - " . $e->getMessage() . "</p>";
    }
}

// 5. Test Recent Activities Query
echo "<h2>5. Recent Activities Test</h2>";
try {
    $stmt = $pdo->query("
        SELECT al.*, CONCAT(cp.first_name, ' ', cp.last_name) as full_name,
               u.username, cp.first_name, cp.last_name
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        ORDER BY al.created_at DESC 
        LIMIT 5
    ");
    $activities = $stmt->fetchAll();
    
    if (empty($activities)) {
        echo "<p class='info'>ℹ No recent activities found in audit_logs</p>";
    } else {
        echo "<p class='success'>✓ Found " . count($activities) . " recent activities:</p>";
        echo "<table><tr><th>Action</th><th>User</th><th>Details</th><th>Date</th></tr>";
        foreach ($activities as $activity) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($activity['action'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($activity['full_name'] ?? $activity['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($activity['details'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($activity['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Recent Activities Error: " . $e->getMessage() . "</p>";
    
    // Try fallback query
    echo "<p class='info'>Trying fallback to attendance records...</p>";
    try {
        $stmt = $pdo->query("
            SELECT a.timestamp as created_at, 
                   CONCAT(cp.first_name, ' ', cp.last_name) as full_name, 
                   'Attendance Scan' as action,
                   cp.first_name, cp.last_name
            FROM attendance a 
            LEFT JOIN cadet_profiles cp ON a.student_id = cp.student_id
            WHERE cp.first_name IS NOT NULL
            ORDER BY a.timestamp DESC 
            LIMIT 5
        ");
        $activities = $stmt->fetchAll();
        
        if (empty($activities)) {
            echo "<p class='info'>ℹ No attendance records found</p>";
        } else {
            echo "<p class='success'>✓ Found " . count($activities) . " attendance records</p>";
        }
    } catch (Exception $e2) {
        echo "<p class='error'>✗ Fallback query also failed: " . $e2->getMessage() . "</p>";
    }
}

// 6. Test Pending Users Query
echo "<h2>6. Pending Users Test</h2>";
try {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.role, u.created_at,
               cp.first_name, cp.last_name, cp.middle_name, cp.student_id, 
               cp.course, cp.section, cp.contact_number
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.status = 'pending' 
        ORDER BY u.created_at DESC 
        LIMIT 5
    ");
    $pending_users = $stmt->fetchAll();
    
    if (empty($pending_users)) {
        echo "<p class='info'>ℹ No pending users found</p>";
    } else {
        echo "<p class='success'>✓ Found " . count($pending_users) . " pending users:</p>";
        echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th><th>Created</th></tr>";
        foreach ($pending_users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Pending Users Error: " . $e->getMessage() . "</p>";
}

// 7. Sample Data from Key Tables
echo "<h2>7. Sample Data</h2>";
$sample_tables = ['users', 'cadet_profiles', 'audit_logs'];

foreach ($sample_tables as $table) {
    echo "<h3>Sample from $table:</h3>";
    try {
        $stmt = $pdo->query("SELECT * FROM $table LIMIT 3");
        $rows = $stmt->fetchAll();
        
        if (empty($rows)) {
            echo "<p class='info'>ℹ No data in $table</p>";
        } else {
            echo "<table>";
            // Header
            echo "<tr>";
            foreach (array_keys($rows[0]) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";
            
            // Data
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error reading $table: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>Diagnostic Complete</h2>";
echo "<p>Check the results above to identify what's causing the admin dashboard data issues.</p>";
?>