<?php
require_once 'includes/db.php';

try {
    
    echo "Testing birthdate retrieval and formatting:\n\n";
    
    $sql = "SELECT 
                cp.id,
                cp.first_name,
                cp.last_name,
                cp.birthdate
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.status = 'active' AND cp.birthdate IS NOT NULL
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cadets)) {
        echo "No cadets found with non-NULL birthdate.\n";
    } else {
        foreach ($cadets as $cadet) {
            echo "ID: " . $cadet['id'] . "\n";
            echo "Name: " . $cadet['first_name'] . " " . $cadet['last_name'] . "\n";
            echo "Raw birthdate: " . var_export($cadet['birthdate'], true) . "\n";
            
            // Test the same logic used in generate_document.php
            $dob = 'N/A';
            if (!empty($cadet['birthdate']) && $cadet['birthdate'] !== null) {
                $timestamp = strtotime($cadet['birthdate']);
                if ($timestamp !== false) {
                    $dob = date('d-M-y', $timestamp);
                }
            }
            echo "Formatted DOB: " . $dob . "\n";
            echo "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>