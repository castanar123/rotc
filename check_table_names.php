<?php
require_once 'includes/db.php';

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING TABLE NAMES ===\n";
    
    // Check for both singular and plural forms
    $tables_to_check = ['cadet_profile', 'cadet_profiles'];
    
    foreach ($tables_to_check as $table_name) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table_name]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "✅ Table '$table_name' EXISTS\n";
            
            // Show columns for existing table
            $stmt = $pdo->prepare("DESCRIBE `$table_name`");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Columns in '$table_name':\n";
            foreach ($columns as $column) {
                echo "  - {$column['Field']} ({$column['Type']})\n";
            }
            echo "\n";
        } else {
            echo "❌ Table '$table_name' does NOT exist\n";
        }
    }
    
    // Check what queries are actually using
    echo "=== SEARCHING FOR TABLE REFERENCES IN CODE ===\n";
    
    // This will help us understand which table name is being used
    $files_to_check = glob('*.php');
    $patterns = ['cadet_profile', 'cadet_profiles'];
    
    foreach ($patterns as $pattern) {
        echo "\nSearching for '$pattern' references:\n";
        foreach ($files_to_check as $file) {
            $content = file_get_contents($file);
            if (stripos($content, $pattern) !== false) {
                // Count occurrences
                $count = substr_count(strtolower($content), strtolower($pattern));
                echo "  - $file: $count occurrences\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>