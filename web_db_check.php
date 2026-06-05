<!DOCTYPE html>
<html>
<head>
    <title>Database Structure Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>ROTC Database Structure Check</h1>
    
    <?php
    require_once 'QR/db.php';
    
    try {
        // Check if database connection is working
        if (isset($db_connection_failed) && $db_connection_failed) {
            echo "<p class='error'>❌ Database connection failed!</p>";
            exit(1);
        }
        
        echo "<p class='success'>✅ Database connection successful</p>";
        
        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>📋 Tables in Database</h2>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        // Check specific tables and their columns
        $critical_tables = ['rifles', 'rifle_assignments', 'cadet_profiles', 'users', 'attendance'];
        
        foreach ($critical_tables as $table) {
            echo "<h3>🔍 Table: $table</h3>";
            
            // Check if table exists
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if ($stmt->rowCount() == 0) {
                echo "<p class='error'>❌ Table '$table' does not exist!</p>";
                continue;
            }
            
            // Get table structure
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check for specific missing columns that are causing errors
        echo "<h2>🔍 Checking for Specific Missing Columns</h2>";
        
        $missing_columns = [];
        
        // Check rifles table for 'status' column
        $stmt = $pdo->query("SHOW COLUMNS FROM rifles LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $missing_columns[] = "rifles.status";
            echo "<p class='error'>❌ Missing: rifles.status</p>";
        } else {
            echo "<p class='success'>✅ Found: rifles.status</p>";
        }
        
        // Check rifle_assignments table for 'assigned_at' column
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'rifle_assignments'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SHOW COLUMNS FROM rifle_assignments LIKE 'assigned_at'");
            if ($stmt->rowCount() == 0) {
                $missing_columns[] = "rifle_assignments.assigned_at";
                echo "<p class='error'>❌ Missing: rifle_assignments.assigned_at</p>";
            } else {
                echo "<p class='success'>✅ Found: rifle_assignments.assigned_at</p>";
            }
        } else {
            $missing_columns[] = "rifle_assignments table";
            echo "<p class='error'>❌ Missing: rifle_assignments table</p>";
        }
        
        // Check cadet_profiles for missing columns
        $cadet_columns_to_check = ['beneficiary_name', 'province_city'];
        foreach ($cadet_columns_to_check as $col) {
            $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE '$col'");
            if ($stmt->rowCount() == 0) {
                $missing_columns[] = "cadet_profiles.$col";
                echo "<p class='error'>❌ Missing: cadet_profiles.$col</p>";
            } else {
                echo "<p class='success'>✅ Found: cadet_profiles.$col</p>";
            }
        }
        
        // Check for qr_code_path column in various tables
        $tables_to_check_qr = ['rifles', 'cadet_profiles', 'users'];
        $qr_found = false;
        foreach ($tables_to_check_qr as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'qr_code_path'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'>✅ Found: $table.qr_code_path</p>";
                    $qr_found = true;
                }
            }
        }
        if (!$qr_found) {
            $missing_columns[] = "qr_code_path (in any table)";
            echo "<p class='error'>❌ Missing: qr_code_path column not found in any table</p>";
        }
        
        // Check users table for approval_status
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'approval_status'");
        if ($stmt->rowCount() == 0) {
            $missing_columns[] = "users.approval_status";
            echo "<p class='error'>❌ Missing: users.approval_status</p>";
        } else {
            echo "<p class='success'>✅ Found: users.approval_status</p>";
        }
        
        if (empty($missing_columns)) {
            echo "<h2 class='success'>🎉 ALL REQUIRED COLUMNS FOUND!</h2>";
        } else {
            echo "<h2 class='warning'>📝 Summary of Missing Elements:</h2>";
            echo "<ul>";
            foreach ($missing_columns as $missing) {
                echo "<li class='error'>$missing</li>";
            }
            echo "</ul>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    ?>
</body>
</html>