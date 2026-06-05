<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_all':
        getAllBorrowers();
        break;
    case 'add_borrower':
        addBorrower();
        break;
    case 'validate_pin':
        validatePin();
        break;
    case 'get_guest':
        getGuestBorrower();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getAllBorrowers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, rank_position, unit, contact_number, is_guest, status FROM borrowers WHERE status = 'active' ORDER BY is_guest ASC, name ASC");
        $stmt->execute();
        $borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'borrowers' => $borrowers
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function addBorrower() {
    global $pdo;
    
    $name = $_POST['name'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $rank_position = $_POST['rank_position'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    
    if (empty($name) || empty($pin)) {
        echo json_encode([
            'success' => false,
            'message' => 'Name and PIN are required'
        ]);
        return;
    }
    
    if (strlen($pin) !== 6 || !ctype_digit($pin)) {
        echo json_encode([
            'success' => false,
            'message' => 'PIN must be exactly 6 digits'
        ]);
        return;
    }
    
    try {
        // Check if name already exists
        $stmt = $pdo->prepare("SELECT id FROM borrowers WHERE name = ? AND status = 'active'");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Borrower name already exists'
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO borrowers (name, pin, rank_position, unit, contact_number, is_guest) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $pin, $rank_position, $unit, $contact_number]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrower added successfully',
            'borrower_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function validatePin() {
    global $pdo;
    
    $borrower_id = $_POST['borrower_id'] ?? '';
    $pin = $_POST['pin'] ?? '';
    
    if (empty($borrower_id) || empty($pin)) {
        echo json_encode([
            'success' => false,
            'message' => 'Borrower ID and PIN are required'
        ]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, is_guest FROM borrowers WHERE id = ? AND pin = ? AND status = 'active'");
        $stmt->execute([$borrower_id, $pin]);
        $borrower = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($borrower) {
            echo json_encode([
                'success' => true,
                'message' => 'PIN validated successfully',
                'borrower' => $borrower
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid PIN or borrower not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function getGuestBorrower() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM borrowers WHERE is_guest = 1 AND status = 'active' LIMIT 1");
        $stmt->execute();
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($guest) {
            echo json_encode([
                'success' => true,
                'guest' => $guest
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Guest borrower not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>