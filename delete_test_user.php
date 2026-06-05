<?php
require_once 'includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    if (isset($input['delete_all_test']) && $input['delete_all_test'] === true) {
        // Delete all test users (emails containing @test.com)
        
        // First, get all test user IDs
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email LIKE '%@test.com'");
        $stmt->execute();
        $testUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($testUserIds)) {
            $pdo->rollBack();
            echo json_encode(['success' => true, 'message' => 'No test users found', 'deleted_count' => 0]);
            exit;
        }
        
        // Delete cadet profiles for test users
        $placeholders = str_repeat('?,', count($testUserIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM cadet_profiles WHERE user_id IN ($placeholders)");
        $stmt->execute($testUserIds);
        
        // Delete the users themselves
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->execute($testUserIds);
        
        $deletedCount = count($testUserIds);
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Deleted $deletedCount test users successfully",
            'deleted_count' => $deletedCount
        ]);
        
    } elseif (isset($input['user_id'])) {
        // Delete specific user
        $userId = (int)$input['user_id'];
        
        if ($userId <= 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        // Check if user exists and is a test user
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Only allow deletion of test users for safety
        if (strpos($user['email'], '@test.com') === false) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Can only delete test users']);
            exit;
        }
        
        // Delete cadet profile first (if exists)
        $stmt = $pdo->prepare("DELETE FROM cadet_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>