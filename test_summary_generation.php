<?php
require_once 'includes/db.php';

echo "=== TESTING SUMMARY DOCUMENT GENERATION ===\n\n";

try {
    // Test the exact summary query
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
    
    echo "Summary query results:\n";
    foreach ($results as $row) {
        echo "  {$row['ms_level']} {$row['gender']}: {$row['count']} cadets\n";
    }
    
    if (empty($results)) {
        echo "❌ No data returned - checking individual conditions...\n\n";
        
        // Check each condition
        $conditions = [
            "basic-cadet users" => "SELECT COUNT(*) as count FROM users WHERE role = 'basic-cadet'",
            "active basic-cadets" => "SELECT COUNT(*) as count FROM users WHERE role = 'basic-cadet' AND status = 'active'",
            "approved basic-cadets" => "SELECT COUNT(*) as count FROM users WHERE role = 'basic-cadet' AND status = 'active' AND approval_status = 'approved'",
            "with active profiles" => "SELECT COUNT(*) as count FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic-cadet' AND u.status = 'active' AND u.approval_status = 'approved' AND cp.status = 'Active'",
            "with gender data" => "SELECT COUNT(*) as count FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic-cadet' AND u.status = 'active' AND u.approval_status = 'approved' AND cp.status = 'Active' AND cp.gender IS NOT NULL"
        ];
        
        foreach ($conditions as $desc => $query) {
            $stmt = $pdo->query($query);
            $count = $stmt->fetch()['count'];
            echo "  {$desc}: {$count}\n";
        }
        
        // Check year_level and gender distribution
        echo "\nYear level distribution:\n";
        $stmt = $pdo->query("SELECT year_level, COUNT(*) as count FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' GROUP BY year_level");
        $levels = $stmt->fetchAll();
        foreach ($levels as $level) {
            $yl = $level['year_level'] ?: 'NULL';
            echo "  '{$yl}': {$level['count']}\n";
        }
        
        echo "\nGender distribution:\n";
        $stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM cadet_profiles cp JOIN users u ON cp.user_id = u.id WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' GROUP BY gender");
        $genders = $stmt->fetchAll();
        foreach ($genders as $gender) {
            $g = $gender['gender'] ?: 'NULL';
            echo "  '{$g}': {$gender['count']}\n";
        }
    } else {
        echo "\n✓ Summary generation should work correctly!\n";
        
        // Generate the actual summary format
        echo "\nGenerated summary format:\n";
        $totals = ['male' => 0, 'female' => 0];
        $msLevels = ['MS-1' => ['male' => 0, 'female' => 0], 'MS-32' => ['male' => 0, 'female' => 0], 'MS-42' => ['male' => 0, 'female' => 0], 'Other' => ['male' => 0, 'female' => 0]];
        
        foreach ($results as $row) {
            $gender = strtolower($row['gender']);
            $msLevels[$row['ms_level']][$gender] = $row['count'];
            $totals[$gender] += $row['count'];
        }
        
        foreach ($msLevels as $level => $genders) {
            if ($genders['male'] > 0 || $genders['female'] > 0) {
                echo "  {$level}: Male={$genders['male']}, Female={$genders['female']}, Total=" . ($genders['male'] + $genders['female']) . "\n";
            }
        }
        echo "  GRAND TOTAL: Male={$totals['male']}, Female={$totals['female']}, Total=" . ($totals['male'] + $totals['female']) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
