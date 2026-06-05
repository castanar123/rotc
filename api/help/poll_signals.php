<?php
// Long-poll for signaling messages from the opposite role
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
switch ($role) { case 'student': case 'officer': break; default: $role = ''; }
$since_id = (int)($input['since_id'] ?? 0);

if ($session_id <= 0 || $role === '' || $token === '') { http_response_code(400); echo json_encode(['error'=>'Invalid parameters']); exit; }

try {
    // Validate token
    $col = $role === 'student' ? 'student_token' : 'officer_token';
    $q = $pdo->prepare("SELECT $col AS tkn FROM help_sessions WHERE id=? LIMIT 1");
    $q->execute([$session_id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || !hash_equals($row['tkn'] ?? '', $token)) { http_response_code(403); echo json_encode(['error'=>'Unauthorized']); exit; }

    $other = $role === 'student' ? 'officer' : 'student';
    $timeoutSec = 25; $intervalMs = 800; $start = microtime(true);
    do {
        $stmt = $pdo->prepare("SELECT id, sender_role, type, payload_json, created_at FROM help_signals
                               WHERE session_id=? AND sender_role=? AND id > ? ORDER BY id ASC LIMIT 100");
        $stmt->execute([$session_id, $other, $since_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows && count($rows) > 0) {
            foreach ($rows as &$r) { $r['payload'] = json_decode($r['payload_json'], true); unset($r['payload_json']); }
            echo json_encode(['success'=>true, 'signals'=>$rows]);
            exit;
        }
        usleep($intervalMs * 1000);
    } while ((microtime(true) - $start) < $timeoutSec);

    echo json_encode(['success'=>true, 'signals'=>[]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Database error']);
}
