<?php
// Include necessary functions
require_once 'includes/rifle_qr_functions.php';

echo "=== Raw QR Data Analysis ===\n\n";

// The raw QR data from the user's error message
$raw_qr_data = 'cmlmbGUtbWFuYWdlbWVudC1zeXN0ZW0ta2V5LTIwMjR8eyJ0eXBlIjoicmlmbGUiLCJyaWZsZV9pZCI6IjEwIiwicmlmbGVfbnVtYmVyIjoiUjAxMCIsImdlbmVyYXRlZF9hdCI6IjIwMjUtMDgtMTkgMTM6MzM6MzAiLCJzeXN0ZW0iOiJyb3RjX3JpZmxlX21hbmFn';

echo "1. Raw QR Data (truncated):\n";
echo $raw_qr_data . "...\n\n";

// Try to decode as base64
echo "2. Attempting base64 decode...\n";
$decoded = base64_decode($raw_qr_data, true);
if ($decoded !== false) {
    echo "✓ Base64 decode successful\n";
    echo "Decoded length: " . strlen($decoded) . " bytes\n";
    echo "First 100 characters: " . substr($decoded, 0, 100) . "\n\n";
    
    // Check if it contains the pipe separator (old format)
    if (strpos($decoded, '|') !== false) {
        echo "3. Detected OLD LEGACY FORMAT (key|json_data)\n";
        $parts = explode('|', $decoded, 2);
        echo "Key part: " . $parts[0] . "\n";
        echo "Data part (first 100 chars): " . substr($parts[1], 0, 100) . "\n\n";
        
        // Try to parse the JSON part
        echo "4. Attempting to parse JSON data...\n";
        $json_data = json_decode($parts[1], true);
        if ($json_data !== null) {
            echo "✓ JSON parsing successful\n";
            echo "Data structure:\n";
            print_r($json_data);
        } else {
            echo "✗ JSON parsing failed\n";
            echo "JSON error: " . json_last_error_msg() . "\n";
            echo "Raw JSON (first 200 chars): " . substr($parts[1], 0, 200) . "\n";
        }
    } else {
        echo "3. This appears to be CryptoJS encrypted data\n";
        echo "Checking for CryptoJS 'Salted__' prefix...\n";
        if (substr($decoded, 0, 8) === 'Salted__') {
            echo "✓ Found CryptoJS 'Salted__' prefix\n";
        } else {
            echo "✗ No CryptoJS 'Salted__' prefix found\n";
        }
    }
} else {
    echo "✗ Base64 decode failed\n";
}

echo "\n=== Analysis Complete ===\n";
echo "\nConclusion: The QR code uses the OLD LEGACY FORMAT (key|json_data)\n";
echo "The scanner expects CryptoJS encrypted format, which is why it's failing.\n";
?>