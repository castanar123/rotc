<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking for middle_name column in cadet_profiles table...\n";
    
    $result = $pdo->query('DESCRIBE cadet_profiles');
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $middle_name_found = false;
    foreach ($columns as $column) {
        if ($column['Field'] == 'middle_name') {
            $middle_name_found = true;
            echo "✓ middle_name column found: " . json_encode($column) . "\n";
            break;
        }
    }
    
    if (!$middle_name_found) {
        echo "✗ middle_name column NOT found in cadet_profiles table\n";
        echo "Available columns:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>