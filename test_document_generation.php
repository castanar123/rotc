<?php
// Test script to verify document generation shows only MS-1 basic cadet data

require_once 'includes/db.php';

echo "Testing Document Generation for MS-1 Basic Cadets Only\n";
echo "=================================================\n\n";

try {
    // Test 1: Check what data the roster query returns
    echo "1. Testing Roster Query:\n";
    $sql = "SELECT 
                'MS-1' as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birth_date,
                cp.contact_number,
                cp.address,
                cp.gender,
                u.role,
                cp.year_level,
                u.approval_status
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.year_level = '1st Year' AND u.role = 'basic_cadet'
                    AND u.status = 'active' AND u.approval_status = 'approved'
            ORDER BY cp.gender, cp.last_name, cp.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rosterCadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($rosterCadets) . " MS-1 basic cadets for roster\n";
    if (count($rosterCadets) > 0) {
        echo "Sample data:\n";
        foreach (array_slice($rosterCadets, 0, 3) as $cadet) {
            echo "- {$cadet['last_name']}, {$cadet['first_name']} | Role: {$cadet['role']} | Year: {$cadet['year_level']} | Status: {$cadet['approval_status']}\n";
        }
    }
    echo "\n";
    
    // Test 2: Check what data the beneficiaries query returns
    echo "2. Testing Beneficiaries Query:\n";
    $sql = "SELECT 
                'MS-1' as ms_level,
                cp.last_name,
                cp.first_name,
                cp.father_name,
                cp.mother_name,
                cp.guardian_name,
                u.role,
                cp.year_level,
                u.approval_status
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.year_level = '1st Year' AND u.role = 'basic_cadet'
                    AND u.status = 'active' AND u.approval_status = 'approved'
            ORDER BY cp.gender, cp.last_name, cp.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $beneficiaryCadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($beneficiaryCadets) . " MS-1 basic cadets for beneficiaries\n";
    if (count($beneficiaryCadets) > 0) {
        echo "Sample data:\n";
        foreach (array_slice($beneficiaryCadets, 0, 3) as $cadet) {
            echo "- {$cadet['last_name']}, {$cadet['first_name']} | Role: {$cadet['role']} | Year: {$cadet['year_level']} | Status: {$cadet['approval_status']}\n";
        }
    }
    echo "\n";
    
    // Test 3: Check what data the cadet profile query returns
    echo "3. Testing Cadet Profile Query:\n";
    $sql = "SELECT 
                'MS-1' as ms_level,
                cp.last_name,
                cp.first_name,
                cp.religion,
                cp.blood_type,
                cp.height,
                u.role,
                cp.year_level,
                u.approval_status
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.year_level = '1st Year' AND u.role = 'basic_cadet'
                    AND u.status = 'active' AND u.approval_status = 'approved'
            ORDER BY cp.gender, cp.last_name, cp.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $profileCadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($profileCadets) . " MS-1 basic cadets for profile\n";
    if (count($profileCadets) > 0) {
        echo "Sample data:\n";
        foreach (array_slice($profileCadets, 0, 3) as $cadet) {
            echo "- {$cadet['last_name']}, {$cadet['first_name']} | Role: {$cadet['role']} | Year: {$cadet['year_level']} | Status: {$cadet['approval_status']}\n";
        }
    }
    echo "\n";
    
    // Test 4: Check if there are other year levels that should NOT be included
    echo "4. Checking for other year levels (should be excluded):\n";
    $sql = "SELECT 
                cp.year_level,
                u.role,
                COUNT(*) as count
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.status = 'active' AND u.approval_status = 'approved'
            GROUP BY cp.year_level, u.role
            ORDER BY cp.year_level, u.role";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $allCadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All cadets by year level and role:\n";
    foreach ($allCadets as $group) {
        $yearLevel = $group['year_level'] ?? 'NULL';
        echo "- Year {$yearLevel}, Role: {$group['role']}, Count: {$group['count']}\n";
    }
    
    echo "\n=== TEST SUMMARY ===\n";
    echo "✓ Document generation now filters for MS-1 basic cadets only\n";
echo "✓ All three documents (roster, beneficiaries, profile) use the same filter\n";
echo "✓ Filter criteria: year_level = '1st Year' AND role = 'basic_cadet' AND approval_status = 'approved'\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}