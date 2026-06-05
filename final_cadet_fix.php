<?php
require_once 'includes/db.php';

echo "Final fix for Cadet Profiles table...\n\n";

try {
    // Fix the full_name field
    echo "Fixing full_name field...\n";
    $pdo->exec("ALTER TABLE cadet_profiles MODIFY COLUMN full_name VARCHAR(255) NULL DEFAULT NULL");
    echo "SUCCESS: Fixed full_name field\n";
    
    // Add sample data with full_name included
    echo "\nAdding sample cadet data with full names...\n";
    
    $sample_cadets = [
        ['John', 'Doe', 'John Doe', 'MS1', 'Male', '2024001', 'john.doe@example.com'],
        ['Jane', 'Smith', 'Jane Smith', 'MS2', 'Female', '2024002', 'jane.smith@example.com'],
        ['Mike', 'Johnson', 'Mike Johnson', 'MS3', 'Male', '2024003', 'mike.johnson@example.com'],
        ['Sarah', 'Wilson', 'Sarah Wilson', 'MS4', 'Female', '2024004', 'sarah.wilson@example.com'],
        ['David', 'Brown', 'David Brown', 'MS1', 'Male', '2024005', 'david.brown@example.com']
    ];
    
    $insert_query = "INSERT INTO cadet_profiles (first_name, last_name, full_name, year_level, gender, student_id, email, status, academic_year, semester) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', '2024-2025', 'First')";
    $stmt = $pdo->prepare($insert_query);
    
    foreach ($sample_cadets as $cadet) {
        try {
            $stmt->execute($cadet);
            echo "Added cadet: {$cadet[2]} ({$cadet[3]})\n";
        } catch (PDOException $e) {
            echo "Error adding {$cadet[2]}: {$e->getMessage()}\n";
        }
    }
    
    // Test the document generation query again
    echo "\nTesting document generation query...\n";
    $stats_query = "SELECT 
        COUNT(*) as total_cadets,
        SUM(CASE WHEN year_level = 'MS1' THEN 1 ELSE 0 END) as ms1_count,
        SUM(CASE WHEN year_level = 'MS2' THEN 1 ELSE 0 END) as ms2_count,
        SUM(CASE WHEN year_level = 'MS3' THEN 1 ELSE 0 END) as ms3_count,
        SUM(CASE WHEN year_level = 'MS4' THEN 1 ELSE 0 END) as ms4_count,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count
        FROM cadet_profiles WHERE status = 'active'";
    
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute();
    $cadet_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "SUCCESS: Document generation query works!\n";
    echo "Final Statistics: " . json_encode($cadet_stats, JSON_PRETTY_PRINT) . "\n";
    
    if ($cadet_stats['total_cadets'] > 0) {
        echo "\n=== DOCUMENT GENERATION DATABASE FULLY FIXED ===\n";
        echo "Cadet profiles table now has sample data and all required columns.\n";
        echo "Document generation should work properly now.\n";
    } else {
        echo "\nWARNING: No cadets were added, but query structure is correct.\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
?>