<?php
// Send signaling message (offer/answer/candidate)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$session_id = (int)($input['session_id'] ?? 0);
$role = strtolower(trim($input['role'] ?? ''));
$token = trim($input['token'] ?? '');
$type = trim($input['type'] ?? '');
$payload = $input['payload'] ?? null;

if ($session_id <= 0 || ($role !== 'student' && $role !== 'officer') || $token === '' || $type === '' || $payload === null) {
    http_response_code(400); echo json_encode(['error'=>'Invalid parameters']); exit;
}

try {
    // Validate token
    $col = $role === 'student' ? 'student_token' : 'officer_token';
    $q = $pdo->prepare("SELECT $col AS tkn FROM help_sessions WHERE id=? LIMIT 1");
    $q->execute([$session_id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || !hash_equals($row['tkn'] ?? '', $token)) { http_response_code(403); echo json_encode(['error'=>'Unauthorized']); exit; }

    $ins = $pdo->prepare("INSERT INTO help_signals (session_id, sender_role, type, payload_json) VALUES (?, ?, ?, ?)");
    $ins->execute([$session_id, $role, $type, json_encode($payload)]);
    $id = (int)$pdo->lastInsertId();
    echo json_encode(['success'=>true, 'id'=>$id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Database error']);
}
