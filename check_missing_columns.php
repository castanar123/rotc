<?php
require_once 'includes/db.php';

echo "=== CHECKING FOR MISSING COLUMNS ===\n\n";

try {
    // Check for beneficiary_address column
    echo "1. CHECKING FOR BENEFICIARY_ADDRESS COLUMN:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'beneficiary_address'");
    $beneficiary_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($beneficiary_cols) > 0) {
        echo "✅ beneficiary_address column EXISTS\n";
        foreach ($beneficiary_cols as $col) {
            echo "  - Field: {$col['Field']}, Type: {$col['Type']}\n";
        }
    } else {
        echo "❌ beneficiary_address column MISSING\n";
    }
    
    // Check for region column
    echo "\n2. CHECKING FOR REGION COLUMN:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'region'");
    $region_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($region_cols) > 0) {
        echo "✅ region column EXISTS\n";
        foreach ($region_cols as $col) {
            echo "  - Field: {$col['Field']}, Type: {$col['Type']}\n";
        }
    } else {
        echo "❌ region column MISSING\n";
    }
    
    // Check for beneficiary_relationship column
    echo "\n3. CHECKING FOR BENEFICIARY_RELATIONSHIP COLUMN:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'beneficiary_relationship'");
    $relationship_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($relationship_cols) > 0) {
        echo "✅ beneficiary_relationship column EXISTS\n";
        foreach ($relationship_cols as $col) {
            echo "  - Field: {$col['Field']}, Type: {$col['Type']}\n";
        }
    } else {
        echo "❌ beneficiary_relationship column MISSING\n";
    }
    
    // Check all beneficiary-related columns
    echo "\n4. ALL BENEFICIARY-RELATED COLUMNS:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles WHERE Field LIKE '%beneficiary%'");
    $all_beneficiary_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($all_beneficiary_cols) > 0) {
        foreach ($all_beneficiary_cols as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "  No beneficiary-related columns found\n";
    }
    
    // Check if we need to add missing columns
    echo "\n5. ADDING MISSING COLUMNS:\n";
    
    if (count($beneficiary_cols) == 0) {
        echo "Adding beneficiary_address column...\n";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN beneficiary_address TEXT");
        echo "✅ beneficiary_address column added\n";
    }
    
    if (count($region_cols) == 0) {
        echo "Adding region column...\n";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN region VARCHAR(100)");
        echo "✅ region column added\n";
    }
    
    if (count($relationship_cols) == 0) {
        echo "Adding beneficiary_relationship column...\n";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN beneficiary_relationship VARCHAR(100)");
        echo "✅ beneficiary_relationship column added\n";
    }
    
    // Verify columns were added
    echo "\n6. VERIFICATION AFTER ADDING COLUMNS:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cadet_profiles WHERE Field IN ('beneficiary_address', 'region', 'beneficiary_relationship')");
    $final_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($final_cols as $col) {
        echo "✅ {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n🎯 COLUMN CHECK COMPLETED\n";
?>