<?php
// Rifle QR Code Generation Functions
// Enhanced with comprehensive debugging and error handling

require_once 'includes/db.php';
require_once 'libs/phpqrcode/qrlib.php';

/**
 * Encrypt data using CryptoJS.AES.encrypt compatible format
 * @param string $data - The data to encrypt
 * @param string $key - The encryption key
 * @return string|false - Base64 encoded encrypted data or false on failure
 */
function cryptoJSAESEncrypt($data, $key) {
    try {
        // Generate random salt (8 bytes)
        $salt = openssl_random_pseudo_bytes(8);
        
        // Derive key and IV using EVP_BytesToKey equivalent (same as CryptoJS)
        $keyIv = evpBytesToKey($key, $salt, 32, 16); // 32 bytes key + 16 bytes IV for AES-256-CBC
        $derivedKey = $keyIv['key'];
        $iv = $keyIv['iv'];
        
        // Encrypt the data
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $derivedKey, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            return false;
        }
        
        // Create CryptoJS format: "Salted__" + salt + encrypted_data
        $salted = "Salted__" . $salt . $encrypted;
        
        // Return base64 encoded result (same as CryptoJS.AES.encrypt().toString())
        return base64_encode($salted);
        
    } catch (Exception $e) {
        error_log("[CRYPTO ERROR] Encryption failed: " . $e->getMessage());
        return false;
    }
}

/**
 * EVP_BytesToKey equivalent for PHP (same key derivation as CryptoJS)
 * @param string $password - The password/key
 * @param string $salt - The salt
 * @param int $keyLen - Key length in bytes
 * @param int $ivLen - IV length in bytes
 * @return array - Array with 'key' and 'iv'
 */
function evpBytesToKey($password, $salt, $keyLen, $ivLen) {
    $d = $di = '';
    while (strlen($d) < ($keyLen + $ivLen)) {
        $di = md5($di . $password . $salt, true);
        $d .= $di;
    }
    return array(
        'key' => substr($d, 0, $keyLen),
        'iv' => substr($d, $keyLen, $ivLen)
    );
}

/**
 * Generate QR code with text label below it
 * @param string $qr_content - The QR code content
 * @param string $filepath - The output file path
 * @param string $label_text - The text to display below QR code
 * @return bool - True on success, false on failure
 */
function generateQRWithLabel($qr_content, $filepath, $label_text) {
    try {
        // Create temporary QR code file
        $temp_qr_file = tempnam(sys_get_temp_dir(), 'qr_temp_') . '.png';
        
        // Generate basic QR code
        QRcode::png($qr_content, $temp_qr_file, QR_ECLEVEL_L, 8, 2);
        
        if (!file_exists($temp_qr_file)) {
            error_log("[QR ERROR] Failed to create temporary QR file");
            return false;
        }
        
        // Load the QR code image
        $qr_image = imagecreatefrompng($temp_qr_file);
        if (!$qr_image) {
            error_log("[QR ERROR] Failed to load QR image");
            unlink($temp_qr_file);
            return false;
        }
        
        // Get QR image dimensions
        $qr_width = imagesx($qr_image);
        $qr_height = imagesy($qr_image);
        
        // Calculate label dimensions
        $font_size = 16;
        $label_height = 40;
        $padding = 10;
        
        // Create new image with space for label
        $final_width = $qr_width;
        $final_height = $qr_height + $label_height + $padding;
        $final_image = imagecreatetruecolor($final_width, $final_height);
        
        // Set background to white
        $white = imagecolorallocate($final_image, 255, 255, 255);
        $black = imagecolorallocate($final_image, 0, 0, 0);
        imagefill($final_image, 0, 0, $white);
        
        // Copy QR code to final image
        imagecopy($final_image, $qr_image, 0, 0, 0, 0, $qr_width, $qr_height);
        
        // Add label text
        $text_x = ($final_width - (strlen($label_text) * 10)) / 2; // Approximate text centering
        $text_y = $qr_height + $padding + 20;
        
        // Use built-in font (font 5 is largest built-in font)
        imagestring($final_image, 5, $text_x, $text_y, "Rifle #" . $label_text, $black);
        
        // Save final image
        $result = imagepng($final_image, $filepath);
        
        // Clean up
        imagedestroy($qr_image);
        imagedestroy($final_image);
        unlink($temp_qr_file);
        
        if (!$result) {
            error_log("[QR ERROR] Failed to save final QR image with label");
            return false;
        }
        
        error_log("[QR SUCCESS] Generated QR code with label: {$label_text}");
        return true;
        
    } catch (Exception $e) {
        error_log("[QR EXCEPTION] Error generating QR with label: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR code for a single rifle (Legacy version - renamed to avoid conflicts)
 * @param int $rifle_id - The rifle ID
 * @param string $rifle_number - The rifle number
 * @return string|false - QR code file path on success, false on failure
 */
function generateLegacyRifleQR($rifle_id, $rifle_number) {
    global $link;
    
    try {
        error_log("[QR DEBUG] Starting QR generation for rifle: {$rifle_number} (ID: {$rifle_id})");
        
        // Validate inputs
        if (empty($rifle_id) || empty($rifle_number)) {
            error_log("[QR ERROR] Invalid inputs - ID: {$rifle_id}, Number: {$rifle_number}");
            return false;
        }
        
        // Create QR codes directory if it doesn't exist
        $qr_dir = 'qr_codes';
        if (!is_dir($qr_dir)) {
            if (!mkdir($qr_dir, 0755, true)) {
                error_log("[QR ERROR] Failed to create QR directory: {$qr_dir}");
                return false;
            }
            error_log("[QR DEBUG] Created QR directory: {$qr_dir}");
        }
        
        // Generate encrypted QR data
        $qr_data = [
            'type' => 'rifle',
            'rifle_id' => $rifle_id,
            'rifle_number' => $rifle_number,
            'rifle_type' => 'Standard',
            'generated_at' => date('Y-m-d H:i:s'),
            'system' => 'rotc_rifle_management',  // Required by scanner
            'encryption_method' => 'rifle',
            'version' => '2.0'
        ];
        
        $json_data = json_encode($qr_data);
        $encryption_key = 'rifle-management-system-key-2024';
        
        // Create CryptoJS.AES.encrypt compatible format
        $qr_content = cryptoJSAESEncrypt($json_data, $encryption_key);
        
        if ($qr_content === false) {
            error_log("[QR ERROR] Failed to encrypt QR data for rifle {$rifle_number}");
            return false;
        }
        
        error_log("[QR DEBUG] Generated encrypted QR content for rifle {$rifle_number}");
        
        // Generate QR code file
        $filename = "rifle_{$rifle_number}.png";
        $filepath = $qr_dir . '/' . $filename;
        
        // Remove existing file if it exists
        if (file_exists($filepath)) {
            unlink($filepath);
            error_log("[QR DEBUG] Removed existing QR file: {$filepath}");
        }
        
        // Generate QR code with label
        error_log("[QR DEBUG] Generating QR code with label file: {$filepath}");
        $qr_generated = generateQRWithLabel($qr_content, $filepath, $rifle_number);
        
        // Verify file was created
        if (!$qr_generated || !file_exists($filepath)) {
            error_log("[QR ERROR] QR file was not created: {$filepath}");
            return false;
        }
        
        $file_size = filesize($filepath);
        error_log("[QR DEBUG] QR file created successfully: {$filepath} (Size: {$file_size} bytes)");
        
        // Update database with QR code path
        $stmt = $link->prepare("UPDATE rifles SET qr_code_path = ? WHERE id = ?");
        $stmt->bind_param("si", $filepath, $rifle_id);
        
        if ($stmt->execute()) {
            error_log("[QR SUCCESS] Database updated for rifle {$rifle_number}");
            return $filepath;
        } else {
            error_log("[QR ERROR] Failed to update database for rifle {$rifle_number}: " . $stmt->error);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("[QR EXCEPTION] Error generating QR for rifle {$rifle_number}: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR codes for all rifles without QR codes
 * @return array - Result with success status, generated count, and total count
 */
function batchGenerateRifleQRs() {
    global $link;
    
    try {
        error_log("[QR DEBUG] Starting batch QR generation");
        
        // Get all rifles without QR codes
        $stmt = $link->prepare("SELECT id, rifle_number FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = ''");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rifles = [];
        while ($row = $result->fetch_assoc()) {
            $rifles[] = $row;
        }
        
        $total_count = count($rifles);
        error_log("[QR DEBUG] Found {$total_count} rifles without QR codes");
        
        if ($total_count == 0) {
            return [
                'success' => true,
                'generated' => 0,
                'total' => 0,
                'message' => 'All rifles already have QR codes'
            ];
        }
        
        $success_count = 0;
        $errors = [];
        
        foreach ($rifles as $rifle) {
            error_log("[QR DEBUG] Processing rifle: {$rifle['rifle_number']}");
            
            $qr_path = generateLegacyRifleQR($rifle['id'], $rifle['rifle_number']);
            
            if ($qr_path) {
                $success_count++;
                error_log("[QR SUCCESS] Generated QR for rifle: {$rifle['rifle_number']}");
            } else {
                $errors[] = "Failed to generate QR for rifle: {$rifle['rifle_number']}";
                error_log("[QR ERROR] Failed to generate QR for rifle: {$rifle['rifle_number']}");
            }
        }
        
        error_log("[QR BATCH COMPLETE] Generated {$success_count} out of {$total_count} QR codes");
        
        return [
            'success' => true,
            'generated' => $success_count,
            'total' => $total_count,
            'errors' => $errors,
            'message' => "Generated {$success_count} QR codes out of {$total_count} rifles"
        ];
        
    } catch (Exception $e) {
        error_log("[QR EXCEPTION] Batch generation error: " . $e->getMessage());
        return [
            'success' => false,
            'generated' => 0,
            'total' => 0,
            'message' => 'Batch generation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Decode QR data
 * @param string $qr_data - Base64 encoded QR data
 * @return array|false - Decoded data on success, false on failure
 */
function decodeQRData($qr_data) {
    try {
        error_log("[QR DEBUG] Decoding QR data");
        
        $decoded = base64_decode($qr_data);
        if (!$decoded) {
            error_log("[QR ERROR] Failed to base64 decode QR data");
            return false;
        }
        
        $data = json_decode($decoded, true);
        if (!$data) {
            error_log("[QR ERROR] Failed to JSON decode QR data");
            return false;
        }
        
        error_log("[QR DEBUG] Successfully decoded QR data for type: " . ($data['type'] ?? 'unknown'));
        return $data;
        
    } catch (Exception $e) {
        error_log("[QR EXCEPTION] Error decoding QR data: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate QR code
 * @param array $qr_data - Decoded QR data
 * @param string $expected_type - Expected QR type (rifle/cadet)
 * @return bool - True if valid, false otherwise
 */
function validateQRCode($qr_data, $expected_type = null) {
    try {
        error_log("[QR DEBUG] Validating QR code");
        
        if (!is_array($qr_data)) {
            error_log("[QR ERROR] QR data is not an array");
            return false;
        }
        
        // Check required fields
        $required_fields = ['type', 'id', 'timestamp', 'hash'];
        foreach ($required_fields as $field) {
            if (!isset($qr_data[$field])) {
                error_log("[QR ERROR] Missing required field: {$field}");
                return false;
            }
        }
        
        // Check type if specified
        if ($expected_type && $qr_data['type'] !== $expected_type) {
            error_log("[QR ERROR] Type mismatch. Expected: {$expected_type}, Got: {$qr_data['type']}");
            return false;
        }
        
        // Validate hash for rifles
        if ($qr_data['type'] === 'rifle' && isset($qr_data['number'])) {
            $expected_hash = md5($qr_data['id'] . $qr_data['number'] . 'rifle_secret_key');
            if ($qr_data['hash'] !== $expected_hash) {
                error_log("[QR ERROR] Hash validation failed for rifle");
                return false;
            }
        }
        
        error_log("[QR SUCCESS] QR code validation passed");
        return true;
        
    } catch (Exception $e) {
        error_log("[QR EXCEPTION] Error validating QR code: " . $e->getMessage());
        return false;
    }
}

/**
 * Get rifle information from QR code
 * @param string $qr_data - QR code data
 * @return array|false - Rifle data on success, false on failure
 */
function getRifleFromQR($qr_data) {
    global $link;
    
    try {
        error_log("[QR DEBUG] Getting rifle from QR");
        
        $decoded = decodeQRData($qr_data);
        if (!$decoded || !validateQRCode($decoded, 'rifle')) {
            error_log("[QR ERROR] Invalid rifle QR code");
            return false;
        }
        
        $stmt = $link->prepare("SELECT * FROM rifles WHERE id = ?");
        $stmt->bind_param("i", $decoded['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $rifle = $result->fetch_assoc();
        
        if ($rifle) {
            error_log("[QR SUCCESS] Found rifle: {$rifle['rifle_number']}");
            return $rifle;
        } else {
            error_log("[QR ERROR] Rifle not found for ID: {$decoded['id']}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("[QR EXCEPTION] Error getting rifle from QR: " . $e->getMessage());
        return false;
    }
}

?>