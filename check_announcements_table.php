<?php
require_once 'includes/db.php';

try {
    // Check if announcements table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    if ($stmt->rowCount() > 0) {
        echo "Announcements table exists.\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE announcements");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nTable structure:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM announcements LIMIT 3");
        $sample_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nSample data (first 3 rows):\n";
        foreach ($sample_data as $row) {
            echo json_encode($row) . "\n";
        }
        
        // Count total records
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM announcements");
        $total = $stmt->fetch()['total'];
        echo "\nTotal announcements: $total\n";
        
    } else {
        echo "Announcements table does not exist.\n";
        
        // Check what tables do exist
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "\nExisting tables:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Save results to file
ob_start();
include __FILE__;
$output = ob_get_clean();
file_put_contents('announcements_check_results.txt', $output);
echo "Results saved to announcements_check_results.txt";
?>