<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    check_login();
    if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'basic'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Role/approval/status filters similar to suggestions
    $sql = "
        SELECT DISTINCT cp.platoon
        FROM cadet_profiles cp
        LEFT JOIN users u ON u.id = cp.user_id
        WHERE (LOWER(u.role) IN ('basic_cadet','basic-cadet','basic cadet','basic','cadet','2cl','1cl','1cl_officer','2cl_officer') OR u.role IS NULL)
          AND (LOWER(u.approval_status) = 'approved' OR u.approval_status IS NULL)
          AND (LOWER(u.status) = 'active' OR u.status IS NULL OR u.status = 1)
          AND cp.platoon IS NOT NULL AND cp.platoon <> ''
        ORDER BY cp.platoon
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $platoons = [];
    foreach ($rows as $r) { if (!empty($r['platoon'])) { $platoons[] = $r['platoon']; } }

    echo json_encode(['success' => true, 'platoons' => $platoons]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
