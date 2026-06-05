<?php
// Simple QR Test - Write results to file
ob_start();

echo "Starting QR Generation Test...\n";

// Test 1: Check phpqrcode library
if (file_exists('libs/phpqrcode/qrlib.php')) {
    echo "✓ phpqrcode library found\n";
    require_once 'libs/phpqrcode/qrlib.php';
} else {
    echo "✗ phpqrcode library not found\n";
    exit;
}

// Test 2: Check GD extension
if (extension_loaded('gd')) {
    echo "✓ GD extension loaded\n";
} else {
    echo "✗ GD extension not loaded\n";
    exit;
}

// Test 3: Create directories
$qr_dir = 'qr_codes/borrower_ids/';
$card_dir = 'id_cards/borrower_cards/';

if (!file_exists($qr_dir)) {
    if (mkdir($qr_dir, 0755, true)) {
        echo "✓ QR directory created\n";
    } else {
        echo "✗ Failed to create QR directory\n";
    }
}

if (!file_exists($card_dir)) {
    if (mkdir($card_dir, 0755, true)) {
        echo "✓ Card directory created\n";
    } else {
        echo "✗ Failed to create card directory\n";
    }
}

// Test 4: Generate simple QR code
try {
    $test_id = 'TEST_' . date('His');
    $qr_path = $qr_dir . $test_id . '_qr.png';
    
    echo "Generating QR for: $test_id\n";
    QRcode::png($test_id, $qr_path, QR_ECLEVEL_H, 8, 2);
    
    if (file_exists($qr_path)) {
        echo "✓ QR code generated successfully\n";
        echo "File size: " . filesize($qr_path) . " bytes\n";
        echo "File path: $qr_path\n";
    } else {
        echo "✗ QR code file not created\n";
    }
} catch (Exception $e) {
    echo "✗ QR generation error: " . $e->getMessage() . "\n";
}

// Test 5: Test database connection
try {
    require_once 'includes/db.php';
    $conn = getConnection();
    if ($conn) {
        echo "✓ Database connection successful\n";
        $conn->close();
    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";

$output = ob_get_clean();
file_put_contents('qr_test_results.txt', $output);
echo $output;
?>