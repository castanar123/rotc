<?php

try {
    $pdo = new PDO('mysql:host=localhost', 'root', 'root');
    echo "Available databases:\n";
    $stmt = $pdo->query('SHOW DATABASES');
    while($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
    
    echo "\nChecking for ROTC-related databases:\n";
    $stmt = $pdo->query('SHOW DATABASES');
    while($row = $stmt->fetch()) {
        if (stripos($row[0], 'rotc') !== false || stripos($row[0], 'qr') !== false) {
            echo "Found: " . $row[0] . "\n";
            
            // Check tables in this database
            $pdo2 = new PDO('mysql:host=localhost;dbname=' . $row[0], 'root', 'root');
            $tables = $pdo2->query('SHOW TABLES');
            echo "  Tables: ";
            $tableList = [];
            while($table = $tables->fetch()) {
                $tableList[] = $table[0];
            }
            echo implode(', ', $tableList) . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>