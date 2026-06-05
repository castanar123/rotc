<?php
require_once 'includes/db.php';

echo "=== DATABASE STRUCTURE ANALYSIS ===\n";
echo "Environment: " . (isProductionEnvironment() ? 'Production (SQLite)' : 'Local (MySQL)') . "\n";
echo "Database connection: " . (isset($pdo) ? 'SUCCESS' : 'FAILED') . "\n\n";

if (isset($pdo)) {
    try {
        // Get all tables
        if (isProductionEnvironment()) {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
            echo "=== SQLITE TABLES ===\n";
        } else {
            $stmt = $pdo->query("SHOW TABLES;");
            echo "=== MYSQL TABLES ===\n";
        }
        
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            echo "- $table\n";
        }
        
        echo "\n=== TABLE STRUCTURES ===\n";
        
        // Check specific tables that are causing errors
        $problematic_tables = ['attendance', 'rifles', 'reports', 'missing_ids'];
        
        foreach ($problematic_tables as $table) {
            echo "\n--- Table: $table ---\n";
            try {
                if (isProductionEnvironment()) {
                    $stmt = $pdo->query("PRAGMA table_info($table);");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($columns) {
                        foreach ($columns as $col) {
                            echo "  {$col['name']} ({$col['type']})\n";
                        }
                    } else {
                        echo "  Table does not exist\n";
                    }
                } else {
                    $stmt = $pdo->query("DESCRIBE $table;");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($columns) {
                        foreach ($columns as $col) {
                            echo "  {$col['Field']} ({$col['Type']})\n";
                        }
                    } else {
                        echo "  Table does not exist\n";
                    }
                }
            } catch (PDOException $e) {
                echo "  ERROR: " . $e->getMessage() . "\n";
            }
        }
        
        // Check for any table with 'status' column
        echo "\n=== TABLES WITH 'status' COLUMN ===\n";
        foreach ($tables as $table) {
            try {
                if (isProductionEnvironment()) {
                    $stmt = $pdo->query("PRAGMA table_info($table);");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $hasStatus = false;
                    foreach ($columns as $col) {
                        if (strtolower($col['name']) === 'status') {
                            $hasStatus = true;
                            break;
                        }
                    }
                } else {
                    $stmt = $pdo->query("DESCRIBE $table;");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $hasStatus = false;
                    foreach ($columns as $col) {
                        if (strtolower($col['Field']) === 'status') {
                            $hasStatus = true;
                            break;
                        }
                    }
                }
                
                if ($hasStatus) {
                    echo "- $table (HAS status column)\n";
                }
            } catch (PDOException $e) {
                // Table doesn't exist, skip
            }
        }
        
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Failed to connect to database\n";
}
?>