<?php
require_once 'includes/db.php';

echo "ATTENDANCE TABLE STRUCTURE:\n";
try {
    $stmt = $pdo->query('DESCRIBE attendance');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\nSAMPLE ATTENDANCE DATA:\n";
    $stmt = $pdo->query('SELECT * FROM attendance LIMIT 3');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) > 0) {
        echo "Columns: " . implode(', ', array_keys($data[0])) . "\n";
        foreach($data as $row) {
            echo "Record: " . implode(' | ', $row) . "\n";
        }
    } else {
        echo "No data found\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>