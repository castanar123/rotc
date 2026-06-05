<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Auth: admin or basic
    check_login();
    if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'basic'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    if ($type === '' || $q === '') {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit;
    }

    $suggestions = [];

    // Build common role/approval/status filter
    $userFilter = [];
    $paramsCommon = [];
    $userFilter[] = "(LOWER(u.role) IN ('basic_cadet','basic-cadet','basic cadet','basic','cadet','2cl','1cl','1cl_officer','2cl_officer') OR u.role IS NULL)";
    $userFilter[] = "(LOWER(u.approval_status) = 'approved' OR u.approval_status IS NULL)";
    $userFilter[] = "(LOWER(u.status) = 'active' OR u.status IS NULL OR u.status = 1)";
    $userWhere = implode(' AND ', $userFilter);

    if ($type === 'name') {
        // Suggest full names that start with query (on first or last name or full name)
        $like = $q . '%';
        $sql = "
            SELECT DISTINCT TRIM(CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name,''), ' ', cp.last_name)) AS full_name
            FROM cadet_profiles cp
            LEFT JOIN users u ON u.id = cp.user_id
            WHERE $userWhere
              AND (
                cp.first_name LIKE ? OR cp.last_name LIKE ? OR CONCAT(cp.first_name, ' ', cp.last_name) LIKE ?
              )
            ORDER BY cp.last_name, cp.first_name
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (!empty($r['full_name'])) $suggestions[] = $r['full_name'];
        }
    } elseif ($type === 'id') {
        // Suggest student IDs starting with query
        $like = $q . '%';
        $sql = "
            SELECT DISTINCT cp.student_id
            FROM cadet_profiles cp
            LEFT JOIN users u ON u.id = cp.user_id
            WHERE $userWhere AND cp.student_id LIKE ?
            ORDER BY cp.student_id
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (!empty($r['student_id'])) $suggestions[] = $r['student_id'];
        }
    } elseif ($type === 'platoon') {
        // Suggest platoon values
        $like = $q . '%';
        $sql = "
            SELECT DISTINCT cp.platoon
            FROM cadet_profiles cp
            LEFT JOIN users u ON u.id = cp.user_id
            WHERE $userWhere AND cp.platoon LIKE ?
            ORDER BY cp.platoon
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (!empty($r['platoon'])) $suggestions[] = $r['platoon'];
        }
    } else {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit;
    }

    echo json_encode(['success' => true, 'suggestions' => array_values(array_unique($suggestions))]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
