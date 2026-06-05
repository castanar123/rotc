<?php
// Database Diagnostic and Repair Tool
// This tool helps diagnose and fix database connection issues

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'includes/db.php';

echo "<!DOCTYPE html>\n";
echo "<html lang='en'>\n";
echo "<head>\n";
echo "    <meta charset='UTF-8'>\n";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "    <title>Database Diagnostic Tool</title>\n";
echo "    <style>\n";
echo "        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }\n";
echo "        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
echo "        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }\n";
echo "        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }\n";
echo "        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }\n";
echo "        .btn:hover { background: #0056b3; }\n";
echo "        .btn-success { background: #28a745; }\n";
echo "        .btn-success:hover { background: #1e7e34; }\n";
echo "        .btn-danger { background: #dc3545; }\n";
echo "        .btn-danger:hover { background: #c82333; }\n";
echo "        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";
echo "    <div class='container'>\n";
echo "        <h1>🔧 Database Diagnostic Tool</h1>\n";
echo "        <p>This tool will help diagnose and fix database connection issues.</p>\n";

$hasErrors = false;
$diagnosticResults = [];

// Step 1: Check XAMPP MySQL Service
echo "        <div class='step'>\n";
echo "            <h2>Step 1: Checking XAMPP MySQL Service</h2>\n";

// Check if MySQL service is running (Windows)
$mysqlRunning = false;
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $output = shell_exec('tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL');
    if (strpos($output, 'mysqld.exe') !== false) {
        $mysqlRunning = true;
        echo "            <div class='success'>✅ MySQL service is running</div>\n";
    } else {
        echo "            <div class='error'>❌ MySQL service is not running</div>\n";
        echo "            <div class='warning'>Please start XAMPP and ensure MySQL is running.</div>\n";
        $hasErrors = true;
    }
} else {
    // For Linux/Mac
    $output = shell_exec('pgrep mysqld');
    if (!empty(trim($output))) {
        $mysqlRunning = true;
        echo "            <div class='success'>✅ MySQL service is running</div>\n";
    } else {
        echo "            <div class='error'>❌ MySQL service is not running</div>\n";
        echo "            <div class='warning'>Please start MySQL service.</div>\n";
        $hasErrors = true;
    }
}
echo "        </div>\n";

// Step 2: Test Basic MySQL Connection
echo "        <div class='step'>\n";
echo "            <h2>Step 2: Testing MySQL Connection</h2>\n";

try {
    $testConnection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);
    if ($testConnection->connect_error) {
        throw new Exception("Connection failed: " . $testConnection->connect_error);
    }
    echo "            <div class='success'>✅ Successfully connected to MySQL server</div>\n";
    echo "            <div class='info'>Server Info: " . $testConnection->server_info . "</div>\n";
    $testConnection->close();
} catch (Exception $e) {
    echo "            <div class='error'>❌ Failed to connect to MySQL server</div>\n";
    echo "            <div class='error'>Error: " . $e->getMessage() . "</div>\n";
    echo "            <div class='warning'>Please check your XAMPP installation and MySQL configuration.</div>\n";
    $hasErrors = true;
}
echo "        </div>\n";

// Step 3: Check Database Existence
echo "        <div class='step'>\n";
echo "            <h2>Step 3: Checking Database '" . DB_NAME . "'</h2>\n";

$databaseExists = false;
try {
    $testConnection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);
    $result = $testConnection->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    
    if ($result && $result->num_rows > 0) {
        $databaseExists = true;
        echo "            <div class='success'>✅ Database '" . DB_NAME . "' exists</div>\n";
    } else {
        echo "            <div class='error'>❌ Database '" . DB_NAME . "' does not exist</div>\n";
        
        // Attempt to create database
        if ($testConnection->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            echo "            <div class='success'>✅ Successfully created database '" . DB_NAME . "'</div>\n";
            $databaseExists = true;
        } else {
            echo "            <div class='error'>❌ Failed to create database: " . $testConnection->error . "</div>\n";
            $hasErrors = true;
        }
    }
    $testConnection->close();
} catch (Exception $e) {
    echo "            <div class='error'>❌ Error checking database: " . $e->getMessage() . "</div>\n";
    $hasErrors = true;
}
echo "        </div>\n";

// Step 4: Check Required Tables
if ($databaseExists) {
    echo "        <div class='step'>\n";
    echo "            <h2>Step 4: Checking Required Tables</h2>\n";
    
    $requiredTables = [
        'users',
        'cadet_profiles',
        'rifles',
        'rifle_assignments',
        'attendance_records',
        'missing_id_requests',
        'qr_codes'
    ];
    
    try {
        $testConnection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        $missingTables = [];
        
        foreach ($requiredTables as $table) {
            $result = $testConnection->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "            <div class='success'>✅ Table '$table' exists</div>\n";
            } else {
                echo "            <div class='error'>❌ Table '$table' is missing</div>\n";
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            echo "            <div class='warning'>Missing tables detected. You may need to run the database schema.</div>\n";
            echo "            <div class='info'>Missing tables: " . implode(', ', $missingTables) . "</div>\n";
            
            // Check if schema files exist
            $schemaFiles = [
                'db/rotc_db.sql',
                'db/updated_rotc_schema.sql',
                'db/advance_rotc_table.sql'
            ];
            
            echo "            <h3>Available Schema Files:</h3>\n";
            foreach ($schemaFiles as $schemaFile) {
                if (file_exists($schemaFile)) {
                    echo "            <div class='info'>📄 Found: $schemaFile</div>\n";
                    echo "            <a href='?import_schema=" . urlencode($schemaFile) . "' class='btn btn-success'>Import $schemaFile</a>\n";
                } else {
                    echo "            <div class='warning'>📄 Not found: $schemaFile</div>\n";
                }
            }
        }
        
        $testConnection->close();
    } catch (Exception $e) {
        echo "            <div class='error'>❌ Error checking tables: " . $e->getMessage() . "</div>\n";
        $hasErrors = true;
    }
    echo "        </div>\n";
}

// Handle schema import
if (isset($_GET['import_schema']) && !empty($_GET['import_schema'])) {
    $schemaFile = $_GET['import_schema'];
    if (file_exists($schemaFile)) {
        echo "        <div class='step'>\n";
        echo "            <h2>Importing Schema: $schemaFile</h2>\n";
        
        try {
            $sql = file_get_contents($schemaFile);
            $testConnection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
            
            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    if ($testConnection->query($statement)) {
                        echo "            <div class='success'>✅ Executed: " . substr($statement, 0, 50) . "...</div>\n";
                    } else {
                        echo "            <div class='error'>❌ Failed: " . substr($statement, 0, 50) . "... Error: " . $testConnection->error . "</div>\n";
                    }
                }
            }
            
            $testConnection->close();
            echo "            <div class='success'>✅ Schema import completed</div>\n";
            echo "            <a href='database_diagnostic.php' class='btn'>Re-run Diagnostic</a>\n";
        } catch (Exception $e) {
            echo "            <div class='error'>❌ Error importing schema: " . $e->getMessage() . "</div>\n";
        }
        echo "        </div>\n";
    }
}

// Step 5: Test Application Database Connection
echo "        <div class='step'>\n";
echo "            <h2>Step 5: Testing Application Database Connection</h2>\n";

try {
    // Test the actual connection from db.php
    $testConnection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    $testConnection->set_charset("utf8mb4");
    
    // Test a simple query
    $result = $testConnection->query("SELECT 1 as test");
    if ($result) {
        echo "            <div class='success'>✅ Application database connection is working</div>\n";
        echo "            <div class='success'>✅ Character set: " . $testConnection->character_set_name() . "</div>\n";
    } else {
        echo "            <div class='error'>❌ Failed to execute test query</div>\n";
        $hasErrors = true;
    }
    
    $testConnection->close();
} catch (Exception $e) {
    echo "            <div class='error'>❌ Application database connection failed: " . $e->getMessage() . "</div>\n";
    $hasErrors = true;
}
echo "        </div>\n";

// Summary and Next Steps
echo "        <div class='step'>\n";
echo "            <h2>🎯 Summary and Next Steps</h2>\n";

if (!$hasErrors) {
    echo "            <div class='success'>🎉 All database checks passed! Your database connection should be working.</div>\n";
    echo "            <a href='index.php' class='btn btn-success'>Go to Application</a>\n";
} else {
    echo "            <div class='error'>⚠️ Some issues were detected. Please address the errors above.</div>\n";
    echo "            <div class='info'>\n";
    echo "                <h3>Manual Fix Instructions:</h3>\n";
    echo "                <ol>\n";
    echo "                    <li>Ensure XAMPP is running and MySQL service is started</li>\n";
    echo "                    <li>Check that MySQL is accessible on localhost:3307</li>\n";
    echo "                    <li>Verify database credentials in includes/db.php</li>\n";
    echo "                    <li>Import the database schema if tables are missing</li>\n";
    echo "                    <li>Check MySQL error logs for detailed error information</li>\n";
    echo "                </ol>\n";
    echo "            </div>\n";
}

echo "            <a href='database_diagnostic.php' class='btn'>Re-run Diagnostic</a>\n";
echo "        </div>\n";

echo "        <div class='step'>\n";
echo "            <h2>📋 System Information</h2>\n";
echo "            <div class='info'>\n";
echo "                <strong>PHP Version:</strong> " . PHP_VERSION . "<br>\n";
echo "                <strong>Operating System:</strong> " . PHP_OS . "<br>\n";
echo "                <strong>Database Server:</strong> " . DB_SERVER . "<br>\n";
echo "                <strong>Database Name:</strong> " . DB_NAME . "<br>\n";
echo "                <strong>Database User:</strong> " . DB_USERNAME . "<br>\n";
echo "                <strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "<br>\n";
echo "            </div>\n";
echo "        </div>\n";

echo "    </div>\n";
echo "</body>\n";
echo "</html>\n";
?>