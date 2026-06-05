<?php
// Check officers table structure in rotc_db

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING OFFICERS TABLE STRUCTURE ===\n";
    
    // Check if officers table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'officers'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Officers table exists\n\n";
        
        // Show table structure
        echo "OFFICERS TABLE STRUCTURE:\n";
        $stmt = $pdo->query("DESCRIBE officers");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
        }
        
        echo "\n=== CHECKING FOR RANK_POSITION COLUMN ===\n";
        $hasRankPosition = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'rank_position') {
                $hasRankPosition = true;
                echo "✓ rank_position column EXISTS\n";
                break;
            }
        }
        
        if (!$hasRankPosition) {
            echo "✗ rank_position column MISSING\n";
            echo "\nAvailable columns that might be used instead:\n";
            foreach ($columns as $column) {
                if (strpos($column['Field'], 'rank') !== false || strpos($column['Field'], 'position') !== false) {
                    echo "- {$column['Field']}\n";
                }
            }
        }
        
        echo "\n=== SAMPLE DATA ===\n";
        $stmt = $pdo->query("SELECT * FROM officers LIMIT 3");
        $officers = $stmt->fetchAll();
        
        if ($officers) {
            foreach ($officers as $officer) {
                echo "Officer ID: {$officer['id']}\n";
                foreach ($officer as $key => $value) {
                    echo "  {$key}: {$value}\n";
                }
                echo "\n";
            }
        } else {
            echo "No officers found in table\n";
        }
        
    } else {
        echo "✗ Officers table does NOT exist\n";
        
        echo "\nAvailable tables in rotc_db:\n";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        foreach ($tables as $table) {
            echo "- {$table[0]}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>