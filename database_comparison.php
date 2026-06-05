<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Comparison - ROTC System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .database-section {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .empty {
            border-color: #ff6b6b;
            background-color: #ffe0e0;
        }
        .active {
            border-color: #51cf66;
            background-color: #e0ffe0;
        }
        .status {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .empty .status {
            color: #d63031;
        }
        .active .status {
            color: #00b894;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ROTC Database Comparison</h1>
        
        <div class="highlight">
            <h3>📊 Database Status Summary</h3>
            <p><strong>rotc_db:</strong> Empty database (0 tables, 0 data)</p>
            <p><strong>rotc_system:</strong> Active database (3 tables with data)</p>
        </div>

        <div class="database-section empty">
            <div class="status">❌ rotc_db Database - EMPTY</div>
            <p><strong>Tables:</strong> 0</p>
            <p><strong>Status:</strong> This database exists but contains no tables or data.</p>
            <p><strong>Note:</strong> This appears to be an empty database that was created but never populated.</p>
        </div>

        <div class="database-section active">
            <div class="status">✅ rotc_system Database - ACTIVE</div>
            <p><strong>Tables:</strong> 3</p>
            <p><strong>Status:</strong> This database contains all your ROTC data and is currently being used by the application.</p>
            
            <h4>Tables in rotc_system:</h4>
            <ul>
                <li><strong>users</strong> - User accounts and authentication</li>
                <li><strong>items</strong> - Inventory items (weapons, equipment, clothing, etc.)</li>
                <li><strong>borrowed_items</strong> - Records of borrowed/issued items</li>
            </ul>

            <?php
            // Database connection
            $servername = "localhost";
            $username = "root";
            $password = "root";
            $dbname = "rotc_system";

            try {
                $conn = new mysqli($servername, $username, $password, $dbname);
                
                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                echo "<h4>Data Summary from rotc_system:</h4>";
                
                // Get counts from each table
                $tables = ['users', 'items', 'borrowed_items'];
                echo "<table>";
                echo "<tr><th>Table</th><th>Record Count</th><th>Sample Data</th></tr>";
                
                foreach ($tables as $table) {
                    $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                    $count = $count_result->fetch_assoc()['count'];
                    
                    // Get sample data
                    $sample_result = $conn->query("SELECT * FROM $table LIMIT 1");
                    $sample = $sample_result->fetch_assoc();
                    $sample_text = $sample ? implode(', ', array_keys($sample)) : 'No data';
                    
                    echo "<tr>";
                    echo "<td><strong>$table</strong></td>";
                    echo "<td>$count records</td>";
                    echo "<td>$sample_text</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Show some actual data
                echo "<h4>Sample Items Data:</h4>";
                $items_result = $conn->query("SELECT item_code, item_name, category, quantity_available FROM items LIMIT 5");
                if ($items_result->num_rows > 0) {
                    echo "<table>";
                    echo "<tr><th>Item Code</th><th>Item Name</th><th>Category</th><th>Available</th></tr>";
                    while ($row = $items_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['quantity_available']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                
                $conn->close();
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>Error connecting to rotc_system database: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <div class="highlight">
            <h3>🔍 What This Means:</h3>
            <ul>
                <li><strong>rotc_db</strong> is an empty database - it exists but has no tables or data</li>
                <li><strong>rotc_system</strong> contains all your actual ROTC data and is the active database</li>
                <li>Your application is currently configured to use <strong>rotc_system</strong></li>
                <li>All your inventory, users, and borrowed items data is safely stored in <strong>rotc_system</strong></li>
            </ul>
            
            <h4>📝 Recommendation:</h4>
            <p>Continue using <strong>rotc_system</strong> as it contains all your data. The <strong>rotc_db</strong> database can be safely ignored or deleted as it's empty.</p>
        </div>

        <div style="margin-top: 30px; padding: 15px; background-color: #e3f2fd; border-radius: 5px;">
            <h4>🔗 Quick Links:</h4>
            <p><a href="admin_dashboard.php">← Back to Admin Dashboard</a></p>
            <p><a href="view_database.php">View Database Details</a></p>
            <p><a href="http://localhost/phpmyadmin" target="_blank">Open phpMyAdmin</a></p>
        </div>
    </div>
</body>
</html>