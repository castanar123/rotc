<?php
require_once 'includes/db.php';

try {
    echo "=== CADET_PROFILES TABLE COLUMNS ===\n";
    $stmt = $pdo->query('DESCRIBE cadet_profiles');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' (' . $row['Type'] . ')' . "\n";
    }
    
    echo "\n=== CHECKING FOR FACEBOOK_PROFILE COLUMN ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'facebook_profile'");
    if ($stmt->rowCount() > 0) {
        echo "✅ facebook_profile column EXISTS\n";
    } else {
        echo "❌ facebook_profile column DOES NOT EXIST\n";
    }
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}