<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Admin only
if (!isset($_SESSION['user_id']) || !rotc_role_in(['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Local helpers for schema checks
function __db_name($pdo) {
    try { $s = $pdo->query('SELECT DATABASE()'); return $s ? $s->fetchColumn() : null; } catch (Throwable $e) { return null; }
}
function __table_exists($pdo, $table) {
    $db = __db_name($pdo); if (!$db) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->execute([$db, $table]);
    return (int)$stmt->fetchColumn() > 0;
}
function __has_col($pdo, $table, $col) {
    $db = __db_name($pdo); if (!$db) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$db, $table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $date = $_GET['date'] ?? date('Y-m-d');
    $semester = $_GET['semester'] ?? '';
    $td = $_GET['td'] ?? '';

    // Normalize semester variants
    $sem = trim((string)$semester);
    if ($sem === '') { $semVars = []; }
    elseif ($sem === '1' || strcasecmp($sem, '1st') === 0) { $semVars = ['1','1st']; }
    elseif ($sem === '2' || strcasecmp($sem, '2nd') === 0) { $semVars = ['2','2nd']; }
    else { $semVars = [$sem]; }
    if (empty($semVars)) { $semVars = ['1','1st','2','2nd']; } // allow both when unspecified
    $semPh = implode(',', array_fill(0, count($semVars), '?'));

    $records = [];

    if (__table_exists($pdo, 'attendance_records')) {
        $hasEventDate = __has_col($pdo, 'attendance_records', 'event_date');
        $dateCond = $hasEventDate ? 'ar.event_date = ?' : 'DATE(ar.recorded_at) = ?';
        $tdClause = '';
        $params = [];
        if ($td !== '') { $tdClause = ' AND ar.event_name = ? '; $params[] = $td . 'TD'; }

        $sql = "
            SELECT 
                ar.id,
                ar.event_name,
                ar.recorded_at AS time_in,
                COALESCE(ar.status,'present') AS status,
                CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name,''), ' ', cp.last_name) AS cadet_name,
                cp.platoon,
                cp.gender
            FROM attendance_records ar
            LEFT JOIN cadet_profiles cp ON cp.id = ar.cadet_id
            LEFT JOIN users u ON u.id = cp.user_id
            WHERE ar.semester IN ($semPh)
              AND $dateCond
              $tdClause
            ORDER BY ar.recorded_at DESC
        ";
        $params = array_merge($semVars, [$date], $params);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fallback to attendance_logs if needed
    if (empty($records) && __table_exists($pdo, 'attendance_logs')) {
        $timeCol = __has_col($pdo,'attendance_logs','time_in') ? 'al.time_in' : (__has_col($pdo,'attendance_logs','created_at') ? 'al.created_at' : 'al.timestamp');
        $dateExpr = 'DATE(' . $timeCol . ')';

        $sql = "
            SELECT 
                al.id,
                al.event_name,
                $timeCol AS time_in,
                COALESCE(al.status,'present') AS status,
                CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name,''), ' ', cp.last_name) AS cadet_name,
                cp.platoon,
                cp.gender
            FROM attendance_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN cadet_profiles cp ON (cp.user_id = u.id OR cp.id = al.cadet_profile_id)
            WHERE $dateExpr = ?
        ";
        $params = [$date];
        if ($semester !== '' && __has_col($pdo,'attendance_logs','semester')) { $sql .= ' AND al.semester = ?'; $params[] = $semester; }
        if ($td !== '') { $sql .= ' AND al.event_name LIKE ?'; $params[] = "%TD $td%"; }
        $sql .= ' ORDER BY ' . $timeCol . ' DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'records' => $records,
        'count' => is_array($records) ? count($records) : 0
    ]);

} catch (Throwable $e) {
    error_log('get_attendance_records.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch attendance records']);
}
?>
