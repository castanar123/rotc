<?php
// Test database connection and check borrower_temp_ids table
require_once 'includes/db.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    echo "<p>✅ Database connection successful</p>";
    
    // Check if borrower_temp_ids table exists
    $result = $link->query("SHOW TABLES LIKE 'borrower_temp_ids'");
    
    if ($result->num_rows > 0) {
        echo "<p>✅ borrower_temp_ids table exists</p>";
        
        // Show table structure
        $structure = $link->query("DESCRIBE borrower_temp_ids");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count records
        $count = $link->query("SELECT COUNT(*) as count FROM borrower_temp_ids");
        $countRow = $count->fetch_assoc();
        echo "<p>Records in table: " . $countRow['count'] . "</p>";
        
    } else {
        echo "<p>❌ borrower_temp_ids table does NOT exist</p>";
        echo "<p>Creating table...</p>";
        
        $createTable = "
        CREATE TABLE borrower_temp_ids (
            id INT AUTO_INCREMENT PRIMARY KEY,
            temp_id VARCHAR(50) UNIQUE NOT NULL,
            prefix VARCHAR(10) NOT NULL,
            sequence_number INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            status ENUM('active', 'used', 'expired') DEFAULT 'active',
            created_by INT,
            notes TEXT
        )";
        
        if ($link->query($createTable)) {
            echo "<p>✅ borrower_temp_ids table created successfully</p>";
        } else {
            echo "<p>❌ Failed to create table: " . $link->error . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Show all tables in database
echo "<h3>All Tables in Database:</h3>";
try {
    $tables = $link->query("SHOW TABLES");
    echo "<ul>";
    while ($table = $tables->fetch_array()) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>Error listing tables: " . $e->getMessage() . "</p>";
}
?>