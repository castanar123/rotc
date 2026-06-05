<?php
session_start();
require_once 'includes/db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Suppress any warnings
error_reporting(0);
ini_set('display_errors', 0);

// Function to send clean JSON response
function sendCleanJsonResponse($data) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Send response
    echo json_encode($data);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendCleanJsonResponse([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendCleanJsonResponse([
        'success' => false,
        'message' => 'Only POST requests allowed'
    ]);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendCleanJsonResponse([
        'success' => false,
        'message' => 'Invalid JSON input'
    ]);
}

$action = $input['action'] ?? '';
$rifle_id = $input['rifle_id'] ?? '';
$rifle_number = $input['rifle_number'] ?? '';
$qr_data = $input['qr_data'] ?? [];

if (!$action || !$rifle_id) {
    sendCleanJsonResponse([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
}

try {
    if ($action === 'assign') {
        // Handle rifle assignment
        handleRifleAssignment($rifle_id, $rifle_number, $qr_data);
    } elseif ($action === 'return') {
        // Handle rifle return
        handleRifleReturn($rifle_id, $rifle_number, $qr_data);
    } else {
        sendCleanJsonResponse([
            'success' => false,
            'message' => 'Invalid action specified'
        ]);
    }
} catch (Exception $e) {
    sendCleanJsonResponse([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function handleRifleAssignment($rifle_id, $rifle_number, $qr_data) {
    global $pdo;
    
    // Check if rifle exists
    $stmt = $pdo->prepare("SELECT * FROM rifles WHERE id = ? OR rifle_number = ?");
    $stmt->execute([$rifle_id, $rifle_number]);
    $rifle = $stmt->fetch();
    
    if (!$rifle) {
        sendCleanJsonResponse([
            'success' => false,
            'message' => 'Rifle not found in database'
        ]);
    }
    
    // Check if rifle is already assigned
    $stmt = $pdo->prepare("SELECT * FROM rifle_assignments WHERE rifle_id = ? AND returned_at IS NULL");
    $stmt->execute([$rifle['id']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        sendCleanJsonResponse([
            'success' => false,
            'message' => 'Rifle is already assigned to someone else'
        ]);
    }
    
    // For now, we'll assign to the current user
    // In a real system, you might scan a cadet QR code first
    $user_id = $_SESSION['user_id'];
    
    // Create assignment record
    $stmt = $pdo->prepare("
        INSERT INTO rifle_assignments (rifle_id, assigned_to, assigned_by, assigned_at, status) 
        VALUES (?, ?, ?, NOW(), 'assigned')
    ");
    $stmt->execute([$rifle['id'], $user_id, $user_id]);
    
    sendCleanJsonResponse([
        'success' => true,
        'message' => "Rifle #{$rifle['rifle_number']} successfully assigned",
        'rifle_info' => [
            'id' => $rifle['id'],
            'number' => $rifle['rifle_number'],
            'assigned_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

function handleRifleReturn($rifle_id, $rifle_number, $qr_data) {
    global $pdo;
    
    // Check if rifle exists
    $stmt = $pdo->prepare("SELECT * FROM rifles WHERE id = ? OR rifle_number = ?");
    $stmt->execute([$rifle_id, $rifle_number]);
    $rifle = $stmt->fetch();
    
    if (!$rifle) {
        sendCleanJsonResponse([
            'success' => false,
            'message' => 'Rifle not found in database'
        ]);
    }
    
    // Check if rifle is currently assigned
    $stmt = $pdo->prepare("
        SELECT ra.*, u.username 
        FROM rifle_assignments ra 
        LEFT JOIN users u ON ra.assigned_to = u.id 
        WHERE ra.rifle_id = ? AND ra.returned_at IS NULL
    ");
    $stmt->execute([$rifle['id']]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        sendCleanJsonResponse([
            'success' => false,
            'message' => 'Rifle is not currently assigned'
        ]);
    }
    
    // Update assignment record to mark as returned
    $stmt = $pdo->prepare("
        UPDATE rifle_assignments 
        SET returned_at = NOW(), returned_by = ?, status = 'returned' 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $assignment['id']]);
    
    sendCleanJsonResponse([
        'success' => true,
        'message' => "Rifle #{$rifle['rifle_number']} successfully returned",
        'rifle_info' => [
            'id' => $rifle['id'],
            'number' => $rifle['rifle_number'],
            'was_assigned_to' => $assignment['username'] ?? 'Unknown',
            'returned_at' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>