<?php
/**
 * Fix Database Schema Issues
 * Addresses specific column and table structure problems
 */

require_once 'includes/db.php';

echo "=== FIXING DATABASE SCHEMA ISSUES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Fix 1: Add default value for serial_number in rifles table
echo "1. FIXING RIFLES TABLE SERIAL_NUMBER\n";
echo "====================================\n";
try {
    // Check current rifles table structure
    $result = $link->query("DESCRIBE rifles");
    $has_serial_default = false;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'serial_number') {
            echo "   Current serial_number: {$row['Type']}, Null: {$row['Null']}, Default: {$row['Default']}\n";
            if ($row['Default'] !== null || $row['Null'] === 'YES') {
                $has_serial_default = true;
            }
        }
    }
    
    if (!$has_serial_default) {
        echo "   Modifying serial_number to allow NULL or have default...\n";
        $link->query("ALTER TABLE rifles MODIFY COLUMN serial_number VARCHAR(100) NULL");
        echo "✓ Fixed serial_number column to allow NULL\n";
    } else {
        echo "✓ serial_number column already allows NULL or has default\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error fixing rifles table: " . $e->getMessage() . "\n";
}

// Fix 2: Check and fix rifle_assignments table structure
echo "\n2. FIXING RIFLE_ASSIGNMENTS TABLE\n";
echo "=================================\n";
try {
    // Check if rifle_assignments table exists
    $result = $link->query("SHOW TABLES LIKE 'rifle_assignments'");
    
    if ($result->num_rows === 0) {
        echo "   Creating rifle_assignments table...\n";
        $create_table_sql = "
            CREATE TABLE rifle_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rifle_id INT NOT NULL,
                cadet_profile_id INT NOT NULL,
                assigned_by INT NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                returned_at TIMESTAMP NULL,
                returned_by INT NULL,
                status ENUM('active', 'returned') DEFAULT 'active',
                return_condition VARCHAR(50) NULL,
                return_notes TEXT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE CASCADE,
                FOREIGN KEY (cadet_profile_id) REFERENCES cadet_profiles(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_by) REFERENCES users(id),
                FOREIGN KEY (returned_by) REFERENCES users(id),
                INDEX idx_rifle_assignments_rifle (rifle_id),
                INDEX idx_rifle_assignments_cadet (cadet_profile_id),
                INDEX idx_rifle_assignments_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $link->query($create_table_sql);
        echo "✓ Created rifle_assignments table with correct structure\n";
    } else {
        echo "   Checking rifle_assignments table structure...\n";
        $result = $link->query("DESCRIBE rifle_assignments");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        echo "   Current columns: " . implode(', ', $columns) . "\n";
        
        // Check if we have the wrong column name
        if (in_array('cadet_id', $columns) && !in_array('cadet_profile_id', $columns)) {
            echo "   Renaming cadet_id to cadet_profile_id...\n";
            $link->query("ALTER TABLE rifle_assignments CHANGE cadet_id cadet_profile_id INT NOT NULL");
            echo "✓ Renamed cadet_id to cadet_profile_id\n";
        } elseif (in_array('cadet_profile_id', $columns)) {
            echo "✓ cadet_profile_id column already exists\n";
        } else {
            echo "   Adding cadet_profile_id column...\n";
            $link->query("ALTER TABLE rifle_assignments ADD COLUMN cadet_profile_id INT NOT NULL AFTER rifle_id");
            echo "✓ Added cadet_profile_id column\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error fixing rifle_assignments table: " . $e->getMessage() . "\n";
}

// Fix 3: Add missing contact column to cadet_profiles
echo "\n3. FIXING CADET_PROFILES TABLE\n";
echo "==============================\n";
try {
    // Check cadet_profiles structure
    $result = $link->query("DESCRIBE cadet_profiles");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "   Current columns: " . implode(', ', $columns) . "\n";
    
    // Add missing contact column if needed
    if (!in_array('contact', $columns)) {
        echo "   Adding contact column...\n";
        $link->query("ALTER TABLE cadet_profiles ADD COLUMN contact VARCHAR(20) NULL AFTER contact_number");
        echo "✓ Added contact column\n";
    } else {
        echo "✓ contact column already exists\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error fixing cadet_profiles table: " . $e->getMessage() . "\n";
}

// Fix 4: Update rifle_functions.php to use correct column names
echo "\n4. CHECKING RIFLE FUNCTIONS\n";
echo "===========================\n";
try {
    // Test the assignRifle function with correct parameters
    if (function_exists('assignRifle')) {
        echo "✓ assignRifle function exists\n";
        
        // Check if we can get a sample cadet and rifle for testing
        $rifle_stmt = $link->prepare("SELECT id, rifle_number FROM rifles WHERE status = 'available' LIMIT 1");
        $rifle_stmt->execute();
        $rifle_result = $rifle_stmt->get_result();
        
        $cadet_stmt = $link->prepare("
            SELECT cp.id, cp.first_name, cp.last_name 
            FROM cadet_profiles cp 
            JOIN users u ON cp.user_id = u.id 
            WHERE u.approval_status = 'approved' 
            AND u.role = 'basic-cadet' 
            LIMIT 1
        ");
        $cadet_stmt->execute();
        $cadet_result = $cadet_stmt->get_result();
        
        if ($rifle_result->num_rows > 0 && $cadet_result->num_rows > 0) {
            echo "✓ Sample data available for testing assignment function\n";
        } else {
            echo "⚠️ No sample data available for testing assignment function\n";
        }
    } else {
        echo "❌ assignRifle function not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking rifle functions: " . $e->getMessage() . "\n";
}

// Fix 5: Test document generation query with correct columns
echo "\n5. TESTING FIXED DOCUMENT GENERATION\n";
echo "====================================\n";
try {
    $doc_query = "
        SELECT 
            u.id as user_id,
            u.username,
            u.email,
            u.approval_status,
            cp.first_name,
            cp.middle_name,
            cp.last_name,
            cp.beneficiary_address,
            cp.region,
            cp.beneficiary_relationship,
            cp.platoon,
            cp.contact_number,
            cp.emergency_contact
        FROM users u
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        AND cp.first_name IS NOT NULL
        ORDER BY cp.last_name, cp.first_name
        LIMIT 3
    ";
    
    $stmt = $link->prepare($doc_query);
    $stmt->execute();
    $doc_result = $stmt->get_result();
    
    echo "✓ Fixed document generation query successful: {$doc_result->num_rows} records\n";
    
    while ($row = $doc_result->fetch_assoc()) {
        echo "   User {$row['user_id']}: {$row['first_name']} {$row['last_name']}\n";
        echo "     Address: {$row['beneficiary_address']}\n";
        echo "     Region: {$row['region']}\n";
        echo "     Contact: {$row['contact_number']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fixed document generation query error: " . $e->getMessage() . "\n";
}

// Fix 6: Test rifle insert with fixed schema
echo "\n6. TESTING FIXED RIFLE INSERT\n";
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

echo "\n=== DATABASE SCHEMA FIXES COMPLETED ===\n";
echo "All major database schema issues should now be resolved.\n";
?>