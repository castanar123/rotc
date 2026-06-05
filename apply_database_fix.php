<?php
// Apply Database Fix Script
// This script applies the complete database fix

require_once 'includes/db.php';

try {
    echo "Starting database fix...\n";
    
    // Read the SQL fix file
    $sqlFile = __DIR__ . '/fix_complete_database.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL fix file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL fix file");
    }
    
    echo "SQL file loaded successfully.\n";
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            
            // Show progress for important statements
            if (stripos($statement, 'ALTER TABLE') === 0) {
                $tableName = '';
                if (preg_match('/ALTER TABLE\s+`?([^\s`]+)`?/i', $statement, $matches)) {
                    $tableName = $matches[1];
                }
                echo "✓ Modified table: $tableName\n";
            } elseif (stripos($statement, 'CREATE TABLE') === 0) {
                $tableName = '';
                if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([^\s`(]+)`?/i', $statement, $matches)) {
                    $tableName = $matches[1];
                }
                echo "✓ Created table: $tableName\n";
            } elseif (stripos($statement, 'CREATE INDEX') === 0) {
                echo "✓ Created index\n";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "✗ Error executing statement: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\n=== Database Fix Summary ===\n";
    echo "Successful statements: $successCount\n";
    echo "Failed statements: $errorCount\n";
    
    // Verify the fixes
    echo "\n=== Verification ===\n";
    
    // Check users table for approval_status column
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'approval_status'");
    if ($stmt->rowCount() > 0) {
        echo "✓ users.approval_status column exists\n";
    } else {
        echo "✗ users.approval_status column missing\n";
    }
    
    // Check cadet_profiles for missing columns
    $missingColumns = ['beneficiary_name', 'province_city', 'guardian_name', 'qr_code_path'];
    foreach ($missingColumns as $column) {
        $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE '$column'");
        if ($stmt->rowCount() > 0) {
            echo "✓ cadet_profiles.$column column exists\n";
        } else {
            echo "✗ cadet_profiles.$column column missing\n";
        }
    }
    
    // Check rifles table for status column
    $stmt = $pdo->query("SHOW COLUMNS FROM rifles LIKE 'status'");
    if ($stmt->rowCount() > 0) {
        echo "✓ rifles.status column exists\n";
    } else {
        echo "✗ rifles.status column missing\n";
    }
    
    // Check rifle_assignments for assigned_at column
    $stmt = $pdo->query("SHOW COLUMNS FROM rifle_assignments LIKE 'assigned_at'");
    if ($stmt->rowCount() > 0) {
        echo "✓ rifle_assignments.assigned_at column exists\n";
    } else {
        echo "✗ rifle_assignments.assigned_at column missing\n";
    }
    
    // Show table count
    $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'rotc_db'");
    $result = $stmt->fetch();
    echo "\nTotal tables in rotc_db: " . $result['table_count'] . "\n";
    
    echo "\nDatabase fix completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>