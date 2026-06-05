<?php
/**
 * Fix Remaining Database Schema Issues
 * Handles foreign key constraints and missing defaults
 */

require_once 'includes/db.php';

echo "=== FIXING REMAINING DATABASE SCHEMA ISSUES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Fix 1: Handle rifle_assignments foreign key constraint issue
echo "1. FIXING RIFLE_ASSIGNMENTS FOREIGN KEY CONSTRAINT\n";
echo "==================================================\n";
try {
    // First, check current foreign keys
    $result = $link->query("
        SELECT 
            CONSTRAINT_NAME, 
            COLUMN_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'rifle_assignments' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    echo "   Current foreign keys:\n";
    $foreign_keys = [];
    while ($row = $result->fetch_assoc()) {
        echo "     {$row['CONSTRAINT_NAME']}: {$row['COLUMN_NAME']} -> {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
        $foreign_keys[] = $row;
    }
    
    // Drop foreign key constraint that references cadet_id
    foreach ($foreign_keys as $fk) {
        if ($fk['COLUMN_NAME'] === 'cadet_id') {
            echo "   Dropping foreign key constraint: {$fk['CONSTRAINT_NAME']}\n";
            $link->query("ALTER TABLE rifle_assignments DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
            echo "✓ Dropped foreign key constraint\n";
        }
    }
    
    // Now rename the column
    $result = $link->query("DESCRIBE rifle_assignments");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    if (in_array('cadet_id', $columns) && !in_array('cadet_profile_id', $columns)) {
        echo "   Renaming cadet_id to cadet_profile_id...\n";
        $link->query("ALTER TABLE rifle_assignments CHANGE cadet_id cadet_profile_id INT NOT NULL");
        echo "✓ Renamed cadet_id to cadet_profile_id\n";
        
        // Re-add the foreign key constraint with correct column name
        echo "   Re-adding foreign key constraint...\n";
        $link->query("
            ALTER TABLE rifle_assignments 
            ADD CONSTRAINT fk_rifle_assignments_cadet_profile 
            FOREIGN KEY (cadet_profile_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE
        ");
        echo "✓ Re-added foreign key constraint\n";
    } else {
        echo "✓ Column already correctly named\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error fixing rifle_assignments: " . $e->getMessage() . "\n";
}

// Fix 2: Handle rifles table model column default
echo "\n2. FIXING RIFLES TABLE MODEL COLUMN\n";
echo "===================================\n";
try {
    // Check rifles table structure
    $result = $link->query("DESCRIBE rifles");
    $columns_info = [];
    while ($row = $result->fetch_assoc()) {
        $columns_info[$row['Field']] = $row;
        if ($row['Field'] === 'model') {
            echo "   Current model column: {$row['Type']}, Null: {$row['Null']}, Default: {$row['Default']}\n";
        }
    }
    
    // Fix model column to allow NULL or have default
    if (isset($columns_info['model'])) {
        if ($columns_info['model']['Null'] === 'NO' && $columns_info['model']['Default'] === null) {
            echo "   Modifying model column to allow NULL...\n";
            $link->query("ALTER TABLE rifles MODIFY COLUMN model VARCHAR(100) NULL");
            echo "✓ Fixed model column to allow NULL\n";
        } else {
            echo "✓ model column already allows NULL or has default\n";
        }
    } else {
        echo "   Adding model column...\n";
        $link->query("ALTER TABLE rifles ADD COLUMN model VARCHAR(100) NULL AFTER rifle_type");
        echo "✓ Added model column\n";
    }
    
    // Also fix serial_number if needed
    if (isset($columns_info['serial_number'])) {
        if ($columns_info['serial_number']['Null'] === 'NO' && $columns_info['serial_number']['Default'] === null) {
            echo "   Modifying serial_number column to allow NULL...\n";
            $link->query("ALTER TABLE rifles MODIFY COLUMN serial_number VARCHAR(100) NULL");
            echo "✓ Fixed serial_number column to allow NULL\n";
        } else {
            echo "✓ serial_number column already allows NULL or has default\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error fixing rifles table: " . $e->getMessage() . "\n";
}

// Fix 3: Test rifle insert with all fixed columns
echo "\n3. TESTING FIXED RIFLE INSERT\n";
echo "=============================\n";
try {
    $test_rifle_number = 'TEST-FIXED-' . time();
    $stmt = $link->prepare("INSERT INTO rifles (rifle_number, rifle_type, status, created_at) VALUES (?, 'mechanical rifle', 'available', NOW())");
    $stmt->bind_param("s", $test_rifle_number);
    $stmt->execute();
    $new_rifle_id = $link->insert_id;
    echo "✓ Successfully inserted rifle: {$test_rifle_number} (ID: {$new_rifle_id})\n";
    
    // Clean up test rifle
    $stmt = $link->prepare("DELETE FROM rifles WHERE id = ?");
    $stmt->bind_param("i", $new_rifle_id);
    $stmt->execute();
    echo "✓ Cleaned up test rifle\n";
    
} catch (Exception $e) {
    echo "❌ Fixed rifle insert error: " . $e->getMessage() . "\n";
}

// Fix 4: Test rifle assignment with correct column names
echo "\n4. TESTING RIFLE ASSIGNMENT\n";
echo "===========================\n";
try {
    // Get a sample rifle and cadet for testing
    $rifle_stmt = $link->prepare("SELECT id, rifle_number FROM rifles WHERE status = 'available' LIMIT 1");
    $rifle_stmt->execute();
    $rifle_result = $rifle_stmt->get_result();
    
    $cadet_stmt = $link->prepare("
        SELECT cp.id, cp.first_name, cp.last_name, u.id as user_id
        FROM cadet_profiles cp 
        JOIN users u ON cp.user_id = u.id 
        WHERE u.approval_status = 'approved' 
        AND u.role = 'basic-cadet' 
        LIMIT 1
    ");
    $cadet_stmt->execute();
    $cadet_result = $cadet_stmt->get_result();
    
    if ($rifle_result->num_rows > 0 && $cadet_result->num_rows > 0) {
        $rifle = $rifle_result->fetch_assoc();
        $cadet = $cadet_result->fetch_assoc();
        
        echo "   Testing assignment: Rifle {$rifle['rifle_number']} to {$cadet['first_name']} {$cadet['last_name']}\n";
        
        // Test assignment insert
        $assign_stmt = $link->prepare("
            INSERT INTO rifle_assignments 
            (rifle_id, cadet_profile_id, assigned_by, assigned_at, status, notes) 
            VALUES (?, ?, ?, NOW(), 'active', 'Test assignment')
        ");
        $admin_id = 1; // Assuming admin user ID is 1
        $assign_stmt->bind_param("iii", $rifle['id'], $cadet['id'], $admin_id);
        $assign_stmt->execute();
        $assignment_id = $link->insert_id;
        
        echo "✓ Successfully created test assignment (ID: {$assignment_id})\n";
        
        // Clean up test assignment
        $cleanup_stmt = $link->prepare("DELETE FROM rifle_assignments WHERE id = ?");
        $cleanup_stmt->bind_param("i", $assignment_id);
        $cleanup_stmt->execute();
        echo "✓ Cleaned up test assignment\n";
        
    } else {
        echo "⚠️ No sample data available for testing assignment\n";
        echo "   Available rifles: {$rifle_result->num_rows}\n";
        echo "   Approved cadets: {$cadet_result->num_rows}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Rifle assignment test error: " . $e->getMessage() . "\n";
}

echo "\n=== REMAINING SCHEMA FIXES COMPLETED ===\n";
echo "All database