<?php
require_once 'includes/db.php';

echo "<h3>Checking student_id unique constraint in cadet_profiles table</h3>";

try {
    // Get table structure
    $stmt = $pdo->query("SHOW CREATE TABLE cadet_profiles");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h4>Current table structure:</h4>";
    echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    
    // Check indexes on student_id
    echo "<h4>Indexes on student_id:</h4>";
    $stmt = $pdo->query("SHOW INDEX FROM cadet_profiles WHERE Column_name = 'student_id'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($indexes as $index) {
        echo "<pre>";
        echo "Key_name: " . $index['Key_name'] . "\n";
        echo "Column_name: " . $index['Column_name'] . "\n";
        echo "Non_unique: " . $index['Non_unique'] . "\n";
        echo "Sub_part: " . ($index['Sub_part'] ?? 'NULL') . "\n";
        echo "Index_type: " . $index['Index_type'] . "\n";
        echo "</pre>";
    }
    
    // Test some sample student_ids
    echo "<h4>Testing sample student_ids:</h4>";
    $testIds = ['0425-0425', '2025-0425', 'TEST-001', '12345678'];
    
    foreach ($testIds as $testId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cadet_profiles WHERE student_id = ?");
        $stmt->execute([$testId]);
        $count = $stmt->fetch()['count'];
        echo "Student ID '$testId': " . ($count > 0 ? "EXISTS ($count records)" : "Available") . "<br>";
    }
    
    // Check column definition
    echo "<h4>Student ID column definition:</h4>";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'student_id'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    echo "Field: " . $column['Field'] . "\n";
    echo "Type: " . $column['Type'] . "\n";
    echo "Null: " . $column['Null'] . "\n";
    echo "Key: " . $column['Key'] . "\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h4>Error:</h4>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
