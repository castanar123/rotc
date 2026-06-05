<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUGGING CADET DATA ===\n\n";
    
    // Check if cadets table exists
    $tables = $pdo->query("SHOW TABLES")->fetchAll();
    echo "Available tables in rotc_db:\n";
    foreach($tables as $table) {
        echo "- " . $table[0] . "\n";
    }
    
    // Check cadets table structure
    echo "\nCadets table structure:\n";
    $structure = $pdo->query("DESCRIBE cadets")->fetchAll();
    foreach($structure as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
    }
    
    // Count total records
    $total_count = $pdo->query("SELECT COUNT(*) as count FROM cadets")->fetch();
    echo "\nTotal cadets in database: {$total_count['count']}\n";
    
    // Count by status
    $status_counts = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM cadets 
        GROUP BY status
    ")->fetchAll();
    
    echo "\nCadets by status:\n";
    foreach($status_counts as $row) {
        echo "- {$row['status']}: {$row['count']}\n";
    }
    
    // Count by MS level and gender
    $ms_gender_counts = $pdo->query("
        SELECT ms_level, gender, COUNT(*) as count 
        FROM cadets 
        GROUP BY ms_level, gender 
        ORDER BY ms_level, gender
    ")->fetchAll();
    
    echo "\nCadets by MS level and gender:\n";
    foreach($ms_gender_counts as $row) {
        echo "- {$row['ms_level']} {$row['gender']}: {$row['count']}\n";
    }
    
    // Show sample records
    echo "\nSample cadet records (first 5):\n";
    $samples = $pdo->query("
        SELECT id, last_name, first_name, ms_level, gender, status 
        FROM cadets 
        LIMIT 5
    ")->fetchAll();
    
    foreach($samples as $cadet) {
        echo "- ID: {$cadet['id']}, Name: {$cadet['first_name']} {$cadet['last_name']}, MS: {$cadet['ms_level']}, Gender: {$cadet['gender']}, Status: {$cadet['status']}\n";
    }
    
    // Test the exact query from document generation
    echo "\nTesting document generation query:\n";
    $doc_query = "
        SELECT 
            ms_level,
            gender,
            COUNT(*) as count
        FROM cadets 
        WHERE status = 'active'
        GROUP BY ms_level, gender
        ORDER BY ms_level, gender
    ";
    
    $doc_results = $pdo->query($doc_query)->fetchAll();
    echo "Document query results:\n";
    foreach($doc_results as $row) {
        echo "- {$row['ms_level']} {$row['gender']}: {$row['count']}\n";
    }
    
    if(empty($doc_results)) {
        echo "No results from document query - checking why...\n";
        
        // Check what status values exist
        $status_values = $pdo->query("SELECT DISTINCT status FROM cadets")->fetchAll();
        echo "Distinct status values:\n";
        foreach($status_values as $status) {
            echo "- '{$status['status']}'\n";
        }
        
        // Check without status filter
        $no_filter_query = "
            SELECT 
                ms_level,
                gender,
                COUNT(*) as count
            FROM cadets 
            GROUP BY ms_level, gender
            ORDER BY ms_level, gender
        ";
        
        $no_filter_results = $pdo->query($no_filter_query)->fetchAll();
        echo "\nQuery without status filter:\n";
        foreach($no_filter_results as $row) {
            echo "- {$row['ms_level']} {$row['gender']}: {$row['count']}\n";
       }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>