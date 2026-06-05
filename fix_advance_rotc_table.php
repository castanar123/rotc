<?php
require_once 'includes/db.php';

try {
    echo "Fixing advance_rotc_signups table structure...\n";
    
    // Drop the existing table with wrong structure
    $pdo->exec("DROP TABLE IF EXISTS advance_rotc_signups");
    echo "Dropped existing table.\n";
    
    // Create the correct table structure
    $sql = "
        CREATE TABLE `advance_rotc_signups` (
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
    
    $pdo->exec($sql);
    echo "Created correct table structure.\n";
    
    // Test the table structure
    $stmt = $pdo->query('DESCRIBE advance_rotc_signups');
    $columns = $stmt->fetchAll();
    
    echo "\nNew table structure:\n";
    foreach($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
    // Test insert
    echo "\nTesting insert...\n";
    $stmt = $pdo->prepare("INSERT INTO advance_rotc_signups (full_name, course, facebook_link) VALUES (?, ?, ?)");
    $result = $stmt->execute(['Test User', 'Test Course', 'https://facebook.com/test']);
    
    if ($result) {
        echo "Insert successful!\n";
        
        // Test select
        $stmt = $pdo->query("SELECT * FROM advance_rotc_signups ORDER BY created_at DESC");
        $data = $stmt->fetchAll();
        echo "Select successful! Found " . count($data) . " records.\n";
        
        // Clean up test data
        $pdo->exec("DELETE FROM advance_rotc_signups WHERE full_name = 'Test User'");
        echo "Test data cleaned up.\n";
    } else {
        echo "Insert failed!\n";
    }
    
    echo "\n✅ advance_rotc_signups table fixed successfully!\n";
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>