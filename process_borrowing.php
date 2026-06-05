<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Set content type to JSON
header('Content-Type: application/json');

// Access control: Allow cadets, officers, instructors, and admins
check_login();
if (!in_array($_SESSION['role'], ['cadet', 'officer', 'instructor', 'admin', '1cl', '2cl', 'commandant'])) {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'User attempted to process rifle borrowing without proper role', $_SESSION['user_id'] ?? null, 'HIGH');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
$qr_code_id = $input['qr_code_id'] ?? '';
$borrower_name = trim($input['borrower_name'] ?? '');
$rifle_ids = $input['rifle_ids'] ?? [];
$notes = trim($input['notes'] ?? '');

if (empty($qr_code_id) || empty($borrower_name) || empty($rifle_ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if (!is_array($rifle_ids) || count($rifle_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No rifles selected']);
    exit();
}

// Sanitize borrower name
if (strlen($borrower_name) < 2 || strlen($borrower_name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Borrower name must be between 2 and 100 characters']);
    exit();
}

// Validate rifle IDs are integers
foreach ($rifle_ids as $rifle_id) {
    if (!is_numeric($rifle_id) || $rifle_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid rifle ID']);
        exit();
    }
}

try {
    // Start transaction
    $link->autocommit(false);
    
    // Verify QR code exists and is active
    $sql = "SELECT * FROM dummy_qr_codes WHERE qr_code_id = ? AND is_active = 1";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("s", $qr_code_id);
    $stmt->execute();
    $qr_result = $stmt->get_result();
    
    if ($qr_result->num_rows === 0) {
        throw new Exception('Invalid or inactive QR code');
    }
    
    $stmt->close();
    
    // Check if rifles are available and not already borrowed
    $rifle_ids_str = implode(',', array_map('intval', $rifle_ids));
    $sql = "SELECT r.id, r.rifle_number, r.status,
                   CASE WHEN rb.id IS NOT NULL THEN 1 ELSE 0 END as is_borrowed
            FROM rifles r
            LEFT JOIN rifle_borrowings rb ON JSON_CONTAINS(rb.rifle_ids, CAST(r.id AS JSON)) AND rb.status = 'active'
            WHERE r.id IN ($rifle_ids_str)";
    
    $result = $link->query($sql);
    $rifle_check = [];
    $unavailable_rifles = [];
    
    while ($row = $result->fetch_assoc()) {
        $rifle_check[$row['id']] = $row;
        if ($row['status'] !== 'available' || $row['is_borrowed'] == 1) {
            $unavailable_rifles[] = $row['rifle_number'];
        }
    }
    
    // Check if all requested rifles exist
    if (count($rifle_check) !== count($rifle_ids)) {
        throw new Exception('Some rifles do not exist');
    }
    
    // Check if any rifles are unavailable
    if (!empty($unavailable_rifles)) {
        throw new Exception('Rifles not available: ' . implode(', ', $unavailable_rifles));
    }
    
    // Insert borrowing record
    $rifle_ids_json = json_encode(array_map('intval', $rifle_ids));
    $borrow_date = date('Y-m-d H:i:s');
    $processed_by = $_SESSION['user_id'];
    
    $sql = "INSERT INTO rifle_borrowings (borrower_name, rifle_ids, qr_code_id, borrowed_at, status, notes, processed_by, created_at) 
            VALUES (?, ?, ?, ?, 'active', ?, ?, ?);";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("sssssii", $borrower_name, $rifle_ids_json, $qr_code_id, $borrow_date, $notes, $processed_by, $processed_by);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create borrowing record');
    }
    
    $borrowing_id = $link->insert_id;
    $stmt->close();
    
    // Update rifle status to borrowed
    $sql = "UPDATE rifles SET status = 'borrowed', updated_at = NOW() WHERE id IN ($rifle_ids_str)";
    if (!$link->query($sql)) {
        throw new Exception('Failed to update rifle status');
    }
    
    // Log the borrowing activity
    SecurityLogger::logSecurityEvent('DATA_MODIFICATION', 
        "Rifles borrowed: " . implode(', ', array_column($rifle_check, 'rifle_number')) . " by $borrower_name", 
        $_SESSION['user_id'], 'MEDIUM');
    
    // Commit transaction
    $link->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Rifles borrowed successfully',
        'borrowing_id' => $borrowing_id,
        'rifle_count' => count($rifle_ids)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $link->rollback();
    
    SecurityLogger::logSecurityEvent('ERROR', 
        "Rifle borrowing failed: " . $e->getMessage(), 
        $_SESSION['user_id'], 'MEDIUM');
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} finally {
    // Restore autocommit
    $link->autocommit(true);
    $link->close();
}
?>