<?php
require_once '../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['student_id']) && !isset($input['student_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID or name is required']);
    exit;
}

try {
    $student_id = $input['student_id'] ?? null;
    $student_name = $input['student_name'] ?? null;
    
    // Check if user exists by student_id or name
    if ($student_id) {
        $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM cadet_profiles WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'exists' => true,
                'student_id' => $user['student_id'],
                'name' => $user['first_name'] . ' ' . $user['last_name']
            ]);
        } else {
            echo json_encode(['exists' => false]);
        }
    } elseif ($student_name) {
        // Search by name (first name + last name)
        $names = explode(' ', trim($student_name), 2);
        $first_name = $names[0];
        $last_name = isset($names[1]) ? $names[1] : '';
        
        if ($last_name) {
            $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM cadet_profiles WHERE first_name LIKE ? AND last_name LIKE ?");
            $stmt->execute(["%$first_name%", "%$last_name%"]);
        } else {
            $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM cadet_profiles WHERE first_name LIKE ? OR last_name LIKE ?");
            $stmt->execute(["%$first_name%", "%$first_name%"]);
        }
        
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            if (count($users) == 1) {
                $user = $users[0];
                echo json_encode([
                    'exists' => true,
                    'student_id' => $user['student_id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name']
                ]);
            } else {
                // Multiple matches found
                $matches = array_map(function($user) {
                    return [
                        'student_id' => $user['student_id'],
                        'name' => $user['first_name'] . ' ' . $user['last_name']
                    ];
                }, $users);
                
                echo json_encode([
                    'exists' => true,
                    'multiple' => true,
                    'matches' => $matches
                ]);
            }
        } else {
            echo json_encode(['exists' => false]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in check_user.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>