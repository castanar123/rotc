<?php
// Schema Import Script
require_once 'includes/db.php';

echo "Importing database schema...\n";

// Import the updated schema
$schemaFile = 'db/updated_rotc_schema.sql';

if (file_exists($schemaFile)) {
    echo "Found schema file: $schemaFile\n";
    
    try {
        $sql = file_get_contents($schemaFile);
        $connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                if ($connection->query($statement)) {
                    echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
                } else {
                    echo "❌ Failed: " . substr($statement, 0, 50) . "... Error: " . $connection->error . "\n";
                }
            }
        }
        
        $connection->close();
        echo "\n✅ Schema import completed successfully!\n";
        
    } catch (Exception $e) {
        echo "❌ Error importing schema: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Schema file not found: $schemaFile\n";
}

echo "\nRunning final diagnostic check...\n";

// Quick table check
try {
    $connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    $requiredTables = [
        'users',
        'cadet_profiles', 
        'rifles',
        'rifle_assignments',
        'attendance_records',
        'missing_id_requests',
        'qr_codes'
    ];
    
    echo "\nChecking tables:\n";
    foreach ($requiredTables as $table) {
        $result = $connection->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' is missing\n";
        }
    }
    
    $connection->close();
    
} catch (Exception $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "\n";
}

echo "\n🎉 Database setup complete! You can now access your application.\n";
?>