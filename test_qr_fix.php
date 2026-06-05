<?php
// Test script to verify the QR fix
require_once 'includes/db.php';
require_once 'includes/rifle_qr_functions.php'; // Only include the enhanced functions

echo "Testing QR Code Fix\n";
echo "==================\n\n";

// Test 1: Generate a new QR code using the enhanced function
echo "1. Testing enhanced QR generation...\n";
$test_rifle_number = 'R010';

if (function_exists('generateEnhancedRifleQR')) {
    $result = generateEnhancedRifleQR($test_rifle_number, 'rifle', true, true);
    
    if ($result['success']) {
        echo "✓ Enhanced QR code generated successfully\n";
        echo "QR Path: " . $result['qr_path'] . "\n";
        echo "Encryption: " . $result['encryption_type'] . "\n";
    } else {
        echo "✗ Failed to generate enhanced QR code: " . $result['message'] . "\n";
        if (isset($result['debug_info'])) {
            echo "Debug info: " . json_encode($result['debug_info'], JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "✗ generateEnhancedRifleQR function not available\n";
}

// Test 2: Test the data format that would be generated
echo "\n2. Testing QR data format...\n";

$test_qr_data = [
    'type' => 'rifle',
    'rifle_id' => '10',
    'rifle_number' => 'R010',
    'rifle_type' => 'Standard',
    'generated_at' => date('Y-m-d H:i:s'),
    'system' => 'rotc_rifle_management',
    'encryption_method' => 'rifle',
    'version' => '2.0'
];

$json_data = json_encode($test_qr_data);
echo "JSON data: $json_data\n";

// Test encryption with enhanced function
if (function_exists('encryptForCryptoJS')) {
    $encryption_key = 'rifle-management-system-key-2024';
    $encrypted_data = encryptForCryptoJS($json_data, $encryption_key);
    
    if ($encrypted_data) {
        echo "✓ Data encrypted successfully with encryptForCryptoJS\n";
        echo "Encrypted data length: " . strlen($encrypted_data) . " characters\n";
        
        // Test if it starts with 'Salted__' (CryptoJS format)
        $decoded = base64_decode($encrypted_data);
        if (substr($decoded, 0, 8) === 'Salted__') {
            echo "✓ Encrypted data is in CryptoJS format\n";
        } else {
            echo "✗ Encrypted data is NOT in CryptoJS format\n";
        }
        
        // Test 3: Try to decrypt using the enhanced function
        echo "\n3. Testing decryption with enhanced function...\n";
        
        if (function_exists('decodeEnhancedQRData')) {
            $decode_result = decodeEnhancedQRData($encrypted_data, true);
            
            if ($decode_result['success']) {
                echo "✓ Successfully decoded QR data\n";
                echo "Decoded data: " . json_encode($decode_result['data']) . "\n";
                
                // Check if system field exists
                if (isset($decode_result['data']['system']) && $decode_result['data']['system'] === 'rotc_rifle_management') {
                    echo "✓ System field is correct\n";
                } else {
                    echo "✗ System field is missing or incorrect\n";
                }
            } else {
                echo "✗ Failed to decode QR data: " . $decode_result['message'] . "\n";
                if (isset($decode_result['debug_info'])) {
                    echo "Debug info: " . json_encode($decode_result['debug_info'], JSON_PRETTY_PRINT) . "\n";
                }
            }
        } else {
            echo "✗ decodeEnhancedQRData function not available\n";
        }
    } else {
        echo "✗ Failed to encrypt data with encryptForCryptoJS\n";
    }
} else {
    echo "✗ encryptForCryptoJS function not available\n";
}

echo "\n==================\n";
echo "Test completed!\n";
?>