<?php
require_once 'includes/db.php';

echo "=== RIFLE MANAGEMENT SYSTEM TEST ===\n";

try {
    // Check rifles table structure
    echo "\n1. CHECKING RIFLES TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE rifles");
    $columns = $stmt->fetchAll();
    
    echo "Current columns in rifles table:\n";
    $has_rifle_type = false;
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - {$column['Null']} - Default: {$column['Default']}\n";
        if ($column['Field'] === 'rifle_type') {
            $has_rifle_type = true;
        }
    }
    
    if (!$has_rifle_type) {
        echo "❌ MISSING: rifle_type column not found!\n";
    } else {
        echo "✅ rifle_type column exists\n";
    }
    
    // Test basic rifle operations
    echo "\n2. TESTING RIFLE OPERATIONS:\n";
    
    // Test INSERT operation
    echo "Testing INSERT operation...\n";
    try {
        if ($has_rifle_type) {
            $stmt = $pdo->prepare("INSERT INTO rifles (rifle_number, rifle_type, serial_number, model, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute(['TEST-001', 'mechanical rifle', 'SN-TEST-001', 'Test Model', 'available', 'Test rifle for management testing']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO rifles (rifle_number, serial_number, model, status, notes) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute(['TEST-001', 'SN-TEST-001', 'Test Model', 'available', 'Test rifle for management testing']);
        }
        echo "✅ INSERT operation successful\n";
        $test_rifle_id = $pdo->lastInsertId();
    } catch (PDOException $e) {
        echo "❌ INSERT failed: " . $e->getMessage() . "\n";
        $test_rifle_id = null;
    }
    
    // Test SELECT operation
    echo "Testing SELECT operation...\n";
    try {
        $stmt = $pdo->query("SELECT * FROM rifles LIMIT 3");
        $rifles = $stmt->fetchAll();
        echo "✅ SELECT operation successful - Found " . count($rifles) . " rifles\n";
        
        if (!empty($rifles)) {
            echo "Sample rifle data:\n";
            foreach ($rifles as $rifle) {
                $type_info = isset($rifle['rifle_type']) ? ", Type: {$rifle['rifle_type']}" : "";
                echo "  - ID: {$rifle['id']}, Number: {$rifle['rifle_number']}, Status: {$rifle['status']}{$type_info}\n";
            }
        }
    } catch (PDOException $e) {
        echo "❌ SELECT failed: " . $e->getMessage() . "\n";
    }
    
    // Test UPDATE operation
    if ($test_rifle_id) {
        echo "Testing UPDATE operation...\n";
        try {
            $stmt = $pdo->prepare("UPDATE rifles SET notes = ? WHERE id = ?");
            $stmt->execute(['Updated notes for testing', $test_rifle_id]);
            echo "✅ UPDATE operation successful\n";
        } catch (PDOException $e) {
            echo "❌ UPDATE failed: " . $e->getMessage() . "\n";
        }
    }
    
    // Test DELETE operation (cleanup)
    if ($test_rifle_id) {
        echo "Testing DELETE operation (cleanup)...\n";
        try {
            $stmt = $pdo->prepare("DELETE FROM rifles WHERE id = ?");
            $stmt->execute([$test_rifle_id]);
            echo "✅ DELETE operation successful\n";
        } catch (PDOException $e) {
            echo "❌ DELETE failed: " . $e->getMessage() . "\n";
        }
    }
    
    // Check related tables
    echo "\n3. CHECKING RELATED TABLES:\n";
    $related_tables = ['rifle_assignments', 'rifle_logs', 'rifle_borrowings'];
    
    foreach ($related_tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            echo "✅ Table '$table' exists with " . count($columns) . " columns\n";
        } catch (PDOException $e) {
            echo "❌ Table '$table' error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎯 RIFLE MANAGEMENT TEST COMPLETED\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
}
?>