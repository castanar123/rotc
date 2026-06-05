<?php
/**
 * Rifle Database Migration Tool
 * Adds rifle_type column and cleans up existing data
 */

require_once 'includes/db.php';

// Function to execute SQL file
function executeSQLFile($pdo, $filename) {
    $results = [];
    
    if (!file_exists($filename)) {
        return ['error' => "File not found: $filename"];
    }
    
    $sql = file_get_contents($filename);
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $stmt = $pdo->prepare($statement);
            $stmt->execute();
            
            // If it's a SELECT statement, fetch results
            if (stripos($statement, 'SELECT') === 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results[] = [
                    'query' => substr($statement, 0, 100) . '...',
                    'data' => $result
                ];
            } else {
                $results[] = [
                    'query' => substr($statement, 0, 100) . '...',
                    'status' => 'executed successfully'
                ];
            }
        } catch (PDOException $e) {
            $results[] = [
                'query' => substr($statement, 0, 100) . '...',
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}

// Check if migration should be run
$runMigration = isset($_POST['run_migration']);
$runCleanup = isset($_POST['run_cleanup']);
$checkStatus = isset($_POST['check_status']) || (!$runMigration && !$runCleanup);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifle Database Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .button { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .button:hover { background: #2980b9; }
        .button.danger { background: #e74c3c; }
        .button.danger:hover { background: #c0392b; }
        .button.success { background: #27ae60; }
        .button.success:hover { background: #229954; }
        .result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔫 Rifle Database Migration Tool</h1>
            <p>Add rifle_type column and clean up existing rifle data</p>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong> This migration will modify your database structure. 
            Please backup your database before proceeding!
        </div>

        <form method="post">
            <button type="submit" name="check_status" class="button">📊 Check Current Status</button>
            <button type="submit" name="run_migration" class="button success">🚀 Run Migration (Add rifle_type column)</button>
            <button type="submit" name="run_cleanup" class="button danger">🧹 Run Cleanup (Organize data)</button>
        </form>

        <?php if ($checkStatus): ?>
            <div class="result info">
                <h3>📋 Current Database Status</h3>
                <?php
                try {
                    // Check if rifle_type column exists
                    $stmt = $pdo->query("DESCRIBE rifles");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $hasRifleType = false;
                    echo "<h4>Current Table Structure:</h4>";
                    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                    foreach ($columns as $column) {
                        if ($column['Field'] === 'rifle_type') {
                            $hasRifleType = true;
                        }
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
                        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
                        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
                        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
                        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    if ($hasRifleType) {
                        echo "<div class='result success'>✅ rifle_type column already exists!</div>";
                        
                        // Show current rifle data
                        $stmt = $pdo->query("SELECT id, rifle_number, rifle_type, status FROM rifles ORDER BY rifle_type, rifle_number");
                        $rifles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<h4>Current Rifle Data:</h4>";
                        echo "<table><tr><th>ID</th><th>Rifle Number</th><th>Type</th><th>Status</th></tr>";
                        foreach ($rifles as $rifle) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($rifle['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($rifle['rifle_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($rifle['rifle_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($rifle['status']) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                        
                        // Show summary
                        $stmt = $pdo->query("SELECT rifle_type, COUNT(*) as count FROM rifles GROUP BY rifle_type");
                        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<h4>Summary by Type:</h4>";
                        echo "<table><tr><th>Rifle Type</th><th>Count</th></tr>";
                        foreach ($summary as $row) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['rifle_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['count']) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<div class='result error'>❌ rifle_type column does not exist. Migration needed!</div>";
                    }
                    
                } catch (PDOException $e) {
                    echo "<div class='result error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($runMigration): ?>
            <div class="result info">
                <h3>🚀 Running Migration...</h3>
                <?php
                $results = executeSQLFile($pdo, 'migrations/add_rifle_type_column.sql');
                foreach ($results as $result) {
                    if (isset($result['error'])) {
                        echo "<div class='result error'>❌ " . htmlspecialchars($result['error']) . "</div>";
                    } elseif (isset($result['data'])) {
                        echo "<div class='result success'>✅ Query executed successfully</div>";
                        if (!empty($result['data'])) {
                            echo "<table><tr>";
                            foreach (array_keys($result['data'][0]) as $header) {
                                echo "<th>" . htmlspecialchars($header) . "</th>";
                            }
                            echo "</tr>";
                            foreach ($result['data'] as $row) {
                                echo "<tr>";
                                foreach ($row as $value) {
                                    echo "<td>" . htmlspecialchars($value) . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</table>";
                        }
                    } else {
                        echo "<div class='result success'>✅ " . htmlspecialchars($result['status']) . "</div>";
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($runCleanup): ?>
            <div class="result info">
                <h3>🧹 Running Cleanup...</h3>
                <?php
                $results = executeSQLFile($pdo, 'migrations/cleanup_rifle_data.sql');
                foreach ($results as $result) {
                    if (isset($result['error'])) {
                        echo "<div class='result error'>❌ " . htmlspecialchars($result['error']) . "</div>";
                    } elseif (isset($result['data'])) {
                        echo "<div class='result success'>✅ Query executed successfully</div>";
                        if (!empty($result['data'])) {
                            echo "<table><tr>";
                            foreach (array_keys($result['data'][0]) as $header) {
                                echo "<th>" . htmlspecialchars($header) . "</th>";
                            }
                            echo "</tr>";
                            foreach ($result['data'] as $row) {
                                echo "<tr>";
                                foreach ($row as $value) {
                                    echo "<td>" . htmlspecialchars($value) . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</table>";
                        }
                    } else {
                        echo "<div class='result success'>✅ " . htmlspecialchars($result['status']) . "</div>";
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="info" style="margin-top: 20px;">
            <h4>📝 Migration Steps:</h4>
            <ol>
                <li><strong>Check Status:</strong> Review current database structure</li>
                <li><strong>Run Migration:</strong> Add rifle_type column with ENUM constraints</li>
                <li><strong>Run Cleanup:</strong> Organize existing data and set proper types</li>
            </ol>
            
            <h4>🎯 Expected Results:</h4>
            <ul>
                <li>Rifles with 'R' prefix (R001, R002, etc.) → mechanical rifle</li>
                <li>Rifles with numeric only (5454, 102, etc.) → wooden rifle</li>
                <li>Rifles with 'TEST' prefix → mechanical rifle</li>
                <li>Remove duplicate rifle numbers</li>
                <li>Clean up invalid status values</li>
            </ul>
        </div>
    </div>
</body>
</html>