<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== USERS TABLE COLUMNS ===\n";
    $desc = $pdo->query("DESCRIBE users");
    $columns = $desc->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n=== CADET_PROFILES TABLE COLUMNS ===\n";
    $desc = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $desc->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n=== SECURITY_LOGS TABLE COLUMNS ===\n";
    $desc = $pdo->query("DESCRIBE security_logs");
    $columns = $desc->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n=== CHECKING FOR MISSING COLUMNS ===\n";
    
    // Check for student_id in users
    $desc = $pdo->query("DESCRIBE users");
    $user_columns = $desc->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('student_id', $user_columns)) {
        echo "users table missing: student_id\n";
    }
    
    // Check for birth_place vs place_of_birth in cadet_profiles
    $desc = $pdo->query("DESCRIBE cadet_profiles");
    $profile_columns = $desc->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('birth_place', $profile_columns)) {
        echo "cadet_profiles table missing: birth_place\n";
        if (in_array('place_of_birth', $profile_columns)) {
            echo "cadet_profiles has: place_of_birth (alias needed)\n";
        }
    }
    
    // Check for timestamp in security_logs
    $desc = $pdo->query("DESCRIBE security_logs");
    $log_columns = $desc->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('timestamp', $log_columns)) {
        echo "security_logs table missing: timestamp\n";
        if (in_array('created_at', $log_columns)) {
            echo "security_logs has: created_at (alias needed)\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>