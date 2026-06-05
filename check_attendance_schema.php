<?php
try {
    $pdo = new PDO('mysql:host=localhost:3306;dbname=rotc_db', 'root', ''); // Changed back to port 3306
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Attendance Table Schema</h2>";
    $stmt = $pdo->query('DESCRIBE attendance');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Sample Attendance Data</h2>";
    $stmt = $pdo->query('SELECT * FROM attendance LIMIT 3');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) > 0) {
        echo "<table border='1'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No attendance data found.</p>";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>