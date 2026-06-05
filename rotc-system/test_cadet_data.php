<?php
require_once 'includes/db.php';

try {
    // Test database connection
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database connection successful!\n";
    echo "Connected to database: " . DB_NAME . "\n\n";
    
    // Check if cadet_profiles table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'cadet_profiles'");
    if ($stmt->rowCount() > 0) {
        echo "cadet_profiles table found!\n\n";
        
        // Get sample cadet data
        $stmt = $pdo->query("SELECT * FROM cadet_profiles LIMIT 5");
        $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Sample cadet data:\n";
        foreach ($cadets as $cadet) {
            echo "ID: " . $cadet['id'] . ", Name: " . $cadet['first_name'] . " " . $cadet['last_name'] . ", Student ID: " . $cadet['student_id'] . "\n";
        }
        
        // Count total cadets
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM cadet_profiles");
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nTotal cadets in database: " . $total['total'] . "\n";
        
    } else {
        echo "cadet_profiles table NOT found!\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>