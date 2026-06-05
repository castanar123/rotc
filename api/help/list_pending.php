<?php
// List pending help sessions for officers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

    $q = $pdo->query("SELECT id, join_code, created_at FROM help_sessions
                      WHERE status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())
                      ORDER BY created_at ASC LIMIT 100");
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([ 'success'=>true, 'sessions'=>$rows ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'error' => 'Database error' ]);
}
