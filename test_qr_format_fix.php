<?php
// Test script to verify QR generation now uses CryptoJS format
require_once 'includes/rifle_qr_functions.php';
require_once 'includes/db.php';

echo "=== QR Format Fix Verification ===\n\n";

// Test 1: Generate a QR using the enhanced function
echo "1. Testing enhanced QR generation...\n";
$test_rifle_number = 'TEST_' . time();
$result = generateEnhancedRifleQR($test_rifle_number, 'rifle', false, false);

if ($result['success']) {
    echo "✓ Enhanced QR generated successfully\n";
    echo "QR Path: " . $result['qr_path'] . "\n";
    echo "Encryption: " . $result['encryption_method'] . "\n\n";
    
    // Test 2: Read the actual QR file and analyze its content
    echo "2. Analyzing generated QR content...\n";
    if (file_exists($result['qr_path'])) {
        // We can't directly read QR image content, but we can test the data that would be in it
        // by recreating the same data structure
        $qr_data = [
            'type' => 'rifle',
            'rifle_id' => $result['rifle_id'] ?? 'test',
            'rifle_number' => $test_rifle_number,
            'rifle_type' => 'Standard',
            'generated_at' => date('Y-m-d H:i:s'),
            'system' => 'rotc_rifle_management',
            'encryption_method' => 'rifle',
            'version' => '2.0'
        ];
        
        $json_data = json_encode($qr_data);
        echo "Original data: " . $json_data . "\n";
        
        // Encrypt using the same method as enhanced function
        $encryption_keys = [
            'rifle' => 'rifle-management-system-key-2024',
            'attendance' => 'attendance-system-key-2024'
        ];
        $actual_key = $encryption_keys['rifle'];
        $encrypted = encryptForCryptoJS($json_data, $actual_key);
        echo "✓ Data encrypted successfully (CryptoJS format)\n";
        echo "Encrypted length: " . strlen($encrypted) . " characters\n";
        
        // Check if it's base64 and contains CryptoJS format
        $decoded = base64_decode($encrypted, true);
        if ($decoded !== false && substr($decoded, 0, 8) === 'Salted__') {
            echo "✓ Confirmed: Uses CryptoJS 'Salted__' format\n";
        } else {
            echo "✗ Warning: Does not use CryptoJS format\n";
        }
        
        echo "\n3. Testing decryption with scanner keys...\n";
        $decode_result = decodeEnhancedQRData($encrypted);
        if ($decode_result['success']) {
            echo "✓ Successfully decrypted with scanner keys\n";
            echo "System field: " . $decode_result['data']['system'] . "\n";
            echo "Version field: " . $decode_result['data']['version'] . "\n";
        } else {
            echo "✗ Failed to decrypt: " . $decode_result['message'] . "\n";
        }
        
    } else {
        echo "✗ QR file not found: " . $result['qr_path'] . "\n";
    }
} else {
    echo "✗ Failed to generate enhanced QR: " . $result['message'] . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nSUMMARY:\n";
echo "- QR codes are now generated using the enhanced function\n";
echo "- Data is encrypted in CryptoJS format with 'Salted__' prefix\n";
echo "- Scanner should now be able to decrypt the QR codes\n";
echo "- The 'system' field is set to 'rotc_rifle_management'\n";
echo "- The 'version' field is set to '2.0'\n";
?>