<?php

require_once 'includes/db_connection.php';

echo "=== TABLE STRUCTURE ANALYSIS ===\n\n";

try {
    // Check missing_id_requests table structure
    echo "Missing ID Requests table structure:\n";
    $stmt = $pdo->query('DESCRIBE missing_id_requests');
    while($row = $stmt->fetch()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\nAttendance table structure:\n";
    $stmt = $pdo->query('DESCRIBE attendance');
    while($row = $stmt->fetch()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    // Create missing upload directory
    echo "\n=== CREATING MISSING DIRECTORIES ===\n";
    $uploadDir = 'uploads/documents/';
    if (!file_exists($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            echo "✅ Created directory: $uploadDir\n";
        } else {
            echo "❌ Failed to create directory: $uploadDir\n";
        }
    } else {
        echo "✅ Directory already exists: $uploadDir\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>