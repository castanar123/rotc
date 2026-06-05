<?php
/**
 * Fix condition_notes column references to use 'notes' instead
 * The rifles table has 'notes' column, not 'condition_notes'
 */

echo "🔧 FIXING CONDITION_NOTES COLUMN REFERENCES\n";
echo "==========================================\n\n";

try {
    // Connect to database
    $pdo = new PDO("mysql:host=localhost;dbname=rotc_db", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. CHECKING CURRENT TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE rifles");
    $columns = [];
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
        if (in_array($row['Field'], ['notes', 'condition_notes'])) {
            echo "  ✅ Found column: {$row['Field']} ({$row['Type']})\n";
        }
    }
    
    if (in_array('notes', $columns)) {
        echo "  ✅ 'notes' column exists - this is correct\n";
    }
    if (in_array('condition_notes', $columns)) {
        echo "  ⚠️ 'condition_notes' column also exists - this might cause confusion\n";
    }
    
    echo "\n2. TESTING OPERATIONS WITH CORRECT COLUMN NAME:\n";
    
    // Test INSERT with 'notes' column
    echo "Testing INSERT with 'notes' column...\n";
    $test_rifle_number = 'TEST-FIX-' . time();
    $stmt = $pdo->prepare("INSERT INTO rifles (rifle_number, rifle_type, status, notes) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$test_rifle_number, 'mechanical rifle', 'available', 'Test rifle for column fix']);
    
    if ($result) {
        echo "  ✅ INSERT with 'notes' column successful\n";
        $test_rifle_id = $pdo->lastInsertId();
        
        // Test UPDATE
        echo "Testing UPDATE with 'notes' column...\n";
        $stmt = $pdo->prepare("UPDATE rifles SET notes = ? WHERE id = ?");
        $result = $stmt->execute(['Updated notes for testing', $test_rifle_id]);
        
        if ($result) {
            echo "  ✅ UPDATE with 'notes' column successful\n";
        }
        
        // Test SELECT
        echo "Testing SELECT with 'notes' column...\n";
        $stmt = $pdo->prepare("SELECT rifle_number, notes FROM rifles WHERE id = ?");
        $stmt->execute([$test_rifle_id]);
        $row = $stmt->fetch();
        
        if ($row) {
            echo "  ✅ SELECT with 'notes' column successful\n";
            echo "    - Rifle: {$row['rifle_number']}\n";
            echo "    - Notes: {$row['notes']}\n";
        }
        
        // Clean up test data
        $stmt = $pdo->prepare("DELETE FROM rifles WHERE id = ?");
        $stmt->execute([$test_rifle_id]);
        echo "  ✅ Test data cleaned up\n";
        
    } else {
        echo "  ❌ INSERT with 'notes' column failed\n";
    }
    
    echo "\n3. FILES THAT NEED TO BE UPDATED:\n";
    echo "The following files use 'condition_notes' and should use 'notes':\n";
    echo "  - test_rifle_management.php\n";
    echo "  - Any other files found in the search results\n";
    
    echo "\n✅ COLUMN REFERENCE FIX ANALYSIS COMPLETED\n";
    echo "\nNext steps:\n";
    echo "1. Update all files to use 'notes' instead of 'condition_notes'\n";
    echo "2. Test rifle management operations\n";
    echo "3. Verify QR code generation works\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}