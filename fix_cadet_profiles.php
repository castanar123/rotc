<?php
require_once 'includes/db.php';

echo "=== Fixing Cadet Profiles Table Structure ===\n\n";

try {
    // 1. Check current cadet_profiles structure
    echo "1. Current cadet_profiles table structure:\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "   {$column['Field']} - {$column['Type']} - Default: {$column['Default']}\n";
    }
    
    // 2. Check if we need to modify the table structure
    $column_names = array_column($columns, 'Field');
    
    $changes_needed = [];
    
    // Check for full_name vs separate names
    if (in_array('full_name', $column_names)) {
        $changes_needed[] = "Replace 'full_name' with separate first_name, middle_name, last_name";
    }
    
    if (!in_array('first_name', $column_names)) {
        $changes_needed[] = "Add first_name column";
    }
    
    if (!in_array('middle_name', $column_names)) {
        $changes_needed[] = "Add middle_name column";
    }
    
    if (!in_array('last_name', $column_names)) {
        $changes_needed[] = "Add last_name column";
    }
    
    if (!in_array('status', $column_names)) {
        $changes_needed[] = "Add status column with default 'active'";
    }
    
    echo "\n2. Changes needed:\n";
    if (empty($changes_needed)) {
        echo "   No changes needed - structure looks good!\n";
    } else {
        foreach ($changes_needed as $change) {
            echo "   - {$change}\n";
        }
    }
    
    // 3. Apply fixes if needed
    if (!empty($changes_needed)) {
        echo "\n3. Applying fixes...\n";
        
        // Add missing columns
        if (!in_array('first_name', $column_names)) {
            $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN first_name VARCHAR(100) AFTER user_id");
            echo "   ✓ Added first_name column\n";
        }
        
        if (!in_array('middle_name', $column_names)) {
            $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN middle_name VARCHAR(100) AFTER first_name");
            echo "   ✓ Added middle_name column\n";
        }
        
        if (!in_array('last_name', $column_names)) {
            $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN last_name VARCHAR(100) AFTER middle_name");
            echo "   ✓ Added last_name column\n";
        }
        
        if (!in_array('status', $column_names)) {
            $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
            echo "   ✓ Added status column with default 'active'\n";
        }
        
        // If full_name exists, try to split it into separate names
        if (in_array('full_name', $column_names)) {
            echo "   Migrating full_name data to separate name fields...\n";
            
            $stmt = $pdo->query("SELECT id, full_name FROM cadet_profiles WHERE full_name IS NOT NULL AND full_name != ''");
            $profiles = $stmt->fetchAll();
            
            foreach ($profiles as $profile) {
                $names = explode(' ', trim($profile['full_name']));
                $first_name = $names[0] ?? '';
                $middle_name = '';
                $last_name = '';
                
                if (count($names) == 2) {
                    $last_name = $names[1];
                } elseif (count($names) >= 3) {
                    $middle_name = $names[1];
                    $last_name = implode(' ', array_slice($names, 2));
                }
                
                $update_stmt = $pdo->prepare("
                    UPDATE cadet_profiles 
                    SET first_name = ?, middle_name = ?, last_name = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$first_name, $middle_name, $last_name, $profile['id']]);
            }
            
            echo "   ✓ Migrated " . count($profiles) . " full_name records\n";
        }
    }
    
    // 4. Show final structure
    echo "\n4. Final cadet_profiles table structure:\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $final_columns = $stmt->fetchAll();
    
    foreach ($final_columns as $column) {
        echo "   {$column['Field']} - {$column['Type']} - Default: {$column['Default']}\n";
    }
    
    // 5. Test sample data
    echo "\n5. Sample cadet profiles data:\n";
    $stmt = $pdo->query("
        SELECT cp.id, cp.first_name, cp.middle_name, cp.last_name, cp.student_number, cp.status,
               u.approval_status, u.status as user_status
        FROM cadet_profiles cp
        JOIN users u ON cp.user_id = u.id
        LIMIT 5
    ");
    $samples = $stmt->fetchAll();
    
    if (empty($samples)) {
        echo "   No cadet profiles found\n";
    } else {
        foreach ($samples as $sample) {
            $full_name = trim($sample['first_name'] . ' ' . $sample['middle_name'] . ' ' . $sample['last_name']);
            echo "   ID: {$sample['id']} - Name: {$full_name} - Student: {$sample['student_number']} - Status: {$sample['status']} - Approval: {$sample['approval_status']}\n";
        }
    }
    
    echo "\n=== Cadet Profiles Fix Complete ===\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}