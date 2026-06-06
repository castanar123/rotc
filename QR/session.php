<?php
/**
 * Session management for the QR code scanner
 * Handles persistent sessions for maintaining scanner state
 * Provides REST API endpoints for AJAX requests
 */

// Clean any previous output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Suppress all errors and warnings to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

require_once __DIR__ . '/../includes/session.php';

// Include database connection (use centralized includes to avoid config conflicts)
require_once __DIR__ . '/../includes/db.php';

// Helper functions for schema detection and fallbacks
function dbReady() {
    return isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && empty($GLOBALS['db_connection_failed']);
}
function getCurrentDatabaseName() {
    global $pdo;
    try {
        $stmt = $pdo->query('SELECT DATABASE()');
        return $stmt ? $stmt->fetchColumn() : null;
    } catch (Exception $e) {
        return null;
    }
}

function tableExists($table) {
    global $pdo;
    // First try a direct SELECT which works without information_schema privileges
    try {
        $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return true;
    } catch (Throwable $e) {
        // Fallback to information_schema when direct SELECT fails for non-existing tables
        try {
            $db = getCurrentDatabaseName();
            if (!$db) return false;
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
            $stmt->execute([$db, $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e2) {
            error_log('QR/session.php tableExists error: ' . $e2->getMessage());
            return false;
        }
    }
}

function tableHasColumn($table, $column) {
    global $pdo;
    // Try a zero-row SELECT on the column which validates existence without information_schema
    try {
        $pdo->query("SELECT `{$column}` FROM `{$table}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        // Fallback to information_schema
        try {
            $db = getCurrentDatabaseName();
            if (!$db) return false;
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$db, $table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e2) {
            error_log('QR/session.php tableHasColumn error: ' . $e2->getMessage());
            return false;
        }
    }
}

function firstExistingColumn($table, $candidates) {
    foreach ($candidates as $col) {
        if (tableHasColumn($table, $col)) return $col;
    }
    return null;
}

// Build a COALESCE date expression for attendance table using existing columns
function buildAttendanceDateExpr($alias = 'a') {
    $candidates = [];
    if (tableHasColumn('attendance', 'time_in')) $candidates[] = "$alias.time_in";
    if (tableHasColumn('attendance', 'created_at')) $candidates[] = "$alias.created_at";
    if (tableHasColumn('attendance', 'timestamp')) $candidates[] = "$alias.timestamp";
    if (tableHasColumn('attendance', 'log_date')) $candidates[] = "$alias.log_date";
    if (empty($candidates)) {
        return "DATE(NOW())";
    }
    $expr = 'DATE(COALESCE(' . implode(', ', $candidates) . '))';
    return $expr;
}

// Build a COALESCE timestamp expression for attendance table using existing columns (not wrapped in DATE())
function buildAttendanceTimestampExpr($alias = 'a') {
    $candidates = [];
    if (tableHasColumn('attendance', 'time_in')) $candidates[] = "$alias.time_in";
    if (tableHasColumn('attendance', 'created_at')) $candidates[] = "$alias.created_at";
    if (tableHasColumn('attendance', 'timestamp')) $candidates[] = "$alias.timestamp";
    if (tableHasColumn('attendance', 'log_date')) $candidates[] = "$alias.log_date";
    if (empty($candidates)) {
        return "NOW()";
    }
    return 'COALESCE(' . implode(', ', $candidates) . ')';
}

// Build join condition between attendance and cadet_profiles based on available keys
function buildAttendanceJoinOn($aliasA = 'a', $aliasS = 's') {
    $ons = [];
    if (tableHasColumn('attendance', 'cadet_id')) {
        $ons[] = "$aliasA.cadet_id = $aliasS.id";
    }
    if (tableHasColumn('attendance', 'student_id') && tableHasColumn('cadet_profiles', 'student_id')) {
        $ons[] = "$aliasA.student_id = $aliasS.student_id";
    }
    if (empty($ons)) return '1=0';
    return implode(' OR ', $ons);
}

// Build user filter clause considering optional columns
function buildUserFilterClause() {
    $parts = [];
    if (tableHasColumn('users','role')) {
        // Allow common cadet role variants in a case-insensitive way
        $parts[] = "(LOWER(u.role) IN ('basic_cadet','basic-cadet','basic cadet','basic','cadet','2cl','1cl','1cl_officer','2cl_officer') OR u.role IS NULL)";
    }
    if (tableHasColumn('users','approval_status')) {
        $parts[] = "(LOWER(u.approval_status) = 'approved' OR u.approval_status IS NULL)";
    }
    if (tableHasColumn('users','status')) {
        $parts[] = "(LOWER(u.status) = 'active' OR u.status IS NULL OR u.status = 1)";
    }
    // Do not hard-filter cadet_profiles.status; include all registered cadets
    if (empty($parts)) return '1=1';
    return implode(' AND ', $parts);
}

// Function to clean output and send JSON response
function sendJsonResponse($data) {
    // Clean any output buffer content
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Ensure we're sending clean JSON
    echo json_encode($data);
    exit();
}

// Normalize semester and return variants that may appear in DB (e.g., 1 => ['1','1st'])
function semesterVariants($semester) {
    $s = trim((string)$semester);
    if ($s === '') return [];
    $norm = strtolower(preg_replace('/\s+/', ' ', $s));
    // Map common variants to a canonical set we will try in queries
    $map1 = [
        '1', '1st', 'first', '1st semester', 'first semester', 'sem 1', 'sem1', 's1'
    ];
    $map2 = [
        '2', '2nd', 'second', '2nd semester', 'second semester', 'sem 2', 'sem2', 's2'
    ];
    $out = [];
    if (in_array($norm, $map1, true)) {
        $out = ['1', '1st', '1st Semester', 'First Semester', 'first semester'];
    } elseif (in_array($norm, $map2, true)) {
        $out = ['2', '2nd', '2nd Semester', 'Second Semester', 'second semester'];
    } else {
        // Include original and trimmed variants
        $out = [$s, $norm];
    }
    return array_values(array_unique($out));
}

// Only handle API requests if this file is called directly
if (basename($_SERVER['PHP_SELF']) === 'session.php') {
    // Handle API requests
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    switch ($action) {
    case 'save_session':
    case 'update_session':
        // Save TD and semester to session
        if (isset($_REQUEST['td']) && isset($_REQUEST['semester'])) {
            $sessionId = createOrUpdateScannerSession($_REQUEST['td'], $_REQUEST['semester']);
            sendJsonResponse([
                'success' => true,
                'session_id' => $sessionId
            ]);
        } else {
            sendJsonResponse([
                'success' => false,
                'message' => 'Missing required parameters'
            ]);
        }
        break;

    case 'diag':
        // Lightweight diagnostics to help debug empty data
        $today = date('Y-m-d');
        $hasAr = tableExists('attendance_records');
        $dbOk = dbReady();
        $arToday = 0;
        if ($dbOk && $hasAr) {
            try {
                $dateColAr = firstExistingColumn('attendance_records', ['event_date','recorded_at','timestamp','created_at','time_in']);
                $col = $dateColAr ?: 'recorded_at';
                $sql = "SELECT COUNT(*) FROM attendance_records WHERE DATE(`$col`) = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$today]);
                $arToday = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {}
        }
        sendJsonResponse([
            'success' => true,
            'db_ready' => $dbOk,
            'has_attendance_records' => $hasAr,
            'today_count_attendance_records' => $arToday,
            'session' => getSavedTdAndSemester()
        ]);
        break;
        
    case 'get_session':
        // Get saved TD and semester
        $sessionData = getSavedTdAndSemester();
        sendJsonResponse([
            'success' => true,
            'td' => $sessionData['td'],
            'semester' => $sessionData['semester']
        ]);
        break;
        
    case 'get_stats':
        // Get attendance statistics
        $td = isset($_REQUEST['td']) ? $_REQUEST['td'] : null;
        $semester = isset($_REQUEST['semester']) ? $_REQUEST['semester'] : null;
        $date = isset($_REQUEST['date']) ? $_REQUEST['date'] : date('Y-m-d');
        
        // If TD and semester are provided, update the session
        if ($td && $semester) {
            createOrUpdateScannerSession($td, $semester);
        }
        
        $stats = getAttendanceStats($date, $td, $semester);
        sendJsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
        break;
        
    case 'get_recent':
    case 'get_recent_attendance':
        // Get recent attendance records
        $td = isset($_REQUEST['td']) ? $_REQUEST['td'] : null;
        $semester = isset($_REQUEST['semester']) ? $_REQUEST['semester'] : null;
        $limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 10;
        $date = isset($_REQUEST['date']) ? $_REQUEST['date'] : date('Y-m-d');
        $records = getRecentAttendance($limit, $date, $td, $semester);
        sendJsonResponse([
            'success' => true,
            'records' => $records
        ]);
        break;
        
    case 'get_training_days':
        // Get all training days from the database
        try {
            if (!dbReady() || !tableExists('training_days')) {
                // Fallback to 1..15
                $list = [];
                for ($i = 1; $i <= 15; $i++) {
                    $suffix = ($i === 1 ? 'st' : ($i === 2 ? 'nd' : ($i === 3 ? 'rd' : 'th')));
                    $list[] = ['td_id' => (string)$i, 'label' => $i . $suffix . ' TD'];
                }
                sendJsonResponse([
                    'success' => true,
                    'training_days' => $list
                ]);
            }
            $stmt = $pdo->query('SELECT td_id, label FROM training_days ORDER BY td_id');
            $trainingDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse([
                'success' => true,
                'training_days' => $trainingDays
            ]);
        } catch (Throwable $e) {
            // On any error, fallback safely
            $list = [];
            for ($i = 1; $i <= 15; $i++) {
                $suffix = ($i === 1 ? 'st' : ($i === 2 ? 'nd' : ($i === 3 ? 'rd' : 'th')));
                $list[] = ['td_id' => (string)$i, 'label' => $i . $suffix . ' TD'];
            }
            sendJsonResponse([
                'success' => true,
                'training_days' => $list
            ]);
        }
        break;
        
    case 'get_session_attendance':
        // Get all attendance records for current TD/semester session
        $td = isset($_REQUEST['td']) ? $_REQUEST['td'] : null;
        $semester = isset($_REQUEST['semester']) ? $_REQUEST['semester'] : null;
        
        // If TD and semester are provided, update the session
        if ($td && $semester) {
            createOrUpdateScannerSession($td, $semester);
        } else {
            // Get from current session
            $sessionData = getSavedTdAndSemester();
            $td = $sessionData['td'];
            $semester = $sessionData['semester'];
        }
        
        try {
            $records = [];
            // Prefer unified attendance_records for today's date (per-day session view)
            if (tableExists('attendance_records')) {
                $userFilter = buildUserFilterClause();
                $today = date('Y-m-d');
                // Optional semester filter
                $semVars = semesterVariants($semester);
                $semClause = '';
                $paramsSem = [];
                if (!empty($semVars)) {
                    $semPh = implode(',', array_fill(0, count($semVars), '?'));
                    $semClause = " AND ar.semester IN ($semPh)";
                    $paramsSem = $semVars;
                }
                $dateColAr = firstExistingColumn('attendance_records', ['event_date','recorded_at','timestamp','created_at','time_in']);
                $tsColAr = firstExistingColumn('attendance_records', ['recorded_at','timestamp','time_in','created_at','event_date','id']);
                $dateCond = $dateColAr ? "DATE(ar.`$dateColAr`) = ?" : "DATE(ar.recorded_at) = ?";
                $tsSel = $tsColAr ? "ar.`$tsColAr`" : "ar.recorded_at";
                $sqlAr = "
                    SELECT 
                        $tsSel AS time_val,
                        COALESCE(CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name,''), ' ', cp.last_name), ar.cadet_name) AS cadet_name,
                        COALESCE(cp.student_id, ar.student_id) AS student_id,
                        cp.platoon,
                        cp.gender,
                        COALESCE(ar.status,'present') AS status
                    FROM attendance_records ar
                    LEFT JOIN cadet_profiles cp ON (cp.id = ar.cadet_id OR cp.student_id = ar.student_id)
                    LEFT JOIN users u ON u.id = cp.user_id
                    WHERE $dateCond
                      $semClause
                      AND $userFilter
                    ORDER BY $tsSel DESC
                    LIMIT 200
                ";
                $params = array_merge([$today], $paramsSem);
                $stmt = $pdo->prepare($sqlAr);
                $stmt->execute($params);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            // Fallback: legacy attendance table by TD/semester
            if (empty($records)) {
                $records = getAttendanceForTdSemester($td, $semester);
            }
            // Fallback: if still no records, try building from attendance_logs for today
            if (empty($records) && tableExists('attendance_logs')) {
                $today = date('Y-m-d');
                $logDateCol = firstExistingColumn('attendance_logs', ['attendance_date','event_date','timestamp','created_at','time_in']);
                $tsCol = firstExistingColumn('attendance_logs', ['timestamp','created_at','time_in']);
                if ($logDateCol) {
                    $where = " WHERE DATE(al.`$logDateCol`) = ? ";
                    $params = [$today];
                    if (!empty($semester) && tableHasColumn('attendance_logs','semester')) {
                        $where .= " AND al.semester = ?";
                        $params[] = $semester;
                    }
                    // Do not attempt to filter by TD via event_name; logs may not encode TD
                    $tsSelect = $tsCol ? "al.`$tsCol`" : "NOW()";
                    $hasLogUserId = tableHasColumn('attendance_logs','user_id');
                    $userJoin = $hasLogUserId ? "(u.id = cp.user_id OR u.id = al.user_id)" : "u.id = cp.user_id";
                    $sql = "
                        SELECT 
                            $tsSelect AS time_val,
                            cp.student_id AS student_id,
                            CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name, ''), ' ', cp.last_name) as cadet_name,
                            cp.platoon,
                            cp.gender
                        FROM attendance_logs al
                        LEFT JOIN cadet_profiles cp ON (cp.id = al.cadet_profile_id" . ($hasLogUserId ? " OR cp.user_id = al.user_id" : "") . ")
                        LEFT JOIN users u ON $userJoin
                        $where
                          AND (u.role IN ('basic_cadet','2cl','1cl') OR u.id IS NULL)
                          AND (u.approval_status = 'approved' OR u.approval_status IS NULL)
                          AND (u.status = 'active' OR u.status IS NULL)
                          AND (cp.status IS NULL OR cp.status IN ('Active','active'))
                        ORDER BY $tsSelect DESC
                        LIMIT 50
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $logRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    // Map to expected structure similar to attendance table output
                    $records = array_map(function($r) {
                        return [
                            'student_id' => $r['student_id'] ?? '',
                            'cadet_name' => $r['cadet_name'] ?? '',
                            'platoon' => $r['platoon'] ?? null,
                            'gender' => $r['gender'] ?? null,
                            'time_in' => $r['time_val'] ?? date('Y-m-d H:i:s'),
                            'status' => 'Present'
                        ];
                    }, $logRows);
                }
            }
            // Use existing stats function for today's date
            $statsNested = getAttendanceStats(date('Y-m-d'), $td, $semester);
            
            // Normalize attendance records for frontend
            $attendance = [];
            foreach ($records as $r) {
                // Determine time_in value
                $timeIn = null;
                if (!empty($r['time_in'])) {
                    $timeIn = $r['time_in'];
                } elseif (!empty($r['log_date']) && !empty($r['log_time'])) {
                    $timeIn = $r['log_date'] . ' ' . $r['log_time'];
                } elseif (!empty($r['created_at'])) {
                    $timeIn = $r['created_at'];
                } elseif (!empty($r['time_val'])) {
                    $timeIn = $r['time_val'];
                } else {
                    $timeIn = date('Y-m-d H:i:s');
                }
                $attendance[] = [
                    'time_in' => $timeIn,
                    'student_id' => $r['student_id'] ?? '',
                    'full_name' => $r['cadet_name'] ?? ($r['name'] ?? ''),
                    'platoon' => $r['platoon'] ?? null,
                    'gender' => $r['gender'] ?? null,
                    'status' => $r['status'] ?? 'Present'
                ];
            }
            
            // Flatten stats for frontend session card
            $flatStats = [
                'total' => $statsNested['total']['strength'] ?? 0,
                'present' => $statsNested['total']['present'] ?? 0,
                'absent' => $statsNested['total']['absent'] ?? 0,
                'attendance_rate' => isset($statsNested['total']['percentage']) ? ($statsNested['total']['percentage'] . '%') : '0%'
            ];
            
            sendJsonResponse([
                'success' => true,
                'session' => [
                    'td' => $td,
                    'semester' => $semester
                ],
                'attendance' => $attendance,
                'stats' => $flatStats
            ]);
        } catch (Throwable $e) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Error fetching attendance: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'check_duplicate':
        // Check if student already has attendance for current TD/semester
        $student_id = isset($_REQUEST['student_id']) ? $_REQUEST['student_id'] : null;
        $td = isset($_REQUEST['td']) ? $_REQUEST['td'] : null;
        $semester = isset($_REQUEST['semester']) ? $_REQUEST['semester'] : null;
        
        if (!$student_id || !$td || !$semester) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Missing required parameters'
            ]);
        }
        
        $isDuplicate = alreadyMarkedToday($student_id, $td, $semester);
        
        sendJsonResponse([
            'success' => true,
            'is_duplicate' => $isDuplicate,
            'message' => $isDuplicate ? 'Student already has attendance for this TD/semester' : 'Student can be marked present'
        ]);
        break;
        
    default:
        // No action specified
        sendJsonResponse([
            'success' => false,
            'message' => 'No action specified'
        ]);
        break;
    }
}

/**
 * Creates or updates a scanner session
 * 
 * @param int $td Training Day number
 * @param int $semester Semester number
 * @return string Session ID
 */
function createOrUpdateScannerSession($td, $semester) {
    global $pdo;
    
    // If DB not ready, persist in PHP session only
    if (!dbReady()) {
        if (!isset($_SESSION['scanner_session_id'])) {
            $_SESSION['scanner_session_id'] = bin2hex(random_bytes(16));
        }
        $_SESSION['td'] = $td;
        $_SESSION['semester'] = $semester;
        return $_SESSION['scanner_session_id'];
    }

    // Generate a session ID if not exists
    if (!isset($_SESSION['scanner_session_id'])) {
        $_SESSION['scanner_session_id'] = bin2hex(random_bytes(32));
    }
    
    $sessionId = $_SESSION['scanner_session_id'];
    $deviceInfo = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    try {
        // Check if session exists
        $stmt = $pdo->prepare("SELECT 1 FROM scanner_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing session
            $stmt = $pdo->prepare("UPDATE scanner_sessions SET td = ?, semester = ?, last_active = NOW() WHERE session_id = ?");
            $stmt->execute([$td, $semester, $sessionId]);
        } else {
            // Create new session
            $stmt = $pdo->prepare("INSERT INTO scanner_sessions (session_id, td, semester, device_info, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $td, $semester, $deviceInfo, $ipAddress]);
        }
    } catch (Exception $e) {
        // If scanner_sessions table doesn't exist, fallback silently to PHP session only
        $_SESSION['td'] = $td;
        $_SESSION['semester'] = $semester;
    }
    
    // Store in PHP session as well
    $_SESSION['td'] = $td;
    $_SESSION['semester'] = $semester;
    
    return $sessionId;
}

/**
 * Gets the current scanner session
 * 
 * @return array|null Session data or null if no session exists
 */
function getCurrentScannerSession() {
    global $pdo;
    
    if (!dbReady()) {
        return null;
    }

    if (!isset($_SESSION['scanner_session_id'])) {
        return null;
    }
    
    $sessionId = $_SESSION['scanner_session_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM scanner_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Table missing or other error; treat as no DB-backed session
        return null;
    }
    
    return null;
}

/**
 * Gets the saved TD and semester from the session
 * 
 * @return array Array with td and semester or default values
 */
function getSavedTdAndSemester() {
    $session = getCurrentScannerSession();
    
    if ($session) {
        return [
            'td' => $session['td'],
            'semester' => $session['semester']
        ];
    }
    
    // Return defaults from PHP session if available
    if (isset($_SESSION['td']) && isset($_SESSION['semester'])) {
        return [
            'td' => $_SESSION['td'],
            'semester' => $_SESSION['semester']
        ];
    }
    
    // Default values if no session exists
    return [
        'td' => 1,
        'semester' => 1
    ];
}

/**
 * Gets attendance statistics for the current session
 * 
 * @param string $date Optional date in Y-m-d format, defaults to today
 * @return array Attendance statistics
 */
function getAttendanceStats($date = null, $td = null, $semester = null) {
    global $pdo;
    
    if (!dbReady()) {
        return [
            'total' => ['strength' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0],
            'by_gender' => [],
            'by_platoon' => [],
            'recent_activity' => []
        ];
    }

    // Use provided parameters or fall back to session data, but never return early zeros
    if (!$td || !$semester) {
        $session = getCurrentScannerSession();
        if ($session) {
            $td = $td ?: $session['td'];
            $semester = $semester ?: $session['semester'];
        } else {
            // Fallback to sane defaults
            $td = $td ?: 1;
            $semester = $semester ?: 1;
        }
    }
    
    $date = $date ?: date('Y-m-d');
    
    // Get statistics using PHP instead of MySQL function for compatibility
    try {
        // Prefer unified attendance_records when available
        $useAR = tableExists('attendance_records');
        if ($useAR) {
            $userFilter = buildUserFilterClause();
            // Semester variants (optional filter)
            $semVars = semesterVariants($semester);
            $semClauseAr = '';
            $paramsSem = [];
            if (!empty($semVars)) {
                $semPh = implode(',', array_fill(0, count($semVars), '?'));
                $semClauseAr = " AND ar.semester IN ($semPh)";
                $paramsSem = $semVars;
            }

            // Date condition: prefer event_date column if exists, else DATE(recorded_at)
            $dateColAr = firstExistingColumn('attendance_records', ['event_date','recorded_at','timestamp','created_at','time_in']);
            if ($dateColAr) {
                $dateCond = "DATE(ar.`$dateColAr`) = ?";
            } else {
                throw new Exception('No usable date column in attendance_records');
            }

            // 1) Constant roster strength (independent of date)
            $sqlStrength = "
                SELECT COUNT(DISTINCT s.id) AS total_strength
                FROM cadet_profiles s
            ";
            $stmt = $pdo->prepare($sqlStrength);
            $stmt->execute();
            $total_strength = (int)$stmt->fetchColumn();

            // 2) Present count for the selected date (present/late)
            $sqlPresent = "
                SELECT COUNT(DISTINCT ar.id) AS total_present
                FROM attendance_records ar
                LEFT JOIN cadet_profiles s ON (ar.cadet_id = s.id OR ar.student_id = s.student_id)
                WHERE $dateCond
                  $semClauseAr
                  AND (LOWER(COALESCE(ar.status,'present')) IN ('present','late'))
            ";
            $paramsPresent = array_merge([$date], $paramsSem);
            $stmt = $pdo->prepare($sqlPresent);
            $stmt->execute($paramsPresent);
            $total_present = (int)$stmt->fetchColumn();
            $total_absent = max(0, $total_strength - $total_present);
            $total_percentage = $total_strength > 0 ? round(($total_present / $total_strength) * 100, 2) : 0;

            // 3) Gender strengths (constant)
            $sqlGenderStrength = "
                SELECT s.gender, COUNT(DISTINCT s.id) AS strength
                FROM cadet_profiles s
                GROUP BY s.gender
            ";
            $stmt = $pdo->prepare($sqlGenderStrength);
            $stmt->execute();
            $genderStrengthRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $by_gender = [];
            $norm = function($val) {
                $v = strtolower(trim((string)$val));
                if ($v === 'm' || $v === 'male') return 'male';
                if ($v === 'f' || $v === 'female') return 'female';
                return 'other';
            };
            foreach ($genderStrengthRows as $g) {
                $key = $norm($g['gender']);
                if (!isset($by_gender[$key])) {
                    $by_gender[$key] = ['strength' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];
                }
                $by_gender[$key]['strength'] += (int)$g['strength'];
            }

            // 4) Gender present (by date)
            $sqlGenderPresent = "
                SELECT s.gender, COUNT(DISTINCT ar.id) AS present
                FROM attendance_records ar
                LEFT JOIN cadet_profiles s ON (ar.cadet_id = s.id OR ar.student_id = s.student_id)
                WHERE $dateCond
                  $semClauseAr
                  AND (LOWER(COALESCE(ar.status,'present')) IN ('present','late'))
                GROUP BY s.gender
            ";
            $paramsGender = array_merge([$date], $paramsSem);
            $stmt = $pdo->prepare($sqlGenderPresent);
            $stmt->execute($paramsGender);
            $genderPresentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($genderPresentRows as $gp) {
                $gk = $norm($gp['gender']);
                if (!isset($by_gender[$gk])) {
                    $by_gender[$gk] = ['strength' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];
                }
                $by_gender[$gk]['present'] += (int)$gp['present'];
            }
            // finalize gender stats
            foreach ($by_gender as $gk => $vals) {
                $abs = max(0, ($vals['strength'] ?? 0) - ($vals['present'] ?? 0));
                $perc = ($vals['strength'] ?? 0) > 0 ? round((($vals['present'] ?? 0) / $vals['strength']) * 100, 2) : 0;
                $by_gender[$gk]['absent'] = $abs;
                $by_gender[$gk]['percentage'] = $perc;
            }

            // 5) Platoon strengths (constant)
            $sqlPlatoonStrength = "
                SELECT s.platoon, COUNT(DISTINCT s.id) AS strength
                FROM cadet_profiles s
                GROUP BY s.platoon
            ";
            $stmt = $pdo->prepare($sqlPlatoonStrength);
            $stmt->execute();
            $platoonStrengthRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $by_platoon = [];
            foreach ($platoonStrengthRows as $p) {
                $key = $p['platoon'];
                $by_platoon[$key] = [
                    'strength' => (int)$p['strength'],
                    'present' => 0,
                    'absent' => 0,
                    'percentage' => 0
                ];
            }

            // 6) Platoon present (by date)
            $sqlPlatoonPresent = "
                SELECT s.platoon, COUNT(DISTINCT ar.id) AS present
                FROM attendance_records ar
                LEFT JOIN cadet_profiles s ON (ar.cadet_id = s.id OR ar.student_id = s.student_id)
                WHERE $dateCond
                  $semClauseAr
                  AND (LOWER(COALESCE(ar.status,'present')) IN ('present','late'))
                GROUP BY s.platoon
            ";
            $paramsPlatoon = array_merge([$date], $paramsSem);
            $stmt = $pdo->prepare($sqlPlatoonPresent);
            $stmt->execute($paramsPlatoon);
            $platoonPresentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($platoonPresentRows as $pp) {
                $key = $pp['platoon'];
                if (!isset($by_platoon[$key])) {
                    $by_platoon[$key] = ['strength' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];
                }
                $by_platoon[$key]['present'] = (int)$pp['present'];
            }
            foreach ($by_platoon as $k => $vals) {
                $abs = max(0, ($vals['strength'] ?? 0) - ($vals['present'] ?? 0));
                $perc = ($vals['strength'] ?? 0) > 0 ? round((($vals['present'] ?? 0) / $vals['strength']) * 100, 2) : 0;
                $by_platoon[$k]['absent'] = $abs;
                $by_platoon[$k]['percentage'] = $perc;
            }

            // Fallback: if strength unexpectedly 0, relax filters and count all cadets
            if ($total_strength === 0) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM cadet_profiles");
                    $total_strength = (int)$stmt->fetchColumn();
                    // Also rebuild by_gender without user filters
                    $stmt = $pdo->query("SELECT LOWER(gender) AS gender, COUNT(*) AS strength FROM cadet_profiles GROUP BY LOWER(gender)");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $by_gender = [];
                    foreach ($rows as $r) {
                        $g = ($r['gender'] === 'male' || $r['gender'] === 'm') ? 'male' : (($r['gender'] === 'female' || $r['gender'] === 'f') ? 'female' : 'other');
                        if (!isset($by_gender[$g])) $by_gender[$g] = ['strength' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];
                        $by_gender[$g]['strength'] += (int)$r['strength'];
                    }
                } catch (Throwable $e) {}
            }

            // Extract male/female strengths for convenience
            $male_strength = isset($by_gender['male']['strength']) ? (int)$by_gender['male']['strength'] : 0;
            $female_strength = isset($by_gender['female']['strength']) ? (int)$by_gender['female']['strength'] : 0;

            // Format results
            $result = [
                'date' => $date,
                'td' => $td,
                'semester' => $semester,
                'total' => [
                    'strength' => $total_strength,
                    'present' => $total_present,
                    'absent' => $total_absent,
                    'percentage' => $total_percentage,
                    'male_strength' => $male_strength,
                    'female_strength' => $female_strength
                ],
                'by_gender' => $by_gender,
                'by_platoon' => $by_platoon
            ];

            // Get recent from helper, but don't let errors wipe out totals
            try {
                $result['recent_activity'] = getRecentAttendance(10, $date, $td, $semester);
            } catch (Throwable $e) {
                error_log('getRecentAttendance error suppressed: ' . $e->getMessage());
                $result['recent_activity'] = [];
            }
            return $result;
        }

        // Dynamic pieces
        $dateExpr = buildAttendanceDateExpr('a');
        $joinOn = buildAttendanceJoinOn('a','s');
        $userFilter = buildUserFilterClause();

        // Build semester variants and placeholders
        $semVars = semesterVariants($semester);
        if (empty($semVars)) { $semVars = [(string)$semester]; }
        $semPh = implode(',', array_fill(0, count($semVars), '?'));

        // Get total statistics (only approved active cadets)
        $sqlTotal = "\n            SELECT \n                COUNT(DISTINCT s.id) as total_strength,\n                COUNT(DISTINCT a.id) as total_present\n            FROM cadet_profiles s\n            LEFT JOIN users u ON s.user_id = u.id\n            LEFT JOIN attendance a ON (" . $joinOn . ")\n                AND a.td = ? \n                AND a.semester IN ($semPh) \n                AND " . $dateExpr . " = ?\n            WHERE " . $userFilter . "\n        ";
        $stmt = $pdo->prepare($sqlTotal);
        $paramsTotal = array_merge([$td], $semVars, [$date]);
        $stmt->execute($paramsTotal);
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_absent = $total['total_strength'] - $total['total_present'];
        $total_percentage = $total['total_strength'] > 0 ? round(($total['total_present'] / $total['total_strength']) * 100, 2) : 0;
        
        // Get statistics by gender (only approved active cadets)
        $sqlGender = "\n            SELECT \n                s.gender,\n                COUNT(DISTINCT s.id) as strength,\n                COUNT(DISTINCT a.id) as present\n            FROM cadet_profiles s\n            LEFT JOIN users u ON s.user_id = u.id\n            LEFT JOIN attendance a ON (" . $joinOn . ")\n                AND a.td = ? \n                AND a.semester IN ($semPh) \n                AND " . $dateExpr . " = ?\n            WHERE " . $userFilter . "\n            GROUP BY s.gender\n        ";
        $stmt = $pdo->prepare($sqlGender);
        $paramsGender = array_merge([$td], $semVars, [$date]);
        $stmt->execute($paramsGender);
        $gender_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics by platoon (only approved active cadets)
        $sqlPlatoon = "\n            SELECT \n                s.platoon,\n                COUNT(DISTINCT s.id) as strength,\n                COUNT(DISTINCT a.id) as present\n            FROM cadet_profiles s\n            LEFT JOIN users u ON s.user_id = u.id\n            LEFT JOIN attendance a ON (" . $joinOn . ")\n                AND a.td = ? \n                AND a.semester IN ($semPh) \n                AND " . $dateExpr . " = ?\n            WHERE " . $userFilter . "\n            GROUP BY s.platoon\n        ";
        $stmt = $pdo->prepare($sqlPlatoon);
        $paramsPlatoon = array_merge([$td], $semVars, [$date]);
        $stmt->execute($paramsPlatoon);
        $platoon_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the results
        $result = [
            'date' => $date,
            'td' => $td,
            'semester' => $semester,
            'total' => [
                'strength' => (int)$total['total_strength'],
                'present' => (int)$total['total_present'],
                'absent' => $total_absent,
                'percentage' => $total_percentage
            ],
            'by_gender' => [],
            'by_platoon' => []
        ];
        
        // Process gender statistics
        foreach ($gender_stats as $stat) {
            $absent = $stat['strength'] - $stat['present'];
            $percentage = $stat['strength'] > 0 ? round(($stat['present'] / $stat['strength']) * 100, 2) : 0;
            
            $result['by_gender'][$stat['gender']] = [
                'strength' => (int)$stat['strength'],
                'present' => (int)$stat['present'],
                'absent' => $absent,
                'percentage' => $percentage
            ];
        }
        
        // Process platoon statistics
        foreach ($platoon_stats as $stat) {
            $absent = $stat['strength'] - $stat['present'];
            $percentage = $stat['strength'] > 0 ? round(($stat['present'] / $stat['strength']) * 100, 2) : 0;
            
            $result['by_platoon'][$stat['platoon']] = [
                'strength' => (int)$stat['strength'],
                'present' => (int)$stat['present'],
                'absent' => $absent,
                'percentage' => $percentage
            ];
        }
        
        // Fallback: if no present in AR, try attendance_logs safely (do not throw)
        if ((int)$result['total']['present'] === 0 && tableExists('attendance_logs')) {
            try {
                $logDateCol = firstExistingColumn('attendance_logs', ['attendance_date','event_date','timestamp','created_at','time_in']);
                if ($logDateCol) {
                    $where = " WHERE DATE(al.`$logDateCol`) = ? ";
                    $params = [$date];
                    if (!empty($semester) && tableHasColumn('attendance_logs','semester')) {
                        $where .= " AND al.semester = ?";
                        $params[] = $semester;
                    }
                    $hasLogUserId = tableHasColumn('attendance_logs','user_id');
                    $userJoin = $hasLogUserId ? "(u.id = s.user_id OR u.id = al.user_id)" : "u.id = s.user_id";
                    $sqlPresent = "
                        SELECT COUNT(DISTINCT COALESCE(u.id, al.cadet_profile_id)) AS present
                        FROM attendance_logs al
                        LEFT JOIN cadet_profiles s ON (s.id = al.cadet_profile_id" . ($hasLogUserId ? " OR s.user_id = al.user_id" : "") . ")
                        LEFT JOIN users u ON $userJoin
                        $where
                          AND (u.role IN ('basic_cadet','cadet','2cl','1cl') OR u.id IS NULL)
                          AND (u.approval_status = 'approved' OR u.approval_status IS NULL)
                          AND (u.status = 'active' OR u.status IS NULL)
                          AND (s.status IS NULL OR s.status IN ('Active','active'))
                    ";
                    $stmt = $pdo->prepare($sqlPresent);
                    $stmt->execute($params);
                    $presentFromLogs = (int)$stmt->fetchColumn();
                    $result['total']['present'] = $presentFromLogs;
                    $result['total']['absent'] = max(0, $result['total']['strength'] - $presentFromLogs);
                    $result['total']['percentage'] = $result['total']['strength'] > 0 ? round(($presentFromLogs / $result['total']['strength']) * 100, 2) : 0;
                }
            } catch (Throwable $e) {
                error_log('logs fallback in stats suppressed: ' . $e->getMessage());
            }
        }
        
        // Get recent activity
        $result['recent_activity'] = getRecentAttendance(10, $date, $td, $semester);
        
        return $result;
        
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log("Attendance stats error: " . $e->getMessage());
        error_log("Query parameters: TD={$td}, Semester={$semester}, Date={$date}");
        
        // Return default structure on error
        return [
            'total' => ['strength' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0],
            'by_gender' => [],
            'by_platoon' => [],
            'recent_activity' => [],
            'error' => $e->getMessage() // Include error for debugging
        ];
    }
}

/**
 * Gets recent attendance records for the current session
 * 
 * @param int $limit Maximum number of records to return
 * @param string $date Optional date in Y-m-d format, defaults to today
 * @return array Recent attendance records
 */
function getRecentAttendance($limit = 10, $date = null, $td = null, $semester = null) {
    global $pdo;
    
    if (!dbReady()) {
        return [];
    }

    // Use provided parameters or fall back to session data
    if (!$td || !$semester) {
        $session = getCurrentScannerSession();
        
        if (!$session) {
            return [];
        }
        
        $td = $td ?: $session['td'];
        $semester = $semester ?: $session['semester'];
    }
    
    $date = $date ?: date('Y-m-d');
    $limit = (int)$limit; // Ensure limit is an integer
    
    // Prefer unified attendance_records when available
    if (tableExists('attendance_records')) {
        try {
            $userFilter = buildUserFilterClause();
            // Optional semester filter with variants
            $semVars = semesterVariants($semester);
            $semClause = '';
            $paramsSem = [];
            if (!empty($semVars)) {
                $semPh = implode(',', array_fill(0, count($semVars), '?'));
                $semClause = " AND ar.semester IN ($semPh)";
                $paramsSem = $semVars;
            }
            // Resilient date condition
            $dateColAr = firstExistingColumn('attendance_records', ['event_date','recorded_at','timestamp','created_at','time_in']);
            $dateCond = $dateColAr ? "DATE(ar.`$dateColAr`) = ?" : "DATE(ar.recorded_at) = ?";
            // Choose a safe ordering column available in attendance_records
            $tsColAr = firstExistingColumn('attendance_records', ['recorded_at','timestamp','time_in','created_at','event_date','id']);
            $orderExpr = $tsColAr ? "ar.`$tsColAr`" : "ar.recorded_at";
            // Do not filter by TD/event_name for recent list; show all for the selected date
            $sql = "
                SELECT 
                    $orderExpr AS timestamp,
                    COALESCE(s.student_id, ar.student_id) AS student_id,
                    COALESCE(CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name), ar.cadet_name) AS name,
                    s.platoon,
                    s.gender,
                    COALESCE(ar.status, 'present') AS status
                FROM attendance_records ar
                LEFT JOIN cadet_profiles s ON (s.id = ar.cadet_id OR s.student_id = ar.student_id)
                LEFT JOIN users u ON u.id = s.user_id
                WHERE $dateCond
                  $semClause
                  AND $userFilter
                ORDER BY $orderExpr DESC
                LIMIT $limit
            ";
            // Params: semester list first (if any), then date must match placeholder order; since dateCond appears first, we need date before sem params
            if (!empty($paramsSem)) {
                // date first, then sem IN (...)? No: SQL has dateCond before semClause, so param order is [date, ...sem]
                $params = array_merge([$date], $paramsSem);
            } else {
                $params = [$date];
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('QR/session.php getRecentAttendance attendance_records error: ' . $e->getMessage());
            // fall through to legacy/logs
        }
    }
    
    $rows = [];
    if (tableExists('attendance')) {
        try {
            $dateExpr = buildAttendanceDateExpr('a');
            $tsExpr = buildAttendanceTimestampExpr('a');
            $joinOn = buildAttendanceJoinOn('a','s');
            $userFilter = buildUserFilterClause();
            $semVars = semesterVariants($semester);
            if (empty($semVars)) { $semVars = [(string)$semester]; }
            $semPh = implode(',', array_fill(0, count($semVars), '?'));
            $sqlRecent = "SELECT a.*, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as name, s.platoon, s.gender \n         FROM attendance a \n         JOIN cadet_profiles s ON (" . $joinOn . ")\n         LEFT JOIN users u ON s.user_id = u.id\n         WHERE a.td = ? AND a.semester IN ($semPh) AND " . $dateExpr . " = ? \n             AND " . $userFilter . "\n         ORDER BY " . $tsExpr . " DESC \n         LIMIT " . $limit;
            $stmt = $pdo->prepare($sqlRecent);
            $paramsRecent = array_merge([$td], $semVars, [$date]);
            $stmt->execute($paramsRecent);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('QR/session.php getRecentAttendance attendance fallback error: ' . $e->getMessage());
            $rows = [];
        }
    }
    
    // Fallback to attendance_logs if no rows found
    if (empty($rows) && tableExists('attendance_logs')) {
        $logDateCol = firstExistingColumn('attendance_logs', ['attendance_date','event_date','timestamp','created_at','time_in']);
        $tsCol = firstExistingColumn('attendance_logs', ['timestamp','created_at','time_in']);
        if ($logDateCol) {
            try {
                $where = " WHERE DATE(al.`$logDateCol`) = ? ";
                $params = [$date];
                if (!empty($semester) && tableHasColumn('attendance_logs','semester')) {
                    $where .= " AND al.semester = ?";
                    $params[] = $semester;
                }
                // Conditional join on users depending on al.user_id existence
                $hasLogUserId = tableHasColumn('attendance_logs','user_id');
                $userJoin = $hasLogUserId ? "(u.id = cp.user_id OR u.id = al.user_id)" : "u.id = cp.user_id";
                // Do not attempt to filter by TD via event_name; logs may not encode TD
                $tsSelect = $tsCol ? "al.`$tsCol`" : "NOW()";
                $sql = "
                    SELECT 
                        $tsSelect AS timestamp,
                        cp.student_id AS student_id,
                        CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name, ''), ' ', cp.last_name) as name,
                        cp.platoon,
                        cp.gender
                    FROM attendance_logs al
                    LEFT JOIN cadet_profiles cp ON (cp.id = al.cadet_profile_id" . ($hasLogUserId ? " OR cp.user_id = al.user_id" : "") . ")
                    LEFT JOIN users u ON $userJoin
                    $where
                      AND (u.role IN ('basic_cadet','cadet','2cl','1cl') OR u.id IS NULL)
                      AND (u.approval_status = 'approved' OR u.approval_status IS NULL)
                      AND (u.status = 'active' OR u.status IS NULL)
                      AND (cp.status IS NULL OR cp.status IN ('Active','active'))
                    ORDER BY $tsSelect DESC
                    LIMIT $limit
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('getRecentAttendance logs fallback error: ' . $e->getMessage());
                $rows = [];
            }
        }
    }
    
    return $rows;
}
?>
