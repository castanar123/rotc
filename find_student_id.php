<?php
require_once 'includes/db.php';

echo "<h3>Searching for existing student_id '0425-0425'</h3>";

try {
    // Find the exact record
    $stmt = $pdo->prepare("SELECT id, student_id, first_name, last_name, email, created_at FROM cadet_profiles WHERE student_id = ?");
    $stmt->execute(['0425-0425']);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo "<h4>Found existing record:</h4>";
        echo "<pre>";
        echo "ID: " . $record['id'] . "\n";
        echo "Student ID: " . $record['student_id'] . "\n";
        echo "Name: " . $record['first_name'] . ' ' . $record['last_name'] . "\n";
        echo "Email: " . $record['email'] . "\n";
        echo "Created: " . $record['created_at'] . "\n";
        echo "</pre>";
        
        // Check if there are any similar records
        echo "<h4>Checking for similar student_ids:</h4>";
        $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM cadet_profiles WHERE student_id LIKE ? ORDER BY student_id");
        $stmt->execute(['%0425%']);
        $similar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($similar as $row) {
            echo "- " . $row['student_id'] . " (" . $row['first_name'] . ' ' . $row['last_name'] . ")<br>";
        }
    } else {
        echo "<h4>No record found for '0425-0425'</h4>";
    }
    
} catch (Exception $e) {
    echo "<h4>Error:</h4>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
