<?php
require_once 'includes/db.php';

try {
    echo "<h2>Cadet Profiles Table Structure:</h2>";
    $stmt = $pdo->query('DESCRIBE cadet_profiles');
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Sample Data:</h2>";
    $stmt = $pdo->query('SELECT * FROM cadet_profiles LIMIT 1');
    $sample = $stmt->fetch();
    if ($sample) {
        echo "<pre>";
        print_r($sample);
        echo "</pre>";
    } else {
        echo "No data found in cadet_profiles table.";
    }
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>