<?php
require_once 'includes/db.php';

echo "CADET_PROFILES TABLE STRUCTURE:\n";
try {
    $stmt = $pdo->query('DESCRIBE cadet_profiles');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\nSAMPLE CADET_PROFILES DATA:\n";
    $stmt = $pdo->query('SELECT * FROM cadet_profiles LIMIT 3');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) > 0) {
        echo "Columns: " . implode(', ', array_keys($data[0])) . "\n";
        foreach($data as $row) {
            echo "Record: " . implode(' | ', $row) . "\n";
        }
    } else {
        echo "No data found\n";
    }
    
    echo "\nTEST JOIN QUERY:\n";
    echo "Testing: attendance.cadet_id = cadet_profiles.user_id\n";
    $stmt = $pdo->query("SELECT a.cadet_id, cp.user_id, cp.first_name, cp.last_name FROM attendance a JOIN cadet_profiles cp ON a.cadet_id = cp.user_id LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ JOIN works with: attendance.cadet_id = cadet_profiles.user_id\n";
        echo "Sample: Cadet ID {$result['cadet_id']} = User ID {$result['user_id']} ({$result['first_name']} {$result['last_name']})\n";
    } else {
        echo "❌ JOIN failed with: attendance.cadet_id = cadet_profiles.user_id\n";
        
        // Try alternative join
        echo "\nTesting alternative: attendance.cadet_id = cadet_profiles.cadet_id\n";
        $stmt = $pdo->query("SELECT a.cadet_id, cp.cadet_id as cp_cadet_id, cp.first_name, cp.last_name FROM attendance a JOIN cadet_profiles cp ON a.cadet_id = cp.cadet_id LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "✓ JOIN works with: attendance.cadet_id = cadet_profiles.cadet_id\n";
        } else {
            echo "❌ JOIN failed with: attendance.cadet_id = cadet_profiles.cadet_id\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>