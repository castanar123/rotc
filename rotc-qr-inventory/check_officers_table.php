<?php
require_once 'includes/db.php';

try {
    echo "Structure of officers table:\n";
    $desc_stmt = $pdo->query("DESCRIBE officers");
    $columns = $desc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "  {$column['Field']} ({$column['Type']})";
        if ($column['Null'] === 'NO') echo " NOT NULL";
        if ($column['Key'] === 'PRI') echo " PRIMARY KEY";
        if ($column['Key'] === 'UNI') echo " UNIQUE";
        if ($column['Default'] !== null) echo " DEFAULT '{$column['Default']}'";
        if ($column['Extra']) echo " {$column['Extra']}";
        echo "\n";
    }
    
    echo "\nSample data from officers table:\n";
    $sample_stmt = $pdo->query("SELECT * FROM officers LIMIT 2");
    $sample_data = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sample_data)) {
        echo "(No data)\n";
    } else {
        foreach ($sample_data as $row) {
            echo "Row: " . json_encode($row) . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>