<?php
/**
 * Debug script to analyze raw QR data format
 * This script will decode and analyze the QR data that's failing to decrypt
 */

// Raw QR data from the error message
$rawQRData = 'cmlmbGUtbWFuYWdlbWVudC1zeXN0ZW0ta2V5LTIwMjR8eyJ0eXBlIjoicmlmbGUiLCJyaWZsZV9pZCI6IjEwIiwicmlmbGVfbnVtYmVyIjoiUjAxMCIsImdlbmVyYXRlZF9hdCI6IjIwMjUtMDgtMTkgMTM6MzM6MzAiLCJzeXN0ZW0iOiJyb3RjX3JpZmxlX21hbmFn';

echo "<h2>QR Data Analysis</h2>";
echo "<h3>1. Raw Data:</h3>";
echo "<pre>" . htmlspecialchars($rawQRData) . "</pre>";

// Try base64 decode
echo "<h3>2. Base64 Decode Attempt:</h3>";
$base64Decoded = base64_decode($rawQRData, true);
if ($base64Decoded !== false) {
    echo "<strong>Success!</strong><br>";
    echo "<pre>" . htmlspecialchars($base64Decoded) . "</pre>";
    
    // Check if it contains a pipe separator (old format)
    if (strpos($base64Decoded, '|') !== false) {
        echo "<h4>Detected pipe separator - this is OLD FORMAT!</h4>";
        $parts = explode('|', $base64Decoded, 2);
        echo "<strong>Key part:</strong> " . htmlspecialchars($parts[0]) . "<br>";
        echo "<strong>Data part:</strong> " . htmlspecialchars($parts[1]) . "<br>";
        
        // Try to decode the JSON part
        if (isset($parts[1])) {
            $jsonData = json_decode($parts[1], true);
            if ($jsonData) {
                echo "<h4>JSON Data Successfully Parsed:</h4>";
                echo "<pre>" . print_r($jsonData, true) . "</pre>";
            } else {
                echo "<h4>JSON Parse Failed:</h4>";
                echo "Error: " . json_last_error_msg() . "<br>";
            }
        }
    }
} else {
    echo "<strong>Failed!</strong> - Not valid base64 data<br>";
}

// Check if it looks like encrypted data
echo "<h3>3. Data Format Analysis:</h3>";
echo "Length: " . strlen($rawQRData) . " characters<br>";
echo "Contains pipe: " . (strpos($rawQRData, '|') !== false ? 'Yes' : 'No') . "<br>";
echo "Contains colon: " . (strpos($rawQRData, ':') !== false ? 'Yes' : 'No') . "<br>";
echo "Starts with: " . substr($rawQRData, 0, 20) . "...<br>";

// Check if it's a CryptoJS format (should have salt:iv:encrypted format)
if (substr_count($rawQRData, ':') >= 2) {
    echo "<h4>Possible CryptoJS Format Detected</h4>";
    $parts = explode(':', $rawQRData);
    echo "Parts count: " . count($parts) . "<br>";
    for ($i = 0; $i < min(3, count($parts)); $i++) {
        echo "Part " . ($i + 1) . ": " . substr($parts[$i], 0, 20) . "...<br>";
    }
}

// Try to identify the format
echo "<h3>4. Format Identification:</h3>";
if (base64_decode($rawQRData, true) !== false) {
    $decoded = base64_decode($rawQRData);
    if (strpos($decoded, '|') !== false) {
        echo "<strong>Format: OLD LEGACY FORMAT</strong><br>";
        echo "Structure: base64(key|json_data)<br>";
        echo "This explains why CryptoJS decryption fails!<br>";
    } else {
        echo "<strong>Format: Unknown base64 format</strong><br>";
    }
} else if (substr_count($rawQRData, ':') >= 2) {
    echo "<strong>Format: CryptoJS encrypted format</strong><br>";
    echo "Structure: salt:iv:encrypted_data<br>";
} else {
    echo "<strong>Format: Unknown or corrupted</strong><br>";
}

echo "<h3>5. Recommendations:</h3>";
if (base64_decode($rawQRData, true) !== false) {
    $decoded = base64_decode($rawQRData);
    if (strpos($decoded, '|') !== false) {
        echo "<ul>";
        echo "<li>This QR code uses the OLD format (key|data)</li>";
        echo "<li>The scanner expects CryptoJS encrypted format</li>";
        echo "<li>Need to update QR generation to use proper encryption</li>";
        echo "<li>Or add legacy format support to the scanner</li>";
        echo "</ul>";
    }
}

?>