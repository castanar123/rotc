<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TABLES IN DATABASE ===\n";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\n=== MISSING COLUMNS ANALYSIS ===\n";
    
    // Check users table
    if (in_array('users', $tables)) {
        $desc = $pdo->query("DESCRIBE users");
        $columns = $desc->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff(['first_name', 'last_name', 'approval_status'], $columns);
        if (!empty($missing)) {
            echo "users: Missing - " . implode(', ', $missing) . "\n";
        } else {
            echo "users: All required columns present\n";
        }
    }
    
    // Check rifles table
    if (in_array('rifles', $tables)) {
        $desc = $pdo->query("DESCRIBE rifles");
        $columns = $desc->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff(['status', 'user_id'], $columns);
        if (!empty($missing)) {
            echo "rifles: Missing - " . implode(', ', $missing) . "\n";
        } else {
            echo "rifles: All required columns present\n";
        }
    }
    
    // Check rifle_assignments table
    if (in_array('rifle_assignments', $tables)) {
        $desc = $pdo->query("DESCRIBE rifle_assignments");
        $columns = $desc->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff(['assigned_at'], $columns);
        if (!empty($missing)) {
            echo "rifle_assignments: Missing - " . implode(', ', $missing) . "\n";
        } else {
            echo "rifle_assignments: All required columns present\n";
        }
    }
    
    // Check attendance table
    if (in_array('attendance', $tables)) {
        $desc = $pdo->query("DESCRIBE attendance");
        $columns = $desc->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff(['user_id'], $columns);
        if (!empty($missing)) {
            echo "attendance: Missing - " . implode(', ', $missing) . "\n";
        } else {
            echo "attendance: All required columns present\n";
        }
    }
    
    // Check security_logs table
    if (in_array('security_logs', $tables)) {
        $desc = $pdo->query("DESCRIBE security_logs");
        $columns = $desc->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff(['action'], $columns);
        if (!empty($missing)) {
            echo "security_logs: Missing - " . implode(', ', $missing) . "\n";
        } else {
            echo "security_logs: All required columns present\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>