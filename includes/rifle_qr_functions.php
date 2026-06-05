<?php
// Suppress any warnings from phpqrcode library
error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/../libs/phpqrcode/qrlib.php';
// Restore normal error reporting
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/db.php';

/**
 * Encrypt data in CryptoJS compatible format
 * @param string $data The data to encrypt
 * @param string $passphrase The encryption passphrase
 * @return string Base64 encoded encrypted data
 */
function encryptForCryptoJS($data, $passphrase) {
    // Generate a random salt (8 bytes)
    $salt = openssl_random_pseudo_bytes(8);
    
    // Derive key and IV using EVP_BytesToKey equivalent
    $key_iv = '';
    $d = $d_i = '';
    while (strlen($key_iv) < 48) { // 32 bytes key + 16 bytes IV
        $d_i = md5($d_i . $passphrase . $salt, true);
        $key_iv .= $d_i;
    }
    
    $key = substr($key_iv, 0, 32); // 256-bit key
    $iv = substr($key_iv, 32, 16); // 128-bit IV
    
    // Encrypt the data
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    // Create the CryptoJS format: "Salted__" + salt + encrypted data
    $result = "Salted__" . $salt . $encrypted;
    
    // Return base64 encoded result
    return base64_encode($result);
}

/**
 * Generate QR code for a rifle
 * @param int $rifle_id The rifle ID
 * @param string $rifle_number The rifle number
 * @return string|false The QR code file path or false on failure
 */
function generateRifleQR($rifle_id, $rifle_number) {
    global $link;
    
    // Create QR data for rifle
    $qr_data = array(
        'type' => 'rifle',
        'rifle_id' => $rifle_id,
        'rifle_number' => $rifle_number,
        'generated_at' => date('Y-m-d H:i:s'),
        'system' => 'rotc_rifle_management'
    );
    
    // Convert to JSON and encrypt
    $json_data = json_encode($qr_data);
    $encryption_key = 'rifle-management-system-key-2024';
    
    // Encrypt using CryptoJS-compatible format
    $encrypted_data = encryptForCryptoJS($json_data, $encryption_key);
    
    // Define QR code path
    $qr_directory = 'uploads/rifle_qrcodes/';
    $qr_filename = 'rifle_' . $rifle_number . '.png';
    $qr_code_path = $qr_directory . $qr_filename;
    
    // Ensure directory exists
    if (!is_dir($qr_directory)) {
        mkdir($qr_directory, 0755, true);
    }
    
    try {
            // Generate QR code with medium error correction and optimal size for better balance
            QRcode::png($encrypted_data, $qr_code_path, QR_ECLEVEL_M, 16, 4);
        
        // Update rifle record with QR path
        $update_sql = "UPDATE rifles SET qr_code_path = ? WHERE id = ?";
        if ($stmt = $link->prepare($update_sql)) {
            $stmt->bind_param("si", $qr_code_path, $rifle_id);
            if ($stmt->execute()) {
                $stmt->close();
                return $qr_code_path;
            }
            $stmt->close();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Rifle QR generation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate an external ROTC_QR_V1 rifle QR code (base64 JSON payload).
 * Uses only the rifle_number as required input; rifle_id is included if found in DB.
 * Does NOT modify rifles.qr_code_path so it won't affect internal encrypted QRs.
 *
 * @param string $rifle_number
 * @return array Result with success flag, qr_path, rifle_number, and payload
 */
function generateExternalRifleQR($rifle_number) {
    global $link;

    // Basic validation (same allowed characters as rifle_management.php)
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $rifle_number)) {
        return [
            'success' => false,
            'message' => 'Invalid rifle number format. Use only letters, numbers, hyphens, and underscores.'
        ];
    }

    // Build payload
    $payload = [
        'system' => 'rotc_system',
        'type' => 'rifle',
        'rifle_number' => $rifle_number,
    ];

    // If rifle exists in DB, include its ID for richer external integration
    $rifleId = null;
    try {
        if (isset($link) && $link) {
            $check = checkRifleExists($rifle_number);
            if (!empty($check['exists']) && !empty($check['rifle']['id'])) {
                $rifleId = (int)$check['rifle']['id'];
                $payload['rifle_id'] = (string)$rifleId;
            }
        }
    } catch (Exception $e) {
        // Non-fatal: just skip rifle_id if lookup fails
        error_log('generateExternalRifleQR: DB lookup failed: ' . $e->getMessage());
    }

    if (!isset($payload['rifle_id'])) {
        $payload['rifle_id'] = (string)$rifle_number;
    }

    $json = json_encode($payload);
    if ($json === false) {
        return [
            'success' => false,
            'message' => 'Failed to encode payload JSON'
        ];
    }

    // ROTC_QR_V1:: + JSON, then base64 encode
    $qr_prefix = 'ROTC_QR_V1::';
    $qr_content = base64_encode($qr_prefix . $json);

    // Prepare file path in a separate folder so internal QRs remain untouched
    $qr_directory = 'uploads/rifle_qrcodes_external/';

    if (!is_dir($qr_directory)) {
        if (!mkdir($qr_directory, 0755, true) && !is_dir($qr_directory)) {
            return [
                'success' => false,
                'message' => 'Failed to create external QR directory'
            ];
        }
    }

    // Sanitize filename component
    $safe_number = preg_replace('/[^A-Za-z0-9\-_]/', '_', $rifle_number);
    $qr_filename = 'rifle_ext_' . $safe_number . '.png';
    $qr_code_path = $qr_directory . $qr_filename;

    try {
        // Medium error correction and solid size
        QRcode::png($qr_content, $qr_code_path, QR_ECLEVEL_M, 16, 4);
    } catch (Exception $e) {
        error_log('External rifle QR generation failed: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'QR code generation failed'
        ];
    }

    if (!file_exists($qr_code_path)) {
        return [
            'success' => false,
            'message' => 'QR code file was not created'
        ];
    }

    // Optionally persist external QR path in database for tracking
    if ($rifleId !== null && isset($link) && $link) {
        try {
            $colRes = $link->query("SHOW COLUMNS FROM rifles LIKE 'external_qr_path'");
            if ($colRes && $colRes->num_rows === 0) {
                $link->query("ALTER TABLE rifles ADD COLUMN external_qr_path VARCHAR(255) DEFAULT NULL");
            }

            $colRes2 = $link->query("SHOW COLUMNS FROM rifles LIKE 'external_qr_path'");
            if ($colRes2 && $colRes2->num_rows > 0) {
                if ($stmt = $link->prepare("UPDATE rifles SET external_qr_path = ? WHERE id = ?")) {
                    $stmt->bind_param("si", $qr_code_path, $rifleId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log('generateExternalRifleQR: failed to persist external_qr_path: ' . $e->getMessage());
        }
    }

    // Log external QR generation in separate table
    if (isset($link) && $link) {
        try {
            $tableRes = $link->query("SHOW TABLES LIKE 'rifle_external_qrs'");
            if ($tableRes && $tableRes->num_rows === 0) {
                $createSql = "CREATE TABLE IF NOT EXISTS rifle_external_qrs (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    rifle_id INT(11) NULL DEFAULT NULL,
                    rifle_number VARCHAR(50) NOT NULL,
                    qr_path VARCHAR(255) NOT NULL,
                    payload_json TEXT DEFAULT NULL,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (rifle_id) REFERENCES rifles(id) ON DELETE SET NULL,
                    INDEX idx_extqr_rifle_number (rifle_number),
                    INDEX idx_extqr_generated_at (generated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $link->query($createSql);
            }

            $tableRes2 = $link->query("SHOW TABLES LIKE 'rifle_external_qrs'");
            if ($tableRes2 && $tableRes2->num_rows > 0) {
                // Disallow duplicate external QR for the same rifle_number
                $safeNumber = mysqli_real_escape_string($link, $rifle_number);
                $checkSql = "SELECT qr_path FROM rifle_external_qrs WHERE rifle_number = '" . $safeNumber . "' LIMIT 1";
                $dupRes = $link->query($checkSql);
                if ($dupRes && $dupRes->num_rows > 0) {
                    $existing = $dupRes->fetch_assoc();
                    return [
                        'success' => false,
                        'message' => 'External QR already exists for this rifle number',
                        'existing_qr_path' => $existing['qr_path'] ?? null,
                        'rifle_number' => $rifle_number
                    ];
                }

                $payloadJson = $json;

                if ($rifleId !== null) {
                    if ($stmt = $link->prepare("INSERT INTO rifle_external_qrs (rifle_id, rifle_number, qr_path, payload_json) VALUES (?, ?, ?, ?)")) {
                        $stmt->bind_param("isss", $rifleId, $rifle_number, $qr_code_path, $payloadJson);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    if ($stmt = $link->prepare("INSERT INTO rifle_external_qrs (rifle_number, qr_path, payload_json) VALUES (?, ?, ?)")) {
                        $stmt->bind_param("sss", $rifle_number, $qr_code_path, $payloadJson);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('generateExternalRifleQR: failed to log to rifle_external_qrs: ' . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'message' => 'External rifle QR generated successfully',
        'qr_path' => $qr_code_path,
        'rifle_number' => $rifle_number,
        'payload' => $payload
    ];
}

/**
 * Delete an existing external rifle QR record and its image.
 * @param int $external_id ID from rifle_external_qrs
 * @return array Result with success flag and message
 */
function deleteExternalRifleQR($external_id) {
    global $link;

    if (!$link) {
        return ['success' => false, 'message' => 'Database connection not available'];
    }

    $external_id = (int)$external_id;
    if ($external_id <= 0) {
        return ['success' => false, 'message' => 'Invalid external QR ID'];
    }

    try {
        // Fetch record
        $stmt = $link->prepare("SELECT rifle_id, rifle_number, qr_path FROM rifle_external_qrs WHERE id = ?");
        $stmt->bind_param("i", $external_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'External QR record not found'];
        }
        $row = $res->fetch_assoc();
        $stmt->close();

        $rifleId = !empty($row['rifle_id']) ? (int)$row['rifle_id'] : null;
        $qrPath = $row['qr_path'] ?? '';
        $rifleNumber = $row['rifle_number'] ?? '';

        // Delete QR image file if it exists
        if ($qrPath !== '' && file_exists($qrPath)) {
            @unlink($qrPath);
        }

        // Remove external_qr_path from rifles table if column exists and rifle_id is set
        if ($rifleId !== null) {
            try {
                $colRes = $link->query("SHOW COLUMNS FROM rifles LIKE 'external_qr_path'");
                if ($colRes && $colRes->num_rows > 0) {
                    if ($up = $link->prepare("UPDATE rifles SET external_qr_path = NULL WHERE id = ?")) {
                        $up->bind_param("i", $rifleId);
                        $up->execute();
                        $up->close();
                    }
                }
            } catch (Exception $e) {
                error_log('deleteExternalRifleQR: failed to clear external_qr_path: ' . $e->getMessage());
            }
        }

        // Delete the external record itself
        $del = $link->prepare("DELETE FROM rifle_external_qrs WHERE id = ?");
        $del->bind_param("i", $external_id);
        $del->execute();
        $del->close();

        return [
            'success' => true,
            'message' => "External QR for rifle {$rifleNumber} deleted successfully"
        ];
    } catch (Exception $e) {
        error_log('deleteExternalRifleQR error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete external QR: ' . $e->getMessage()];
    }
}

/**
 * Update an existing external rifle QR (change rifle_number and regenerate QR).
 * This deletes the old record and image, then generates a fresh external QR
 * with the new rifle number, ensuring there are no duplicates.
 *
 * @param int $external_id ID from rifle_external_qrs
 * @param string $rifle_number New rifle number
 * @return array Result with success flag, message, and new QR data
 */
function updateExternalRifleQR($external_id, $rifle_number) {
    global $link;

    if (!$link) {
        return ['success' => false, 'message' => 'Database connection not available'];
    }

    $external_id = (int)$external_id;
    $rifle_number = trim((string)$rifle_number);

    if ($external_id <= 0) {
        return ['success' => false, 'message' => 'Invalid external QR ID'];
    }

    if ($rifle_number === '') {
        return ['success' => false, 'message' => 'Rifle number cannot be empty'];
    }

    // Validate allowed characters (same as generator)
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $rifle_number)) {
        return ['success' => false, 'message' => 'Rifle number can contain letters, numbers, dashes, and underscores only'];
    }

    try {
        // Fetch existing record
        $stmt = $link->prepare("SELECT rifle_id, rifle_number, qr_path FROM rifle_external_qrs WHERE id = ?");
        $stmt->bind_param("i", $external_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'External QR record not found'];
        }
        $row = $res->fetch_assoc();
        $stmt->close();

        $oldNumber = $row['rifle_number'] ?? '';
        $oldPath = $row['qr_path'] ?? '';
        $rifleId = !empty($row['rifle_id']) ? (int)$row['rifle_id'] : null;

        if ($oldNumber === $rifle_number) {
            // Nothing to change
            return ['success' => true, 'message' => 'No changes to apply'];
        }

        // Ensure new rifle_number is not already used by another external QR
        $dupStmt = $link->prepare("SELECT id FROM rifle_external_qrs WHERE rifle_number = ? AND id <> ? LIMIT 1");
        $dupStmt->bind_param("si", $rifle_number, $external_id);
        $dupStmt->execute();
        $dupRes = $dupStmt->get_result();
        if ($dupRes && $dupRes->num_rows > 0) {
            $dupStmt->close();
            return ['success' => false, 'message' => 'Another external QR already uses this rifle number'];
        }
        $dupStmt->close();

        // Delete old QR image if it exists
        if ($oldPath !== '' && file_exists($oldPath)) {
            @unlink($oldPath);
        }

        // Remove existing row before regenerating to avoid duplicate checks
        $del = $link->prepare("DELETE FROM rifle_external_qrs WHERE id = ?");
        $del->bind_param("i", $external_id);
        $del->execute();
        $del->close();

        // Also clear external_qr_path on linked rifle if applicable
        if ($rifleId !== null) {
            try {
                $colRes = $link->query("SHOW COLUMNS FROM rifles LIKE 'external_qr_path'");
                if ($colRes && $colRes->num_rows > 0) {
                    if ($up = $link->prepare("UPDATE rifles SET external_qr_path = NULL WHERE id = ?")) {
                        $up->bind_param("i", $rifleId);
                        $up->execute();
                        $up->close();
                    }
                }
            } catch (Exception $e) {
                error_log('updateExternalRifleQR: failed to clear external_qr_path: ' . $e->getMessage());
            }
        }

        // Generate a fresh external QR with the new rifle number
        $result = generateExternalRifleQR($rifle_number);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => "External QR updated from {$oldNumber} to {$rifle_number}",
            'qr_path' => $result['qr_path'] ?? null,
            'rifle_number' => $rifle_number,
            'payload' => $result['payload'] ?? null
        ];
    } catch (Exception $e) {
        error_log('updateExternalRifleQR error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update external QR: ' . $e->getMessage()];
    }
}

/**
 * Check if rifle number already exists in database
 * @param string $rifle_number The rifle number to check
 * @return array Result with exists status and rifle data
 */
function checkRifleExists($rifle_number) {
    global $link;
    
    $stmt = $link->prepare("SELECT id, rifle_number, qr_code_path, status FROM rifles WHERE rifle_number = ?");
    $stmt->bind_param("s", $rifle_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rifle = $result->fetch_assoc();
        $stmt->close();
        return [
            'exists' => true,
            'rifle' => $rifle
        ];
    }
    
    $stmt->close();
    return ['exists' => false, 'rifle' => null];
}

/**
 * Add new rifle to database
 * @param string $rifle_number The rifle number
 * @param string $status The rifle status (default: 'available')
 * @param string $condition_notes Condition notes (optional)
 * @return int|false The rifle ID or false on failure
 */
function addNewRifle($rifle_number, $status = 'available', $condition_notes = '') {
    global $link;
    
    try {
        // Determine which notes column exists
        $notesCol = 'notes';
        try {
            $res = $link->query("SHOW COLUMNS FROM rifles LIKE 'notes'");
            if (!$res || $res->num_rows === 0) {
                // Fallback to condition_notes if notes doesn't exist
                $res2 = $link->query("SHOW COLUMNS FROM rifles LIKE 'condition_notes'");
                if ($res2 && $res2->num_rows > 0) {
                    $notesCol = 'condition_notes';
                }
            }
        } catch (Exception $e) { /* ignore */ }

        $sql = "INSERT INTO rifles (rifle_number, status, {$notesCol}) VALUES (?, ?, ?)";
        $stmt = $link->prepare($sql);
        $stmt->bind_param("sss", $rifle_number, $status, $condition_notes);
        
        if ($stmt->execute()) {
            $rifle_id = $link->insert_id;
            $stmt->close();
            
            // Log the creation
            logRifleAction($rifle_id, null, 'created', $_SESSION['user_id'] ?? 1, "Rifle {$rifle_number} added to inventory");
            
            return $rifle_id;
        }
        
        $stmt->close();
        return false;
    } catch (Exception $e) {
        error_log("Failed to add rifle: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate or regenerate QR code for rifle (handles duplicates)
 * @param string $rifle_number The rifle number
 * @param bool $force_regenerate Force regeneration even if QR exists
 * @return array Result with success status and data
 */
function generateOrRegenerateRifleQR($rifle_number, $force_regenerate = false) {
    global $link;
    
    try {
        // Check if rifle exists
        $check_result = checkRifleExists($rifle_number);
        
        if ($check_result['exists']) {
            $rifle = $check_result['rifle'];
            
            // If QR exists and not forcing regeneration, return existing
            if (!empty($rifle['qr_code_path']) && !$force_regenerate && file_exists($rifle['qr_code_path'])) {
                return [
                    'success' => true,
                    'message' => 'QR code already exists',
                    'qr_path' => $rifle['qr_code_path'],
                    'rifle_number' => $rifle['rifle_number'],
                    'action' => 'existing'
                ];
            }
            
            // Generate QR for existing rifle using enhanced function
            $result = generateEnhancedRifleQR($rifle['rifle_number'], 'rifle', false, $force_regenerate);
            $qr_path = $result['success'] ? $result['qr_path'] : false;
            
            if ($qr_path) {
                return [
                    'success' => true,
                    'message' => 'QR code generated successfully',
                    'qr_path' => $qr_path,
                    'rifle_number' => $rifle['rifle_number'],
                    'action' => 'regenerated'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to generate QR code'
                ];
            }
        } else {
            // Rifle doesn't exist, add it first
            $rifle_id = addNewRifle($rifle_number);
            
            if ($rifle_id) {
                $result = generateEnhancedRifleQR($rifle_number, 'rifle', false, false);
                $qr_path = $result['success'] ? $result['qr_path'] : false;
                
                if ($qr_path) {
                    return [
                        'success' => true,
                        'message' => 'Rifle added and QR code generated successfully',
                        'qr_path' => $qr_path,
                        'rifle_number' => $rifle_number,
                        'action' => 'created'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Rifle added but QR generation failed'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to add rifle to database'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Generate/Regenerate QR error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Internal error: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate QR codes for all rifles that don't have one
 * @return array Array with success count and total processed
 */
function batchGenerateRifleQRs() {
    global $link;
    
    $success_count = 0;
    $total_count = 0;
    $errors = array();
    
    // Find rifles without QR codes
    $sql = "SELECT id, rifle_number FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = ''";
    $result = $link->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($rifle = $result->fetch_assoc()) {
            $total_count++;
            $result_data = generateEnhancedRifleQR($rifle['rifle_number'], 'rifle', false, false);
            
            if ($result_data['success']) {
                $success_count++;
            } else {
                $errors[] = "Failed to generate QR for rifle: " . $rifle['rifle_number'];
            }
        }
    }
    
    return array(
        'success' => $success_count > 0,
        'generated' => $success_count,
        'total' => $total_count,
        'errors' => $errors
    );
}

/**
 * Generate QR code for a cadet (for rifle assignment)
 * @param int $cadet_id The cadet profile ID
 * @return string|false The QR code file path or false on failure
 */
function generateCadetQR($cadet_id) {
    global $link;
    
    // Get cadet details
    $sql = "SELECT student_id, CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) AS full_name, platoon, course FROM cadet_profiles WHERE id = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("i", $cadet_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cadet = $result->fetch_assoc();
        $stmt->close();
        
        if (!$cadet) {
            return false;
        }
        
        // Create QR data for cadet
        $qr_data = array(
            'type' => 'cadet',
            'cadet_id' => $cadet_id,
            'student_id' => $cadet['student_id'],
            'name' => $cadet['full_name'],
            'platoon' => $cadet['platoon'],
            'course' => $cadet['course'],
            'generated_at' => date('Y-m-d H:i:s'),
            'system' => 'rotc_rifle_management'
        );
        
        // Convert to JSON and encrypt
        $json_data = json_encode($qr_data);
        $encryption_key = 'rifle-management-system-key-2024';
        $encrypted_data = base64_encode($encryption_key . '|' . $json_data);
        
        // Define QR code path
        $qr_directory = 'uploads/cadet_qrcodes/';
        $qr_filename = 'cadet_' . $cadet['student_id'] . '.png';
        $qr_code_path = $qr_directory . $qr_filename;
        
        // Ensure directory exists
        if (!is_dir($qr_directory)) {
            mkdir($qr_directory, 0755, true);
        }
        
        try {
            // Generate QR code
            QRcode::png($encrypted_data, $qr_code_path, QR_ECLEVEL_H, 8, 2);
            
            // Update cadet record with QR path (if column exists)
            $update_sql = "UPDATE cadet_profiles SET rifle_qr_code_path = ? WHERE id = ?";
            if ($stmt = $link->prepare($update_sql)) {
                $stmt->bind_param("si", $qr_code_path, $cadet_id);
                $stmt->execute();
                $stmt->close();
            }
            
            return $qr_code_path;
        } catch (Exception $e) {
            error_log("Cadet QR generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Decode QR code data
 * @param string $qr_data The scanned QR code data
 * @return array|false Decoded data or false on failure
 */
function decodeQRData($qr_data) {
    $encryption_key = 'rifle-management-system-key-2024';
    
    try {
        // Decode base64
        $decoded = base64_decode($qr_data);
        
        // Check if it starts with our encryption key
        if (strpos($decoded, $encryption_key . '|') === 0) {
            $json_data = substr($decoded, strlen($encryption_key . '|'));
            $data = json_decode($json_data, true);
            
            if ($data && isset($data['type']) && isset($data['system']) && $data['system'] === 'rotc_rifle_management') {
                return $data;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("QR decode failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate QR code for rifle operations
 * @param string $qr_data The scanned QR code data
 * @param string $expected_type Expected type ('rifle' or 'cadet')
 * @return array|false Validation result or false on failure
 */
function validateQRCode($qr_data, $expected_type) {
    $decoded = decodeQRData($qr_data);
    
    if (!$decoded || $decoded['type'] !== $expected_type) {
        return false;
    }
    
    return $decoded;
}

/**
 * Get rifle information from QR code
 * @param string $qr_data The scanned QR code data
 * @return array|false Rifle information or false on failure
 */
function getRifleFromQR($qr_data) {
    global $link;
    
    $decoded = validateQRCode($qr_data, 'rifle');
    if (!$decoded) {
        return false;
    }
    
    $rifle_id = $decoded['rifle_id'];
    
    $sql = "SELECT * FROM rifles WHERE id = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("i", $rifle_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rifle = $result->fetch_assoc();
        $stmt->close();
        
        return $rifle;
    }
    
    return false;
}

/**
 * Get cadet information from QR code
 * @param string $qr_data The scanned QR code data
 * @return array|false Cadet information or false on failure
 */
function getCadetFromQR($qr_data) {
    global $link;
    
    $decoded = validateQRCode($qr_data, 'cadet');
    if (!$decoded) {
        return false;
    }
    
    $cadet_id = $decoded['cadet_id'];
    
    $sql = "SELECT * FROM cadet_profiles WHERE id = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("i", $cadet_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cadet = $result->fetch_assoc();
        $stmt->close();
        
        return $cadet;
    }
    
    return false;
}

/**
 * Enhanced QR generation with dual encryption support and debug information
 * @param string $rifle_number The rifle number
 * @param string $encryption_type 'rifle' or 'attendance'
 * @param bool $debug_mode Enable detailed debug logging
 * @param bool $force_regenerate Force regeneration even if QR exists
 * @return array Result with success status, debug info, and data
 */
function generateEnhancedRifleQR($rifle_number, $encryption_type = 'rifle', $debug_mode = false, $force_regenerate = false) {
    global $link;
    
    $debug_info = [
        'start_time' => microtime(true),
        'rifle_number' => $rifle_number,
        'encryption_type' => $encryption_type,
        'debug_mode' => $debug_mode,
        'force_regenerate' => $force_regenerate,
        'steps' => [],
        'errors' => [],
        'warnings' => []
    ];
    
    try {
        // Step 1: Validate input parameters
        $debug_info['steps'][] = 'Validating input parameters';
        
        if (empty($rifle_number)) {
            $debug_info['errors'][] = 'Empty rifle number provided';
            return [
                'success' => false,
                'message' => 'Rifle number is required',
                'debug_info' => $debug_info
            ];
        }
        
        if (!in_array($encryption_type, ['rifle', 'attendance'])) {
            $debug_info['errors'][] = 'Invalid encryption type: ' . $encryption_type;
            return [
                'success' => false,
                'message' => 'Invalid encryption type. Must be "rifle" or "attendance"',
                'debug_info' => $debug_info
            ];
        }
        
        // Step 2: Check if rifle exists in database
        $debug_info['steps'][] = 'Checking rifle existence in database';
        
        $stmt = $link->prepare("SELECT id, rifle_number, rifle_type, qr_code_path, status FROM rifles WHERE rifle_number = ?");
        $stmt->bind_param("s", $rifle_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $rifle = $result->fetch_assoc();
        $stmt->close();
        
        if (!$rifle) {
            $debug_info['errors'][] = 'Rifle not found in database: ' . $rifle_number;
            return [
                'success' => false,
                'message' => 'Rifle not found in database',
                'debug_info' => $debug_info
            ];
        }
        
        $debug_info['rifle_data'] = $rifle;
        $debug_info['steps'][] = 'Rifle found with ID: ' . $rifle['id'];
        
        // Step 3: Check existing QR code
        if (!empty($rifle['qr_code_path']) && !$force_regenerate) {
            if (file_exists($rifle['qr_code_path'])) {
                $debug_info['steps'][] = 'QR code already exists at: ' . $rifle['qr_code_path'];
                $debug_info['warnings'][] = 'Using existing QR code (use force_regenerate=true to recreate)';
                
                return [
                    'success' => true,
                    'message' => 'QR code already exists',
                    'qr_path' => $rifle['qr_code_path'],
                    'rifle_number' => $rifle['rifle_number'],
                    'action' => 'existing',
                    'debug_info' => $debug_info
                ];
            } else {
                $debug_info['warnings'][] = 'QR path exists in database but file not found: ' . $rifle['qr_code_path'];
            }
        }
        
        // Step 4: Prepare QR data
        $debug_info['steps'][] = 'Preparing QR data with encryption type: ' . $encryption_type;
        
        $qr_data = [
            'type' => 'rifle',
            'rifle_id' => $rifle['id'],
            'rifle_number' => $rifle['rifle_number'],
            'rifle_type' => $rifle['rifle_type'],
            'generated_at' => date('Y-m-d H:i:s'),
            'system' => 'rotc_rifle_management',
            'encryption_method' => $encryption_type,
            'version' => '2.0'
        ];
        
        $debug_info['qr_data'] = $qr_data;
        
        // Step 5: Choose encryption method and key
        $debug_info['steps'][] = 'Selecting encryption method and key';
        
        if ($encryption_type === 'rifle') {
            $encryption_key = 'rifle-management-system-key-2024';
            $debug_info['encryption_key_type'] = 'rifle_key';
        } else {
            $encryption_key = 'attendance-system-key-2024';
            $debug_info['encryption_key_type'] = 'attendance_key';
        }
        
        // Step 6: Encrypt data
        $debug_info['steps'][] = 'Encrypting QR data';
        
        $json_data = json_encode($qr_data);
        $debug_info['json_length'] = strlen($json_data);
        
        try {
            $encrypted_data = encryptForCryptoJS($json_data, $encryption_key);
            $debug_info['encrypted_length'] = strlen($encrypted_data);
            $debug_info['steps'][] = 'Data encrypted successfully';
        } catch (Exception $e) {
            $debug_info['errors'][] = 'Encryption failed: ' . $e->getMessage();
            return [
                'success' => false,
                'message' => 'Failed to encrypt QR data',
                'debug_info' => $debug_info
            ];
        }
        
        // Step 7: Prepare file paths
        $debug_info['steps'][] = 'Preparing QR code file paths';
        
        $qr_directory = 'uploads/rifle_qrcodes/';
        $qr_filename = 'rifle_' . $rifle['rifle_number'] . '_' . $encryption_type . '.png';
        $qr_code_path = $qr_directory . $qr_filename;
        
        $debug_info['qr_directory'] = $qr_directory;
        $debug_info['qr_filename'] = $qr_filename;
        $debug_info['qr_code_path'] = $qr_code_path;
        
        // Step 8: Ensure directory exists
        if (!is_dir($qr_directory)) {
            $debug_info['steps'][] = 'Creating QR directory: ' . $qr_directory;
            if (!mkdir($qr_directory, 0755, true)) {
                $debug_info['errors'][] = 'Failed to create QR directory';
                return [
                    'success' => false,
                    'message' => 'Failed to create QR directory',
                    'debug_info' => $debug_info
                ];
            }
        } else {
            $debug_info['steps'][] = 'QR directory already exists';
        }
        
        // Step 9: Generate QR code with enhanced settings for uneven surfaces
        $debug_info['steps'][] = 'Generating QR code image with enhanced settings for uneven surfaces';
        
        try {
            // Use low error correction and larger size for better scanning on curved surfaces
            QRcode::png($encrypted_data, $qr_code_path, QR_ECLEVEL_L, 20, 4);
            
            if (file_exists($qr_code_path)) {
                $debug_info['file_size'] = filesize($qr_code_path);
                $debug_info['steps'][] = 'QR code file created successfully';
            } else {
                $debug_info['errors'][] = 'QR code file was not created';
                return [
                    'success' => false,
                    'message' => 'QR code file was not created',
                    'debug_info' => $debug_info
                ];
            }
        } catch (Exception $e) {
            $debug_info['errors'][] = 'QR code generation failed: ' . $e->getMessage();
            return [
                'success' => false,
                'message' => 'QR code generation failed',
                'debug_info' => $debug_info
            ];
        }
        
        // Step 10: Update database
        $debug_info['steps'][] = 'Updating database with QR path';
        
        $update_sql = "UPDATE rifles SET qr_code_path = ? WHERE id = ?";
        if ($stmt = $link->prepare($update_sql)) {
            $stmt->bind_param("si", $qr_code_path, $rifle['id']);
            if ($stmt->execute()) {
                $debug_info['steps'][] = 'Database updated successfully';
                $stmt->close();
            } else {
                $debug_info['warnings'][] = 'Failed to update database with QR path';
                $stmt->close();
            }
        } else {
            $debug_info['warnings'][] = 'Failed to prepare database update statement';
        }
        
        // Step 11: Final validation
        $debug_info['steps'][] = 'Performing final validation';
        $debug_info['end_time'] = microtime(true);
        $debug_info['total_time'] = $debug_info['end_time'] - $debug_info['start_time'];
        
        return [
            'success' => true,
            'message' => 'Enhanced QR code generated successfully',
            'qr_path' => $qr_code_path,
            'rifle_number' => $rifle['rifle_number'],
            'encryption_type' => $encryption_type,
            'action' => $force_regenerate ? 'regenerated' : 'created',
            'debug_info' => $debug_mode ? $debug_info : ['total_time' => $debug_info['total_time']]
        ];
        
    } catch (Exception $e) {
        $debug_info['errors'][] = 'Exception: ' . $e->getMessage();
        $debug_info['exception_trace'] = $e->getTraceAsString();
        
        return [
            'success' => false,
            'message' => 'Internal error during QR generation',
            'debug_info' => $debug_info
        ];
    }
}

/**
 * Enhanced batch QR generation with dual encryption support
 * @param string $encryption_type 'rifle' or 'attendance'
 * @param bool $debug_mode Enable detailed debug logging
 * @return array Result with success status, counts, and debug info
 */
function batchGenerateEnhancedRifleQRs($encryption_type = 'rifle', $debug_mode = false) {
    global $link;
    
    $debug_info = [
        'start_time' => microtime(true),
        'encryption_type' => $encryption_type,
        'debug_mode' => $debug_mode,
        'processed_rifles' => [],
        'errors' => [],
        'warnings' => []
    ];
    
    $success_count = 0;
    $total_count = 0;
    $errors = [];
    
    try {
        // Find rifles without QR codes or force regeneration
        $sql = "SELECT id, rifle_number, rifle_type, qr_code_path FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = '' ORDER BY rifle_type, rifle_number";
        $result = $link->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $debug_info['total_rifles_found'] = $result->num_rows;
            
            while ($rifle = $result->fetch_assoc()) {
                $total_count++;
                
                $rifle_debug = [
                    'rifle_number' => $rifle['rifle_number'],
                    'rifle_type' => $rifle['rifle_type'],
                    'start_time' => microtime(true)
                ];
                
                $qr_result = generateEnhancedRifleQR($rifle['rifle_number'], $encryption_type, $debug_mode, false);
                
                $rifle_debug['end_time'] = microtime(true);
                $rifle_debug['processing_time'] = $rifle_debug['end_time'] - $rifle_debug['start_time'];
                $rifle_debug['success'] = $qr_result['success'];
                
                if ($qr_result['success']) {
                    $success_count++;
                    $rifle_debug['qr_path'] = $qr_result['qr_path'];
                } else {
                    $rifle_debug['error'] = $qr_result['message'];
                    $errors[] = "Rifle {$rifle['rifle_number']}: {$qr_result['message']}";
                }
                
                if ($debug_mode && isset($qr_result['debug_info'])) {
                    $rifle_debug['detailed_debug'] = $qr_result['debug_info'];
                }
                
                $debug_info['processed_rifles'][] = $rifle_debug;
            }
        } else {
            $debug_info['warnings'][] = 'No rifles found that need QR codes';
        }
        
        $debug_info['end_time'] = microtime(true);
        $debug_info['total_time'] = $debug_info['end_time'] - $debug_info['start_time'];
        
        return [
            'success' => $success_count > 0,
            'message' => "Generated {$success_count} out of {$total_count} QR codes with {$encryption_type} encryption",
            'generated' => $success_count,
            'total' => $total_count,
            'encryption_type' => $encryption_type,
            'errors' => $errors,
            'debug_info' => $debug_mode ? $debug_info : [
                'total_time' => $debug_info['total_time'],
                'total_rifles_processed' => $total_count
            ]
        ];
        
    } catch (Exception $e) {
        $debug_info['errors'][] = 'Exception: ' . $e->getMessage();
        $debug_info['exception_trace'] = $e->getTraceAsString();
        
        return [
            'success' => false,
            'message' => 'Internal error during batch QR generation',
            'generated' => $success_count,
            'total' => $total_count,
            'errors' => $errors,
            'debug_info' => $debug_info
        ];
    }
}

/**
 * Enhanced QR decryption with dual encryption support and debug information
 * @param string $qr_data The scanned QR code data
 * @param bool $debug_mode Enable detailed debug logging
 * @return array Result with success status, decoded data, and debug info
 */
function decodeEnhancedQRData($qr_data, $debug_mode = false) {
    $debug_info = [
        'start_time' => microtime(true),
        'qr_data_length' => strlen($qr_data),
        'debug_mode' => $debug_mode,
        'decryption_attempts' => [],
        'errors' => [],
        'warnings' => []
    ];
    
    $encryption_keys = [
        'rifle' => 'rifle-management-system-key-2024',
        'attendance' => 'attendance-system-key-2024'
    ];
    
    try {
        // Try each encryption method
        foreach ($encryption_keys as $key_type => $encryption_key) {
            $attempt_debug = [
                'key_type' => $key_type,
                'start_time' => microtime(true)
            ];
            
            try {
                // Attempt CryptoJS-compatible decryption
                $decrypted = decryptFromCryptoJS($qr_data, $encryption_key);
                
                if ($decrypted !== false) {
                    $attempt_debug['decryption_success'] = true;
                    $attempt_debug['decrypted_length'] = strlen($decrypted);
                    
                    // Try to parse JSON
                    $data = json_decode($decrypted, true);
                    
                    if ($data && is_array($data)) {
                        $attempt_debug['json_parse_success'] = true;
                        $attempt_debug['data_type'] = $data['type'] ?? 'unknown';
                        
                        // Validate required fields
                        if (isset($data['system']) && $data['system'] === 'rotc_rifle_management') {
                            $attempt_debug['validation_success'] = true;
                            $attempt_debug['end_time'] = microtime(true);
                            $attempt_debug['processing_time'] = $attempt_debug['end_time'] - $attempt_debug['start_time'];
                            
                            $debug_info['decryption_attempts'][] = $attempt_debug;
                            $debug_info['successful_key_type'] = $key_type;
                            $debug_info['end_time'] = microtime(true);
                            $debug_info['total_time'] = $debug_info['end_time'] - $debug_info['start_time'];
                            
                            return [
                                'success' => true,
                                'data' => $data,
                                'encryption_type' => $key_type,
                                'debug_info' => $debug_mode ? $debug_info : ['total_time' => $debug_info['total_time']]
                            ];
                        } else {
                            $attempt_debug['validation_error'] = 'Invalid system identifier';
                        }
                    } else {
                        $attempt_debug['json_parse_error'] = 'Failed to parse JSON or invalid format';
                    }
                } else {
                    $attempt_debug['decryption_error'] = 'Decryption failed';
                }
            } catch (Exception $e) {
                $attempt_debug['exception'] = $e->getMessage();
            }
            
            $attempt_debug['end_time'] = microtime(true);
            $attempt_debug['processing_time'] = $attempt_debug['end_time'] - $attempt_debug['start_time'];
            $debug_info['decryption_attempts'][] = $attempt_debug;
        }
        
        // If we get here, all decryption attempts failed
        $debug_info['errors'][] = 'All decryption attempts failed';
        $debug_info['end_time'] = microtime(true);
        $debug_info['total_time'] = $debug_info['end_time'] - $debug_info['start_time'];
        
        return [
            'success' => false,
            'message' => 'Failed to decrypt QR code with any available key',
            'debug_info' => $debug_info
        ];
        
    } catch (Exception $e) {
        $debug_info['errors'][] = 'Exception: ' . $e->getMessage();
        $debug_info['exception_trace'] = $e->getTraceAsString();
        
        return [
            'success' => false,
            'message' => 'Internal error during QR decryption',
            'debug_info' => $debug_info
        ];
    }
}

/**
 * Decrypt data from CryptoJS compatible format
 * @param string $encrypted_data Base64 encoded encrypted data
 * @param string $passphrase The decryption passphrase
 * @return string|false Decrypted data or false on failure
 */
function decryptFromCryptoJS($encrypted_data, $passphrase) {
    try {
        // Decode base64
        $data = base64_decode($encrypted_data);
        
        if ($data === false || strlen($data) < 16) {
            return false;
        }
        
        // Check for "Salted__" prefix
        if (substr($data, 0, 8) !== "Salted__") {
            return false;
        }
        
        // Extract salt and encrypted data
        $salt = substr($data, 8, 8);
        $encrypted = substr($data, 16);
        
        // Derive key and IV using EVP_BytesToKey equivalent
        $key_iv = '';
        $d = $d_i = '';
        while (strlen($key_iv) < 48) { // 32 bytes key + 16 bytes IV
            $d_i = md5($d_i . $passphrase . $salt, true);
            $key_iv .= $d_i;
        }
        
        $key = substr($key_iv, 0, 32); // 256-bit key
        $iv = substr($key_iv, 32, 16); // 128-bit IV
        
        // Decrypt the data
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        return $decrypted;
    } catch (Exception $e) {
        return false;
    }
}

?>