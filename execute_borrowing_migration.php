<?php
/**
 * Execute borrowing table migration
 */

require_once 'includes/db.php';

try {
    echo "<h2>Executing Rifle Borrowing Migration</h2>";
    
    // Read the SQL file
    $sql = file_get_contents('migrations/create_borrowing_table.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    // Split into individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || 
            preg_match('/^(USE|SELECT|--)/i', $statement) ||
            preg_match('/^\s*$/', $statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "<p style='color: green;'>✓ Executed: " . htmlspecialchars(substr($statement, 0, 80)) . "...</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p style='color: orange;'>Statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
        }
    }
    
    echo "<h3 style='color: blue;'>Migration completed!</h3>";
    
    // Verify tables were created
    echo "<h3>Verifying created tables:</h3>";
    
    $tables = ['rifle_borrowings', 'dummy_qr_codes'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
                
                // Show table structure
                $desc = $pdo->query("DESCRIBE $table");
                echo "<details><summary>$table structure</summary>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                while ($row = $desc->fetch()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
                    echo "</tr>";
                }
                echo "</table></details>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' not found</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error checking table '$table': " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    // Check dummy QR codes
    echo "<h3>Checking dummy QR codes:</h3>";
    try {
        $stmt = $pdo->query("SELECT * FROM dummy_qr_codes");
        $qr_codes = $stmt->fetchAll();
        
        if (count($qr_codes) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>QR Code ID</th><th>Description</th><th>Active</th></tr>";
            foreach ($qr_codes as $qr) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($qr['id']) . "</td>";
                echo "<td>" . htmlspecialchars($qr['qr_code_id']) . "</td>";
                echo "<td>" . htmlspecialchars($qr['description']) . "</td>";
                echo "<td>" . ($qr['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>No dummy QR codes found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking dummy QR codes: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>