<?php
// Officer accepts a pending session by join_code
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$join_code = trim($input['join_code'] ?? '');
if ($join_code === '') { http_response_code(400); echo json_encode(['error'=>'join_code required']); exit; }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS help_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        join_code VARCHAR(12) NOT NULL UNIQUE,
        student_user_id INT NULL,
        officer_id INT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        student_token VARCHAR(64) NOT NULL,
        officer_token VARCHAR(64) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $officer_token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("UPDATE help_sessions SET status='accepted', officer_token=?, updated_at=NOW()
                           WHERE join_code=? AND status='pending' LIMIT 1");
    $stmt->execute([$officer_token, $join_code]);
    if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error'=>'Session not found or not pending']); exit; }

    $q = $pdo->prepare("SELECT id FROM help_sessions WHERE join_code=? LIMIT 1");
    $q->execute([$join_code]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    echo json_encode([ 'success'=>true, 'session_id'=>(int)$row['id'], 'officer_token'=>$officer_token ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'error' => 'Database error' ]);
}
