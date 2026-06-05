<?php
// Database configuration
$host = 'localhost:3306';
$username = 'root';
$password = '';
$database = 'rotc_db';

echo "=== ROTC Database Structure Analysis ===\n";
echo "Database: $database\n";
echo "Host: $host\n";
echo "Username: $username\n\n";

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful!\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    echo "\n";
    
    // Check specific tables and their columns
    $critical_tables = ['rifles', 'rifle_assignments', 'cadet_profiles', 'users', 'attendance'];
    
    foreach ($critical_tables as $table) {
        echo "=== Table: $table ===\n";
        
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->rowCount() == 0) {
            echo "❌ Table '$table' does not exist!\n\n";
            continue;
        }
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Columns (" . count($columns) . "):\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})";
            if ($column['Key'] == 'PRI') echo " [PRIMARY KEY]";
            if ($column['Key'] == 'MUL') echo " [FOREIGN KEY]";
            if ($column['Null'] == 'NO') echo " [NOT NULL]";
            echo "\n";
        }
        echo "\n";
    }
    
    // Check for specific missing columns that are causing errors
    echo "=== Missing Columns Check ===\n";
    
    $missing_columns = [];
    
    // Check rifles table for 'status' column
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM rifles LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $missing_columns[] = "rifles.status";
            echo "❌ Missing: rifles.status\n";
        } else {
            echo "✅ Found: rifles.status\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking rifles table: " . $e->getMessage() . "\n";
    }
    
    // Check rifle_assignments table for 'assigned_at' column
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'rifle_assignments'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SHOW COLUMNS FROM rifle_assignments LIKE 'assigned_at'");
            if ($stmt->rowCount() == 0) {
                $missing_columns[] = "rifle_assignments.assigned_at";
                echo "❌ Missing: rifle_assignments.assigned_at\n";
            } else {
                echo "✅ Found: rifle_assignments.assigned_at\n";
            }
        } else {
            $missing_columns[] = "rifle_assignments table";
            echo "❌ Missing: rifle_assignments table\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking rifle_assignments table: " . $e->getMessage() . "\n";
    }
    
    // Check cadet_profiles for missing columns
    $cadet_columns_to_check = ['beneficiary_name', 'province_city'];
    foreach ($cadet_columns_to_check as $col) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE '$col'");
            if ($stmt->rowCount() == 0) {
                $missing_columns[] = "cadet_profiles.$col";
                echo "❌ Missing: cadet_profiles.$col\n";
            } else {
                echo "✅ Found: cadet_profiles.$col\n";
            }
        } catch (Exception $e) {
            echo "❌ Error checking cadet_profiles.$col: " . $e->getMessage() . "\n";
        }
    }
    
    // Check for qr_code_path column in various tables
    $tables_to_check_qr = ['rifles', 'cadet_profiles', 'users'];
    $qr_found = false;
    foreach ($tables_to_check_qr as $table) {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'qr_code_path'");
                if ($stmt->rowCount() > 0) {
                    echo "✅ Found: $table.qr_code_path\n";
                    $qr_found = true;
                }
            }
        } catch (Exception $e) {
            echo "❌ Error checking $table.qr_code_path: " . $e->getMessage() . "\n";
        }
    }
    if (!$qr_found) {
        $missing_columns[] = "qr_code_path (in any table)";
        echo "❌ Missing: qr_code_path column not found in any table\n";
    }
    
    // Check users table for approval_status
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'approval_status'");
        if ($stmt->rowCount() == 0) {
            $missing_columns[] = "users.approval_status";
            echo "❌ Missing: users.approval_status\n";
        } else {
            echo "✅ Found: users.approval_status\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking users.approval_status: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Summary ===\n";
    if (empty($missing_columns)) {
        echo "🎉 ALL REQUIRED COLUMNS FOUND!\n";
    } else {
        echo "📝 Missing Elements (" . count($missing_columns) . "):\n";
        foreach ($missing_columns as $missing) {
            echo "  - $missing\n";
        }
        echo "\n🔧 These columns need to be added to fix the reported errors.\n";
    }
    
} catch(PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting steps:\n";
    echo "1. Make sure XAMPP MySQL is running\n";
    echo "2. Check if rotc_db database exists\n";
    echo "3. Verify MySQL credentials\n";
}
?>