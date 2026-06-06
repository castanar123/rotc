<?php
// Aggressive cache control headers for mobile browsers
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('ETag: "' . md5(time()) . '"');

// Generate cache-busting version parameter - Force complete cache refresh
$cache_version = time() . '_cleared_' . rand(1000, 9999);

// Start output buffering to prevent any unwanted output before JSON responses
ob_start();

// Suppress any potential warnings or notices that could interfere with JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

// Increase input limits to handle unlimited comma-separated rifle numbers
ini_set('max_input_vars', 5000);
ini_set('max_input_nesting_level', 500);

require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/rifle_functions.php';
require_once 'includes/rifle_qr_functions.php';
require_once 'includes/SecurityLogger.php';

// Restore error reporting for development (but keep display_errors off)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Detect database availability early to avoid fatals when connection fails
$DB_AVAILABLE = (isset($link) && $link);
$DB_ERROR_MSG = $GLOBALS['DB_CONNECTION_ERROR'] ?? null;

// Lightweight health check (visit rifle_management.php?health=1)
if (isset($_GET['health'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/plain');
    $status = $DB_AVAILABLE ? 'available' : ('unavailable' . ($DB_ERROR_MSG ? (': ' . $DB_ERROR_MSG) : ''));
    echo 'Rifle Management health OK | DB: ' . $status;
    exit;
}

// Helper to check if a column exists on a table (mysqli)
function rm_column_exists($link, $table, $column) {
    $tbl = mysqli_real_escape_string($link, $table);
    $col = mysqli_real_escape_string($link, $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$tbl` LIKE '$col'");
    return $res && mysqli_num_rows($res) > 0;
}

// Determine which column should be used as borrower key (temp_id if present, else name)
function rm_borrower_key_col($link) {
    if (rm_column_exists($link, 'borrowers', 'temp_id')) return 'temp_id';
    if (rm_column_exists($link, 'borrowers', 'name')) return 'name';
    // Fallback (should not normally happen)
    return 'id';
}

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
    // Log unauthorized access attempt
    $security_logger = new SecurityLogger();
    $security_logger->logSecurityEvent(
        $_SESSION['user_id'] ?? null,
        'UNAUTHORIZED_ACCESS',
        'Non-admin user attempted to access rifle management',
        ['page' => 'rifle_management'],
        'high'
    );
    
    // If this is an AJAX request, return JSON error instead of redirecting
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        sendCleanJsonResponse(['success' => false, 'message' => 'Authentication required', 'redirect' => 'login.php']);
        exit;
    }
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

// Log successful admin access to rifle management
$security_logger = new SecurityLogger();
$security_logger->logSecurityEvent(
    $_SESSION['user_id'],
    'ADMIN_ACCESS',
    'Admin accessed rifle management page',
    ['page' => 'rifle_management'],
    'low'
);

// Function to ensure clean JSON output
function sendCleanJsonResponse($data) {
    // Clean all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Output JSON and exit
    echo json_encode($data);
    exit;
}

// Handle AJAX requests for rifle operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean any output buffer content before processing
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        switch ($_POST['action']) {
            case 'assign_rifle':
                $cadet_id = $_POST['cadet_id'];
                $rifle_id = $_POST['rifle_id'];
                $result = assignRifle($cadet_id, $rifle_id);
                sendCleanJsonResponse($result);
                break;
                
            case 'return_rifle':
                $assignment_id = $_POST['assignment_id'];
                $returned_by = $_SESSION['user_id'] ?? 1; // Default to admin user if session not available
                $condition = $_POST['condition'] ?? 'good';
                $notes = $_POST['notes'] ?? '';
                $result = returnRifleByAssignment($assignment_id, $returned_by, $condition, $notes);
                sendCleanJsonResponse($result);
                break;
                
            case 'get_current_assignments':
                try {
                    $assignments = getCurrentAssignments();
                    $html = '';
                    
                    if (empty($assignments)) {
                        $html = '<div class="empty-state modern-empty">';
                        $html .= '<div class="empty-icon"><i class="fas fa-user-slash"></i></div>';
                        $html .= '<p class="empty-text">No rifles currently assigned</p>';
                        $html .= '<p class="empty-subtext">Assignments will appear here when rifles are checked out to cadets</p>';
                        $html .= '</div>';
                    } else {
                        foreach ($assignments as $assignment) {
                            // Calculate duration
                            $assigned_time = new DateTime($assignment['assigned_at']);
                            $current_time = new DateTime();
                            $interval = $assigned_time->diff($current_time);
                            if ($interval->days > 0) {
                                $duration = $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
                            } elseif ($interval->h > 0) {
                                $duration = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                            } else {
                                $duration = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                            }

                            $html .= '<div class="assignment-item modern-assignment-item">';
                            $html .= '<div class="assignment-avatar">';
                            $html .= '<div class="rifle-badge">';
                            $html .= '<i class="fas fa-crosshairs"></i>';
                            $html .= '<span class="rifle-number">#' . htmlspecialchars($assignment['rifle_number']) . '</span>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '<div class="assignment-details">';
                            $html .= '<div class="assignment-primary">';
                            $html .= '<span class="cadet-name">' . htmlspecialchars($assignment['cadet_name']) . '</span>';
                            $html .= '<span class="platoon-badge">' . htmlspecialchars($assignment['platoon']) . ' Platoon</span>';
                            $html .= '</div>';
                            $html .= '<div class="assignment-meta">';
                            $html .= '<span class="time-badge">';
                            $html .= '<i class="fas fa-calendar-alt"></i>';
                            $html .= 'Assigned: ' . date('M j, Y g:i A', strtotime($assignment['assigned_at']));
                            $html .= '</span>';
                            $html .= '<span class="duration-badge">';
                            $html .= '<i class="fas fa-clock"></i>';
                            $html .= $duration;
                            $html .= '</span>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '<div class="assignment-actions">';
                            $html .= '<div class="status-indicator status-assigned">';
                            $html .= '<span class="status-dot dot-warning"></span>';
                            $html .= '<span class="status-text">Assigned</span>';
                            $html .= '</div>';
                            $html .= '<button class="btn btn-sm btn-outline btn-return" onclick="returnRifle(' . $assignment['id'] . ')" title="Return Rifle">';
                            $html .= '<i class="fas fa-undo"></i>';
                            $html .= 'Return';
                            $html .= '</button>';
                            $html .= '</div>';
                            $html .= '</div>';
                        }
                    }
                    
                    sendCleanJsonResponse([
                        'success' => true,
                        'html' => $html,
                        'count' => count($assignments)
                    ]);
                } catch (Exception $e) {
                    error_log("Error getting current assignments: " . $e->getMessage());
                    sendCleanJsonResponse([
                        'success' => false,
                        'message' => 'Error loading assignments: ' . $e->getMessage()
                    ]);
                }
                break;
                
            case 'generate_rifle_qrs':
                try {
                    error_log("[QR DEBUG] Enhanced batch QR generation started");
                    
                    // Get encryption type and debug mode from request
                    $encryption_type = $_POST['encryption_type'] ?? 'rifle'; // 'rifle' or 'attendance'
                    $debug_mode = isset($_POST['debug_mode']) && $_POST['debug_mode'] === 'true';
                    
                    error_log("[QR DEBUG] Encryption type: {$encryption_type}, Debug mode: " . ($debug_mode ? 'enabled' : 'disabled'));
                    
                    // Check if enhanced batch generation function exists
                    if (!function_exists('batchGenerateEnhancedRifleQRs')) {
                        error_log("[QR ERROR] batchGenerateEnhancedRifleQRs function not found, falling back to standard function");
                        
                        if (!function_exists('batchGenerateRifleQRs')) {
                            error_log("[QR ERROR] batchGenerateRifleQRs function not found");
                            sendCleanJsonResponse(['success' => false, 'message' => 'Batch QR generation function not available']);
                        }
                        
                        $result = batchGenerateRifleQRs();
                    } else {
                        // Get count of rifles without QR codes first
                        $stmt = $link->prepare("SELECT COUNT(*) as count FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = ''");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $count_row = $result->fetch_assoc();
                        $rifles_without_qr = $count_row['count'];
                        
                        error_log("[QR DEBUG] Found {$rifles_without_qr} rifles without QR codes");
                        
                        if ($rifles_without_qr == 0) {
                            sendCleanJsonResponse([
                                'success' => true,
                                'message' => 'All rifles already have QR codes',
                                'generated' => 0,
                                'total' => 0,
                                'debug_info' => $debug_mode ? ['message' => 'No rifles need QR generation'] : null
                            ]);
                        }
                        
                        // Generate enhanced QR codes
                        error_log("[QR DEBUG] Calling batchGenerateEnhancedRifleQRs with encryption: {$encryption_type}");
                        $result = batchGenerateEnhancedRifleQRs($encryption_type, $debug_mode);
                    }
                    
                    error_log("[QR DEBUG] Batch generation result: " . json_encode($result));
                    
                    if ($result['success']) {
                        error_log("[QR SUCCESS] Generated {$result['generated']} QR codes out of {$result['total']} rifles");
                        $response = [
                            'success' => true,
                            'message' => "Generated {$result['generated']} QR codes successfully with {$encryption_type} encryption!",
                            'generated' => $result['generated'],
                            'total' => $result['total'],
                            'encryption_type' => $encryption_type
                        ];
                        
                        if ($debug_mode && isset($result['debug_info'])) {
                            $response['debug_info'] = $result['debug_info'];
                        }
                        
                        sendCleanJsonResponse($response);
                    } else {
                        error_log("[QR ERROR] Batch generation failed: " . $result['message']);
                        $response = ['success' => false, 'message' => $result['message']];
                        
                        if ($debug_mode && isset($result['debug_info'])) {
                            $response['debug_info'] = $result['debug_info'];
                        }
                        
                        sendCleanJsonResponse($response);
                    }
                } catch (Exception $e) {
                    error_log("[QR EXCEPTION] Enhanced batch QR generation error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Internal error: ' . $e->getMessage()]);
                }
                break;
                
            case 'generate_single_qr':
                try {
                    error_log("[QR DEBUG] Enhanced single QR generation started");
                    
                    $rifle_id = $_POST['rifle_id'] ?? '';
                    $rifle_number = $_POST['rifle_number'] ?? '';
                    $force_regenerate = isset($_POST['force_regenerate']) && $_POST['force_regenerate'] === 'true';
                    $encryption_type = $_POST['encryption_type'] ?? 'rifle'; // 'rifle' or 'attendance'
                    $debug_mode = isset($_POST['debug_mode']) && $_POST['debug_mode'] === 'true';
                    
                    error_log("[QR DEBUG] Encryption type: {$encryption_type}, Debug mode: " . ($debug_mode ? 'enabled' : 'disabled'));
                    
                    if (empty($rifle_id) && empty($rifle_number)) {
                        error_log("[QR ERROR] No rifle ID or rifle number provided");
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle ID or rifle number is required']);
                    }
                    
                    // If rifle_number is provided, use enhanced generation
                    if (!empty($rifle_number)) {
                        // Validate rifle number format
                        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $rifle_number)) {
                            sendCleanJsonResponse(['success' => false, 'message' => 'Invalid rifle number format']);
                        }
                        
                        // Try enhanced function first
                        if (function_exists('generateEnhancedRifleQR')) {
                            error_log("[QR DEBUG] Using enhanced QR generation for rifle: {$rifle_number}");
                            $result = generateEnhancedRifleQR($rifle_number, $encryption_type, $debug_mode, $force_regenerate);
                            
                            if ($result['success']) {
                                $response = [
                                    'success' => true,
                                    'message' => "QR code generated successfully with {$encryption_type} encryption!",
                                    'qr_path' => $result['qr_path'],
                                    'rifle_number' => $rifle_number,
                                    'encryption_type' => $encryption_type
                                ];
                                
                                if ($debug_mode && isset($result['debug_info'])) {
                                    $response['debug_info'] = $result['debug_info'];
                                }
                                
                                sendCleanJsonResponse($response);
                            } else {
                                $response = ['success' => false, 'message' => $result['message']];
                                
                                if ($debug_mode && isset($result['debug_info'])) {
                                    $response['debug_info'] = $result['debug_info'];
                                }
                                
                                sendCleanJsonResponse($response);
                            }
                        } else if (function_exists('generateOrRegenerateRifleQR')) {
                            error_log("[QR DEBUG] Falling back to standard QR generation");
                            $result = generateOrRegenerateRifleQR($rifle_number, $force_regenerate);
                            sendCleanJsonResponse($result);
                        } else {
                            error_log("[QR ERROR] No QR generation functions available");
                            sendCleanJsonResponse(['success' => false, 'message' => 'QR generation function not available']);
                        }
                    }
                    
                    // Original logic for rifle_id with enhanced support
                    error_log("[QR DEBUG] Processing rifle ID: " . $rifle_id);
                    
                    // Get rifle details
                    $stmt = $link->prepare("SELECT * FROM rifles WHERE id = ?");
                    $stmt->bind_param("i", $rifle_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $rifle = $result->fetch_assoc();
                    
                    if (!$rifle) {
                        error_log("[QR ERROR] Rifle not found for ID: " . $rifle_id);
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle not found']);
                    }
                    
                    error_log("[QR DEBUG] Found rifle: " . $rifle['rifle_number']);
                    
                    // Try enhanced function first
                    if (function_exists('generateEnhancedRifleQR')) {
                        error_log("[QR DEBUG] Using enhanced QR generation for rifle ID: {$rifle_id}");
                        $result = generateEnhancedRifleQR($rifle['rifle_number'], $encryption_type, $debug_mode, $force_regenerate);
                        
                        if ($result['success']) {
                            $response = [
                                'success' => true,
                                'message' => "QR code generated successfully with {$encryption_type} encryption!",
                                'qr_path' => $result['qr_path'],
                                'rifle_number' => $rifle['rifle_number'],
                                'encryption_type' => $encryption_type
                            ];
                            
                            if ($debug_mode && isset($result['debug_info'])) {
                                $response['debug_info'] = $result['debug_info'];
                            }
                            
                            sendCleanJsonResponse($response);
                        } else {
                            $response = ['success' => false, 'message' => $result['message']];
                            
                            if ($debug_mode && isset($result['debug_info'])) {
                                $response['debug_info'] = $result['debug_info'];
                            }
                            
                            sendCleanJsonResponse($response);
                        }
                    } else {
                        // Fallback to original function
                        if (!function_exists('generateRifleQR')) {
                            error_log("[QR ERROR] generateRifleQR function not found");
                            sendCleanJsonResponse(['success' => false, 'message' => 'QR generation function not available']);
                        }
                        
                        // Generate QR code
                        error_log("[QR DEBUG] Calling generateRifleQR for rifle: " . $rifle['rifle_number']);
                        $qr_path = generateRifleQR($rifle['id'], $rifle['rifle_number']);
                        
                        error_log("[QR DEBUG] QR generation result: " . json_encode($qr_path));
                        
                        if ($qr_path) {
                            // Verify the QR file was actually created
                            if (!file_exists($qr_path)) {
                                error_log("[QR ERROR] QR file not found at: " . $qr_path);
                                sendCleanJsonResponse(['success' => false, 'message' => 'QR file was not created properly']);
                            }
                            
                            error_log("[QR SUCCESS] QR code generated successfully at: " . $qr_path);
                            sendCleanJsonResponse([
                                'success' => true,
                                'message' => 'QR code generated successfully (standard encryption)',
                                'qr_path' => $qr_path,
                                'rifle_number' => $rifle['rifle_number'],
                                'encryption_type' => 'rifle'
                            ]);
                        } else {
                            error_log("[QR ERROR] QR generation failed");
                            sendCleanJsonResponse(['success' => false, 'message' => 'Failed to generate QR code']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("[QR EXCEPTION] Enhanced single QR generation error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Internal error: ' . $e->getMessage()]);
                }
                exit;

            case 'update_external_rifle_qr':
                if (function_exists('updateExternalRifleQR')) {
                    $external_id = $_POST['external_id'] ?? '';
                    $rifle_number = $_POST['rifle_number'] ?? '';

                    if (empty($external_id) || !is_numeric($external_id)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Valid external QR ID is required']);
                    }

                    $result = updateExternalRifleQR((int)$external_id, $rifle_number);
                    sendCleanJsonResponse($result);
                } else {
                    sendCleanJsonResponse(['success' => false, 'message' => 'External update function not available']);
                }
                exit;

            case 'delete_external_rifle_qr':
                if (function_exists('deleteExternalRifleQR')) {
                    $external_id = $_POST['external_id'] ?? '';

                    if (empty($external_id) || !is_numeric($external_id)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Valid external QR ID is required']);
                    }

                    $result = deleteExternalRifleQR((int)$external_id);
                    sendCleanJsonResponse($result);
                } else {
                    sendCleanJsonResponse(['success' => false, 'message' => 'External delete function not available']);
                }
                exit;

            case 'delete_all_external_rifles':
                $pin = $_POST['pin'] ?? '';
                
                if ($pin !== '472005') {
                    sendCleanJsonResponse(['success' => false, 'message' => 'Invalid PIN. Access denied.']);
                    exit;
                }

                try {
                    // Ensure table exists
                    $tableRes = $link->query("SHOW TABLES LIKE 'rifle_external_qrs'");
                    if (!$tableRes || $tableRes->num_rows === 0) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'External rifle QR table does not exist.']);
                        exit;
                    }

                    // Get count before deletion
                    $countRes = $link->query("SELECT COUNT(*) as count FROM rifle_external_qrs");
                    $countRow = $countRes->fetch_assoc();
                    $totalDeleted = $countRow['count'] ?? 0;

                    if ($totalDeleted === 0) {
                        sendCleanJsonResponse(['success' => true, 'message' => 'No external rifle QRs found to delete.', 'deleted_count' => 0]);
                        exit;
                    }

                    // Get all QR file paths before deletion for cleanup
                    $filesRes = $link->query("SELECT qr_path FROM rifle_external_qrs WHERE qr_path IS NOT NULL AND qr_path != ''");
                    $filesToDelete = [];
                    while ($row = $filesRes->fetch_assoc()) {
                        $filesToDelete[] = $row['qr_path'];
                    }

                    // Delete all records
                    $deleteRes = $link->query("DELETE FROM rifle_external_qrs");
                    
                    if (!$deleteRes) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Failed to delete external rifle QRs: ' . $link->error]);
                        exit;
                    }

                    // Clean up QR files
                    $filesCleaned = 0;
                    foreach ($filesToDelete as $filePath) {
                        $fullPath = __DIR__ . '/' . ltrim($filePath, '/');
                        if (file_exists($fullPath) && unlink($fullPath)) {
                            $filesCleaned++;
                        }
                    }

                    sendCleanJsonResponse([
                        'success' => true,
                        'message' => "Successfully deleted {$totalDeleted} external rifle QR(s) and cleaned up {$filesCleaned} file(s).",
                        'deleted_count' => $totalDeleted,
                        'files_cleaned' => $filesCleaned
                    ]);
                } catch (Exception $e) {
                    sendCleanJsonResponse(['success' => false, 'message' => 'Error deleting external rifle QRs: ' . $e->getMessage()]);
                }
                exit;

            case 'generate_new_rifle_qr_external':
                try {
                    error_log("[QR DEBUG] New EXTERNAL rifle QR generation started");

                    // Support both single and multiple rifle numbers
                    $rifle_numbers = $_POST['rifle_numbers'] ?? $_POST['rifle_number'] ?? '';
                    
                    if (empty($rifle_numbers)) {
                        error_log("[QR ERROR] No rifle number provided for external QR");
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle number is required']);
                    }

                    if (!function_exists('generateExternalRifleQR')) {
                        error_log("[QR ERROR] generateExternalRifleQR function not found");
                        sendCleanJsonResponse(['success' => false, 'message' => 'External QR generation function not available']);
                    }

                    // Split by comma and trim whitespace
                    $rifle_number_array = array_map('trim', explode(',', $rifle_numbers));
                    $rifle_number_array = array_filter($rifle_number_array, function($r) { return !empty($r); });
                    
                    if (count($rifle_number_array) === 0) {
                        error_log("[QR ERROR] No valid rifle numbers found after parsing");
                        sendCleanJsonResponse(['success' => false, 'message' => 'No valid rifle numbers provided']);
                    }

                    $generated_qrs = [];
                    $errors = [];

                    foreach ($rifle_number_array as $rifle_number) {
                        $result = generateExternalRifleQR($rifle_number);
                        
                        if ($result['success']) {
                            $generated_qrs[] = [
                                'rifle_number' => $result['rifle_number'],
                                'qr_path' => $result['qr_path'],
                                'payload' => $result['payload'] ?? null
                            ];
                        } else {
                            $errors[] = "Failed to generate QR for {$rifle_number}: " . ($result['message'] ?? 'unknown error');
                            error_log("[QR ERROR] External QR generation failed for {$rifle_number}: " . ($result['message'] ?? 'unknown'));
                        }
                    }

                    if (empty($generated_qrs)) {
                        sendCleanJsonResponse([
                            'success' => false,
                            'message' => 'Failed to generate any external QR codes. Errors: ' . implode('; ', $errors)
                        ]);
                    }

                    // Return response
                    if (count($generated_qrs) === 1) {
                        // Single QR result (backward compatibility)
                        $qr = $generated_qrs[0];
                        sendCleanJsonResponse([
                            'success' => true,
                            'message' => 'External rifle QR generated successfully',
                            'qr_path' => $qr['qr_path'],
                            'rifle_number' => $qr['rifle_number'],
                            'payload' => $qr['payload']
                        ]);
                    } else {
                        // Multiple QR results
                        sendCleanJsonResponse([
                            'success' => true,
                            'message' => 'Generated ' . count($generated_qrs) . ' external QR codes successfully',
                            'multiple' => true,
                            'qrs' => $generated_qrs,
                            'errors' => $errors
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("[QR EXCEPTION] New external rifle QR generation error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Internal error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_external_rifle_qrs':
                try {
                    // Ensure table exists; if not, return empty list gracefully
                    $tableRes = $link->query("SHOW TABLES LIKE 'rifle_external_qrs'");
                    if (!$tableRes || $tableRes->num_rows === 0) {
                        sendCleanJsonResponse([
                            'success' => true,
                            'items' => []
                        ]);
                    }

                    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
                    $filter = $_POST['filter'] ?? 'all';

                    $sql = "SELECT re.id, re.rifle_id, re.rifle_number, re.qr_path, re.payload_json, re.generated_at, r.status AS rifle_status
                            FROM rifle_external_qrs re
                            LEFT JOIN rifles r ON re.rifle_id = r.id";

                    $conditions = [];
                    $params = [];
                    $types = '';

                    if ($search !== '') {
                        $conditions[] = "re.rifle_number LIKE CONCAT('%', ?, '%')";
                        $params[] = $search;
                        $types .= 's';
                    }

                    if ($filter === 'linked') {
                        $conditions[] = 're.rifle_id IS NOT NULL';
                    } elseif ($filter === 'unlinked') {
                        $conditions[] = 're.rifle_id IS NULL';
                    }

                    if (!empty($conditions)) {
                        $sql .= ' WHERE ' . implode(' AND ', $conditions);
                    }

                    $sql .= ' ORDER BY re.generated_at DESC LIMIT 200';

                    if ($stmt = $link->prepare($sql)) {
                        if ($types !== '') {
                            $stmt->bind_param($types, ...$params);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $items = [];
                        while ($row = $result->fetch_assoc()) {
                            $items[] = $row;
                        }
                        $stmt->close();

                        sendCleanJsonResponse([
                            'success' => true,
                            'items' => $items
                        ]);
                    } else {
                        sendCleanJsonResponse([
                            'success' => false,
                            'message' => 'Failed to prepare query for external rifle QRs'
                        ]);
                    }
                } catch (Exception $e) {
                    error_log('[QR EXCEPTION] Get external rifle QRs error: ' . $e->getMessage());
                    sendCleanJsonResponse([
                        'success' => false,
                        'message' => 'Failed to load external rifle QRs: ' . $e->getMessage()
                    ]);
                }
                exit;

            case 'get_unified_rifles':
                try {
                    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
                    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 100;
                    $search = $_POST['search'] ?? '';
                    $type = $_POST['type'] ?? 'all';
                    $type = in_array($type, ['internal','external','all'], true) ? $type : 'all';

                    $items = [];
                    $internal_count = 0;
                    $external_count = 0;

                    // Internal rifles
                    if ($type === 'internal' || $type === 'all') {
                        if (function_exists('getAllRifles')) {
                            $result = getAllRifles($page, $limit, $search);
                            foreach ($result['rifles'] as $row) {
                                $items[] = [
                                    'id' => (int)$row['id'],
                                    'rifle_number' => $row['rifle_number'],
                                    'status' => $row['current_status'] ?? $row['status'],
                                    'qr_code_path' => $row['qr_code_path'],
                                    'type' => 'internal',
                                ];
                                $internal_count++;
                            }
                        } else {
                            // Fallback to simple rifles query
                            $sql = "SELECT id, rifle_number, status, qr_code_path FROM rifles";
                            $params = [];
                            $types = '';
                            if (!empty($search)) {
                                $sql .= " WHERE rifle_number LIKE CONCAT('%', ?, '%') OR status LIKE CONCAT('%', ?, '%')";
                                $params = [$search, $search];
                                $types = 'ss';
                            }
                            $sql .= " ORDER BY rifle_number LIMIT ? OFFSET ?";
                            $offset = ($page - 1) * $limit;
                            $stmt = $link->prepare($sql);
                            if (!empty($params)) {
                                $types .= 'ii';
                                $params[] = $limit;
                                $params[] = $offset;
                                $stmt->bind_param($types, ...$params);
                            } else {
                                $stmt->bind_param('ii', $limit, $offset);
                            }
                            $stmt->execute();
                            $res = $stmt->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $items[] = [
                                    'id' => (int)$row['id'],
                                    'rifle_number' => $row['rifle_number'],
                                    'status' => $row['status'],
                                    'qr_code_path' => $row['qr_code_path'],
                                    'type' => 'internal',
                                ];
                                $internal_count++;
                            }
                            $stmt->close();
                        }
                    }

                    // External rifle QRs
                    if ($type === 'external' || $type === 'all') {
                        $tableRes = $link->query("SHOW TABLES LIKE 'rifle_external_qrs'");
                        if ($tableRes && $tableRes->num_rows > 0) {
                            $sql = "SELECT re.id, re.rifle_id, re.rifle_number, re.qr_path, re.generated_at, r.status AS rifle_status
                                    FROM rifle_external_qrs re
                                    LEFT JOIN rifles r ON re.rifle_id = r.id";
                            $conditions = [];
                            $params = [];
                            $types = '';
                            if (!empty($search)) {
                                $conditions[] = "re.rifle_number LIKE CONCAT('%', ?, '%')";
                                $params[] = $search;
                                $types .= 's';
                            }
                            if (!empty($conditions)) {
                                $sql .= ' WHERE ' . implode(' AND ', $conditions);
                            }
                            $sql .= ' ORDER BY re.generated_at DESC LIMIT 200';

                            if ($stmt = $link->prepare($sql)) {
                                if ($types !== '') {
                                    $stmt->bind_param($types, ...$params);
                                }
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($row = $res->fetch_assoc()) {
                                    $items[] = [
                                        'id' => (int)$row['id'],
                                        'rifle_number' => $row['rifle_number'],
                                        'status' => $row['rifle_status'] ?: 'External QR',
                                        'qr_code_path' => $row['qr_path'],
                                        'type' => 'external',
                                        'generated_at' => $row['generated_at'],
                                        'linked' => !empty($row['rifle_id']),
                                        'rifle_status' => $row['rifle_status'],
                                    ];
                                    $external_count++;
                                }
                                $stmt->close();
                            }
                        }
                    }

                    sendCleanJsonResponse([
                        'success' => true,
                        'items' => $items,
                        'internal_count' => $internal_count,
                        'external_count' => $external_count,
                        'total' => $internal_count + $external_count,
                    ]);
                } catch (Exception $e) {
                    error_log('[QR EXCEPTION] get_unified_rifles error: ' . $e->getMessage());
                    sendCleanJsonResponse([
                        'success' => false,
                        'message' => 'Failed to load rifles: ' . $e->getMessage()
                    ]);
                }
                exit;

            case 'get_rifle_list':
                if (function_exists('getAllRifles')) {
                    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
                    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
                    $search = $_POST['search'] ?? '';
                    
                    $result = getAllRifles($page, $limit, $search);
                    sendCleanJsonResponse([
                        'success' => true,
                        'rifles' => $result['rifles'],
                        'total' => $result['total'],
                        'page' => $result['page'],
                        'limit' => $result['limit'],
                        'total_pages' => $result['total_pages']
                    ]);
                } else {
                    try {
                        error_log("[QR DEBUG] Getting rifle list");
                        
                        $stmt = $link->prepare("SELECT id, rifle_number, rifle_type, status, qr_code_path FROM rifles ORDER BY rifle_type, rifle_number");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $rifles = [];
                        
                        while ($row = $result->fetch_assoc()) {
                            $rifles[] = $row;
                        }
                        
                        error_log("[QR DEBUG] Found " . count($rifles) . " rifles");
                        
                        sendCleanJsonResponse([
                            'success' => true,
                            'rifles' => $rifles
                        ]);
                    } catch (Exception $e) {
                        error_log("[QR EXCEPTION] Get rifle list error: " . $e->getMessage());
                        sendCleanJsonResponse(['success' => false, 'message' => 'Failed to load rifle list: ' . $e->getMessage()]);
                    }
                }
                exit;
                
            case 'update_rifle':
                if (function_exists('updateRifle')) {
                    $rifle_id = $_POST['rifle_id'] ?? '';
                    $rifle_number = $_POST['rifle_number'] ?? '';

                    if (empty($rifle_id) || !is_numeric($rifle_id)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Valid rifle ID is required']);
                    }

                    $result = updateRifle((int)$rifle_id, $rifle_number);
                    sendCleanJsonResponse($result);
                } else {
                    sendCleanJsonResponse(['success' => false, 'message' => 'Update function not available']);
                }
                exit;
                
            case 'delete_rifle':
                if (function_exists('deleteRifle')) {
                    $rifle_id = $_POST['rifle_id'] ?? '';
                    
                    if (empty($rifle_id) || !is_numeric($rifle_id)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Valid rifle ID is required']);
                    }
                    
                    $result = deleteRifle((int)$rifle_id);
                    sendCleanJsonResponse($result);
                } else {
                    sendCleanJsonResponse(['success' => false, 'message' => 'Delete function not available']);
                }
                exit;
                
            case 'get_rifle_stats':
                if (function_exists('getRifleStatistics')) {
                    $stats = getRifleStatistics();
                    sendCleanJsonResponse([
                        'success' => true,
                        'stats' => $stats
                    ]);
                } else {
                    sendCleanJsonResponse([
                        'success' => false,
                        'message' => 'Statistics function not available'
                    ]);
                }
                exit;
                
            case 'get_all_qr_codes':
                try {
                    error_log("[QR DEBUG] Getting all QR codes for printing");
                    
                    $qr_codes = [];

                    // Internal rifle QRs
                    $stmt = $link->prepare("SELECT id, rifle_number, rifle_type, status, qr_code_path FROM rifles WHERE qr_code_path IS NOT NULL AND qr_code_path != '' ORDER BY rifle_type, rifle_number");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        if (file_exists($row['qr_code_path'])) {
                            $qr_codes[] = [
                                'id' => $row['id'],
                                'rifle_number' => $row['rifle_number'],
                                'rifle_type' => $row['rifle_type'],
                                'status' => $row['status'],
                                'qr_path' => $row['qr_code_path'],
                                'type' => 'internal'
                            ];
                        }
                    }
                    $stmt->close();

                    // External rifle QRs (ROTC_QR_V1)
                    $tableRes = $link->query("SHOW TABLES LIKE 'rifle_external_qrs'");
                    if ($tableRes && $tableRes->num_rows > 0) {
                        $sqlExt = "SELECT id, rifle_number, qr_path FROM rifle_external_qrs ORDER BY generated_at DESC";
                        $extStmt = $link->prepare($sqlExt);
                        $extStmt->execute();
                        $extRes = $extStmt->get_result();
                        while ($row = $extRes->fetch_assoc()) {
                            if (!empty($row['qr_path']) && file_exists($row['qr_path'])) {
                                $qr_codes[] = [
                                    'id' => $row['id'],
                                    'rifle_number' => $row['rifle_number'],
                                    'rifle_type' => 'external',
                                    'status' => 'External QR',
                                    'qr_path' => $row['qr_path'],
                                    'type' => 'external'
                                ];
                            }
                        }
                        $extStmt->close();
                    }
                    
                    error_log("[QR DEBUG] Found " . count($qr_codes) . " QR codes for printing");
                    
                    sendCleanJsonResponse([
                        'success' => true,
                        'qr_codes' => $qr_codes,
                        'total' => count($qr_codes)
                    ]);
                } catch (Exception $e) {
                    error_log("[QR EXCEPTION] Get all QR codes error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to load QR codes: ' . $e->getMessage()]);
                }
                exit;
                
            case 'generate_new_rifle_qr':
                try {
                    error_log("[QR DEBUG] New rifle QR generation started");
                    
                    $rifle_number = $_POST['rifle_number'] ?? '';
                    
                    if (empty($rifle_number)) {
                        error_log("[QR ERROR] No rifle number provided");
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle number is required']);
                    }

                    // Batch mode: support comma-separated rifle numbers
                    if (strpos($rifle_number, ',') !== false) {
                        error_log("[QR DEBUG] Batch new rifle processing started");

                        // Split, trim, and de-duplicate
                        $parts = array_filter(array_map(function($s){ return trim($s); }, explode(',', $rifle_number)), function($s){ return $s !== ''; });
                        $parts = array_values(array_unique($parts));

                        if (empty($parts)) {
                            sendCleanJsonResponse(['success' => false, 'message' => 'No valid rifle numbers provided']);
                        }

                        // Prepare statements once
                        $select_stmt = $link->prepare("SELECT id, rifle_number, qr_code_path FROM rifles WHERE rifle_number = ?");
                        $insert_stmt = $link->prepare("INSERT INTO rifles (rifle_number, rifle_type, status, created_at) VALUES (?, ?, 'available', NOW())");

                        $results = [
                            'success' => true,
                            'message' => 'Batch processed',
                            'processed' => 0,
                            'created' => 0,
                            'regenerated' => 0,
                            'skipped_existing_qr' => 0,
                            'errors' => [],
                            'items' => []
                        ];

                        foreach ($parts as $rn) {
                            $results['processed']++;

                            // Validate each rifle number format
                            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $rn)) {
                                $msg = 'Invalid rifle number format';
                                $results['errors'][] = ['rifle_number' => $rn, 'error' => $msg];
                                $results['items'][] = ['rifle_number' => $rn, 'status' => 'error', 'message' => $msg];
                                continue;
                            }

                            try {
                                // Check existing
                                $select_stmt->bind_param("s", $rn);
                                $select_stmt->execute();
                                $existing = $select_stmt->get_result()->fetch_assoc();

                                if ($existing) {
                                    // If QR exists, skip; otherwise generate
                                    if (!empty($existing['qr_code_path']) && file_exists($existing['qr_code_path'])) {
                                        $results['skipped_existing_qr']++;
                                        $results['items'][] = [
                                            'rifle_number' => $rn,
                                            'status' => 'existing_qr',
                                            'qr_path' => $existing['qr_code_path']
                                        ];
                                    } else {
                                        // Generate QR for existing rifle
                                        $qr_path = false;
                                        if (function_exists('generateEnhancedRifleQR')) {
                                            $gen = generateEnhancedRifleQR($rn, 'rifle', false, true);
                                            $qr_path = $gen['success'] ? $gen['qr_path'] : false;
                                        } elseif (function_exists('generateRifleQR')) {
                                            $qr_path = generateRifleQR($existing['id'], $rn);
                                        }

                                        if ($qr_path && file_exists($qr_path)) {
                                            $results['regenerated']++;
                                            $results['items'][] = [
                                                'rifle_number' => $rn,
                                                'status' => 'regenerated',
                                                'qr_path' => $qr_path
                                            ];
                                        } else {
                                            $msg = 'Failed to generate QR for existing rifle';
                                            $results['errors'][] = ['rifle_number' => $rn, 'error' => $msg];
                                            $results['items'][] = ['rifle_number' => $rn, 'status' => 'error', 'message' => $msg];
                                        }
                                    }
                                } else {
                                    // Determine rifle type
                                    $rifle_type = 'mechanical rifle';
                                    if (preg_match('/^\d+$/', $rn)) {
                                        $rifle_type = 'wooden rifle';
                                    } elseif (preg_match('/^R\d+$/', $rn)) {
                                        $rifle_type = 'mechanical rifle';
                                    } elseif (preg_match('/^TEST/', $rn)) {
                                        $rifle_type = 'mechanical rifle';
                                    }

                                    $insert_stmt->bind_param("ss", $rn, $rifle_type);
                                    if ($insert_stmt->execute()) {
                                        $new_id = $link->insert_id;
                                        $qr_path = false;
                                        if (function_exists('generateEnhancedRifleQR')) {
                                            $gen = generateEnhancedRifleQR($rn, 'rifle', false, false);
                                            $qr_path = $gen['success'] ? $gen['qr_path'] : false;
                                        } elseif (function_exists('generateRifleQR')) {
                                            $qr_path = generateRifleQR($new_id, $rn);
                                        }

                                        if ($qr_path && file_exists($qr_path)) {
                                            $results['created']++;
                                            $results['items'][] = [
                                                'rifle_number' => $rn,
                                                'status' => 'created',
                                                'qr_path' => $qr_path
                                            ];
                                        } else {
                                            $msg = 'Rifle added but failed to generate QR code';
                                            $results['errors'][] = ['rifle_number' => $rn, 'error' => $msg];
                                            $results['items'][] = ['rifle_number' => $rn, 'status' => 'error', 'message' => $msg];
                                        }
                                    } else {
                                        $msg = 'Failed to add rifle to database';
                                        $results['errors'][] = ['rifle_number' => $rn, 'error' => $msg];
                                        $results['items'][] = ['rifle_number' => $rn, 'status' => 'error', 'message' => $msg];
                                    }
                                }
                            } catch (Exception $ie) {
                                $results['errors'][] = ['rifle_number' => $rn, 'error' => $ie->getMessage()];
                                $results['items'][] = ['rifle_number' => $rn, 'status' => 'error', 'message' => $ie->getMessage()];
                            }
                        }

                        // Summarize message
                        $results['message'] = sprintf(
                            'Processed %d. Created: %d, Regenerated: %d, Skipped existing: %d, Errors: %d',
                            $results['processed'], $results['created'], $results['regenerated'], $results['skipped_existing_qr'], count($results['errors'])
                        );

                        sendCleanJsonResponse($results);
                    }

                    // Validate rifle number format (single)
                    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $rifle_number)) {
                        error_log("[QR ERROR] Invalid rifle number format: " . $rifle_number);
                        sendCleanJsonResponse(['success' => false, 'message' => 'Invalid rifle number format. Use only letters, numbers, hyphens, and underscores.']);
                    }
                    
                    error_log("[QR DEBUG] Processing rifle number: " . $rifle_number);
                    
                    // Check if rifle already exists in database
                    $stmt = $link->prepare("SELECT id, rifle_number, qr_code_path FROM rifles WHERE rifle_number = ?");
                    $stmt->bind_param("s", $rifle_number);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existing_rifle = $result->fetch_assoc();
                    
                    if ($existing_rifle) {
                        error_log("[QR DEBUG] Rifle already exists with ID: " . $existing_rifle['id']);
                        
                        // Check if QR code already exists
                        if (!empty($existing_rifle['qr_code_path']) && file_exists($existing_rifle['qr_code_path'])) {
                            error_log("[QR DEBUG] QR code already exists for rifle: " . $rifle_number);
                            sendCleanJsonResponse([
                                'success' => true,
                                'message' => 'QR code already exists for this rifle',
                                'qr_path' => $existing_rifle['qr_code_path'],
                                'rifle_number' => $rifle_number,
                                'action' => 'existing'
                            ]);
                        } else {
                            // Generate QR for existing rifle
                            error_log("[QR DEBUG] Generating QR for existing rifle: " . $rifle_number);
                            
                            if (!function_exists('generateRifleQR')) {
                                error_log("[QR ERROR] generateRifleQR function not found");
                                sendCleanJsonResponse(['success' => false, 'message' => 'QR generation function not available']);
                            }
                            
                            // Use enhanced QR generation function
                            if (function_exists('generateEnhancedRifleQR')) {
                                error_log("[QR DEBUG] Using enhanced QR generation for existing rifle: " . $rifle_number);
                                $result = generateEnhancedRifleQR($rifle_number, 'rifle', false, true);
                                $qr_path = $result['success'] ? $result['qr_path'] : false;
                            } else {
                                error_log("[QR DEBUG] Falling back to legacy QR generation for existing rifle: " . $rifle_number);
                                $qr_path = generateRifleQR($existing_rifle['id'], $rifle_number);
                            }
                            
                            if ($qr_path && file_exists($qr_path)) {
                                error_log("[QR SUCCESS] QR code generated for existing rifle: " . $rifle_number);
                                sendCleanJsonResponse([
                                    'success' => true,
                                    'message' => 'QR code generated successfully for existing rifle',
                                    'qr_path' => $qr_path,
                                    'rifle_number' => $rifle_number,
                                    'action' => 'regenerated'
                                ]);
                            } else {
                                error_log("[QR ERROR] Failed to generate QR for existing rifle: " . $rifle_number);
                                sendCleanJsonResponse(['success' => false, 'message' => 'Failed to generate QR code for existing rifle']);
                            }
                        }
                    } else {
                        // Rifle doesn't exist, add it to database first
                        error_log("[QR DEBUG] Adding new rifle to database: " . $rifle_number);
                        
                        // Determine rifle type based on rifle number pattern
                        $rifle_type = 'mechanical rifle'; // default
                        if (preg_match('/^\d+$/', $rifle_number)) {
                            $rifle_type = 'wooden rifle'; // numeric rifle numbers are wooden
                        } elseif (preg_match('/^R\d+$/', $rifle_number)) {
                            $rifle_type = 'mechanical rifle'; // R-prefixed are mechanical
                        } elseif (preg_match('/^TEST/', $rifle_number)) {
                            $rifle_type = 'mechanical rifle'; // test rifles are mechanical
                        }
                        
                        $insert_stmt = $link->prepare("INSERT INTO rifles (rifle_number, rifle_type, status, created_at) VALUES (?, ?, 'available', NOW())");
                        $insert_stmt->bind_param("ss", $rifle_number, $rifle_type);
                        
                        if ($insert_stmt->execute()) {
                            $new_rifle_id = $link->insert_id;
                            error_log("[QR DEBUG] New rifle added with ID: " . $new_rifle_id);
                            
                            // Generate QR code for new rifle using enhanced function
                            if (function_exists('generateEnhancedRifleQR')) {
                                error_log("[QR DEBUG] Using enhanced QR generation for new rifle: " . $rifle_number);
                                $result = generateEnhancedRifleQR($rifle_number, 'rifle', false, false);
                                $qr_path = $result['success'] ? $result['qr_path'] : false;
                            } else if (function_exists('generateRifleQR')) {
                                error_log("[QR DEBUG] Falling back to legacy QR generation for new rifle: " . $rifle_number);
                                $qr_path = generateRifleQR($new_rifle_id, $rifle_number);
                            } else {
                                error_log("[QR ERROR] No QR generation functions available");
                                sendCleanJsonResponse(['success' => false, 'message' => 'QR generation function not available']);
                            }
                            
                            if ($qr_path && file_exists($qr_path)) {
                                error_log("[QR SUCCESS] QR code generated for new rifle: " . $rifle_number);
                                sendCleanJsonResponse([
                                    'success' => true,
                                    'message' => 'New rifle added and QR code generated successfully',
                                    'qr_path' => $qr_path,
                                    'rifle_number' => $rifle_number,
                                    'action' => 'created'
                                ]);
                            } else {
                                error_log("[QR ERROR] Failed to generate QR for new rifle: " . $rifle_number);
                                sendCleanJsonResponse(['success' => false, 'message' => 'Rifle added but failed to generate QR code']);
                            }
                        } else {
                            error_log("[QR ERROR] Failed to add new rifle to database: " . $rifle_number);
                            sendCleanJsonResponse(['success' => false, 'message' => 'Failed to add rifle to database']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("[QR EXCEPTION] New rifle QR generation error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Internal error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'regenerate_qr':
                try {
                    error_log("[QR DEBUG] QR regeneration started");
                    
                    $rifle_number = $_POST['rifle_number'] ?? '';
                    
                    if (empty($rifle_number)) {
                        error_log("[QR ERROR] No rifle number provided for regeneration");
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle number is required']);
                    }
                    
                    error_log("[QR DEBUG] Regenerating QR for rifle: " . $rifle_number);
                    
                    // Check if rifle exists in database
                    $stmt = $link->prepare("SELECT id, rifle_number, qr_code_path FROM rifles WHERE rifle_number = ?");
                    $stmt->bind_param("s", $rifle_number);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existing_rifle = $result->fetch_assoc();
                    
                    if (!$existing_rifle) {
                        error_log("[QR ERROR] Rifle not found for regeneration: " . $rifle_number);
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle not found in database']);
                    }
                    
                    // Delete old QR file if it exists
                    if (!empty($existing_rifle['qr_code_path']) && file_exists($existing_rifle['qr_code_path'])) {
                        unlink($existing_rifle['qr_code_path']);
                        error_log("[QR DEBUG] Deleted old QR file: " . $existing_rifle['qr_code_path']);
                    }
                    
                    // Generate new QR code using enhanced function
                    if (function_exists('generateEnhancedRifleQR')) {
                        error_log("[QR DEBUG] Using enhanced QR regeneration for rifle: " . $rifle_number);
                        $result = generateEnhancedRifleQR($rifle_number, 'rifle', false, true); // Force regeneration
                        $qr_path = $result['success'] ? $result['qr_path'] : false;
                    } else if (function_exists('generateRifleQR')) {
                        error_log("[QR DEBUG] Falling back to legacy QR regeneration for rifle: " . $rifle_number);
                        $qr_path = generateRifleQR($existing_rifle['id'], $rifle_number);
                    } else {
                        error_log("[QR ERROR] No QR generation functions available for regeneration");
                        sendCleanJsonResponse(['success' => false, 'message' => 'QR generation function not available']);
                    }
                    
                    if ($qr_path && file_exists($qr_path)) {
                        error_log("[QR SUCCESS] QR code regenerated for rifle: " . $rifle_number);
                        sendCleanJsonResponse([
                            'success' => true,
                            'message' => 'QR code regenerated successfully',
                            'qr_path' => $qr_path,
                            'rifle_number' => $rifle_number
                        ]);
                    } else {
                        error_log("[QR ERROR] Failed to regenerate QR for rifle: " . $rifle_number);
                        sendCleanJsonResponse(['success' => false, 'message' => 'Failed to regenerate QR code']);
                    }
                } catch (Exception $e) {
                    error_log("[QR EXCEPTION] QR regeneration error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Internal error: ' . $e->getMessage()]);
                }
                exit;
                
            // New Borrowing Workflow Handlers
            case 'check_borrower_qr':
                try {
                    $qr_data = $_POST['qr_data'] ?? '';
                    
                    if (empty($qr_data)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'QR data is required']);
                    }
                    
                    // Check if borrower exists using schema-aware key
                    $keyCol = rm_borrower_key_col($link);
                    $sql = "SELECT * FROM borrowers WHERE $keyCol = ? AND status = 'active'";
                    $stmt = $link->prepare($sql);
                    $stmt->bind_param("s", $qr_data);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $borrower = $result->fetch_assoc();
                    
                    if ($borrower) {
                        sendCleanJsonResponse([
                            'success' => true,
                            'borrower_found' => true,
                            'borrower' => [
                                'id' => $borrower['id'],
                                'name' => $borrower['name'],
                                'course' => $borrower['course'],
                                'contact' => $borrower['contact'],
                                'temp_id' => $borrower['temp_id']
                            ]
                        ]);
                    } else {
                        // Check if borrower key exists but is inactive
                        $keyCol = rm_borrower_key_col($link);
                        $sql = "SELECT * FROM borrowers WHERE $keyCol = ?";
                        $stmt = $link->prepare($sql);
                        $stmt->bind_param("s", $qr_data);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $inactive_borrower = $result->fetch_assoc();
                        
                        sendCleanJsonResponse([
                            'success' => true,
                            'borrower_found' => false,
                            'borrower_key' => $qr_data,
                            'is_recycled' => $inactive_borrower ? true : false
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("Check borrower QR error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to check borrower QR']);
                }
                exit;
                
            case 'register_borrower':
                try {
                    $temp_id = $_POST['temp_id'] ?? '';
                    $name = $_POST['name'] ?? '';
                    $course = $_POST['course'] ?? '';
                    $contact = $_POST['contact'] ?? '';
                    
                    if (empty($temp_id) || empty($name) || empty($course)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Temp ID, name, and course are required']);
                    }
                    
                    // Check if borrower key already exists and is active
                    $keyCol = rm_borrower_key_col($link);
                    $sql = "SELECT id FROM borrowers WHERE $keyCol = ? AND status = 'active'";
                    $stmt = $link->prepare($sql);
                    $stmt->bind_param("s", $temp_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'This temp ID is already active']);
                    }
                    
                    // Update existing borrower or create new one
                    $keyCol = rm_borrower_key_col($link);
                    $sql = "SELECT id FROM borrowers WHERE $keyCol = ?";
                    $stmt = $link->prepare($sql);
                    $stmt->bind_param("s", $temp_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existing = $result->fetch_assoc();
                    
                    if ($existing) {
                        // Update existing borrower using schema-aware key
                        $keyCol = rm_borrower_key_col($link);
                        $sql = "UPDATE borrowers SET name = ?, course = ?, contact = ?, status = 'active', updated_at = NOW() WHERE $keyCol = ?";
                        $stmt = $link->prepare($sql);
                        $stmt->bind_param("ssss", $name, $course, $contact, $temp_id);
                        $borrower_id = $existing['id'];
                    } else {
                        // Create new borrower (use temp_id if column exists, otherwise use name as key)
                        if (rm_column_exists($link, 'borrowers', 'temp_id')) {
                            $stmt = $link->prepare("INSERT INTO borrowers (temp_id, name, course, contact, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                            $stmt->bind_param("ssss", $temp_id, $name, $course, $contact);
                        } else {
                            // Use provided temp_id value as name key if temp_id column missing
                            $stmt = $link->prepare("INSERT INTO borrowers (name, course, contact, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                            $stmt->bind_param("sss", $temp_id, $course, $contact);
                        }
                        $borrower_id = null;
                    }
                    
                    if ($stmt->execute()) {
                        if (!$borrower_id) {
                            $borrower_id = $link->insert_id;
                        }
                        
                        sendCleanJsonResponse([
                            'success' => true,
                            'message' => 'Borrower registered successfully',
                            'borrower' => [
                                'id' => $borrower_id,
                                'name' => $name,
                                'course' => $course,
                                'contact' => $contact,
                                // Provide both for compatibility
                                'borrower_key' => $temp_id,
                                'temp_id' => $temp_id
                            ]
                        ]);
                    } else {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Failed to register borrower']);
                    }
                } catch (Exception $e) {
                    error_log("Register borrower error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to register borrower']);
                }
                exit;
                
            case 'check_rifle_qr':
                try {
                    $qr_data = $_POST['qr_data'] ?? '';
                    
                    if (empty($qr_data)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'QR data is required']);
                    }
                    
                    // Check if rifle exists and is available
                    $stmt = $link->prepare("SELECT * FROM rifles WHERE rifle_number = ? AND status = 'available'");
                    $stmt->bind_param("s", $qr_data);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $rifle = $result->fetch_assoc();
                    
                    if ($rifle) {
                        sendCleanJsonResponse([
                            'success' => true,
                            'rifle_found' => true,
                            'rifle' => [
                                'id' => $rifle['id'],
                                'rifle_number' => $rifle['rifle_number'],
                                'rifle_type' => $rifle['rifle_type'],
                                'status' => $rifle['status']
                            ]
                        ]);
                    } else {
                        // Check if rifle exists but not available
                        $stmt = $link->prepare("SELECT * FROM rifles WHERE rifle_number = ?");
                        $stmt->bind_param("s", $qr_data);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $unavailable_rifle = $result->fetch_assoc();
                        
                        if ($unavailable_rifle) {
                            sendCleanJsonResponse([
                                'success' => false,
                                'message' => 'Rifle is not available (Status: ' . $unavailable_rifle['status'] . ')'
                            ]);
                        } else {
                            sendCleanJsonResponse([
                                'success' => false,
                                'message' => 'Rifle not found'
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Check rifle QR error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to check rifle QR']);
                }
                exit;
                
            case 'confirm_borrowing':
                try {
                    $borrower_id = $_POST['borrower_id'] ?? '';
                    $rifle_ids = $_POST['rifle_ids'] ?? [];
                    
                    if (empty($borrower_id) || empty($rifle_ids) || !is_array($rifle_ids)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Borrower ID and rifle IDs are required']);
                    }
                    
                    $link->begin_transaction();
                    
                    try {
                        // Get borrower details
                        $stmt = $link->prepare("SELECT * FROM borrowers WHERE id = ? AND status = 'active'");
                        $stmt->bind_param("i", $borrower_id);
                        $stmt->execute();
                        $borrower = $stmt->get_result()->fetch_assoc();
                        
                        if (!$borrower) {
                            throw new Exception('Borrower not found or inactive');
                        }
                        
                        $borrowed_rifles = [];
                        
                        foreach ($rifle_ids as $rifle_id) {
                            // Verify rifle is still available
                            $stmt = $link->prepare("SELECT * FROM rifles WHERE id = ? AND status = 'available'");
                            $stmt->bind_param("i", $rifle_id);
                            $stmt->execute();
                            $rifle = $stmt->get_result()->fetch_assoc();
                            
                            if (!$rifle) {
                                throw new Exception('Rifle ID ' . $rifle_id . ' is not available');
                            }
                            
                            // Create borrowing record
                            $stmt = $link->prepare("INSERT INTO rifle_assignments (rifle_id, borrower_id, assigned_by, assigned_at, status) VALUES (?, ?, ?, NOW(), 'active')");
                            $stmt->bind_param("iii", $rifle_id, $borrower_id, $_SESSION['user_id']);
                            
                            if (!$stmt->execute()) {
                                throw new Exception('Failed to create borrowing record for rifle ID ' . $rifle_id);
                            }
                            
                            // Update rifle status
                            $stmt = $link->prepare("UPDATE rifles SET status = 'assigned' WHERE id = ?");
                            $stmt->bind_param("i", $rifle_id);
                            
                            if (!$stmt->execute()) {
                                throw new Exception('Failed to update rifle status for rifle ID ' . $rifle_id);
                            }
                            
                            $borrowed_rifles[] = [
                                'id' => $rifle['id'],
                                'rifle_number' => $rifle['rifle_number'],
                                'rifle_type' => $rifle['rifle_type']
                            ];
                        }
                        
                        $link->commit();
                        
                        sendCleanJsonResponse([
                            'success' => true,
                            'message' => 'Borrowing confirmed successfully',
                            'borrower' => [
                                'name' => $borrower['name'],
                                'course' => $borrower['course'],
                                'temp_id' => $borrower['temp_id']
                            ],
                            'rifles' => $borrowed_rifles,
                            'total_rifles' => count($borrowed_rifles)
                        ]);
                        
                    } catch (Exception $e) {
                        $link->rollback();
                        throw $e;
                    }
                } catch (Exception $e) {
                    error_log("Confirm borrowing error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to confirm borrowing: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_borrower_rifles':
                try {
                    $qr_data = $_POST['qr_data'] ?? '';
                    
                    if (empty($qr_data)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'QR data is required']);
                    }
                    
                    // Get borrower and their active rifles
                    $stmt = $link->prepare("
                        SELECT b.*, r.id as rifle_id, r.rifle_number, r.rifle_type, ra.assigned_at
                        FROM borrowers b
                        JOIN rifle_assignments ra ON b.id = ra.borrower_id
                        JOIN rifles r ON ra.rifle_id = r.id
                        WHERE b." . rm_borrower_key_col($link) . " = ? AND ra.status = 'active'
                        ORDER BY ra.assigned_at DESC
                    ");
                    $stmt->bind_param("s", $qr_data);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $borrower_info = null;
                    $rifles = [];
                    
                    while ($row = $result->fetch_assoc()) {
                        if (!$borrower_info) {
                            $borrower_info = [
                                'id' => $row['id'],
                                'name' => $row['name'],
                                'course' => $row['course'],
                                'contact' => $row['contact'],
                                // Provide key if available
                                'borrower_key' => $qr_data,
                                'temp_id' => isset($row['temp_id']) ? $row['temp_id'] : ''
                            ];
                        }
                        
                        $rifles[] = [
                            'id' => $row['rifle_id'],
                            'rifle_number' => $row['rifle_number'],
                            'rifle_type' => $row['rifle_type'],
                            'assigned_at' => $row['assigned_at']
                        ];
                    }
                    
                    if ($borrower_info) {
                        sendCleanJsonResponse([
                            'success' => true,
                            'borrower' => $borrower_info,
                            'rifles' => $rifles,
                            'total_rifles' => count($rifles)
                        ]);
                    } else {
                        sendCleanJsonResponse([
                            'success' => false,
                            'message' => 'No active borrowings found for this borrower'
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("Get borrower rifles error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to get borrower rifles']);
                }
                exit;
                
            case 'return_single_rifle':
                try {
                    $rifle_id = $_POST['rifle_id'] ?? '';
                    $borrower_id = $_POST['borrower_id'] ?? '';
                    
                    if (empty($rifle_id) || empty($borrower_id)) {
                        sendCleanJsonResponse(['success' => false, 'message' => 'Rifle ID and borrower ID are required']);
                    }
                    
                    $link->begin_transaction();
                    
                    try {
                        // Update assignment status
                        $stmt = $link->prepare("UPDATE rifle_assignments SET status = 'returned', returned_at = NOW(), returned_by = ? WHERE rifle_id = ? AND borrower_id = ? AND status = 'active'");
                        $stmt->bind_param("iii", $_SESSION['user_id'], $rifle_id, $borrower_id);
                        
                        if (!$stmt->execute() || $stmt->affected_rows === 0) {
                            throw new Exception('Failed to update assignment record');
                        }
                        
                        // Update rifle status
                        $stmt = $link->prepare("UPDATE rifles SET status = 'available' WHERE id = ?");
                        $stmt->bind_param("i", $rifle_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to update rifle status');
                        }
                        
                        // Get rifle details for response
                        $stmt = $link->prepare("SELECT rifle_number, rifle_type FROM rifles WHERE id = ?");
                        $stmt->bind_param("i", $rifle_id);
                        $stmt->execute();
                        $rifle = $stmt->get_result()->fetch_assoc();
                        
                        $link->commit();
                        
                        sendCleanJsonResponse([
                            'success' => true,
                            'message' => 'Rifle returned successfully',
                            'rifle' => [
                                'id' => $rifle_id,
                                'rifle_number' => $rifle['rifle_number'],
                                'rifle_type' => $rifle['rifle_type']
                            ]
                        ]);
                        
                    } catch (Exception $e) {
                        $link->rollback();
                        throw $e;
                    }
                } catch (Exception $e) {
                    error_log("Return single rifle error: " . $e->getMessage());
                    sendCleanJsonResponse(['success' => false, 'message' => 'Failed to return rifle: ' . $e->getMessage()]);
                }
                exit;
                

            default:
                sendCleanJsonResponse(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
    } catch (Exception $e) {
        sendCleanJsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get rifle management statistics (safe even if DB unavailable)
try {
    if ($DB_AVAILABLE) {
        $stats = function_exists('getRifleStatistics')
            ? getRifleStatistics()
            : ['total_rifles' => 0, 'available_rifles' => 0, 'assigned_rifles' => 0, 'maintenance_rifles' => 0];
        $current_assignments = function_exists('getCurrentAssignments')
            ? getCurrentAssignments()
            : [];
        // Recent activities are now loaded dynamically via JavaScript
        // Get rifles needing QR codes
        $rifles_without_qr_sql = "SELECT COUNT(*) as count FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = ''";
        $res = $link->query($rifles_without_qr_sql);
        $row = $res ? $res->fetch_assoc() : ['count' => 0];
        $rifles_without_qr = (int)($row['count'] ?? 0);
    } else {
        if ($DB_ERROR_MSG) { error_log("Rifle Management: DB unavailable - $DB_ERROR_MSG"); }
        $stats = ['total_rifles' => 0, 'available_rifles' => 0, 'assigned_rifles' => 0, 'maintenance_rifles' => 0];
        $current_assignments = [];
        $rifles_without_qr = 0;
    }
} catch (Throwable $e) {
    error_log("Rifle Management Dashboard Error: " . $e->getMessage());
    $stats = ['total_rifles' => 0, 'available_rifles' => 0, 'assigned_rifles' => 0, 'maintenance_rifles' => 0];
    $current_assignments = [];
    $rifles_without_qr = 0;
}

$page_title = 'Rifle Management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="cache-control" content="max-age=0">
    <meta name="expires" content="0">
    <meta name="pragma" content="no-cache">
    <title>Rifle Management - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css?v=<?php echo $cache_version; ?>">
    <link rel="stylesheet" href="css/dashboard-redesigned.css?v=<?php echo $cache_version; ?>">
    <link rel="stylesheet" href="css/mobile-responsive.css?v=<?php echo $cache_version; ?>">
    <link rel="stylesheet" href="css/rifle-mobile.css?v=<?php echo $cache_version; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Font loading error prevention */
        @font-face {
            font-family: 'Rajdhani-Fallback';
            src: local('Arial'), local('Helvetica'), local('sans-serif');
            font-display: swap;
        }
        
        /* Ensure fonts load with fallbacks */
        body, .rifle-management {
            font-family: 'Rajdhani', 'Rajdhani-Fallback', Arial, sans-serif !important;
            font-display: swap;
        }
        
        /* Icon fallback for Font Awesome loading issues */
        .fa, .fas, .far, .fab {
            font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Pro', Arial, sans-serif;
            font-display: swap;
        }
        
        /* Error handling styles */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            border: 1px solid #c3e6cb;
        }
    </style>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔫</text></svg>">
    <style>
        .rifle-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .rifle-card:hover {
            background-color: #28a745;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .rifle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .rifle-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .rifle-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        /* Enhanced Status Badge Styling */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid transparent;
        }
        
        .badge-success, .status-available {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #28a745;
        }
        
        .badge-success::before, .status-available::before {
            content: "✓";
            font-weight: bold;
        }
        
        .badge-primary, .status-assigned {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-color: #ffc107;
        }
        
        .badge-primary::before, .status-assigned::before {
            content: "👤";
        }
        
        .badge-warning, .status-maintenance {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #dc3545;
        }
        
        .badge-warning::before, .status-maintenance::before {
            content: "🔧";
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #dc3545;
        }
        
        .badge-danger::before {
            content: "❌";
        }
        
        .badge-secondary {
            background: linear-gradient(135deg, #e2e3e5, #d6d8db);
            color: #383d41;
            border-color: #6c757d;
        }
        
        .badge-secondary::before {
            content: "❓";
        }
        
        .assignment-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
        }
        
        .scanner-container {
            background: var(--card-bg);
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .scanner-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.1rem;
        }
        
        .activity-assign {
            background: #d4edda;
            color: #155724;
        }
        
        .activity-return {
            background: #cce5ff;
            color: #004085;
        }
        
        /* QR Generator Styles - Dark Theme */
        .qr-generator-section {
            margin-bottom: 2rem;
            background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(15, 20, 25, 0.98) 100%);
            border: 1px solid #333;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .qr-generator-tabs {
            display: flex;
            border-bottom: 2px solid #333;
            margin-bottom: 1.5rem;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            color: #888;
            font-weight: 500;
        }
        
        .tab-btn.active {
            border-bottom-color: #22c55e;
            color: #ffffff;
        }
        
        .tab-btn:hover {
            background: #2a2a2a;
            color: #ffffff;
        }
        
        .qr-tab-content {
            display: none;
        }
        
        .qr-tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #ffffff;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #333;
            border-radius: 6px;
            background: #2a2a2a;
            color: #ffffff;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .form-control::placeholder {
            color: #888;
        }
        
        .form-actions {
            margin-top: 1.5rem;
        }
        
        .qr-result {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background: #2a2a2a;
            border: 1px solid #333;
        }
        
        .qr-preview {
            text-align: center;
            margin: 1rem 0;
        }
        
        .qr-preview img {
            max-width: 200px;
            border: 1px solid #333;
            border-radius: 8px;
        }
        
        .batch-info {
            background: #2a2a2a;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #333;
            color: #ffffff;
        }
        
        .batch-stats {
            margin-top: 1rem;
            font-weight: 500;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #333;
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #22c55e;
            transition: width 0.3s ease;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--header-bg);
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--text-color);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Option Tabs for QR Generation */
        .qr-generation-options {
            margin-top: 1rem;
        }
        
        .option-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        
        .option-tab {
            background: none;
            border: none;
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-radius: 6px 6px 0 0;
            transition: all 0.3s ease;
            color: #888;
            font-weight: 500;
            border-bottom: 2px solid transparent;
        }
        
        .option-tab.active {
            background: #22c55e;
            color: white;
            border-bottom-color: #22c55e;
        }
        
        .option-tab:hover:not(.active) {
            background: #2a2a2a;
            color: #22c55e;
        }
        
        .generation-mode {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .generation-mode.active {
            display: block;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Scrollable List Styles */
        .scrollable-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
        }
        
        .scrollable-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .scrollable-list::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        .scrollable-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Modern Dashboard Card Styles */
        .dashboard-card {
            background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(15, 20, 25, 0.98) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(40, 167, 69, 0.2);
            border-radius: 16px;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card:hover {
            border-color: rgba(40, 167, 69, 0.4);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .modern-header {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            border-bottom: 1px solid rgba(40, 167, 69, 0.2);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header-title h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .header-icon {
            color: #28a745;
            font-size: 1.5rem;
        }
        
        .header-badge {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .count-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }
        
        .modern-content {
            padding: 2rem;
        }
        
        /* Modern Empty State */
        .modern-empty {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .empty-text {
            font-size: 1.25rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }
        
        .empty-subtext {
            font-size: 0.95rem;
            color: #6c757d;
            margin: 0;
        }
        
        /* Modern Activity List */
        .modern-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        
        .modern-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .modern-list::-webkit-scrollbar-track {
            background: rgba(40, 167, 69, 0.1);
            border-radius: 3px;
        }
        
        .modern-list::-webkit-scrollbar-thumb {
            background: #28a745;
            border-radius: 3px;
        }
        
        .modern-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(40, 167, 69, 0.1);
            transition: all 0.3s ease;
        }
        
        .modern-item:hover {
            background: rgba(40, 167, 69, 0.05);
            border-radius: 8px;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .modern-item:last-child {
            border-bottom: none;
        }
        
        .activity-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .avatar-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .avatar-info {
            background: linear-gradient(135deg, #17a2b8, #20c997);
        }
        
        .activity-details {
            flex: 1;
            min-width: 0;
        }
        
        .activity-primary {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            flex-wrap: wrap;
        }
        
        .cadet-name {
            font-weight: 600;
            color: #ffffff;
        }
        
        .action-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .rifle-badge {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .activity-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .time-badge {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .activity-status {
            display: flex;
            align-items: center;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .dot-success {
            background: #28a745;
        }
        
        .dot-info {
            background: #17a2b8;
        }
        
        /* Show More Item */
        .show-more-item {
            padding: 1rem 0;
            border-top: 1px solid rgba(40, 167, 69, 0.1);
            margin-top: 1rem;
        }
        
        .show-more-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .show-more-text {
            margin-right: 0.5rem;
        }
        
        .btn-link {
            background: none;
            border: none;
            color: #28a745;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
        }
        
        .btn-link:hover {
            color: #20c997;
        }
        
        /* Modern Search Controls */
        .modern-controls {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .modern-search {
            flex: 1;
            max-width: 400px;
        }
        
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            color: #6c757d;
            z-index: 2;
        }
        
        .modern-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid rgba(40, 167, 69, 0.3);
            border-radius: 25px;
            background: rgba(15, 20, 25, 0.8);
            color: #ffffff;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .modern-input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
            background: rgba(15, 20, 25, 0.95);
        }
        
        .modern-input::placeholder {
            color: #6c757d;
        }
        
        .count-container {
            display: flex;
            align-items: center;
        }
        
        /* Modern Grid */
        .modern-grid {
            min-height: 300px;
        }
        
        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .loading-spinner {
            font-size: 2rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .loading-text {
            font-size: 1rem;
            color: #ffffff;
            margin: 0;
        }
        
        /* Search Container Styles */
        .header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .search-container {
            position: relative;
            min-width: 250px;
        }
        
        .search-input {
            width: 100%;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        
        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        
        .item-count {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        /* Show More Indicator */
        .show-more-indicator {
            text-align: center;
            padding: 1rem;
            color: var(--text-muted);
            font-style: italic;
            border-top: 1px solid var(--border-color);
            background: #f8f9fa;
        }
        
        .show-more-indicator i {
            margin-right: 0.5rem;
        }
        
        /* Enhanced Card Header */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--header-bg);
            border-radius: 12px 12px 0 0;
        }
        
        /* Enhanced Rifle Item Styles with Visual Indicators */
        .rifle-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
           background: rgba(255, 255, 255, 0.05);
 
            margin-bottom: 0.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .rifle-item:hover {
            background-color: #00ff7f;
            color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,255,127,0.3);
        }
        
        .rifle-item:last-child {
            border-bottom: none;
        }
        
        /* Status-based border colors */
        .rifle-item[data-status="available"] {
            border-left-color: #28a745;
        }
        
        .rifle-item[data-status="assigned"] {
            border-left-color: #ffc107;
        }
        
        .rifle-item[data-status="maintenance"] {
            border-left-color: #dc3545;
        }
        
        .rifle-item[data-status="lost"] {
            border-left-color: #6c757d;
        }
        
        /* Rifle Info Layout */
        .rifle-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }
        
        .rifle-number {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .rifle-number::before {
            content: "🔫";
            font-size: 1.2rem;
        }
        
        .rifle-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .rifle-qr {
            margin-left: auto;
        }
        
        .rifle-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        /* Activity Item Enhanced Styles */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
            background: rgba(255, 255, 255, 0.05);

        }
        
        .activity-item:hover {
            background-color: #00ff7f;
            color: #000000;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
        
        .no-results i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Text Selection and Highlight Styles */
        ::selection {
            background-color: #00ff7f;
            color: #000000;
        }
        
        ::-moz-selection {
            background-color: #00ff7f;
            color: #000000;
        }
        
        /* Search Highlight Styles */
        .highlight {
            background-color: #ffc107;
            color: #000000;
            padding: 0.1rem 0.2rem;
            border-radius: 3px;
            font-weight: 600;
        }
        
        mark {
            background-color: #ffc107;
            color: #000000;
            padding: 0.1rem 0.2rem;
            border-radius: 3px;
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
        }
        
        /* QR Label Styles */
        .qr-label {
            text-align: center;
            margin-top: 10px;
            padding: 12px;
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            border-radius: 4px;
            font-size: 20px;
            font-weight: bold;
            color: #e0e0e0;
            letter-spacing: 1px;
        }
        
        /* Print Modal Styles */
        .print-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(15, 20, 25, 0.98) 100%);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .print-modal-content {
            background: #1e2329;
            color: #e0e0e0;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            height: 85vh;
            max-height: 85vh;
            overflow: hidden;
            border: 1px solid #3a3a3a;
            display: flex;
            flex-direction: column;
        }
        
        .print-modal-content .modal-header {
            border-bottom: 1px solid #3a3a3a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .print-modal-content .modal-header h3 {
            color: #00ff7f;
            margin: 0;
            font-weight: 600;
        }
        
        .print-modal-content .btn-close {
            background: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #3a3a3a;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .print-modal-content .btn-close:hover {
            background: #ff4d4d;
            border-color: #ff4d4d;
        }
        
        .modal-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
        }
        
        .print-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
            flex-shrink: 0;
        }
        
        .print-option {
            padding: 20px;
            border: 2px solid #3a3a3a;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #2a2a2a;
            color: #e0e0e0;
        }
        
        .print-option:hover {
            border-color: #00ff7f;
            background: #1a3a1a;
            transform: translateY(-2px);
        }
        
        .print-option i {
            font-size: 2em;
            color: #00ff7f;
            margin-bottom: 10px;
        }
        
        .print-option h4 {
            color: #e0e0e0;
            margin: 10px 0;
            font-weight: 600;
        }
        
        .print-option p {
            color: #a0a0a0;
            margin: 0;
            font-size: 14px;
        }
        
        /* Print Layout Styles */
        .print-layout {
            display: none;
        }
        
        @media print {
            @page {
                size: A4;
                margin: 0.2cm;
            }
            
            body * {
                visibility: hidden;
            }
            
            .print-layout, .print-layout * {
                visibility: visible;
            }
            
            .print-layout {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                display: block !important;
            }
            
            .print-page {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: repeat(3, auto);
                gap: 5mm;
                padding: 5mm;
                page-break-inside: avoid;
                width: 100%;
                min-height: auto;
                box-sizing: border-box;
                max-width: 210mm;
            }
            
            .print-page.full-page {
                page-break-after: always;
            }
            
            .print-page:last-child {
                page-break-after: auto;
            }
            
            .print-qr-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                page-break-inside: avoid;
                width: 60mm;
                height: 70mm;
                border: 1px solid #ddd;
                border-radius: 2mm;
                padding: 2mm;
                box-sizing: border-box;
            }
            
            .print-qr-item img {
                width: 250px;
                height: 250px;
                margin-bottom: 2px;
                object-fit: contain;
                max-width: 100%;
                max-height: calc(100% - 20px);
            }
            
            .print-qr-label {
                font-size: 14px;
                font-weight: bold;
                color: #000;
                margin-top: 0px;
                letter-spacing: 0.3px;
                white-space: nowrap;
                line-height: 1;
            }
            
            /* Legacy support for old class names */
            .qr-print-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1px;
                padding: 1px;
                page-break-inside: avoid;
                max-width: 210mm;
            }
        }
        
        }
        
        .rifle-selection {
            border: 1px solid #3a3a3a;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            background: #2a2a2a;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .rifle-selection h4 {
            color: #00ff7f;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .rifle-checkbox {
            display: flex;
            align-items: center;
            padding: 8px;
            margin: 5px 0;
            border-radius: 4px;
            transition: background 0.2s;
            color: #e0e0e0;
        }
        
        .rifle-checkbox:hover {
            background: #1a3a1a;
        }
        
        .rifle-checkbox input {
            margin-right: 10px;
            accent-color: #00ff7f;
        }
        
        .rifle-checkbox label {
            color: #e0e0e0;
            cursor: pointer;
            flex: 1;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .modal-actions .btn {
            background: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #3a3a3a;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .modal-actions .btn-primary {
            background: #00ff7f;
            color: #000;
            border-color: #00ff7f;
        }
        
        .modal-actions .btn-primary:hover {
            background: #00e673;
            border-color: #00e673;
        }
        
        .modal-actions .btn-secondary:hover {
            background: #3a3a3a;
            border-color: #4a4a4a;
        }
        
        /* QR Search and Control Styles */
        .qr-search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .qr-search-input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            background: #1a1a1a;
            border: 1px solid #3a3a3a;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .qr-search-input:focus {
            outline: none;
            border-color: #00ff7f;
            box-shadow: 0 0 0 2px rgba(0, 255, 127, 0.2);
        }
        
        .qr-search-input::placeholder {
            color: #888;
        }
        
        .qr-search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            pointer-events: none;
        }
        
        .qr-control-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .sticky-controls {
            position: sticky;
            top: 0;
            background: #2a2a2a;
            z-index: 10;
            padding: 10px 0;
            border-bottom: 1px solid #3a3a3a;
            margin-bottom: 10px;
        }
        
        .qr-control-buttons .btn {
            background: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #3a3a3a;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 13px;
        }
        
        .qr-control-buttons .btn-primary {
            background: #00ff7f;
            color: #000;
            border-color: #00ff7f;
        }
        
        .qr-control-buttons .btn-primary:hover {
            background: #00e673;
            border-color: #00e673;
        }
        
        .qr-control-buttons .btn-secondary:hover {
            background: #3a3a3a;
            border-color: #4a4a4a;
        }
        
        .scrollable-qr-list {
            flex: 1;
            overflow-y: auto;
            border: 1px solid #3a3a3a;
            border-radius: 4px;
            background: #1a1a1a;
            min-height: 0;
            max-height: 300px;
        }
        
        .scrollable-qr-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable-qr-list::-webkit-scrollbar-track {
            background: #2a2a2a;
            border-radius: 4px;
        }
        
        .scrollable-qr-list::-webkit-scrollbar-thumb {
            background: #4a4a4a;
            border-radius: 4px;
        }
        
        .scrollable-qr-list::-webkit-scrollbar-thumb:hover {
            background: #5a5a5a;
        }
        
        .rifle-checkboxes .rifle-checkbox {
            margin: 0;
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        
        .rifle-checkboxes .rifle-checkbox:last-child {
            border-bottom: none;
        }
        
        .rifle-checkboxes .rifle-checkbox:hover {
            background: #2a2a2a;
        }
        
        /* Responsive Design for Mobile and Tablets */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
                padding: 1rem;
            }
            
            .dashboard-card {
                margin-bottom: 1.5rem;
            }
            
            .modern-header {
                padding: 1rem 1.5rem;
            }
            
            .modern-content {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .qr-generator-tabs {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .tab-btn {
                width: 100%;
                text-align: center;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0.5rem;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 280px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 999;
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }
            
            .sidebar.sidebar-open {
                transform: translateX(0);
            }
            
            .main-content.sidebar-open {
                margin-left: 0;
            }
            
            .sidebar-toggle-fixed {
                left: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .scanner-container {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .card-header {
                padding: 1rem;
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
            
            .card-content {
                padding: 1rem;
            }
            
            .header-controls {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }
            
            .search-container {
                min-width: auto;
            }
            
            .scrollable-list {
                max-height: 300px;
            }
            
            .rifle-item {
                padding: 0.75rem;
            }
            
            .rifle-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .rifle-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .qr-generator-section .card {
                margin: 0;
            }
            
            .form-actions {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            /* Recent Activities Responsive Design */
            .modern-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
                padding: 1rem;
            }
            
            .activity-avatar {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
            
            .activity-primary {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .cadet-name {
                font-size: 0.95rem;
            }
            
            .action-text {
                font-size: 0.85rem;
            }
            
            .rifle-badge {
                font-size: 0.75rem;
                padding: 0.2rem 0.6rem;
            }
            
            .activity-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                width: 100%;
            }
            
            .time-badge {
                font-size: 0.75rem;
            }
            
            .modern-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .header-title h3 {
                font-size: 1.1rem;
            }
            
            .count-badge {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .modern-item {
                padding: 0.75rem;
            }
            
            .modern-header {
                padding: 1rem;
            }
            
            .modern-content {
                padding: 1rem;
            }
            
            .activity-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .cadet-name {
                font-size: 0.9rem;
            }
            
            .action-text {
                font-size: 0.8rem;
            }
            
            .rifle-badge {
                font-size: 0.7rem;
                padding: 0.15rem 0.5rem;
            }
            
            .time-badge {
                font-size: 0.7rem;
            }
            
            .header-title h3 {
                font-size: 1rem;
            }
            
            .count-badge {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
            
            .modern-list {
                max-height: 350px;
            }
        }
    </style>
    
    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '';
            include __DIR__ . '/includes/admin_nav.php';
        ?>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Rifle Management System</h1>
                        <p class="header-subtitle">Manage rifle assignments and track inventory</p>
                    </div>
                    <div class="header-actions">
                        <button class="qr-integration-btn" onclick="window.location.href='rifle_scanner.php'">
                            <i class="fas fa-qrcode"></i>
                            QR Scanner
                        </button>
                        <button class="manual-attendance-btn" onclick="toggleQRGenerator()">
                            <i class="fas fa-magic"></i>
                            Generate QR Codes
                        </button>
                        <button class="action-btn" onclick="toggleBorrowingSection()">
                            <i class="fas fa-handshake"></i>
                            Rifle Borrowing
                        </button>
                        <button class="action-btn" onclick="window.location.href='rifle_backup_manager.php'">
                            <i class="fas fa-shield-alt"></i>
                            Backup Manager
                        </button>
                    </div>
                </div>
            </div>

            <!-- QR Generator Section -->
            <div id="qrGeneratorSection" class="qr-generator-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-qrcode"></i> QR Code Generator</h3>
                        <button class="btn btn-sm btn-secondary" onclick="toggleQRGenerator()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="qr-generator-tabs">
                            <button class="tab-btn active" onclick="switchQRTab('single')">
                                <i class="fas fa-file"></i> Single QR
                            </button>
                            <button class="tab-btn" onclick="switchQRTab('batch')">
                                <i class="fas fa-layer-group"></i> Batch QR
                            </button>
                            <button class="tab-btn print-qr-btn" onclick="openPrintModal()">
                                <i class="fas fa-print"></i> Print QR
                            </button>
                        </div>
                        
                        <!-- Single QR Generation -->
                        <div id="singleQRTab" class="qr-tab-content active">
                            <div class="qr-generation-options">
                                <div class="option-tabs">
                                    <button type="button" class="option-tab active" onclick="switchGenerationMode('new')">
                                        <i class="fas fa-plus"></i> New Rifle
                                    </button>
                                    <button type="button" class="option-tab" onclick="switchGenerationMode('existing')">
                                        <i class="fas fa-list"></i> Existing Rifle
                                    </button>
                                </div>
                                
                                <!-- New Rifle Input -->
                                <div id="newRifleMode" class="generation-mode active">
                                    <div class="form-group">
                                        <label for="newRifleNumber">Enter Rifle Number:</label>
                                        <input type="text" id="newRifleNumber" class="form-control" placeholder="e.g., R001, R002, AR-15-001 (comma-separated, unlimited)">
                                        <small class="form-text text-muted">Tip: You can add unlimited rifles at once. Separate rifle numbers with commas. Allowed characters: letters, numbers, dash (-), underscore (_).</small>
                                        <small class="form-text text-muted">Enter unique rifle numbers. System will check for duplicates and process all valid entries.</small>
                                    </div>
                                    <div class="form-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <button class="btn btn-primary" onclick="generateNewRifleQR()">
                                            <i class="fas fa-qrcode"></i> Generate Internal QR
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="generateNewRifleQRExternal()" title="Generate external ROTC_QR_V1 QR using only rifle number">
                                            <i class="fas fa-external-link-alt"></i> Generate External QR
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteAllExternalRifles()" title="Delete ALL external rifle QRs (requires PIN)">
                                            <i class="fas fa-trash-alt"></i> Delete All External
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Existing Rifle Selection -->
                                <div id="existingRifleMode" class="generation-mode">
                                    <div class="form-group">
                                        <label for="rifleSelect">Select Existing Rifle:</label>
                                        <select id="rifleSelect" class="form-control">
                                            <option value="">Loading rifles...</option>
                                        </select>
                                    </div>
                                    <div class="form-actions">
                                        <button class="btn btn-primary" onclick="generateSingleQR()">
                                            <i class="fas fa-qrcode"></i> Generate QR Code
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="singleQRResult" class="qr-result" style="display: none;"></div>
                        </div>
                        
                        <!-- Batch QR Generation -->
                        <div id="batchQRTab" class="qr-tab-content">
                            <div class="batch-info">
                                <p><i class="fas fa-info-circle"></i> Generate QR codes for all rifles that don't have one.</p>
                                <div id="batchStats" class="batch-stats"></div>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn-warning" onclick="generateBatchQR()">
                                    <i class="fas fa-layer-group"></i> Generate All QR Codes
                                </button>
                            </div>
                            <div id="batchQRResult" class="qr-result" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rifle Borrowing & Returning Section -->
            <div id="borrowingSection" class="borrowing-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-handshake"></i> Rifle Borrowing & Returning System</h3>
                        <button class="btn btn-sm btn-secondary" onclick="toggleBorrowingSection()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Borrowing Action Tabs -->
                        <div class="borrowing-action-tabs">
                            <button class="tab-btn active" onclick="switchBorrowingMode('borrow')">
                                <i class="fas fa-hand-holding"></i> Borrow Rifles
                            </button>
                            <button class="tab-btn" onclick="switchBorrowingMode('return')">
                                <i class="fas fa-undo"></i> Return Rifles
                            </button>
                            <button class="tab-btn" onclick="switchBorrowingMode('active')">
                                <i class="fas fa-list"></i> Active Borrowings
                            </button>
                            <button class="tab-btn" onclick="switchBorrowingMode('returnHistory')">
                                <i class="fas fa-history"></i> Return History
                            </button>
                            <button class="tab-btn" onclick="switchBorrowingMode('history')">
                                <i class="fas fa-clock"></i> All History
                            </button>

                        </div>
                        
                        <!-- Borrow Rifles Tab -->
                        <div id="borrowTab" class="borrowing-tab-content active">
                            <div class="borrowing-workflow">
                                <div class="workflow-step">
                                    <div class="step-header">
                                        <div class="step-number">1</div>
                                        <h4>Scan Borrower QR (Temp ID)</h4>
                                    </div>
                                    <div class="step-content">
                                        <div class="qr-scanner-container">
                                            <button class="btn btn-primary" onclick="startBorrowerQRScan()">
                                                <i class="fas fa-camera"></i> Scan Borrower QR
                                            </button>
                                            <div id="borrowerQRResult" class="qr-result" style="display: none;"></div>
                                        </div>
                                        <div id="borrowerDetailsForm" class="borrower-form" style="display: none;">
                                            <h5>New Borrower Registration</h5>
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="borrowerName">Full Name *</label>
                                                    <input type="text" id="borrowerName" class="form-control" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="borrowerCourse">Course *</label>
                                                    <input type="text" id="borrowerCourse" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="borrowerContact">Contact Number</label>
                                                    <input type="text" id="borrowerContact" class="form-control">
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="borrowerTempId">Temp ID</label>
                                                    <input type="text" id="borrowerTempId" class="form-control" readonly>
                                                </div>
                                            </div>
                                            <button class="btn btn-success" onclick="registerBorrower()">
                                                <i class="fas fa-user-plus"></i> Register Borrower
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="workflow-step" id="rifleSelectionStep" style="display: none;">
                                    <div class="step-header">
                                        <div class="step-number">2</div>
                                        <h4>Scan Rifles (One by One)</h4>
                                    </div>
                                    <div class="step-content">
                                        <div class="current-borrower-info" id="currentBorrowerInfo"></div>
                                        <div class="rifle-scanning">
                                            <button class="btn btn-primary" onclick="startRifleQRScan()">
                                                <i class="fas fa-camera"></i> Scan Rifle QR
                                            </button>
                                            <div id="selectedRiflesList" class="selected-rifles-list"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="workflow-step" id="confirmBorrowStep" style="display: none;">
                                    <div class="step-header">
                                        <div class="step-number">3</div>
                                        <h4>Confirm Borrowing</h4>
                                    </div>
                                    <div class="step-content">
                                        <div id="borrowingSummary" class="borrowing-summary"></div>
                                        <div class="confirmation-actions">
                                            <button class="btn btn-success" onclick="confirmBorrowing()">
                                                <i class="fas fa-check"></i> Confirm Borrow
                                            </button>
                                            <button class="btn btn-secondary" onclick="cancelBorrowing()">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Return Rifles Tab -->
                        <div id="returnTab" class="borrowing-tab-content">
                            <div class="return-workflow">
                                <div class="workflow-step">
                                    <div class="step-header">
                                        <div class="step-number">1</div>
                                        <h4>Scan Borrower QR</h4>
                                    </div>
                                    <div class="step-content">
                                        <div class="qr-scanner-container">
                                            <button class="btn btn-primary" onclick="startReturnBorrowerQRScan()">
                                                <i class="fas fa-camera"></i> Scan Borrower QR
                                            </button>
                                            <div id="returnBorrowerResult" class="qr-result" style="display: none;"></div>
                                        </div>
                                        <div id="borrowerRiflesList" class="borrower-rifles-list" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Borrowings Tab -->
                        <div id="activeBorrowingsTab" class="borrowing-tab-content">
                            <div id="activeBorrowingsList" class="borrowing-list">
                                <div id="activeBorrowingsLoading" class="loading-state">
                                    <div class="loading-spinner">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </div>
                                    <p class="loading-text">Loading active borrowings...</p>
                                </div>
                                <div id="activeBorrowingsContent"></div>
                            </div>
                        </div>
                        
                        <!-- Return History Tab -->
                        <div id="returnHistoryTab" class="borrowing-tab-content">
                            <div id="returnHistoryList" class="borrowing-list">
                                <div id="returnHistoryLoading" class="loading-state">
                                    <div class="loading-spinner">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </div>
                                    <p class="loading-text">Loading return history...</p>
                                </div>
                                <div id="returnHistoryContent"></div>
                            </div>
                        </div>
                        
                        <!-- History Tab -->
                        <div id="historyTab" class="borrowing-tab-content">
                            <div id="historyLoading" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                                <p class="loading-text">Loading borrowing history...</p>
                            </div>
                            <div id="historyContent"></div>
                        </div>
                        

                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Rifles</span>
                        <i class="fas fa-crosshairs stat-icon"></i>
                    </div>
                    <div class="stat-value" id="totalRifles"><?php echo $stats['total_rifles']; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-check"></i>
                        <span>Inventory</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Available</span>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                    <div class="stat-value" id="availableRifles"><?php echo $stats['available_rifles']; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>Ready</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Assigned</span>
                        <i class="fas fa-user-check stat-icon"></i>
                    </div>
                    <div class="stat-value" id="assignedRifles"><?php echo $stats['assigned_rifles']; ?></div>
                    <div class="stat-change neutral">
                        <i class="fas fa-minus"></i>
                        <span>In Use</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Assigned</span>
                        <i class="fas fa-arrow-up stat-icon"></i>
                    </div>
                    <div class="stat-value" id="assignedRifles"><?php echo isset($stats['assigned_rifles']) ? $stats['assigned_rifles'] : 0; ?></div>
                    <div class="stat-change neutral">
                        <i class="fas fa-arrow-up"></i>
                        <span>Out</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Returned</span>
                        <i class="fas fa-arrow-down stat-icon"></i>
                    </div>
                    <div class="stat-value" id="returnedRifles"><?php echo isset($stats['returned_rifles']) ? $stats['returned_rifles'] : 0; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-down"></i>
                        <span>Back</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Maintenance</span>
                        <i class="fas fa-tools stat-icon"></i>
                    </div>
                    <div class="stat-value" id="maintenanceRifles"><?php echo $stats['maintenance_rifles']; ?></div>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Service</span>
                    </div>
                </div>
            </div>

            <!-- Quick Scanner Section -->
            <div class="scanner-container fade-in">
                <div class="scanner-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3>Quick QR Scanner</h3>
                <p>Scan cadet and rifle QR codes to assign or return rifles</p>
                <button class="btn btn-primary" onclick="window.location.href='rifle_scanner.php'">
                    <i class="fas fa-camera"></i>
                    Open Scanner
                </button>
            </div>

            <!-- Content Grid -->
            <div class="content-grid fade-in">
                <!-- Current Assignments -->
                <div class="dashboard-card modern-card">
                    <div class="card-header modern-header">
                        <div class="header-title">
                            <i class="fas fa-user-check header-icon"></i>
                            <h3>Current Assignments</h3>
                        </div>
                        <div class="header-controls modern-controls">
                            <div class="search-container modern-search" style="margin-right: 10px;">
                                <div class="search-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" id="assignmentSearchInput" class="search-input modern-input" placeholder="Search assignments..." onkeyup="filterAssignments()" style="width: 200px;">
                                </div>
                            </div>
                            <button class="btn btn-outline btn-sm" onclick="refreshCurrentAssignments()" title="Refresh Assignments" style="margin-right: 10px;">
                                <i class="fas fa-sync-alt"></i>
                                Refresh
                            </button>
                            <div class="count-container">
                                <span class="count-badge" id="assignments-count"><?php echo count($current_assignments); ?></span>
                            </div>
                            <a href="rifle_assignments.php" class="btn btn-outline btn-sm">
                                <i class="fas fa-external-link-alt"></i>
                                View All
                            </a>
                        </div>
                    </div>
                    <div class="card-content modern-content">
                        <div id="current-assignments-container">
                        <?php if (empty($current_assignments)): ?>
                            <div class="empty-state modern-empty">
                                <div class="empty-icon">
                                    <i class="fas fa-user-slash"></i>
                                </div>
                                <p class="empty-text">No rifles currently assigned</p>
                                <p class="empty-subtext">Assignments will appear here when rifles are checked out to cadets</p>
                            </div>
                        <?php else: ?>
                            <div class="assignment-list modern-list" id="assignment-list">
                                <?php foreach (array_slice($current_assignments, 0, 5) as $assignment): ?>
                                    <div class="assignment-item modern-assignment-item">
                                        <div class="assignment-avatar">
                                            <div class="rifle-badge">
                                                <i class="fas fa-crosshairs"></i>
                                                <span class="rifle-number">#<?php echo htmlspecialchars($assignment['rifle_number']); ?></span>
                                            </div>
                                        </div>
                                        <div class="assignment-details">
                                            <div class="assignment-primary">
                                                <span class="cadet-name"><?php echo htmlspecialchars($assignment['cadet_name']); ?></span>
                                                <span class="platoon-badge"><?php echo htmlspecialchars($assignment['platoon']); ?> Platoon</span>
                                            </div>
                                            <div class="assignment-meta">
                                                <span class="time-badge">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    Assigned: <?php echo date('M j, Y g:i A', strtotime($assignment['assigned_at'])); ?>
                                                </span>
                                                <span class="duration-badge">
                                                    <i class="fas fa-clock"></i>
                                                    <?php 
                                                        $assigned_time = new DateTime($assignment['assigned_at']);
                                                        $current_time = new DateTime();
                                                        $interval = $assigned_time->diff($current_time);
                                                        if ($interval->days > 0) {
                                                            echo $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
                                                        } elseif ($interval->h > 0) {
                                                            echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                                        } else {
                                                            echo $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="assignment-actions">
                                            <div class="status-indicator status-assigned">
                                                <span class="status-dot dot-warning"></span>
                                                <span class="status-text">Assigned</span>
                                            </div>
                                            <button class="btn btn-sm btn-outline btn-return" onclick="returnRifle(<?php echo $assignment['id']; ?>)" title="Return Rifle">
                                                <i class="fas fa-undo"></i>
                                                Return
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($current_assignments) > 5): ?>
                                    <div class="show-more-item">
                                        <div class="show-more-content">
                                            <i class="fas fa-ellipsis-h"></i>
                                            <span class="show-more-text"><?php echo count($current_assignments) - 5; ?> more assignments</span>
                                            <a href="rifle_assignments.php" class="btn-link">View All</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="dashboard-card modern-card">
                    <div class="card-header modern-header">
                        <div class="header-title">
                            <i class="fas fa-history header-icon"></i>
                            <h3>Recent Activities</h3>
                        </div>
                        <div class="header-badge">
                            <span class="count-badge" id="activities-count">0</span>
                        </div>
                    </div>
                    <div class="card-content modern-content">
                        <div id="recent-activities">
                            <div class="empty-state modern-empty">
                                <div class="empty-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <p class="empty-text">No recent activities</p>
                                <p class="empty-subtext">Activity will appear here when rifles are assigned or returned</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rifle List Section -->
            <div class="dashboard-card modern-card fade-in">
                <div class="card-header modern-header">
                    <div class="header-title">
                        <i class="fas fa-list header-icon"></i>
                        <h3>All Rifles</h3>
                    </div>
                    <div class="header-controls modern-controls">
                        <div class="search-container modern-search">
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="rifleSearchInput" class="search-input modern-input" placeholder="Search rifles by number, status..." onkeyup="filterRifles()">
                            </div>
                        </div>
                        <div class="filter-container" style="margin-right: 10px;">
                            <select id="rifleTypeFilter" class="form-control" onchange="onRifleFilterChange()">
                                <option value="all" selected>All</option>
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                        </div>
                        <div class="count-container">
                            <span class="count-badge" id="rifleCount">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="card-content modern-content">
                    <div id="rifleList" class="rifle-grid modern-grid">
                        <div class="loading-state">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <p class="loading-text">Loading rifles...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Container -->
            <div class="stats-container fade-in">
                <!-- This container is used by JavaScript for dynamic stats updates -->
            </div>

            <!-- System Alerts -->
            <?php if ($rifles_without_qr > 0): ?>
                <div class="alert alert-warning fade-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>QR Codes Needed:</strong> <?php echo $rifles_without_qr; ?> rifles are missing QR codes.
                    <button class="btn btn-sm btn-warning" onclick="generateAllRifleQRs()" style="margin-left: 1rem;">
                        Generate Now
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Print Modal -->
    <div id="printModal" class="print-modal">
        <div class="print-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-print"></i> Print QR Codes</h3>
                <button class="btn-close" onclick="closePrintModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="print-options">
                    <div class="print-option" onclick="printAllQRs()">
                        <i class="fas fa-print"></i>
                        <h4>Print All QR Codes</h4>
                        <p>Print all generated QR codes (9 per page)</p>
                    </div>
                    <div class="print-option" onclick="printExternalQRs()">
                        <i class="fas fa-print"></i>
                        <h4>Print External QRs</h4>
                        <p>Print only external rifle QR codes</p>
                    </div>
                    <div class="print-option" onclick="showSelectQRs()">
                        <i class="fas fa-check-square"></i>
                        <h4>Select QR Codes to Print</h4>
                        <p>Choose specific QR codes to print</p>
                    </div>
                </div>
                <div id="rifleSelection" class="rifle-selection" style="display: none;">
                    <h4>Select Rifles to Print:</h4>
                    
                    <!-- Search Bar -->
                    <div class="qr-search-container">
                        <input type="text" id="qrSearchInput" class="qr-search-input" placeholder="Search rifles by number..." oninput="filterQRCodes()">
                        <i class="fas fa-search qr-search-icon"></i>
                    </div>
                    
                    <!-- Control Buttons (Sticky) -->
                    <div class="qr-control-buttons sticky-controls">
                        <button class="btn btn-secondary" onclick="selectAllRifles()">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                        <button class="btn btn-secondary" onclick="deselectAllRifles()">
                            <i class="fas fa-times"></i> Deselect All
                        </button>
                        <button class="btn btn-primary" onclick="printSelectedQRs()">
                            <i class="fas fa-print"></i> Print Selected
                        </button>
                    </div>
                    
                    <!-- QR Checkboxes List -->
                    <div id="rifleCheckboxes" class="rifle-checkboxes scrollable-qr-list"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Layout (Hidden) -->
    <div id="printLayout" class="print-layout" style="display: none;">
        <!-- QR codes will be dynamically inserted here -->
    </div>

    <!-- Scripts -->
    <script src="js/dashboard.js"></script>
    <script>
        // QR Generator visibility toggle
        function toggleQRGenerator() {
            const qrSection = document.getElementById('qrGeneratorSection');
            if (qrSection.style.display === 'none' || qrSection.style.display === '') {
                qrSection.style.display = 'block';
                loadRifleList();
            } else {
                qrSection.style.display = 'none';
            }
        }

        // Tab switching for QR generator
        function switchQRTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.qr-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + 'QRTab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Load rifle list if switching to single tab
            if (tabName === 'single') {
                loadRifleList();
            }
        }

        // Legacy tab switching function
        function switchTab(tabName) {
            switchQRTab(tabName);
        }

        // Helper function to handle fetch responses with authentication check
        function handleFetchResponse(response) {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response received:', text);
                    throw new Error('Server returned non-JSON response');
                });
            }
            return response.json().then(data => {
                // Check for authentication error
                if (data.success === false && data.redirect === 'login.php') {
                    alert('Your session has expired. Please log in again.');
                    window.location.href = 'login.php';
                    return;
                }
                return data;
            });
        }

        function getSelectedRifleType() {
            const select = document.getElementById('rifleTypeFilter');
            return select ? select.value : 'all';
        }

        function onRifleFilterChange() {
            const searchInput = document.getElementById('rifleSearchInput');
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            loadRifleList(1, searchTerm);
        }

        // Load unified rifle list (internal + external) for dropdown and card
        function loadRifleList(page = 1, search = '') {
            const type = getSelectedRifleType();
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_unified_rifles&page=${page}&search=${encodeURIComponent(search)}&type=${encodeURIComponent(type)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data && data.success) {
                    const items = Array.isArray(data.items) ? data.items : [];

                    // Populate existing rifle dropdown with internal rifles only
                    const select = document.getElementById('rifleSelect');
                    if (select) {
                        select.innerHTML = '<option value="">Select a rifle...</option>';
                        items.forEach(rifle => {
                            if (rifle.type === 'internal') {
                                const option = document.createElement('option');
                                option.value = rifle.id;
                                option.textContent = `${rifle.rifle_number} - ${rifle.status}`;
                                if (rifle.qr_code_path) {
                                    option.textContent += ' (Has QR)';
                                }
                                select.appendChild(option);
                            }
                        });
                    }

                    const rifleList = document.getElementById('rifleList');
                    if (rifleList && items.length > 0) {
                        window.allRifles = items;
                        displayRiflesInScrollableList(items);
                        updateRifleCount(
                            items.length,
                            data.total || items.length,
                            data.internal_count || 0,
                            data.external_count || 0
                        );
                    } else if (rifleList) {
                        rifleList.innerHTML = '<div class="no-results">No rifles found.</div>';
                        updateRifleCount(0, 0);
                    }

                } else {
                    console.error('Failed to load rifle list:', data.message);
                    const rifleList = document.getElementById('rifleList');
                    if (rifleList) {
                        rifleList.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error loading rifle list:', error);
                const rifleList = document.getElementById('rifleList');
                if (rifleList) {
                    rifleList.innerHTML = '<div class="alert alert-danger">An error occurred while loading the rifle list.</div>';
                }
            });
        }
        
        function displayRiflesInScrollableList(rifles) {
            const rifleList = document.getElementById('rifleList');
            if (!rifleList) {
                console.error('Rifle list container not found');
                return;
            }
            
            if (!rifles || rifles.length === 0) {
                rifleList.innerHTML = '<div class="no-results">No rifles found</div>';
                return;
            }
            
            let html = '<div class="scrollable-list">';
            
            rifles.forEach((rifle, index) => {
                const type = rifle.type || 'internal';
                const isExternal = type === 'external';

                let statusHtml = '';
                if (isExternal) {
                    const linked = !!rifle.linked;
                    const linkedClass = linked ? 'success' : 'secondary';
                    const linkedText = linked ? 'Linked' : 'Not Linked';
                    statusHtml += `<span class="badge badge-${linkedClass}" style="margin-right: 6px;">${linkedText}</span>`;

                    if (rifle.rifle_status && rifle.rifle_status !== 'N/A') {
                        const rsBadge = getStatusBadgeClass(rifle.rifle_status);
                        statusHtml += `<span class="badge badge-${rsBadge}">${rifle.rifle_status}</span>`;
                    }
                } else {
                    const statusBadge = getStatusBadgeClass(rifle.status || 'available');
                    statusHtml = `<span class="badge badge-${statusBadge}">${rifle.status}</span>`;
                }

                const qrLink = rifle.qr_code_path ? 
                    `<a href="${rifle.qr_code_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-qrcode"></i> View QR
                    </a>` : 
                    '<span class="text-muted">No QR</span>';
                
                const isVisible = index < 5 ? '' : 'style="display: none;"';
                
                html += `
                    <div class="rifle-item" data-status="${(rifle.status || '').toLowerCase()}" ${isVisible}>
                        <div class="rifle-info">
                            <div class="rifle-number">
                                <strong>${rifle.rifle_number}</strong>
                                ${isExternal ? '<span class="badge badge-info" style="margin-left: 6px;">External</span>' : ''}
                            </div>
                            <div class="rifle-status">${statusHtml}</div>
                            <div class="rifle-qr">
                                ${qrLink}
                                ${isExternal && rifle.generated_at ? `<div class="time-badge" style="margin-top: 4px;"><i class="fas fa-clock"></i> ${rifle.generated_at}</div>` : ''}
                            </div>
                            <div class="rifle-actions">
                                ${isExternal ? `
                                <button class="btn btn-sm btn-info" onclick="editExternalRifleQR(${rifle.id}, '${rifle.rifle_number}')" title="Edit External QR">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="printSingleQR('${rifle.qr_code_path}', '${rifle.rifle_number}', true)" title="Print External QR">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteExternalRifleQR(${rifle.id}, '${rifle.rifle_number}')" title="Delete External QR">
                                    <i class="fas fa-trash"></i>
                                </button>
                                ` : `
                                <button class="btn btn-sm btn-info" onclick="editRifle(${rifle.id}, '${rifle.rifle_number}')" title="Edit Rifle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="generateSingleQRForce(${rifle.id})" title="Regenerate QR">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteRifle(${rifle.id}, '${rifle.rifle_number}')" title="Delete Rifle">
                                    <i class="fas fa-trash"></i>
                                </button>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            if (rifles.length > 5) {
                html += '<div class="show-more-indicator">Scroll to see more rifles...</div>';
            }
            
            html += '</div>';
            rifleList.innerHTML = html;
        }
        
        function updateRifleCount(showing, total, internalCount = null, externalCount = null) {
            const countElement = document.getElementById('rifleCount');
            if (countElement) {
                const displayCount = Math.min(showing, 5);
                const type = getSelectedRifleType();

                if (type === 'internal') {
                    const internal = internalCount !== null ? internalCount : total;
                    countElement.textContent = `Showing ${displayCount} of ${internal} internal rifles`;
                } else if (type === 'external') {
                    const external = externalCount !== null ? externalCount : total;
                    countElement.textContent = `Showing ${displayCount} of ${external} external QRs`;
                } else {
                    const internal = internalCount !== null ? internalCount : total;
                    const external = externalCount !== null ? externalCount : 0;
                    countElement.textContent = `Showing ${displayCount} of ${total} rifles (Internal: ${internal}, External: ${external})`;
                }
            }
        }
        
        function getStatusBadgeClass(status) {
            switch(status.toLowerCase()) {
                case 'available': return 'success';
                case 'assigned': return 'primary';
                case 'maintenance': return 'warning';
                case 'lost': return 'danger';
                default: return 'secondary';
            }
        }
        
        function loadRifleStats() {
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_rifle_stats'
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('totalRifles').textContent = stats.total || 0;
                    document.getElementById('availableRifles').textContent = stats.available || 0;
                    document.getElementById('assignedRifles').textContent = stats.assigned || 0;
                    document.getElementById('assignedRifles').textContent = stats.assigned || 0;
                    document.getElementById('returnedRifles').textContent = stats.returned || 0;
                    document.getElementById('maintenanceRifles').textContent = stats.maintenance || 0;
                } else {
                    console.error('Error loading rifle stats:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading stats:', error);
            });
        }
        
        function deleteRifle(rifleId, rifleNumber) {
            if (!confirm(`Are you sure you want to delete rifle ${rifleNumber}? This action cannot be undone.`)) {
                return;
            }
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_rifle&rifle_id=${rifleId}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    alert(`Rifle ${rifleNumber} has been successfully deleted.`);
                    loadRifleList(); // Refresh the list
                    loadRifleStats(); // Refresh statistics
                } else {
                    alert(`Error deleting rifle: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the rifle.');
            });
        }

        function editExternalRifleQR(externalId, currentNumber) {
            const original = String(currentNumber || '').trim();
            const input = prompt(`Edit external rifle number for ${original}:`, original);
            if (input === null) {
                return; // cancelled
            }
            const newNumber = input.trim();
            if (!newNumber) {
                alert('Rifle number cannot be empty.');
                return;
            }
            if (!/^[A-Za-z0-9_\-]+$/.test(newNumber)) {
                alert('Rifle number can contain letters, numbers, dashes, and underscores only.');
                return;
            }
            if (newNumber === original) {
                return; // nothing changed
            }

            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_external_rifle_qr&external_id=${encodeURIComponent(externalId)}&rifle_number=${encodeURIComponent(newNumber)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data && data.success) {
                    alert(data.message || 'External rifle QR updated successfully.');
                    loadRifleList();
                } else {
                    alert(`Error updating external QR: ${data && data.message ? data.message : 'Unknown error'}`);
                }
            })
            .catch(error => {
                console.error('Error updating external QR:', error);
                alert('An error occurred while updating the external QR.');
            });
        }

        function deleteAllExternalRifles() {
            const pin = prompt('Enter PIN to delete ALL external rifle QRs:');
            if (!pin) {
                return; // User cancelled
            }

            // Double confirmation
            if (!confirm('WARNING: This will PERMANENTLY delete ALL external rifle QRs and their files. This action cannot be undone. Are you absolutely sure?')) {
                return;
            }

            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_all_external_rifles&pin=${encodeURIComponent(pin)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    alert(`Success: ${data.message}\nDeleted: ${data.deleted_count} QR(s)\nFiles cleaned: ${data.files_cleaned} file(s)`);
                    loadRifleList(); // Refresh the list
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error deleting all external rifles:', error);
                alert('An error occurred while deleting external rifle QRs.');
            });
        }

        function deleteExternalRifleQR(externalId, rifleNumber) {
            if (!confirm(`Are you sure you want to delete the external QR for rifle ${rifleNumber}? This will remove the QR image.`)) {
                return;
            }

            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_external_rifle_qr&external_id=${encodeURIComponent(externalId)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data && data.success) {
                    alert(data.message || 'External rifle QR deleted successfully.');
                    loadRifleList();
                } else {
                    alert(`Error deleting external QR: ${data && data.message ? data.message : 'Unknown error'}`);
                }
            })
            .catch(error => {
                console.error('Error deleting external QR:', error);
                alert('An error occurred while deleting the external QR.');
            });
        }

        // Generate single QR code
        function generateSingleQR() {
            // Declare variables outside try block to ensure proper scope
            let rifleSelect, resultDiv, rifleId;
            
            try {
                rifleSelect = document.getElementById('rifleSelect');
                resultDiv = document.getElementById('singleQRResult');
                
                // Check if DOM elements exist
                if (!rifleSelect) {
                    console.error('Rifle select element not found');
                    alert('Error: Rifle selection not available. Please refresh the page.');
                    return;
                }
                
                if (!resultDiv) {
                    console.error('Result div element not found');
                    alert('Error: Result display not available. Please refresh the page.');
                    return;
                }
                
                rifleId = rifleSelect.value;
                
                if (!rifleId) {
                    alert('Please select a rifle first.');
                    return;
                }
            } catch (error) {
                console.error('Error in generateSingleQR:', error);
                alert('An unexpected error occurred. Please refresh the page and try again.');
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<p>Generating QR code...</p>';
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_single_qr&rifle_id=${rifleId}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    let actionText = '';
                    if (data.action === 'created') {
                        actionText = '<span class="badge badge-success">New rifle added</span>';
                    } else if (data.action === 'regenerated') {
                        actionText = '<span class="badge badge-warning">QR regenerated</span>';
                    } else if (data.action === 'existing') {
                        actionText = '<span class="badge badge-info">Using existing QR</span>';
                    }
                    
                    resultDiv.innerHTML = `
                        <div class="qr-result">
                            <h4>QR Code Generated Successfully! ${actionText}</h4>
                            <div class="qr-preview">
                                <img src="${data.qr_path}" alt="QR Code for ${data.rifle_number}">
                            </div>
                            <p><strong>Rifle:</strong> ${data.rifle_number}</p>
                            <p><strong>File:</strong> ${data.qr_path}</p>
                            <div class="qr-label">
                                <strong>Rifle #${data.rifle_number}</strong>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" onclick="downloadQR('${data.qr_path}', '${data.rifle_number}')">Download PNG</button>
                                <button type="button" class="btn btn-success" onclick="printSingleQR('${data.qr_path}', '${data.rifle_number}')">Print QR</button>
                                <button type="button" class="btn btn-secondary" onclick="generateSingleQRForce(${rifleId})">Force Regenerate</button>
                            </div>
                        </div>
                    `;
                    // Reload rifle list to update status
                    loadRifleList();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while generating QR code.</div>';
            });
        }

        // Generate EXTERNAL ROTC_QR_V1 QR for a single new rifle number (rifle number only)
        function generateNewRifleQRExternal() {
            let rifleNumberInput, resultDiv, rifleNumber, rifleNumbers;

            try {
                rifleNumberInput = document.getElementById('newRifleNumber');
                resultDiv = document.getElementById('singleQRResult');
                
                if (!rifleNumberInput) {
                    console.error('New rifle number input not found');
                    alert('Error: Rifle number input not available. Please refresh the page.');
                    return;
                }

                if (!resultDiv) {
                    console.error('Result div element not found');
                    alert('Error: Result display not available. Please refresh the page.');
                    return;
                }

                rifleNumber = rifleNumberInput.value.trim();

                // Support multiple rifle numbers separated by commas for external QR generation
                if (!rifleNumber) {
                    alert('Please enter at least one rifle number.');
                    rifleNumberInput.focus();
                    return;
                }

                // Split by comma and trim whitespace
                rifleNumbers = rifleNumber.split(',').map(r => r.trim()).filter(r => r);
                
                if (rifleNumbers.length === 0) {
                    alert('Please enter valid rifle numbers separated by commas.');
                    rifleNumberInput.focus();
                    return;
                }

                // Validate each rifle number
                for (const rn of rifleNumbers) {
                    if (!/^[A-Za-z0-9_\-]+$/.test(rn)) {
                        alert(`Invalid rifle number "${rn}". Rifle numbers can contain letters, numbers, dashes, and underscores only.`);
                        rifleNumberInput.focus();
                        return;
                    }
                }
            } catch (error) {
                console.error('Error in generateNewRifleQRExternal:', error);
                alert('An unexpected error occurred. Please refresh the page and try again.');
                return;
            }

            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `<p>Generating external QR codes for ${rifleNumbers.length} rifle${rifleNumbers.length > 1 ? 's' : ''}...</p>`;

            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_new_rifle_qr_external&rifle_numbers=${encodeURIComponent(rifleNumbers.join(','))}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data && typeof data === 'object' && 'payload' in data) {
                    try {
                        console.log('[External QR] Payload from server:', data.payload);
                    } catch (e) {
                        // Ignore logging errors
                    }
                }

                if (data.success) {
                    if (data.multiple && data.qrs && data.qrs.length > 0) {
                        // Display multiple QR results
                        let qrResultsHtml = `
                            <div class="qr-result">
                                <h4>External QR Codes Generated Successfully!</h4>
                                <p><strong>${data.qrs.length}</strong> QR code${data.qrs.length > 1 ? 's' : ''} generated.</p>
                                <div class="qr-preview-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                        `;
                        
                        data.qrs.forEach(qr => {
                            qrResultsHtml += `
                                <div class="qr-preview-item" style="text-align: center; border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                                    <img src="${qr.qr_path}" alt="External QR Code for ${qr.rifle_number}" style="max-width: 150px; height: auto;">
                                    <p style="margin: 10px 0 5px; font-weight: bold;">Rifle: ${qr.rifle_number}</p>
                                    <p style="margin: 0; font-size: 12px; color: #666;">${qr.qr_path}</p>
                                    <div style="margin-top: 10px;">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="downloadQR('${qr.qr_path}', '${qr.rifle_number}_external')">Download</button>
                                        <button type="button" class="btn btn-sm btn-success" onclick="printSingleQR('${qr.qr_path}', '${qr.rifle_number}', true)">Print</button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        qrResultsHtml += `
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-info" onclick="printExternalQRs()">Print All External QRs</button>
                                </div>
                            </div>
                        `;
                        resultDiv.innerHTML = qrResultsHtml;
                    } else {
                        // Single QR result (backward compatibility)
                        resultDiv.innerHTML = `
                            <div class="qr-result">
                                <h4>External QR Code Generated Successfully!</h4>
                                <div class="qr-preview">
                                    <img src="${data.qr_path}" alt="External QR Code for ${data.rifle_number}">
                                </div>
                                <p><strong>Rifle:</strong> ${data.rifle_number}</p>
                                <p><strong>File:</strong> ${data.qr_path}</p>
                                <div class="qr-label">
                                    <strong>External QR - Rifle #${data.rifle_number}</strong>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-primary" onclick="downloadQR('${data.qr_path}', '${data.rifle_number}_external')">Download PNG</button>
                                    <button type="button" class="btn btn-success" onclick="printSingleQR('${data.qr_path}', '${data.rifle_number}', true)">Print QR</button>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while generating external QR code.</div>';
            });
        }
        
        function generateSingleQRForce(rifleId) {
            // Declare resultDiv outside try block to ensure proper scope
            let resultDiv;
            
            try {
                resultDiv = document.getElementById('singleQRResult');
                
                if (!resultDiv) {
                    console.error('Result div element not found in generateSingleQRForce');
                    alert('Error: Result display not available. Please refresh the page.');
                    return;
                }
                
                if (!rifleId) {
                    console.error('No rifle ID provided to generateSingleQRForce');
                    alert('Error: No rifle selected for QR generation.');
                    return;
                }
                
                resultDiv.innerHTML = '<p>Force regenerating QR code...</p>';
            } catch (error) {
                console.error('Error in generateSingleQRForce:', error);
                alert('An unexpected error occurred. Please refresh the page and try again.');
                return;
            }
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_single_qr&rifle_id=${rifleId}&force_regenerate=true`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="qr-result">
                            <h4>QR Code Force Regenerated! <span class="badge badge-warning">Regenerated</span></h4>
                            <div class="qr-preview">
                                <img src="${data.qr_path}" alt="QR Code for ${data.rifle_number}">
                            </div>
                            <p><strong>Rifle:</strong> ${data.rifle_number}</p>
                            <p><strong>File:</strong> ${data.qr_path}</p>
                            <div class="qr-label">
                                <strong>Rifle #${data.rifle_number}</strong>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" onclick="downloadQR('${data.qr_path}', '${data.rifle_number}')">Download PNG</button>
                                <button type="button" class="btn btn-success" onclick="printSingleQR('${data.qr_path}', '${data.rifle_number}')">Print QR</button>
                            </div>
                        </div>
                    `;
                    // Reload rifle list to update status
                    loadRifleList();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while force regenerating the QR code.</div>';
            });
        }

        // Generate batch QR codes
        function generateBatchQR() {
            // Declare resultDiv outside try block to ensure proper scope
            let resultDiv;
            
            try {
                resultDiv = document.getElementById('batchQRResult');
                
                if (!resultDiv) {
                    console.error('Batch result div element not found');
                    alert('Error: Result display not available. Please refresh the page.');
                    return;
                }
                
                if (!confirm('Generate QR codes for all rifles without QR codes?')) {
                    return;
                }
            } catch (error) {
                console.error('Error in generateBatchQR:', error);
                alert('An unexpected error occurred. Please refresh the page and try again.');
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `
                <div class="batch-info">
                    <p>Generating QR codes...</p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                </div>
            `;
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=generate_rifle_qrs'
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="qr-result">
                            <h4>Batch QR Generation Complete!</h4>
                            <div class="batch-stats">
                                <p><strong>Generated:</strong> ${data.generated} QR codes</p>
                                <p><strong>Total Processed:</strong> ${data.total} rifles</p>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
                                <button type="button" class="btn btn-success" onclick="showPrintOptions()">Print QR Codes</button>
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while generating QR codes.</div>';
            });
        }

        // Download QR code
        function downloadQR(qrPath, rifleNumber) {
            const link = document.createElement('a');
            link.href = qrPath;
            link.download = `rifle_${rifleNumber}_qr.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Handle option tabs for QR generation
        function switchGenerationMode(mode) {
            try {
                // Remove active class from all tabs
                document.querySelectorAll('.option-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Hide all generation modes
                document.querySelectorAll('.generation-mode').forEach(modeElement => {
                    modeElement.classList.remove('active');
                });
                
                // Activate selected tab and mode
                if (event && event.target) {
                    event.target.classList.add('active');
                }
                
                // Map mode to correct element ID
                let targetElementId;
                if (mode === 'new') {
                    targetElementId = 'newRifleMode';
                } else if (mode === 'existing') {
                    targetElementId = 'existingRifleMode';
                } else {
                    console.error('Unknown mode:', mode);
                    return;
                }
                
                const targetElement = document.getElementById(targetElementId);
                if (targetElement) {
                    targetElement.classList.add('active');
                } else {
                    console.error('Target element not found:', targetElementId);
                }
            } catch (error) {
                console.error('Error in switchGenerationMode:', error);
            }
        }
        
        // Generate QR for new rifle number
        function generateNewRifleQR() {
            // Declare variables outside try block to ensure proper scope
            let rifleNumberInput, resultDiv, rifleNumber;
            
            try {
                rifleNumberInput = document.getElementById('newRifleNumber');
                resultDiv = document.getElementById('singleQRResult');
                
                // Check if DOM elements exist
                if (!rifleNumberInput) {
                    console.error('New rifle number input not found');
                    alert('Error: Rifle number input not available. Please refresh the page.');
                    return;
                }
                
                if (!resultDiv) {
                    console.error('Result div element not found');
                    alert('Error: Result display not available. Please refresh the page.');
                    return;
                }
                
                rifleNumber = rifleNumberInput.value.trim();
                
                if (!rifleNumber) {
                    alert('Please enter a rifle number.');
                    rifleNumberInput.focus();
                    return;
                }
                
                // Validate input: allow comma-separated list (letters, numbers, dash, underscore, commas, spaces)
                if (!/^[A-Za-z0-9_,\-\s]+$/.test(rifleNumber)) {
                    alert('Input can contain letters, numbers, dashes, underscores, commas and spaces.');
                    rifleNumberInput.focus();
                    return;
                }
                
            } catch (error) {
                console.error('Error in generateNewRifleQR:', error);
                alert('An unexpected error occurred. Please refresh the page and try again.');
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<p>Checking rifle number and generating QR code...</p>';
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_new_rifle_qr&rifle_number=${encodeURIComponent(rifleNumber)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    // Batch response handling if items array present
                    if (Array.isArray(data.items)) {
                        let summary = `
                            <div class="alert alert-success">
                                ${data.message}
                                <div>Created: ${data.created}, Regenerated: ${data.regenerated}, Skipped existing: ${data.skipped_existing_qr}, Errors: ${data.errors ? data.errors.length : 0}</div>
                            </div>`;
                        let list = '<ul class="list-group">';
                        data.items.forEach(it => {
                            let badgeClass = 'secondary';
                            if (it.status === 'created') badgeClass = 'success';
                            else if (it.status === 'regenerated') badgeClass = 'warning';
                            else if (it.status === 'existing_qr') badgeClass = 'info';
                            else if (it.status === 'error') badgeClass = 'danger';
                            list += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>${it.rifle_number}</span>
                                <span class="badge bg-${badgeClass}">${it.status}</span>
                            </li>`;
                        });
                        list += '</ul>';
                        resultDiv.innerHTML = summary + list;
                        // Clear input and refresh lists
                        rifleNumberInput.value = '';
                        loadRifleList();
                        loadRifleStats();
                        return;
                    }

                    // Single response handling (existing behavior)
                    let actionText = '';
                    if (data.action === 'created') {
                        actionText = '<span class="badge badge-success">New rifle added</span>';
                    } else if (data.action === 'existing') {
                        actionText = '<span class="badge badge-info">Rifle already exists</span>';
                    } else if (data.action === 'regenerated') {
                        actionText = '<span class="badge badge-warning">QR regenerated</span>';
                    }

                    resultDiv.innerHTML = `
                        <div class="qr-result">
                            <h4>QR Code Generated Successfully! ${actionText}</h4>
                            <div class="qr-preview">
                                <img src="${data.qr_path}" alt="QR Code for ${data.rifle_number}">
                            </div>
                            <p><strong>Rifle:</strong> ${data.rifle_number}</p>
                            <p><strong>File:</strong> ${data.qr_path}</p>
                            <div class="qr-label">
                                <strong>Rifle #${data.rifle_number}</strong>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" onclick="downloadQR('${data.qr_path}', '${data.rifle_number}')">Download PNG</button>
                                <button type="button" class="btn btn-success" onclick="printSingleQR('${data.qr_path}', '${data.rifle_number}')">Print QR</button>
                                <button type="button" class="btn btn-secondary" onclick="generateNewRifleQRForce('${data.rifle_number}')">Force Regenerate</button>
                            </div>
                        </div>
                    `;

                    // Clear the input and reload lists
                    rifleNumberInput.value = '';
                    loadRifleList();
                    loadRifleStats();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while generating QR code.</div>';
            });
        }
        
        // Force regenerate QR for new rifle number
        function generateNewRifleQRForce(rifleNumber) {
            // Declare resultDiv outside try block to ensure proper scope
            let resultDiv;
            
            try {
                resultDiv = document.getElementById('singleQRResult');
                
                if (!resultDiv) {
                    console.error('Result div element not found');
                    alert('Error: Result display not available. Please refresh the page.');
                    return;
                }
                
                if (!rifleNumber) {
                    console.error('No rifle number provided');
                    alert('Error: No rifle number provided for QR generation.');
                    return;
                }
                
                resultDiv.innerHTML = '<p>Force regenerating QR code...</p>';
            } catch (error) {
                console.error('Error in generateNewRifleQRForce:', error);
                alert('An unexpected error occurred. Please refresh the page and try again.');
                return;
            }
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_new_rifle_qr&rifle_number=${encodeURIComponent(rifleNumber)}&force_regenerate=true`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="qr-result">
                            <h4>QR Code Force Regenerated! <span class="badge badge-warning">Regenerated</span></h4>
                            <div class="qr-preview">
                                <img src="${data.qr_path}" alt="QR Code for ${data.rifle_number}">
                            </div>
                            <p><strong>Rifle:</strong> ${data.rifle_number}</p>
                            <p><strong>File:</strong> ${data.qr_path}</p>
                            <div class="qr-label">
                                <strong>Rifle #${data.rifle_number}</strong>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" onclick="downloadQR('${data.qr_path}', '${data.rifle_number}')">Download PNG</button>
                                <button type="button" class="btn btn-success" onclick="printSingleQR('${data.qr_path}', '${data.rifle_number}')">Print QR</button>
                            </div>
                        </div>
                    `;
                    
                    // Reload lists
                    loadRifleList();
                    loadRifleStats();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while force regenerating the QR code.</div>';
            });
        }

        // Legacy function for backward compatibility
        function generateAllRifleQRs() {
            generateBatchQR();
        }

        function returnRifle(assignmentId) {
            // Auto-return rifle without modal - direct API call
            if (!confirm('Are you sure you want to return this rifle?')) {
                return;
            }
            
            // Show loading indicator
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Returning...';
            button.disabled = true;
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=return_rifle&assignment_id=${assignmentId}&condition=good&notes=Auto-returned`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    showAlert('Rifle returned successfully!', 'success');
                    // Reload current assignments
                    loadCurrentAssignments();
                    // Reload rifle stats
                    loadRifleStats();
                    // Reload recent activities
                    loadRecentActivities();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while returning the rifle.', 'danger');
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        function showReturnModal(assignmentId) {
            const modalHtml = `
                <div class="modal fade" id="returnRifleModal" tabindex="-1" role="dialog" aria-labelledby="returnRifleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="returnRifleModalLabel">
                                    <i class="fas fa-undo"></i> Return Rifle
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Please confirm the rifle return and add any notes about the condition.
                                </div>
                                <form id="returnRifleForm">
                                    <div class="form-group">
                                        <label for="returnCondition">Rifle Condition:</label>
                                        <select class="form-control" id="returnCondition" name="condition">
                                            <option value="good">Good</option>
                                            <option value="fair">Fair</option>
                                            <option value="poor">Poor</option>
                                            <option value="damaged">Damaged</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="returnNotes">Notes (Optional):</label>
                                        <textarea class="form-control" id="returnNotes" name="notes" rows="3" placeholder="Any additional notes about the rifle condition or return..."></textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-success" onclick="confirmReturnRifle(${assignmentId})">
                                    <i class="fas fa-check"></i> Confirm Return
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('returnRifleModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            $('#returnRifleModal').modal('show');
            
            // Clean up modal when hidden
            $('#returnRifleModal').on('hidden.bs.modal', function () {
                $(this).remove();
            });
        }
        
        function confirmReturnRifle(assignmentId) {
            const condition = document.getElementById('returnCondition').value;
            const notes = document.getElementById('returnNotes').value;
            
            // Show loading state
            const confirmBtn = document.querySelector('#returnRifleModal .btn-success');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=return_rifle&assignment_id=${assignmentId}&condition=${encodeURIComponent(condition)}&notes=${encodeURIComponent(notes)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    $('#returnRifleModal').modal('hide');
                    showAlert('Rifle returned successfully!', 'success');
                    // Reload current assignments
                    loadCurrentAssignments();
                    // Reload rifle stats
                    loadRifleStats();
                    // Reload recent activities
                    loadRecentActivities();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while returning the rifle.', 'danger');
            })
            .finally(() => {
                // Restore button state
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }
        
        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    ${message}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector('.alert:last-of-type');
                if (alert) {
                    $(alert).alert('close');
                }
            }, 5000);
        }

        // Font loading error handler
        function handleFontLoadingErrors() {
            // Check if fonts are loaded
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function() {
                    console.log('Fonts loaded successfully');
                }).catch(function(error) {
                    console.warn('Font loading error:', error);
                    // Apply fallback styles if needed
                    document.body.style.fontFamily = 'Arial, sans-serif';
                });
            }
        }
        
        // Global error handler for uncaught errors
        window.addEventListener('error', function(event) {
            console.error('Global error caught:', event.error);
            // Don't show alert for font-related errors
            if (event.error && event.error.message && 
                (event.error.message.includes('font') || 
                 event.error.message.includes('glyph') ||
                 event.error.message.includes('bbox'))) {
                console.warn('Font-related error suppressed:', event.error.message);
                event.preventDefault();
                return false;
            }
        });
        
        // DOM safety check function
        function waitForElement(selector, callback, maxAttempts = 50) {
            let attempts = 0;
            const checkElement = () => {
                const element = document.querySelector(selector);
                if (element) {
                    callback(element);
                } else if (attempts < maxAttempts) {
                    attempts++;
                    setTimeout(checkElement, 100);
                } else {
                    console.error(`Element ${selector} not found after ${maxAttempts} attempts`);
                }
            };
            checkElement();
        }
        
        // Search functionality for rifles
        function filterRifles() {
            const searchInput = document.getElementById('rifleSearchInput');
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            
            // Use the existing loadRifleList function with search parameter
            loadRifleList(1, searchTerm);
        }
        
        // Debounced search to avoid too many requests
        let searchTimeout;
        function debouncedFilterRifles() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterRifles, 300);
        }
        
        // Enhanced rifle list loading with search support
        function loadRifleListWithSearch() {
            const searchInput = document.getElementById('rifleSearchInput');
            const searchTerm = searchInput ? searchInput.value : '';
            loadRifleList(1, searchTerm);
        }
        
        // Mobile sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('sidebar-open');
                mainContent.classList.toggle('sidebar-open');
                
                // Create overlay if it doesn't exist
                if (!overlay && sidebar.classList.contains('sidebar-open')) {
                    const newOverlay = document.createElement('div');
                    newOverlay.className = 'sidebar-overlay';
                    newOverlay.onclick = closeSidebar;
                    document.body.appendChild(newOverlay);
                } else if (overlay && !sidebar.classList.contains('sidebar-open')) {
                    overlay.remove();
                }
            }
        }
        
        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar) sidebar.classList.remove('sidebar-open');
            if (mainContent) mainContent.classList.remove('sidebar-open');
            if (overlay) overlay.remove();
        }
        
        // Initialize page with enhanced error handling
        document.addEventListener('DOMContentLoaded', function() {
            try {
                handleFontLoadingErrors();
                
                // Initialize mobile sidebar toggle
                const sidebarToggle = document.querySelector('.sidebar-toggle-fixed');
                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', toggleSidebar);
                }
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    const sidebar = document.querySelector('.sidebar');
                    const sidebarToggle = document.querySelector('.sidebar-toggle-fixed');
                    
                    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('sidebar-open')) {
                        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                            closeSidebar();
                        }
                    }
                });
                
                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        closeSidebar();
                    }
                });
                
                // Wait for critical elements before initializing
                waitForElement('#rifleList', function() {
                    loadRifleList();
                });
                
                waitForElement('.stats-container', function() {
                    loadRifleStats();
                });
                
                // Initialize search functionality
                waitForElement('#rifleSearchInput', function(searchInput) {
                    searchInput.addEventListener('input', debouncedFilterRifles);
                    searchInput.addEventListener('keyup', function(e) {
                        if (e.key === 'Enter') {
                            filterRifles();
                        }
                    });
                });
                
                console.log('Rifle management system initialized successfully');
            } catch (error) {
                console.error('Error during initialization:', error);
                // Show user-friendly error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = 'System initialization error. Please refresh the page.';
                document.body.insertBefore(errorDiv, document.body.firstChild);
            }
        });
        
        // Assignment management functions
        function refreshCurrentAssignments() {
            const container = document.getElementById('current-assignments-container');
            const countBadge = document.getElementById('assignments-count');
            
            if (container) {
                container.innerHTML = '<div class="loading-state"><div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div><p class="loading-text">Loading assignments...</p></div>';
            }
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_current_assignments'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    container.innerHTML = data.html;
                    if (countBadge) {
                        countBadge.textContent = data.count;
                    }
                    showAlert('Assignments refreshed successfully', 'success');
                } else {
                    container.innerHTML = '<div class="alert alert-danger">Error loading assignments: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error refreshing assignments:', error);
                container.innerHTML = '<div class="alert alert-danger">Error refreshing assignments. Please try again.</div>';
            });
        }
        
        function filterAssignments() {
            const searchInput = document.getElementById('assignmentSearchInput');
            const assignmentItems = document.querySelectorAll('.assignment-item');
            
            if (!searchInput || !assignmentItems.length) return;
            
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            
            assignmentItems.forEach(item => {
                const cadetName = item.querySelector('.cadet-name')?.textContent.toLowerCase() || '';
                const rifleNumber = item.querySelector('.rifle-number')?.textContent.toLowerCase() || '';
                const platoon = item.querySelector('.platoon-badge')?.textContent.toLowerCase() || '';
                
                const isVisible = cadetName.includes(searchTerm) || 
                                rifleNumber.includes(searchTerm) || 
                                platoon.includes(searchTerm);
                
                item.style.display = isVisible ? 'flex' : 'none';
                if (isVisible) visibleCount++;
            });
            
            // Update count badge
            const countBadge = document.getElementById('assignments-count');
            if (countBadge) {
                countBadge.textContent = visibleCount;
            }
        }
        
        // Print functionality
        let currentQRData = [];
        
        function openPrintModal() {
            console.log('openPrintModal called');
            // Load all rifles with QR codes for printing
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_qr_codes'
            })
            .then(handleFetchResponse)
            .then(data => {
                console.log('QR codes response:', data);
                if (data.success && data.qr_codes) {
                    currentQRData = data.qr_codes.map(qr => ({
                        src: qr.qr_path,
                        label: `Rifle #${qr.rifle_number}`,
                        rifleNumber: qr.rifle_number,
                        type: qr.type || (qr.rifle_type === 'external' ? 'external' : 'internal')
                    }));
                    
                    console.log('Current QR data:', currentQRData);
                    
                    const modal = document.getElementById('printModal');
                    console.log('Modal element:', modal);
                    if (modal) {
                        modal.style.display = 'flex';
                        console.log('Modal display set to flex');
                    } else {
                        console.error('Print modal not found!');
                    }
                } else {
                    console.log('No QR codes found or request failed:', data);
                    alert('No QR codes found. Please generate QR codes first.');
                }
            })
            .catch(error => {
                console.error('Error loading QR codes:', error);
                alert('Error loading QR codes. Please try again.');
            });
        }
        
        function showPrintOptions() {
            const modal = document.getElementById('printModal');
            if (modal) {
                modal.style.display = 'flex';
                // Collect current QR data from batch results
                collectCurrentQRData();
            }
        }
        
        function closePrintModal() {
            const modal = document.getElementById('printModal');
            const rifleSelection = document.getElementById('rifleSelection');
            if (modal) modal.style.display = 'none';
            if (rifleSelection) rifleSelection.style.display = 'none';
        }
        
        function collectCurrentQRData() {
            currentQRData = [];
            const qrResults = document.querySelectorAll('.qr-result');
            qrResults.forEach(result => {
                const qrImg = result.querySelector('img');
                const rifleLabel = result.querySelector('.qr-label');
                if (qrImg && rifleLabel) {
                    currentQRData.push({
                        src: qrImg.src,
                        label: rifleLabel.textContent,
                        rifleNumber: rifleLabel.textContent.replace('Rifle #', '')
                    });
                }
            });
        }
        
        function showSelectQRs() {
            const rifleSelection = document.getElementById('rifleSelection');
            const rifleCheckboxes = document.getElementById('rifleCheckboxes');
            
            if (rifleSelection && rifleCheckboxes) {
                rifleSelection.style.display = 'block';
                
                // Generate checkboxes for each QR
                rifleCheckboxes.innerHTML = '';
                currentQRData.forEach((qr, index) => {
                    const checkboxDiv = document.createElement('div');
                    checkboxDiv.className = 'rifle-checkbox';
                    checkboxDiv.innerHTML = `
                        <input type="checkbox" id="rifle_${index}" value="${index}" checked>
                        <label for="rifle_${index}">
                            <img src="${qr.src}" alt="QR Code" style="width: 40px; height: 40px; margin-right: 10px;">
                            ${qr.label}
                        </label>
                    `;
                    rifleCheckboxes.appendChild(checkboxDiv);
                });
                
                // Clear search input when showing QR selection
                const searchInput = document.getElementById('qrSearchInput');
                if (searchInput) {
                    searchInput.value = '';
                }
            }
        }
        
        function filterQRCodes() {
            const searchInput = document.getElementById('qrSearchInput');
            const rifleCheckboxes = document.querySelectorAll('.rifle-checkbox');
            
            if (!searchInput || !rifleCheckboxes.length) return;
            
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            
            rifleCheckboxes.forEach(checkbox => {
                const label = checkbox.querySelector('label');
                const rifleText = label ? label.textContent.toLowerCase() : '';
                
                const isVisible = rifleText.includes(searchTerm);
                checkbox.style.display = isVisible ? 'block' : 'none';
                if (isVisible) visibleCount++;
            });
            
            // Update the count display if needed
            console.log(`Showing ${visibleCount} of ${rifleCheckboxes.length} QR codes`);
        }
        
        function selectAllRifles() {
            const checkboxes = document.querySelectorAll('#rifleCheckboxes input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }
        
        function deselectAllRifles() {
            const checkboxes = document.querySelectorAll('#rifleCheckboxes input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }
        
        function printAllQRs() {
            if (currentQRData.length === 0) {
                alert('No QR codes available to print. Please generate QR codes first.');
                return;
            }
            
            // Ask user if they want to regenerate QR codes
            if (confirm('Do you want to regenerate QR codes before printing? This will create fresh QR codes with updated encryption.')) {
                regenerateAndPrintQRs(currentQRData);
            } else {
                generatePrintLayout(currentQRData);
                closePrintModal();
            }
        }
        
        function printExternalQRs() {
            if (currentQRData.length === 0) {
                alert('No QR codes available to print. Please generate QR codes first.');
                return;
            }
            
            const externalQRs = currentQRData.filter(qr => qr.type === 'external');
            if (externalQRs.length === 0) {
                alert('No external rifle QRs found to print.');
                return;
            }
            
            // External QRs are already ROTC_QR_V1 payloads; do not regenerate
            generatePrintLayout(externalQRs);
            closePrintModal();
        }
        
        function printSelectedQRs() {
            const selectedCheckboxes = document.querySelectorAll('#rifleCheckboxes input[type="checkbox"]:checked');
            const selectedQRs = [];
            
            selectedCheckboxes.forEach(checkbox => {
                const index = parseInt(checkbox.value);
                if (currentQRData[index]) {
                    selectedQRs.push(currentQRData[index]);
                }
            });
            
            if (selectedQRs.length === 0) {
                alert('Please select at least one QR code to print.');
                return;
            }
            
            // Ask user if they want to regenerate QR codes
            if (confirm('Do you want to regenerate QR codes before printing? This will create fresh QR codes with updated encryption.')) {
                regenerateAndPrintQRs(selectedQRs);
            } else {
                generatePrintLayout(selectedQRs);
                closePrintModal();
            }
        }
        
        function regenerateAndPrintQRs(qrData) {
            if (qrData.length === 0) {
                alert('No QR codes to regenerate.');
                return;
            }
            
            // Show progress indicator
            const progressDiv = document.createElement('div');
            progressDiv.id = 'regenerateProgress';
            progressDiv.innerHTML = `
                <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                           background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); 
                           z-index: 10000; text-align: center; min-width: 300px;">
                    <h4>Regenerating QR Codes</h4>
                    <div style="margin: 15px 0;">Progress: <span id="regenProgress">0</span> / ${qrData.length}</div>
                    <div style="background: #f0f0f0; height: 10px; border-radius: 5px; overflow: hidden;">
                        <div id="regenProgressBar" style="background: #007bff; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 14px; color: #666;">Please wait...</div>
                </div>
            `;
            document.body.appendChild(progressDiv);
            
            let regeneratedQRs = [];
            let completedCount = 0;
            
            // Function to regenerate a single QR code
            function regenerateSingleQR(qrItem, index) {
                const isExternal = (qrItem.type && qrItem.type === 'external') || String(qrItem.rifleNumber || '').toLowerCase().includes('external');
                
                if (isExternal) {
                    // Regenerate external QRs to ensure fresh ROTC_QR_V1 payload
                    return fetch('rifle_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=generate_new_rifle_qr_external&rifle_numbers=${encodeURIComponent(qrItem.rifleNumber)}`
                    })
                    .then(handleFetchResponse)
                    .then(data => {
                        completedCount++;
                        const progressElement = document.getElementById('regenProgress');
                        const progressBarElement = document.getElementById('regenProgressBar');
                        
                        if (progressElement) {
                            progressElement.textContent = completedCount;
                        }
                        if (progressBarElement) {
                            progressBarElement.style.width = `${(completedCount / qrData.length) * 100}%`;
                        }
                        
                        if (data.success) {
                            // Handle both single and multiple QR responses
                            if (data.multiple && data.qrs && data.qrs.length > 0) {
                                // Find the QR for this specific rifle number
                                const matchingQR = data.qrs.find(qr => qr.rifle_number === qrItem.rifleNumber);
                                if (matchingQR) {
                                    regeneratedQRs.push({
                                        ...qrItem,
                                        src: matchingQR.qr_path
                                    });
                                } else {
                                    // Fallback to original if not found
                                    regeneratedQRs.push(qrItem);
                                }
                            } else {
                                // Single QR response
                                regeneratedQRs.push({
                                    ...qrItem,
                                    src: data.qr_path
                                });
                            }
                        } else {
                            // On error, keep original
                            regeneratedQRs.push(qrItem);
                        }
                    })
                    .catch(error => {
                        console.error('Error regenerating external QR:', error);
                        completedCount++;
                        const progressElement = document.getElementById('regenProgress');
                        const progressBarElement = document.getElementById('regenProgressBar');
                        
                        if (progressElement) {
                            progressElement.textContent = completedCount;
                        }
                        if (progressBarElement) {
                            progressBarElement.style.width = `${(completedCount / qrData.length) * 100}%`;
                        }
                        
                        // On error, keep original
                        regeneratedQRs.push(qrItem);
                    });
                }

                return fetch('rifle_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=regenerate_qr&rifle_number=${encodeURIComponent(qrItem.rifleNumber)}`
                })
                .then(response => response.json())
                .then(data => {
                    completedCount++;
                    const progressElement = document.getElementById('regenProgress');
                    const progressBarElement = document.getElementById('regenProgressBar');
                    
                    if (progressElement) {
                        progressElement.textContent = completedCount;
                    }
                    if (progressBarElement) {
                        progressBarElement.style.width = `${(completedCount / qrData.length) * 100}%`;
                    }
                    
                    if (data.success) {
                        regeneratedQRs.push({
                            src: data.qr_path + '?t=' + Date.now(), // Add timestamp to force reload
                            label: qrItem.label,
                            rifleNumber: qrItem.rifleNumber,
                            type: qrItem.type || 'internal'
                        });
                    } else {
                        console.error(`Failed to regenerate QR for rifle ${qrItem.rifleNumber}:`, data.message);
                        // Keep original QR if regeneration fails
                        regeneratedQRs.push(qrItem);
                    }
                })
                .catch(error => {
                    completedCount++;
                    const progressElement = document.getElementById('regenProgress');
                    const progressBarElement = document.getElementById('regenProgressBar');
                    
                    if (progressElement) {
                        progressElement.textContent = completedCount;
                    }
                    if (progressBarElement) {
                        progressBarElement.style.width = `${(completedCount / qrData.length) * 100}%`;
                    }
                    
                    console.error(`Error regenerating QR for rifle ${qrItem.rifleNumber}:`, error);
                    // Keep original QR if regeneration fails
                    regeneratedQRs.push(qrItem);
                });
            }
            
            // Regenerate all QR codes sequentially
            const regeneratePromises = qrData.map((qrItem, index) => regenerateSingleQR(qrItem, index));
            
            Promise.all(regeneratePromises)
                .then(() => {
                    // Remove progress indicator
                    document.body.removeChild(progressDiv);
                    
                    // Sort regenerated QRs to maintain original order
                    regeneratedQRs.sort((a, b) => {
                        const aIndex = qrData.findIndex(item => item.rifleNumber === a.rifleNumber);
                        const bIndex = qrData.findIndex(item => item.rifleNumber === b.rifleNumber);
                        return aIndex - bIndex;
                    });
                    
                    // Update currentQRData with regenerated QRs
                    currentQRData = regeneratedQRs;
                    
                    // Generate print layout with regenerated QRs
                    generatePrintLayout(regeneratedQRs);
                    closePrintModal();
                })
                .catch(error => {
                    document.body.removeChild(progressDiv);
                    console.error('Error during QR regeneration:', error);
                    alert('Some QR codes could not be regenerated. Proceeding with available QR codes.');
                    generatePrintLayout(regeneratedQRs.length > 0 ? regeneratedQRs : qrData);
                    closePrintModal();
                });
        }
        
        function generatePrintLayout(qrData) {
            const printLayout = document.getElementById('printLayout');
            if (!printLayout) return;
            
            printLayout.innerHTML = '';
            
            // Only create pages if there are QR codes to display
            if (qrData.length === 0) return;
            
            // Create pages with up to 9 QR codes each (3x3 grid)
            for (let i = 0; i < qrData.length; i += 9) {
                const page = document.createElement('div');
                page.className = 'print-page';
                
                // Get up to 9 QR codes for this page
                const pageQRs = qrData.slice(i, i + 9);
                
                // Only add QR codes that exist - no empty divs
                pageQRs.forEach(qr => {
                    const qrItem = document.createElement('div');
                    qrItem.className = 'print-qr-item';
                    qrItem.innerHTML = `
                        <img src="${qr.src}" alt="QR Code" onload="this.style.opacity=1">
                        <div class="print-qr-label">${qr.label}</div>
                    `;
                    page.appendChild(qrItem);
                });
                
                // Only add page if it has QR codes
                if (pageQRs.length > 0) {
                    // Add 'full-page' class if this page has exactly 9 QR codes
                    if (pageQRs.length === 9) {
                        page.classList.add('full-page');
                    }
                    printLayout.appendChild(page);
                }
            }
            
            // Ensure all images are loaded before printing
            const images = printLayout.querySelectorAll('img');
            let loadedImages = 0;
            const totalImages = images.length;
            
            if (totalImages === 0) {
                // No images to load, print immediately
                setTimeout(() => {
                    window.print();
                }, 300);
                return;
            }
            
            images.forEach(img => {
                if (img.complete) {
                    loadedImages++;
                } else {
                    img.onload = () => {
                        loadedImages++;
                        if (loadedImages === totalImages) {
                            setTimeout(() => {
                                window.print();
                            }, 300);
                        }
                    };
                    img.onerror = () => {
                        loadedImages++;
                        if (loadedImages === totalImages) {
                            setTimeout(() => {
                                window.print();
                            }, 300);
                        }
                    };
                }
            });
            
            // If all images are already loaded
            if (loadedImages === totalImages) {
                setTimeout(() => {
                    window.print();
                }, 300);
            }
        }
        
        function printSingleQR(qrSrc, rifleNumber, isExternal = false) {
            const cleanNumber = String(rifleNumber || '').trim();
            const qrItem = {
                src: qrSrc,
                label: `Rifle #${cleanNumber}`,
                rifleNumber: cleanNumber,
                type: isExternal ? 'external' : 'internal'
            };
            
            // Always regenerate QRs before printing to ensure current payload and fresh generation
            regenerateAndPrintQRs([qrItem]);
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('printModal');
            if (event.target === modal) {
                closePrintModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePrintModal();
            }
        });
        
        // Recent Activities Functions
        function loadRecentActivities() {
            fetch('api/rifle_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_recent_activities',
                    limit: 10
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        displayRecentActivities(data.activities);
                    } else {
                        console.error('Failed to load recent activities:', data.message);
                    }
                } catch (parseError) {
                    console.error('Error parsing JSON response:', parseError);
                    console.error('Response text:', text);
                }
            })
            .catch(error => {
                console.error('Error loading recent activities:', error);
            });
        }
        
        function displayRecentActivities(activities) {
            const container = document.getElementById('recent-activities');
            const countBadge = document.getElementById('activities-count');
            
            if (!container || !countBadge) return;
            
            countBadge.textContent = activities.length;
            
            if (activities.length === 0) {
                container.innerHTML = `
                    <div class="empty-state modern-empty">
                        <div class="empty-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <p class="empty-text">No recent activities</p>
                        <p class="empty-subtext">Activity will appear here when rifles are assigned or returned</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="activity-list modern-list">';
            
            activities.slice(0, 5).forEach(activity => {
                const actionIcon = activity.action === 'assigned' ? 'fa-arrow-right' : 'fa-arrow-left';
                const actionClass = activity.action === 'assigned' ? 'avatar-success' : 'avatar-info';
                const statusClass = activity.action === 'assigned' ? 'dot-success' : 'dot-info';
                const actionText = activity.action === 'assigned' ? 'assigned' : 'returned';
                
                const timestamp = new Date(activity.timestamp);
                const formattedTime = timestamp.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                
                html += `
                    <div class="activity-item modern-item">
                        <div class="activity-avatar ${actionClass}">
                            <i class="fas ${actionIcon}"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-primary">
                                <span class="cadet-name">${activity.cadet_name || 'Unknown'}</span>
                                <span class="action-text">${actionText}</span>
                                <span class="rifle-badge">Rifle #${activity.serial_number}</span>
                            </div>
                            <div class="activity-meta">
                                <span class="time-badge">
                                    <i class="fas fa-clock"></i>
                                    ${formattedTime}
                                </span>
                            </div>
                        </div>
                        <div class="activity-status">
                            <span class="status-dot ${statusClass}"></span>
                        </div>
                    </div>
                `;
            });
            
            if (activities.length > 5) {
                html += `
                    <div class="show-more-item">
                        <div class="show-more-content">
                            <i class="fas fa-ellipsis-h"></i>
                            <span class="show-more-text">${activities.length - 5} more activities</span>
                            <button class="btn-link" onclick="loadAllActivities()">View All</button>
                        </div>
                    </div>
                `;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function loadAllActivities() {
            // This function can be expanded to show a modal or navigate to a full activities page
            alert('View all activities functionality can be implemented here');
        }
        
        // Load recent activities when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentActivities();
        });
        
        // Refresh activities every 30 seconds
        setInterval(loadRecentActivities, 30000);
        
        // Borrowing Management Functions
        function toggleBorrowingSection() {
            const section = document.getElementById('borrowingSection');
            if (!section) {
                console.error('Borrowing section element not found');
                return;
            }
            
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                loadBorrowingData();
            } else {
                section.style.display = 'none';
            }
        }
        
        // Tab switching for borrowing modes
        function switchBorrowingMode(mode) {
            console.debug('[BORROWING DEBUG] switchBorrowingMode called with mode:', mode);
            
            // Hide all tab contents
            document.querySelectorAll('.borrowing-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            console.debug('[BORROWING DEBUG] All tab contents hidden');
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.borrowing-action-tabs .tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            console.debug('[BORROWING DEBUG] All tab buttons deactivated');
            
            // Show selected tab content
            switch(mode) {
                case 'borrow':
                    console.debug('[BORROWING DEBUG] Switching to borrow mode');
                    document.getElementById('borrowTab').classList.add('active');
                    resetBorrowingWorkflow();
                    break;
                case 'return':
                    console.debug('[BORROWING DEBUG] Switching to return mode');
                    document.getElementById('returnTab').classList.add('active');
                    resetReturnWorkflow();
                    break;
                case 'active':
                    console.debug('[BORROWING DEBUG] Switching to active borrowings mode');
                    document.getElementById('activeBorrowingsTab').classList.add('active');
                    loadActiveBorrowings();
                    break;
                case 'returnHistory':
                    console.debug('[BORROWING DEBUG] Switching to return history mode');
                    document.getElementById('returnHistoryTab').classList.add('active');
                    loadBorrowingData();
                    break;
                case 'history':
                    console.debug('[BORROWING DEBUG] Switching to borrowing history mode');
                    document.getElementById('historyTab').classList.add('active');
                    loadBorrowingHistory();
                    break;
                case 'generate':
                    console.debug('[BORROWING DEBUG] Switching to generate QR mode');
                    document.getElementById('generateTab').classList.add('active');
                    resetGenerateForm();
                    break;
                default:
                    console.warn('[BORROWING DEBUG] Unknown mode:', mode);
            }
            
            // Add active class to clicked button
            if (event && event.target) {
                event.target.classList.add('active');
                console.debug('[BORROWING DEBUG] Active class added to button:', event.target.textContent.trim());
            } else {
                console.warn('[BORROWING DEBUG] No event target found for button activation');
            }
            
            console.debug('[BORROWING DEBUG] switchBorrowingMode completed for mode:', mode);
        }

        // Global variables for borrowing workflow
        let currentBorrower = null;
        let selectedRifles = [];
        let tempBorrowerId = null;

        // Reset borrowing workflow
        function resetBorrowingWorkflow() {
            currentBorrower = null;
            selectedRifles = [];
            tempBorrowerId = null;
            
            // Hide workflow steps
            const rifleSelectionStep = document.getElementById('rifleSelectionStep');
            const confirmBorrowStep = document.getElementById('confirmBorrowStep');
            if (rifleSelectionStep) rifleSelectionStep.style.display = 'none';
            if (confirmBorrowStep) confirmBorrowStep.style.display = 'none';
            
            // Reset forms and results
            const borrowerQRResult = document.getElementById('borrowerQRResult');
            const borrowerDetailsForm = document.getElementById('borrowerDetailsForm');
            const selectedRiflesList = document.getElementById('selectedRiflesList');
            const borrowingSummary = document.getElementById('borrowingSummary');
            
            if (borrowerQRResult) borrowerQRResult.style.display = 'none';
            if (borrowerDetailsForm) borrowerDetailsForm.style.display = 'none';
            if (selectedRiflesList) selectedRiflesList.innerHTML = '';
            if (borrowingSummary) borrowingSummary.innerHTML = '';
            
            // Clear form inputs
            const borrowerName = document.getElementById('borrowerName');
            const borrowerCourse = document.getElementById('borrowerCourse');
            const borrowerContact = document.getElementById('borrowerContact');
            const borrowerTempId = document.getElementById('borrowerTempId');
            
            if (borrowerName) borrowerName.value = '';
            if (borrowerCourse) borrowerCourse.value = '';
            if (borrowerContact) borrowerContact.value = '';
            if (borrowerTempId) borrowerTempId.value = '';
        }

        // Reset return workflow
        function resetReturnWorkflow() {
            const returnBorrowerResult = document.getElementById('returnBorrowerResult');
            const borrowerRiflesList = document.getElementById('borrowerRiflesList');
            
            if (returnBorrowerResult) returnBorrowerResult.style.display = 'none';
            if (borrowerRiflesList) {
                borrowerRiflesList.style.display = 'none';
                borrowerRiflesList.innerHTML = '';
            }
        }

        // Start borrower QR scan
        function startBorrowerQRScan() {
            // Simulate QR scan - in real implementation, this would use camera
            const qrCode = prompt('Enter Borrower QR Code (Temp ID):');
            if (qrCode) {
                processBorrowerQR(qrCode);
            }
        }

        // Process borrower QR code
        function processBorrowerQR(qrCode) {
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_borrower_qr&qr_code=${encodeURIComponent(qrCode)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    if (data.borrower) {
                        // Existing borrower found
                        currentBorrower = data.borrower;
                        tempBorrowerId = qrCode;
                        showBorrowerFound(data.borrower);
                        showRifleSelectionStep();
                    } else {
                        // New borrower - show registration form
                        tempBorrowerId = qrCode;
                        showBorrowerRegistrationForm(qrCode);
                    }
                } else {
                    alert('Error checking borrower QR: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while checking the borrower QR code.');
            });
        }

        // Show borrower found result
        function showBorrowerFound(borrower) {
            const resultDiv = document.getElementById('borrowerQRResult');
            if (!resultDiv) {
                console.error('borrowerQRResult element not found');
                return;
            }
            resultDiv.innerHTML = `
                <h5>Borrower Found</h5>
                <p><strong>Name:</strong> ${borrower.name}</p>
                <p><strong>Course:</strong> ${borrower.course}</p>
                <p><strong>Contact:</strong> ${borrower.contact || 'N/A'}</p>
                <p><strong>Temp ID:</strong> ${borrower.temp_id}</p>
            `;
            resultDiv.style.display = 'block';
        }

        // Show borrower registration form
        function showBorrowerRegistrationForm(tempId) {
            const borrowerTempIdElement = document.getElementById('borrowerTempId');
            const borrowerDetailsFormElement = document.getElementById('borrowerDetailsForm');
            
            if (!borrowerTempIdElement) {
                console.error('borrowerTempId element not found');
                return;
            }
            if (!borrowerDetailsFormElement) {
                console.error('borrowerDetailsForm element not found');
                return;
            }
            
            borrowerTempIdElement.value = tempId;
            borrowerDetailsFormElement.style.display = 'block';
        }

        // Register new borrower
        function registerBorrower() {
            const borrowerNameElement = document.getElementById('borrowerName');
            const borrowerCourseElement = document.getElementById('borrowerCourse');
            const borrowerContactElement = document.getElementById('borrowerContact');
            const borrowerTempIdElement = document.getElementById('borrowerTempId');
            
            if (!borrowerNameElement || !borrowerCourseElement || !borrowerContactElement || !borrowerTempIdElement) {
                console.error('One or more borrower form elements not found');
                alert('Form elements not found. Please refresh the page.');
                return;
            }
            
            const name = borrowerNameElement.value.trim();
            const course = borrowerCourseElement.value.trim();
            const contact = borrowerContactElement.value.trim();
            const tempId = borrowerTempIdElement.value;
            
            if (!name || !course) {
                alert('Please fill in all required fields (Name and Course).');
                return;
            }
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=register_borrower&name=${encodeURIComponent(name)}&course=${encodeURIComponent(course)}&contact=${encodeURIComponent(contact)}&temp_id=${encodeURIComponent(tempId)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    currentBorrower = {
                        name: name,
                        course: course,
                        contact: contact,
                        temp_id: tempId
                    };
                    
                    // Hide registration form and show success
                    const borrowerDetailsFormElement = document.getElementById('borrowerDetailsForm');
                    if (borrowerDetailsFormElement) {
                        borrowerDetailsFormElement.style.display = 'none';
                    }
                    showBorrowerFound(currentBorrower);
                    showRifleSelectionStep();
                } else {
                    alert('Error registering borrower: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while registering the borrower.');
            });
        }

        // Show rifle selection step
        function showRifleSelectionStep() {
            const rifleSelectionStepElement = document.getElementById('rifleSelectionStep');
            if (!rifleSelectionStepElement) {
                console.error('rifleSelectionStep element not found');
                return;
            }
            rifleSelectionStepElement.style.display = 'block';
            
            // Update current borrower info
            const borrowerInfo = document.getElementById('currentBorrowerInfo');
            if (!borrowerInfo) {
                console.error('currentBorrowerInfo element not found');
                return;
            }
            borrowerInfo.innerHTML = `
                <h5>Current Borrower</h5>
                <p><strong>Name:</strong> ${currentBorrower.name}</p>
                <p><strong>Course:</strong> ${currentBorrower.course}</p>
                <p><strong>Temp ID:</strong> ${currentBorrower.temp_id}</p>
            `;
        }

        // Start rifle QR scan
        function startRifleQRScan() {
            // Simulate QR scan - in real implementation, this would use camera
            const qrCode = prompt('Scan Rifle QR Code:');
            if (qrCode) {
                processRifleQR(qrCode);
            }
        }

        // Process rifle QR code
        function processRifleQR(qrCode) {
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_rifle_qr&qr_code=${encodeURIComponent(qrCode)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success && data.rifle) {
                    if (data.rifle.status === 'available') {
                        addRifleToSelection(data.rifle);
                    } else {
                        alert(`Rifle ${data.rifle.rifle_number} is not available (Status: ${data.rifle.status})`);
                    }
                } else {
                    alert('Rifle not found or error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while checking the rifle QR code.');
            });
        }

        // Add rifle to selection
        function addRifleToSelection(rifle) {
            // Check if rifle already selected
            if (selectedRifles.find(r => r.id === rifle.id)) {
                alert(`Rifle ${rifle.rifle_number} is already selected.`);
                return;
            }
            
            selectedRifles.push(rifle);
            updateSelectedRiflesList();
            
            // Show confirm step if rifles are selected
            if (selectedRifles.length > 0) {
                showConfirmBorrowStep();
            }
        }

        // Update selected rifles list
        function updateSelectedRiflesList() {
            const listDiv = document.getElementById('selectedRiflesList');
            if (!listDiv) {
                console.error('selectedRiflesList element not found');
                return;
            }
            
            if (selectedRifles.length === 0) {
                listDiv.innerHTML = '<p class="text-muted">No rifles selected yet.</p>';
                return;
            }
            
            let html = '<h5>Selected Rifles:</h5>';
            selectedRifles.forEach((rifle, index) => {
                html += `
                    <div class="selected-rifle-item">
                        <span><strong>${rifle.rifle_number}</strong> - ${rifle.status}</span>
                        <button class="btn btn-sm btn-danger" onclick="removeRifleFromSelection(${index})">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                `;
            });
            
            listDiv.innerHTML = html;
        }

        // Remove rifle from selection
        function removeRifleFromSelection(index) {
            selectedRifles.splice(index, 1);
            updateSelectedRiflesList();
            
            if (selectedRifles.length === 0) {
                const confirmBorrowStepElement = document.getElementById('confirmBorrowStep');
                if (confirmBorrowStepElement) {
                    confirmBorrowStepElement.style.display = 'none';
                }
            } else {
                updateBorrowingSummary();
            }
        }

        // Show confirm borrow step
        function showConfirmBorrowStep() {
            const confirmBorrowStepElement = document.getElementById('confirmBorrowStep');
            if (!confirmBorrowStepElement) {
                console.error('confirmBorrowStep element not found');
                return;
            }
            confirmBorrowStepElement.style.display = 'block';
            updateBorrowingSummary();
        }

        // Update borrowing summary
        function updateBorrowingSummary() {
            const summaryDiv = document.getElementById('borrowingSummary');
            if (!summaryDiv) {
                console.error('borrowingSummary element not found');
                return;
            }
            
            let html = `
                <h5>Borrowing Summary</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Borrower Details:</h6>
                        <p><strong>Name:</strong> ${currentBorrower.name}</p>
                        <p><strong>Course:</strong> ${currentBorrower.course}</p>
                        <p><strong>Contact:</strong> ${currentBorrower.contact || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Rifles to Borrow:</h6>
                        <p><strong>Total Rifles:</strong> ${selectedRifles.length}</p>
                        <ul>
            `;
            
            selectedRifles.forEach(rifle => {
                html += `<li>${rifle.rifle_number}</li>`;
            });
            
            html += `
                        </ul>
                    </div>
                </div>
            `;
            
            summaryDiv.innerHTML = html;
        }

        // Confirm borrowing
        function confirmBorrowing() {
            if (!currentBorrower || selectedRifles.length === 0) {
                alert('Please complete all steps before confirming.');
                return;
            }
            
            const rifleIds = selectedRifles.map(rifle => rifle.id);
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=confirm_borrowing&temp_id=${encodeURIComponent(tempBorrowerId)}&rifle_ids=${JSON.stringify(rifleIds)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    alert(`Successfully borrowed ${selectedRifles.length} rifles to ${currentBorrower.name}`);
                    resetBorrowingWorkflow();
                    loadRifleStats(); // Refresh stats
                } else {
                    alert('Error confirming borrowing: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while confirming the borrowing.');
            });
        }

        // Cancel borrowing
        function cancelBorrowing() {
            if (confirm('Are you sure you want to cancel this borrowing? All selected rifles will be cleared.')) {
                resetBorrowingWorkflow();
            }
        }

        // Start return borrower QR scan
        function startReturnBorrowerQRScan() {
            // Simulate QR scan - in real implementation, this would use camera
            const qrCode = prompt('Scan Borrower QR Code for Return:');
            if (qrCode) {
                processReturnBorrowerQR(qrCode);
            }
        }

        // Process return borrower QR
        function processReturnBorrowerQR(qrCode) {
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_borrower_rifles&temp_id=${encodeURIComponent(qrCode)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success && data.borrower) {
                    showBorrowerRiflesForReturn(data.borrower, data.rifles);
                } else {
                    alert('No active borrowings found for this borrower or error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while checking borrower rifles.');
            });
        }

        // Show borrower rifles for return
        function showBorrowerRiflesForReturn(borrower, rifles) {
            const resultDiv = document.getElementById('returnBorrowerResult');
            resultDiv.innerHTML = `
                <h5>Borrower Information</h5>
                <p><strong>Name:</strong> ${borrower.name}</p>
                <p><strong>Course:</strong> ${borrower.course}</p>
                <p><strong>Contact:</strong> ${borrower.contact || 'N/A'}</p>
                <p><strong>Active Borrowings:</strong> ${rifles.length} rifles</p>
            `;
            resultDiv.style.display = 'block';
            
            const riflesDiv = document.getElementById('borrowerRiflesList');
            
            if (rifles.length === 0) {
                riflesDiv.innerHTML = '<p class="text-muted">No rifles currently borrowed by this person.</p>';
            } else {
                let html = '<h5>Borrowed Rifles:</h5>';
                rifles.forEach(rifle => {
                    html += `
                        <div class="selected-rifle-item">
                            <span><strong>${rifle.rifle_number}</strong> - Borrowed on ${new Date(rifle.borrowed_at).toLocaleDateString()}</span>
                            <button class="btn btn-sm btn-success" onclick="returnSingleRifle(${rifle.id}, '${rifle.rifle_number}', '${borrower.temp_id}')">
                                <i class="fas fa-undo"></i> Return
                            </button>
                        </div>
                    `;
                });
                riflesDiv.innerHTML = html;
            }
            
            riflesDiv.style.display = 'block';
        }

        // Return single rifle
        function returnSingleRifle(rifleId, rifleNumber, tempId) {
            if (!confirm(`Are you sure you want to return rifle ${rifleNumber}?`)) {
                return;
            }
            
            fetch('rifle_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=return_rifle&rifle_id=${rifleId}&temp_id=${encodeURIComponent(tempId)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if (data.success) {
                    alert(`Rifle ${rifleNumber} has been successfully returned.`);
                    // Refresh the borrower rifles list
                    processReturnBorrowerQR(tempId);
                    loadRifleStats(); // Refresh stats
                } else {
                    alert('Error returning rifle: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while returning the rifle.');
            });
        }

        function switchBorrowingTab(tabName) {
            // Hide all tab contents
            document.getElementById('activeBorrowingsTab').style.display = 'none';
            document.getElementById('returnHistoryTab').style.display = 'none';
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.borrowing-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab and mark button as active
            if (tabName === 'active') {
                document.getElementById('activeBorrowingsTab').style.display = 'block';
                document.querySelector('[onclick="switchBorrowingTab(\'active\')"]').classList.add('active');
            } else {
                document.getElementById('returnHistoryTab').style.display = 'block';
                document.querySelector('[onclick="switchBorrowingTab(\'history\')"]').classList.add('active');
            }
        }
        
        // Load borrowing history for history tab
        function loadBorrowingHistory() {
            console.debug('📚 loadBorrowingHistory: Starting to load complete borrowing history');
            
            const historyContent = document.getElementById('historyContent');
            const historyLoading = document.getElementById('historyLoading');
            
            console.debug('🔍 loadBorrowingHistory: Found elements', {
                historyContent: !!historyContent,
                historyLoading: !!historyLoading
            });
            
            // Show loading state
            if (historyLoading) {
                historyLoading.style.display = 'block';
                console.debug('✅ loadBorrowingHistory: Showing loading state');
            }
            
            // Load complete borrowing history (both active and returned)
            console.debug('📚 loadBorrowingHistory: Fetching all borrowing history');
            fetch('borrowing_management.php?action=get_all_history')
                .then(response => {
                    console.debug('📚 loadBorrowingHistory: Response received', { status: response.status, ok: response.ok });
                    return response.json();
                })
                .then(data => {
                    console.debug('📚 loadBorrowingHistory: Data parsed', data);
                    
                    if (historyLoading) {
                        historyLoading.style.display = 'none';
                        console.debug('✅ loadBorrowingHistory: Hidden loading state');
                    }
                    
                    if (data.success) {
                        console.debug('✅ loadBorrowingHistory: Calling displayBorrowingHistory with', data.history.length, 'history items');
                        displayBorrowingHistory(data.history);
                    } else {
                        console.warn('⚠️ loadBorrowingHistory: Request failed', data);
                        if (historyContent) {
                            historyContent.innerHTML = '<p class="text-center text-gray-500">Error loading borrowing history</p>';
                        }
                    }
                })
                .catch(error => {
                    console.error('❌ loadBorrowingHistory: Error loading borrowing history:', error);
                    
                    if (historyLoading) {
                        historyLoading.style.display = 'none';
                        console.debug('✅ loadBorrowingHistory: Hidden loading state (error case)');
                    }
                    
                    if (historyContent) {
                        historyContent.innerHTML = '<p class="text-center text-gray-500">Error loading borrowing history</p>';
                    }
                });
        }
        
        // Display borrowing history
        function displayBorrowingHistory(history) {
            console.debug('📚 displayBorrowingHistory: Starting to display borrowing history with', history.length, 'items');
            
            const container = document.getElementById('historyContent');
            
            console.debug('🔍 displayBorrowingHistory: Found container element', !!container);
            
            if (!container) {
                console.error('❌ displayBorrowingHistory: historyContent element not found');
                return;
            }
            
            if (history.length === 0) {
                console.debug('📚 displayBorrowingHistory: No history items to display');
                container.innerHTML = '<p class="text-center text-gray-500">No borrowing history</p>';
                return;
            }
            
            console.debug('📚 displayBorrowingHistory: Building HTML table for', history.length, 'history items');
            
            let html = `
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rifles</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Returned Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            history.forEach(borrowing => {
                const rifleNumbers = borrowing.rifle_numbers ? borrowing.rifle_numbers.split(',') : [];
                const rifleDisplay = rifleNumbers.map(num => `<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1">#${num.trim()}</span>`).join('');
                
                const status = borrowing.returned_at ? 
                    '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Returned</span>' : 
                    '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Active</span>';
                
                const returnedDate = borrowing.returned_at ? 
                    new Date(borrowing.returned_at).toLocaleDateString() : 
                    '-';
                
                html += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${borrowing.borrower_name}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${rifleDisplay}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(borrowing.borrowed_at).toLocaleDateString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${status}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${returnedDate}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            console.debug('✅ displayBorrowingHistory: Setting container innerHTML with generated table');
            container.innerHTML = html;
            console.debug('✅ displayBorrowingHistory: Successfully displayed borrowing history');
        }

        function loadBorrowingData() {
            console.debug('🔄 loadBorrowingData: Starting to load borrowing data');
            
            // Show loading states with null checks
            const activeBorrowingsLoading = document.getElementById('activeBorrowingsLoading');
            const returnHistoryLoading = document.getElementById('returnHistoryLoading');
            
            console.debug('🔍 loadBorrowingData: Found loading elements', {
                activeBorrowingsLoading: !!activeBorrowingsLoading,
                returnHistoryLoading: !!returnHistoryLoading
            });
            
            if (activeBorrowingsLoading) {
                activeBorrowingsLoading.style.display = 'block';
                console.debug('✅ loadBorrowingData: Showing active borrowings loading state');
            }
            if (returnHistoryLoading) {
                returnHistoryLoading.style.display = 'block';
                console.debug('✅ loadBorrowingData: Showing return history loading state');
            }
            
            // Load borrowing statistics
            console.debug('📊 loadBorrowingData: Fetching borrowing statistics');
            fetch('borrowing_management.php?action=get_stats')
                .then(response => {
                    console.debug('📊 loadBorrowingData: Stats response received', { status: response.status, ok: response.ok });
                    return response.json();
                })
                .then(data => {
                    console.debug('📊 loadBorrowingData: Stats data parsed', data);
                    if (data.success) {
                        const activeBorrowingsCount = document.getElementById('activeBorrowingsCount');
                        const returnedTodayCount = document.getElementById('returnedTodayCount');
                        const totalBorrowingsCount = document.getElementById('totalBorrowingsCount');
                        
                        console.debug('📊 loadBorrowingData: Updating stats elements', {
                            activeBorrowingsCount: !!activeBorrowingsCount,
                            returnedTodayCount: !!returnedTodayCount,
                            totalBorrowingsCount: !!totalBorrowingsCount,
                            stats: data.stats
                        });
                        
                        if (activeBorrowingsCount) activeBorrowingsCount.textContent = data.stats.active || 0;
                        if (returnedTodayCount) returnedTodayCount.textContent = data.stats.returned_today || 0;
                        if (totalBorrowingsCount) totalBorrowingsCount.textContent = data.stats.total || 0;
                    } else {
                        console.warn('⚠️ loadBorrowingData: Stats request failed', data);
                    }
                })
                .catch(error => {
                    console.error('❌ loadBorrowingData: Error loading borrowing stats:', error);
                });
            
            // Load active borrowings
            console.debug('🔄 loadBorrowingData: Fetching active borrowings');
            fetch('borrowing_management.php?action=get_active')
                .then(response => {
                    console.debug('🔄 loadBorrowingData: Active borrowings response received', { status: response.status, ok: response.ok });
                    return response.json();
                })
                .then(data => {
                    console.debug('🔄 loadBorrowingData: Active borrowings data parsed', data);
                    const activeBorrowingsLoading = document.getElementById('activeBorrowingsLoading');
                    const activeBorrowingsContent = document.getElementById('activeBorrowingsContent');
                    
                    console.debug('🔄 loadBorrowingData: Found active borrowings elements', {
                        activeBorrowingsLoading: !!activeBorrowingsLoading,
                        activeBorrowingsContent: !!activeBorrowingsContent
                    });
                    
                    if (activeBorrowingsLoading) {
                        activeBorrowingsLoading.style.display = 'none';
                        console.debug('✅ loadBorrowingData: Hidden active borrowings loading state');
                    }
                    if (data.success) {
                        console.debug('✅ loadBorrowingData: Calling displayActiveBorrowings with', data.borrowings.length, 'borrowings');
                        displayActiveBorrowings(data.borrowings);
                    } else {
                        console.warn('⚠️ loadBorrowingData: Active borrowings request failed', data);
                        if (activeBorrowingsContent) {
                            activeBorrowingsContent.innerHTML = '<p class="text-center text-gray-500">Error loading active borrowings</p>';
                        }
                    }
                })
                .catch(error => {
                    console.error('❌ loadBorrowingData: Error loading active borrowings:', error);
                    const activeBorrowingsLoading = document.getElementById('activeBorrowingsLoading');
                    if (activeBorrowingsLoading) {
                        activeBorrowingsLoading.style.display = 'none';
                        console.debug('✅ loadBorrowingData: Hidden active borrowings loading state (error case)');
                    }
                });
            
            // Load return history
            console.debug('📜 loadBorrowingData: Fetching return history');
            fetch('borrowing_management.php?action=get_history')
                .then(response => {
                    console.debug('📜 loadBorrowingData: Return history response received', { status: response.status, ok: response.ok });
                    return response.json();
                })
                .then(data => {
                    console.debug('📜 loadBorrowingData: Return history data parsed', data);
                    const returnHistoryLoading = document.getElementById('returnHistoryLoading');
                    const returnHistoryContent = document.getElementById('returnHistoryContent');
                    
                    console.debug('📜 loadBorrowingData: Found return history elements', {
                        returnHistoryLoading: !!returnHistoryLoading,
                        returnHistoryContent: !!returnHistoryContent
                    });
                    
                    if (returnHistoryLoading) {
                        returnHistoryLoading.style.display = 'none';
                        console.debug('✅ loadBorrowingData: Hidden return history loading state');
                    }
                    if (data.success) {
                        console.debug('✅ loadBorrowingData: Calling displayReturnHistory with', data.history.length, 'history items');
                        displayReturnHistory(data.history);
                    } else {
                        console.warn('⚠️ loadBorrowingData: Return history request failed', data);
                        if (returnHistoryContent) {
                            returnHistoryContent.innerHTML = '<p class="text-center text-gray-500">Error loading return history</p>';
                        }
                    }
                })
                .catch(error => {
                    console.error('❌ loadBorrowingData: Error loading return history:', error);
                    const returnHistoryLoading = document.getElementById('returnHistoryLoading');
                    if (returnHistoryLoading) {
                        returnHistoryLoading.style.display = 'none';
                        console.debug('✅ loadBorrowingData: Hidden return history loading state (error case)');
                    }
                });
        }
        
        function displayActiveBorrowings(borrowings) {
            console.debug('📋 displayActiveBorrowings: Starting to display', borrowings.length, 'active borrowings');
            const container = document.getElementById('activeBorrowingsContent');
            
            if (!container) {
                console.error('❌ displayActiveBorrowings: activeBorrowingsContent element not found');
                return;
            }
            
            console.debug('✅ displayActiveBorrowings: Found container element');
            
            if (borrowings.length === 0) {
                console.debug('📋 displayActiveBorrowings: No active borrowings to display');
                container.innerHTML = '<p class="text-center text-gray-500">No active borrowings</p>';
                return;
            }
            
            console.debug('📋 displayActiveBorrowings: Building HTML table for', borrowings.length, 'borrowings');
            
            let html = `
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rifles</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            borrowings.forEach(borrowing => {
                const rifleNumbers = borrowing.rifle_numbers ? borrowing.rifle_numbers.split(',') : [];
                const rifleDisplay = rifleNumbers.map(num => `<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1">#${num.trim()}</span>`).join('');
                
                html += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${borrowing.borrower_name}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${rifleDisplay}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(borrowing.borrowed_at).toLocaleDateString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="returnRifle(${borrowing.id})" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                Return
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            console.debug('✅ displayReturnHistory: Setting container innerHTML with generated table');
            container.innerHTML = html;
            console.debug('✅ displayReturnHistory: Successfully displayed return history');
        }
        
        function displayReturnHistory(history) {
            console.debug('📜 displayReturnHistory: Starting to display', history.length, 'return history items');
            const container = document.getElementById('returnHistoryContent');
            
            if (!container) {
                console.error('❌ displayReturnHistory: returnHistoryContent element not found');
                return;
            }
            
            console.debug('✅ displayReturnHistory: Found container element');
            
            if (history.length === 0) {
                console.debug('📜 displayReturnHistory: No return history to display');
                container.innerHTML = '<p class="text-center text-gray-500">No return history</p>';
                return;
            }
            
            console.debug('📜 displayReturnHistory: Building HTML table for', history.length, 'history items');
            
            let html = `
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rifles</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Returned Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            history.forEach(borrowing => {
                const rifleNumbers = borrowing.rifle_numbers ? borrowing.rifle_numbers.split(',') : [];
                const rifleDisplay = rifleNumbers.map(num => `<span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded mr-1 mb-1">#${num.trim()}</span>`).join('');
                
                html += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${borrowing.borrower_name}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${rifleDisplay}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(borrowing.borrowed_at).toLocaleDateString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(borrowing.returned_at).toLocaleDateString()}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function returnRifle(borrowingId) {
            if (!confirm('Are you sure you want to return these rifles?')) {
                return;
            }
            
            fetch('borrowing_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=return_rifles&borrowing_id=${borrowingId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Rifles returned successfully!');
                    loadBorrowingData(); // Refresh the data
                    loadRifleGrid(); // Refresh the rifle grid to update statuses
                } else {
                    alert('Error returning rifles: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error returning rifles:', error);
                alert('Error returning rifles. Please try again.');
            });
        }
        
        // QR ID Generation Functions

    </script>
    
    <!-- Mobile Navigation Script -->
    <script src="js/mobile-navigation.js"></script>
</body>
</html>
