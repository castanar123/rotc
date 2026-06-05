<?php
/**
 * Test QR Generation Functions
 * This script tests the QR generation functionality directly
 */

require_once 'includes/db.php';
require_once 'generate_id_card.php';

echo "<h2>Testing QR Generation Functions</h2>";

// Test 1: Check if required directories can be created
echo "<h3>Test 1: Directory Creation</h3>";
$qr_dir = 'qr_codes/borrower_ids/';
$card_dir = 'id_cards/borrower_cards/';

if (!file_exists($qr_dir)) {
    if (mkdir($qr_dir, 0755, true)) {
        echo "✓ QR codes directory created successfully<br>";
    } else {
        echo "✗ Failed to create QR codes directory<br>";
    }
} else {
    echo "✓ QR codes directory already exists<br>";
}

if (!file_exists($card_dir)) {
    if (mkdir($card_dir, 0755, true)) {
        echo "✓ ID cards directory created successfully<br>";
    } else {
        echo "✗ Failed to create ID cards directory<br>";
    }
} else {
    echo "✓ ID cards directory already exists<br>";
}

// Test 2: Check if phpqrcode library is accessible
echo "<h3>Test 2: QR Library Check</h3>";
if (file_exists('libs/phpqrcode/qrlib.php')) {
    echo "✓ phpqrcode library found<br>";
    
    // Test basic QR generation
    try {
        $test_qr_path = $qr_dir . 'test_qr.png';
        QRcode::png('TEST123', $test_qr_path, QR_ECLEVEL_H, 8, 2);
        
        if (file_exists($test_qr_path)) {
            echo "✓ Basic QR code generation successful<br>";
            echo "QR file size: " . filesize($test_qr_path) . " bytes<br>";
        } else {
            echo "✗ QR code file was not created<br>";
        }
    } catch (Exception $e) {
        echo "✗ QR generation failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ phpqrcode library not found<br>";
}

// Test 3: Check GD extension
echo "<h3>Test 3: GD Extension Check</h3>";
if (extension_loaded('gd')) {
    echo "✓ GD extension is loaded<br>";
    $gd_info = gd_info();
    echo "GD Version: " . $gd_info['GD Version'] . "<br>";
    echo "PNG Support: " . ($gd_info['PNG Support'] ? 'Yes' : 'No') . "<br>";
} else {
    echo "✗ GD extension is not loaded<br>";
}

// Test 4: Test generateROTCIDCard function
echo "<h3>Test 4: ROTC ID Card Generation</h3>";
try {
    $test_id = 'TEST_' . date('YmdHis');
    echo "Testing with ID: $test_id<br>";
    
    $result = generateROTCIDCard($test_id);
    
    if ($result['success']) {
        echo "✓ ROTC ID Card generation successful<br>";
        echo "QR Path: " . $result['qr_path'] . "<br>";
        echo "Front Path: " . $result['front_path'] . "<br>";
        echo "Back Path: " . $result['back_path'] . "<br>";
        
        // Check if files actually exist
        if (file_exists($result['qr_path'])) {
            echo "✓ QR file exists (" . filesize($result['qr_path']) . " bytes)<br>";
        } else {
            echo "✗ QR file does not exist<br>";
        }
        
        if (file_exists($result['front_path'])) {
            echo "✓ Front card file exists (" . filesize($result['front_path']) . " bytes)<br>";
        } else {
            echo "✗ Front card file does not exist<br>";
        }
        
        if (file_exists($result['back_path'])) {
            echo "✓ Back card file exists (" . filesize($result['back_path']) . " bytes)<br>";
        } else {
            echo "✗ Back card file does not exist<br>";
        }
        
    } else {
        echo "✗ ROTC ID Card generation failed: " . $result['error'] . "<br>";
    }
} catch (Exception $e) {
    echo "✗ Exception during ID card generation: " . $e->getMessage() . "<br>";
}

// Test 5: Database connection test
echo "<h3>Test 5: Database Connection</h3>";
try {
    $conn = getConnection();
    if ($conn) {
        echo "✓ Database connection successful<br>";
        
        // Check if borrower_temp_ids table exists
        $result = $conn->query("SHOW TABLES LIKE 'borrower_temp_ids'");
        if ($result && $result->num_rows > 0) {
            echo "✓ borrower_temp_ids table exists<br>";
        } else {
            echo "✗ borrower_temp_ids table does not exist<br>";
        }
        
        $conn->close();
    } else {
        echo "✗ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

echo "<h3>Test Complete</h3>";
echo "<p>Check the results above to identify any issues with QR generation.</p>";