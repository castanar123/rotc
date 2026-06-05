<?php
// Execute rifle management schema
require_once 'includes/db.php';

try {
    // Read the SQL file
    $sqlFile = 'db/rifle_management_schema.sql';
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if (!$link->query($statement)) {
                throw new Exception("Error executing statement: " . $link->error . "\nStatement: $statement");
            }
        }
    }
    
    echo "Rifle management schema executed successfully!\n";
    echo "Tables created: rifles, rifle_assignments, rifle_logs\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$link->close();
?>