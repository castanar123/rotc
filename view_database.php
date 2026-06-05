<?php
// Simple Database Viewer
require_once 'includes/db.php';

// Function to display table data
function displayTable($tableName, $connection) {
    echo "<h3>Table: $tableName</h3>";
    
    try {
        if (DB_TYPE === 'mysql') {
            $result = $connection->query("SELECT * FROM $tableName");
            
            if ($result && $result->num_rows > 0) {
                echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
                
                // Get column names
                $fields = $result->fetch_fields();
                echo "<tr style='background-color: #f0f0f0;'>";
                foreach ($fields as $field) {
                    echo "<th>" . htmlspecialchars($field->name) . "</th>";
                }
                echo "</tr>";
                
                // Display data
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                echo "<p><strong>Total records:</strong> " . $result->num_rows . "</p>";
            } else {
                echo "<p style='color: orange;'>No data found in table $tableName</p>";
            }
        } else {
            // SQLite using PDO
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM $tableName");
            $data = $stmt->fetchAll();
            
            if (!empty($data)) {
                echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
                
                // Get column names
                $columns = array_keys($data[0]);
                echo "<tr style='background-color: #f0f0f0;'>";
                foreach ($columns as $column) {
                    echo "<th>" . htmlspecialchars($column) . "</th>";
                }
                echo "</tr>";
                
                // Display data
                foreach ($data as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                echo "<p><strong>Total records:</strong> " . count($data) . "</p>";
            } else {
                echo "<p style='color: orange;'>No data found in table $tableName</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error accessing table $tableName: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Function to get all tables
function getAllTables($connection) {
    $tables = [];
    
    try {
        if (DB_TYPE === 'mysql') {
            $result = $connection->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
            }
        } else {
            // SQLite
            global $pdo;
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error getting tables: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    return $tables;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROTC Database Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        h3 {
            color: #555;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            padding: 8px;
            text-align: left;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .info {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #ffe7e7;
            border: 1px solid #ffb3b3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #e7ffe7;
            border: 1px solid #b3ffb3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ROTC Database Viewer</h1>
        
        <div class="info">
            <strong>Database Type:</strong> <?php echo strtoupper(DB_TYPE); ?><br>
            <strong>Database Name:</strong> <?php echo DB_NAME; ?><br>
            <?php if (DB_TYPE === 'mysql'): ?>
                <strong>Server:</strong> <?php echo DB_SERVER; ?><br>
            <?php else: ?>
                <strong>Database File:</strong> <?php echo DB_PATH; ?><br>
            <?php endif; ?>
        </div>
        
        <?php
        // Test connection
        if (DB_TYPE === 'mysql' && isset($link)) {
            echo '<div class="success"><strong>MySQL Connection:</strong> Successfully connected to database!</div>';
        } elseif (DB_TYPE === 'sqlite' && isset($pdo)) {
            echo '<div class="success"><strong>SQLite Connection:</strong> Successfully connected to database!</div>';
        } else {
            echo '<div class="error"><strong>Connection Error:</strong> Could not connect to database!</div>';
        }
        
        // Get and display all tables
        $tables = getAllTables(DB_TYPE === 'mysql' ? $link : $pdo);
        
        if (empty($tables)) {
            echo '<div class="error"><strong>No Tables Found:</strong> The database appears to be empty or tables could not be accessed.</div>';
        } else {
            echo '<div class="success"><strong>Tables Found:</strong> ' . implode(', ', $tables) . '</div>';
            
            // Display each table
            foreach ($tables as $table) {
                displayTable($table, DB_TYPE === 'mysql' ? $link : $pdo);
            }
        }
        ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php" style="background-color: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Back to Main System</a>
        </div>
    </div>
</body>
</html>