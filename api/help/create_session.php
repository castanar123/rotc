<?php
// Create a student help session
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/SecurityLogger.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
    // Ensure tables exist
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS help_signals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        sender_role VARCHAR(10) NOT NULL,
        type VARCHAR(20) NOT NULL,
        payload_json LONGTEXT NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_role (session_id, sender_role, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $student_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $student_token = bin2hex(random_bytes(16));

    // Generate unique 6-digit join code
    $code = null; $tries = 0;
    do {
        $tries++;
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT id FROM help_sessions WHERE join_code = ? AND status IN ('pending','accepted','connected') LIMIT 1");
        $chk->execute([$code]);
    } while ($chk->fetch() && $tries < 10);

    $ins = $pdo->prepare("INSERT INTO help_sessions (join_code, student_user_id, status, student_token, created_at, updated_at, expires_at)
                          VALUES (?, ?, 'pending', ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR))");
    $ins->execute([$code, $student_user_id, $student_token]);
    $session_id = (int)$pdo->lastInsertId();

    try {
        $logger = new SecurityLogger();
        $logger->logSecurityEvent($student_user_id, 'HELP_CREATE_SESSION', 'Student opened help session', [ 'session_id'=>$session_id, 'join_code'=>$code ], 'low');
    } catch (Throwable $e) { /* ignore logging errors */ }

    echo json_encode([ 'success'=>true, 'session_id'=>$session_id, 'join_code'=>$code, 'student_token'=>$student_token ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'error' => 'Database error' ]);
}
