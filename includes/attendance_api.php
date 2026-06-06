<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';

// Handle different actions based on request
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'save_session':
        saveSession();
        break;
    case 'get_session':
        getSession();
        break;
    case 'get_stats':
        getAttendanceStats();
        break;
    case 'get_recent':
        getRecentAttendance();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

// Save TD and semester to session
function saveSession() {
    if (isset($_POST['td']) && isset($_POST['semester'])) {
        $_SESSION['attendance_td'] = $_POST['td'];
        $_SESSION['attendance_semester'] = $_POST['semester'];
        sendResponse(true, 'Session saved');
    } else {
        sendResponse(false, 'Missing parameters');
    }
}

// Get TD and semester from session
function getSession() {
    $response = [
        'success' => true,
        'td' => isset($_SESSION['attendance_td']) ? $_SESSION['attendance_td'] : '',
        'semester' => isset($_SESSION['attendance_semester']) ? $_SESSION['attendance_semester'] : ''
    ];
    echo json_encode($response);
    exit;
}

// Normalize semester variants (e.g., '1' => ['1','1st'])
function normalizeSemesterVariants($semester) {
    $s = trim((string)$semester);
    if ($s === '') return [''];
    if ($s === '1' || strcasecmp($s, '1st') === 0) return ['1','1st'];
    if ($s === '2' || strcasecmp($s, '2nd') === 0) return ['2','2nd'];
    return [$s, $s];
}

// Get attendance statistics
function getAttendanceStats() {
    global $pdo;
    
    // Get parameters
    $td = isset($_GET['td']) ? $_GET['td'] : '';
    $semester = isset($_GET['semester']) ? $_GET['semester'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Validate parameters
    if (empty($td) || empty($semester)) {
        sendResponse(false, 'Missing parameters');
        return;
    }
    
    try {
        // Total Strength = approved, active basic cadets
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total
                               FROM users u
                               WHERE u.role IN ('basic-cadet','basic_cadet')
                                 AND u.approval_status = 'approved'
                                 AND u.status = 'active'");
        $stmt->execute();
        $totalCadets = (int)$stmt->fetchColumn();
        
        // Present count from attendance_records for given date (filter by semester; accept variants)
        $semVars = normalizeSemesterVariants($semester);
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.cadet_id) AS present
                               FROM attendance_records a
                               JOIN cadet_profiles c ON a.cadet_id = c.id
                               JOIN users u ON c.user_id = u.id
                               WHERE a.semester IN (?,?)
                                 AND DATE(a.recorded_at) = ?
                                 AND u.role IN ('basic-cadet','basic_cadet')
                                 AND u.approval_status = 'approved'
                                 AND u.status = 'active'");
        $stmt->execute([$semVars[0], $semVars[1], $date]);
        $presentCadets = (int)$stmt->fetchColumn();
        
        // Calculate absent cadets
        $absentCadets = max(0, $totalCadets - $presentCadets);
        
        // Calculate attendance percentage
        $attendancePercentage = $totalCadets > 0 ? round(($presentCadets / $totalCadets) * 100) : 0;
        
        // Get gender-based statistics
        $maleStats = getGenderStats('Male', $td, $semester, $date);
        $femaleStats = getGenderStats('Female', $td, $semester, $date);
        
        // Get platoon-based statistics
        $platoonStats = getPlatoonStats($td, $semester, $date);
        
        // Prepare response
        $stats = [
            'total' => [
                'strength' => $totalCadets,
                'present' => $presentCadets,
                'absent' => $absentCadets,
                'percentage' => $attendancePercentage
            ],
            'male' => $maleStats,
            'female' => $femaleStats,
            'platoons' => $platoonStats
        ];
        
        sendResponse(true, 'Statistics retrieved successfully', ['stats' => $stats]);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving statistics: ' . $e->getMessage());
    }
}

// Get gender-based statistics
function getGenderStats($gender, $td, $semester, $date) {
    global $pdo;
    
    // Total gender strength among approved, active basic cadets
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total
                           FROM cadet_profiles c
                           JOIN users u ON c.user_id = u.id
                           WHERE c.gender = ?
                             AND u.role IN ('basic-cadet','basic_cadet')
                             AND u.approval_status = 'approved'
                             AND u.status = 'active'");
    $stmt->execute([$gender]);
    $totalCadets = (int)$stmt->fetchColumn();
    
    // Present gender count from attendance_records (date + semester; accept variants)
    $semVars = normalizeSemesterVariants($semester);
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.cadet_id) AS present
                           FROM attendance_records a
                           JOIN cadet_profiles c ON a.cadet_id = c.id
                           JOIN users u ON c.user_id = u.id
                           WHERE c.gender = ?
                             AND a.semester IN (?,?)
                             AND DATE(a.recorded_at) = ?
                             AND u.role IN ('basic-cadet','basic_cadet')
                             AND u.approval_status = 'approved'
                             AND u.status = 'active'");
    $stmt->execute([$gender, $semVars[0], $semVars[1], $date]);
    $presentCadets = (int)$stmt->fetchColumn();
    
    // Calculate absent cadets
    $absentCadets = max(0, $totalCadets - $presentCadets);
    
    // Calculate attendance percentage
    $attendancePercentage = $totalCadets > 0 ? round(($presentCadets / $totalCadets) * 100) : 0;
    
    return [
        'strength' => $totalCadets,
        'present' => $presentCadets,
        'absent' => $absentCadets,
        'percentage' => $attendancePercentage
    ];
}

// Get platoon-based statistics
function getPlatoonStats($td, $semester, $date) {
    global $pdo;
    
    $platoons = [];
    
    // Get list of platoons among approved, active basic cadets
    $platoonsQuery = "SELECT DISTINCT c.platoon
                      FROM cadet_profiles c
                      JOIN users u ON c.user_id = u.id
                      WHERE c.platoon IS NOT NULL AND c.platoon != ''
                        AND u.role IN ('basic-cadet','basic_cadet')
                        AND u.approval_status = 'approved'
                        AND u.status = 'active'";
    foreach ($pdo->query($platoonsQuery) as $platoonRow) {
        $platoonName = $platoonRow['platoon'];
        
        // Total strength for this platoon (approved, active basic cadets)
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total
                               FROM cadet_profiles c
                               JOIN users u ON c.user_id = u.id
                               WHERE c.platoon = ?
                                 AND u.role IN ('basic-cadet','basic_cadet')
                                 AND u.approval_status = 'approved'
                                 AND u.status = 'active'");
        $stmt->execute([$platoonName]);
        $totalCadets = (int)$stmt->fetchColumn();
        
        // Present in this platoon from attendance_records (date + semester; accept variants)
        $semVars = normalizeSemesterVariants($semester);
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.cadet_id) AS present
                               FROM attendance_records a
                               JOIN cadet_profiles c ON a.cadet_id = c.id
                               JOIN users u ON c.user_id = u.id
                               WHERE c.platoon = ?
                                 AND a.semester IN (?,?)
                                 AND DATE(a.recorded_at) = ?
                                 AND u.role IN ('basic-cadet','basic_cadet')
                                 AND u.approval_status = 'approved'
                                 AND u.status = 'active'");
        $stmt->execute([$platoonName, $semVars[0], $semVars[1], $date]);
        $presentCadets = (int)$stmt->fetchColumn();
        
        $absentCadets = max(0, $totalCadets - $presentCadets);
        
        $platoons[] = [
            'name' => $platoonName,
            'strength' => $totalCadets,
            'present' => $presentCadets,
            'absent' => $absentCadets
        ];
    }
    
    return $platoons;
}

// Get recent attendance records
function getRecentAttendance() {
    global $pdo;
    
    // Get parameters
    $td = isset($_GET['td']) ? $_GET['td'] : '';
    $semester = isset($_GET['semester']) ? $_GET['semester'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Validate parameters
    if (empty($td) || empty($semester)) {
        sendResponse(false, 'Missing parameters');
        return;
    }
    
    try {
        // Get recent attendance records from attendance_records (date + semester; accept variants)
        $query = "SELECT a.recorded_at AS timestamp,
                         c.student_id,
                         CONCAT(c.first_name, ' ', c.last_name) AS name,
                         c.platoon,
                         c.gender
                  FROM attendance_records a
                  JOIN cadet_profiles c ON a.cadet_id = c.id
                  JOIN users u ON c.user_id = u.id
                  WHERE a.semester IN (?,?)
                    AND DATE(a.recorded_at) = ?
                    AND u.role IN ('basic-cadet','basic_cadet')
                    AND u.approval_status = 'approved'
                    AND u.status = 'active'
                  ORDER BY a.recorded_at DESC
                  LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $semVars = normalizeSemesterVariants($semester);
        $stmt->execute([$semVars[0], $semVars[1], $date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Records retrieved successfully', ['records' => $records]);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving records: ' . $e->getMessage());
    }
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = []) {
    $response = array_merge(
        [
            'success' => $success,
            'message' => $message
        ],
        $data
    );
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
