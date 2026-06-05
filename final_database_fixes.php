<?php
require_once 'includes/db.php';

echo "=== FINAL DATABASE FIXES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Fix 1: Fix rifles table columns
echo "1. FIXING RIFLES TABLE COLUMNS\n";
echo "==============================\n";
try {
    // Fix model column to allow NULL
    $link->query("ALTER TABLE rifles MODIFY COLUMN model VARCHAR(100) NULL");
    echo "✓ Fixed model column to allow NULL\n";
    
    // Fix serial_number column to allow NULL
    $link->query("ALTER TABLE rifles MODIFY COLUMN serial_number VARCHAR(100) NULL");
    echo "✓ Fixed serial_number column to allow NULL\n";
    
} catch (Exception $e) {
    echo "❌ Error fixing rifles table: " . $e->getMessage() . "\n";
}

// Fix 2: Test rifle insert
echo "\n2. TESTING RIFLE INSERT\n";
echo "=======================\n";
try {
    $test_rifle = 'TEST-' . time();
    $stmt = $link->prepare("INSERT INTO rifles (rifle_number, rifle_type, status) VALUES (?, 'mechanical rifle', 'available')");
    $stmt->bind_param("s", $test_rifle);
    $stmt->execute();
    $rifle_id = $link->insert_id;
    echo "✓ Rifle insert successful: {$test_rifle}\n";
    
    // Cleanup
    $link->query("DELETE FROM rifles WHERE id = {$rifle_id}");
    echo "✓ Cleaned up test rifle\n";
    
} catch (Exception $e) {
    echo "❌ Rifle insert error: " . $e->getMessage() . "\n";
}

// Fix 3: Check rifle_assignments table
echo "\n3. CHECKING RIFLE_ASSIGNMENTS TABLE\n";
echo "===================================\n";
try {
    $result = $link->query("DESCRIBE rifle_assignments");
    $has_cadet_id = false;
    $has_cadet_profile_id = false;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'cadet_id') $has_cadet_id = true;
        if ($row['Field'] === 'cadet_profile_id') $has_cadet_profile_id = true;
        echo "   Column: {$row['Field']} ({$row['Type']})\n";
    }
    
    if ($has_cadet_id && !$has_cadet_profile_id) {
        echo "   Need to rename cadet_id to cadet_profile_id\n";
    } else {
        echo "✓ Column structure is correct\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking rifle_assignments: " . $e->getMessage() . "\n";
}

// Fix 4: Test document generation query
echo "\n4. TESTING DOCUMENT GENERATION\n";
echo "==============================\n";
try {
    $stmt = $link->prepare("
        SELECT 
            cp.first_name,
            cp.last_name,
            cp.address,
            cp.barangay,
            cp.city,
            cp.region,
            cp.contact,
            cp.beneficiary_address
        FROM cadet_profiles cp
        JOIN users u ON cp.user_id = u.id
        WHERE u.approval_status = 'approved'
        LIMIT 3
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "✓ Document generation query successful: " . $result->num_rows . " records\n";
    while ($row = $result->fetch_assoc()) {
        echo "   User: {$row['first_name']} {$row['last_name']}\n";
        echo "     Address: {$row['address']}, {$row['barangay']}, {$row['city']}\n";
        echo "     Region: {$row['region']}\n";
        echo "     Contact: {$row['contact']}\n";
        echo "     Beneficiary Address: {$row['beneficiary_address']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Document generation error: " . $e->getMessage() . "\n";
}

echo "\n=== FIXES COMPLETED ===\n";