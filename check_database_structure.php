<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DATABASE STRUCTURE ANALYSIS ===\n\n";
    
    // Get all tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "TABLE: $table\n";
        echo str_repeat('-', 50) . "\n";
        
        // Get table structure
        $desc = $pdo->query("DESCRIBE $table");
        $columns = $desc->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            echo sprintf("  %-20s %-15s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
            );
        }
        echo "\n";
    }
    
    echo "\n=== MISSING COLUMNS ANALYSIS ===\n\n";
    
    // Check for specific missing columns based on errors
    $missing_columns = [
        'users' => ['first_name', 'last_name', 'approval_status'],
        'cadet_profiles' => ['beneficiary_name', 'province_city', 'birth_place'],
        'rifles' => ['status', 'user_id'],
        'rifle_assignments' => ['assigned_at'],
        'attendance' => ['user_id'],
        'security_logs' => ['action'],
        'qr_codes' => ['qr_code_path']
    ];
    
    foreach ($missing_columns as $table => $columns) {
        if (in_array($table, $tables)) {
            $desc = $pdo->query("DESCRIBE $table");
            $existing_columns = $desc->fetchAll(PDO::FETCH_COLUMN);
            
            $missing = array_diff($columns, $existing_columns);
            if (!empty($missing)) {
                echo "$table: Missing columns - " . implode(', ', $missing) . "\n";
            }
        } else {
            echo "$table: Table does not exist\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>