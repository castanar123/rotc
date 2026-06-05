<?php
require_once 'includes/db.php';

echo "=== DEBUGGING DOCUMENT GENERATION FIELDS ===\n\n";

try {
    // Check what fields exist in cadet_profiles
    echo "1. Checking cadet_profiles table structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles");
    $columns = $stmt->fetchAll();
    echo "Available columns in cadet_profiles:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    // Check sample data for birth_date field
    echo "2. Checking birth_date field data...\n";
    $stmt = $pdo->query("
        SELECT 
            cp.first_name, 
            cp.last_name, 
            cp.birth_date,
            cp.birthday,
            u.year_level
        FROM cadet_profiles cp 
        JOIN users u ON cp.user_id = u.id 
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        LIMIT 5
    ");
    $samples = $stmt->fetchAll();
    
    foreach ($samples as $sample) {
        echo "  {$sample['first_name']} {$sample['last_name']}:\n";
        echo "    birth_date: " . ($sample['birth_date'] ?: 'NULL') . "\n";
        echo "    birthday: " . ($sample['birthday'] ?? 'N/A') . "\n";
        echo "    year_level: " . ($sample['year_level'] ?: 'NULL') . "\n";
    }
    echo "\n";
    
    // Check year_level distribution
    echo "3. Checking year_level distribution...\n";
    $stmt = $pdo->query("
        SELECT 
            u.year_level, 
            COUNT(*) as count 
        FROM users u 
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        GROUP BY u.year_level
    ");
    $year_levels = $stmt->fetchAll();
    
    foreach ($year_levels as $yl) {
        $level = $yl['year_level'] ?: 'NULL';
        echo "  Year Level '{$level}': {$yl['count']} cadets\n";
    }
    echo "\n";
    
    // Test the exact query from beneficiaries generation
    echo "4. Testing beneficiaries query...\n";
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'Other'
                END as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birth_date,
                cp.birthday,
                cp.beneficiary_name,
                cp.beneficiary_address,
                cp.address,
                cp.gender,
                cp.father_name,
                cp.mother_name,
                cp.guardian_name
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
                AND cp.gender IS NOT NULL
            LIMIT 3";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query returned " . count($results) . " rows:\n";
    foreach ($results as $row) {
        echo "  {$row['first_name']} {$row['last_name']} ({$row['ms_level']}):\n";
        echo "    birth_date: " . ($row['birth_date'] ?: 'NULL') . "\n";
        echo "    birthday: " . ($row['birthday'] ?? 'NULL') . "\n";
        echo "    father_name: " . ($row['father_name'] ?: 'NULL') . "\n";
        echo "    mother_name: " . ($row['mother_name'] ?: 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
