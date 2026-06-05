<?php
require_once 'includes/db.php';

echo "=== FIXING YEAR LEVELS FOR PROPER SUMMARY COUNTS ===\n\n";

try {
    // Check current year_level distribution
    echo "1. Current year_level distribution:\n";
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
    $levels = $stmt->fetchAll();
    foreach ($levels as $level) {
        $yl = $level['year_level'] ?: 'NULL';
        echo "  '{$yl}': {$level['count']} users\n";
    }
    echo "\n";
    
    // Fix NULL year_levels by setting them to appropriate values
    echo "2. Fixing NULL year_levels...\n";
    
    // Get users with NULL year_level
    $stmt = $pdo->query("
        SELECT u.id, u.username, cp.first_name, cp.last_name
        FROM users u 
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        AND (u.year_level IS NULL OR u.year_level = '')
    ");
    $nullUsers = $stmt->fetchAll();
    
    echo "Found " . count($nullUsers) . " users with NULL year_level\n";
    
    if (!empty($nullUsers)) {
        // Set them to '1st Year' as default (can be changed later)
        $stmt = $pdo->prepare("
            UPDATE users 
            SET year_level = '1st Year' 
            WHERE role = 'basic-cadet' 
            AND approval_status = 'approved'
            AND (year_level IS NULL OR year_level = '')
        ");
        $stmt->execute();
        $updated = $stmt->rowCount();
        echo "✓ Updated {$updated} users to '1st Year'\n\n";
    }
    
    // Check updated distribution
    echo "3. Updated year_level distribution:\n";
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
    $levels = $stmt->fetchAll();
    foreach ($levels as $level) {
        $yl = $level['year_level'] ?: 'NULL';
        echo "  '{$yl}': {$level['count']} users\n";
    }
    echo "\n";
    
    // Test summary query again
    echo "4. Testing summary query with fixed data...\n";
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'Other'
                END as ms_level,
                cp.gender,
                COUNT(*) as count
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
                AND cp.gender IS NOT NULL
            GROUP BY 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'Other'
                END, 
                cp.gender
            ORDER BY ms_level, cp.gender";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Summary query now returns:\n";
    foreach ($results as $row) {
        echo "  {$row['ms_level']} {$row['gender']}: {$row['count']} cadets\n";
    }
    
    if (!empty($results)) {
        echo "\n✓ Summary document generation should now work correctly!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== YEAR LEVEL FIX COMPLETE ===\n";
?>
