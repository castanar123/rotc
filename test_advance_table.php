<?php
require_once 'includes/db.php';

try {
    // Check if advance_rotc_signups table exists
    $stmt = $pdo->query('DESCRIBE advance_rotc_signups');
    $columns = $stmt->fetchAll();
    
    echo "advance_rotc_signups table structure:\n";
    foreach($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
    // Test a simple insert
    echo "\nTesting insert...\n";
    $stmt = $pdo->prepare("INSERT INTO advance_rotc_signups (full_name, course, facebook_link) VALUES (?, ?, ?)");
    $result = $stmt->execute(['Test User', 'Test Course', 'https://facebook.com/test']);
    
    if ($result) {
        echo "Insert successful!\n";
        
        // Clean up test data
        $pdo->exec("DELETE FROM advance_rotc_signups WHERE full_name = 'Test User'");
        echo "Test data cleaned up.\n";
    } else {
        echo "Insert failed!\n";
    }
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    
    // If table doesn't exist, create it
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "\nCreating advance_rotc_signups table...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS `advance_rotc_signups` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `full_name` varchar(255) NOT NULL,
                `course` varchar(255) NOT NULL,
                `facebook_link` varchar(500) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_course` (`course`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $pdo->exec($sql);
            echo "Table created successfully!\n";
        } catch(Exception $e2) {
            echo "Error creating table: " . $e2->getMessage() . "\n";
        }
    }
}
?>