<?php
// attendance_operations.php - API endpoints for attendance management operations

// Start output buffering early to catch stray output/BOM from includes
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    // Clean any buffered output and exit
    if (ob_get_length()) { ob_end_clean(); }
    exit(0);
}

// Include database connection
require_once '../includes/db.php';
require_once '../includes/SecurityLogger.php';
require_once '../includes/term_enrollment.php';

// Initialize SecurityLogger
$securityLogger = new SecurityLogger();

try {
    ensure_term_enrollment_schema();
} catch (Throwable $e) {
    // ignore schema issues for API
}

// If DB connection failed, return JSON error immediately
if (!isset($link) || !$link || isset($GLOBALS['DB_CONNECTION_ERROR'])) {
    http_response_code(500);
    $msg = 'Database connection error';
    if (isset($GLOBALS['DB_CONNECTION_ERROR'])) {
        $msg .= ': ' . $GLOBALS['DB_CONNECTION_ERROR'];
    }
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Get the request data with robust parsing
$raw = file_get_contents('php://input');
$raw = is_string($raw) ? trim($raw) : '';
// Strip UTF-8 BOM if present
if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
    $raw = substr($raw, 3);
}
$input = [];
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
// Fallback to form POST if JSON body empty
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    $out = json_encode(['success' => false, 'message' => 'Invalid request']);
    if (ob_get_length()) { ob_clean(); }
    echo $out;
    exit;
}

$action = $input['action'];
$response = ['success' => false, 'message' => 'Unknown action'];

try {
    switch ($action) {
        case 'record_attendance':
            $response = recordAttendance($input);
            break;
            
        case 'check_attendance':
            $response = checkAttendance($input);
            break;
            
        case 'get_attendance_records':
            $response = getAttendanceRecords($input);
            break;
            
        default:
            http_response_code(400);
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    error_log('Attendance Operations API Error: ' . $e->getMessage());
}

// Clean any prior output and emit pure JSON only
$jsonOut = json_encode($response);
if (ob_get_length()) { ob_clean(); }
echo $jsonOut;

/**
 * Ensure attendance_records table exists with expected columns
 */
function ensureAttendanceSchema($link) {
    // Create table if it doesn't exist (idempotent)
    $create_table_query = "
        CREATE TABLE IF NOT EXISTS attendance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cadet_id INT NULL,
            cadet_name VARCHAR(255) NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            school_year VARCHAR(20) NOT NULL,
            semester VARCHAR(20) NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            event_date DATE NULL,
            recorded_by INT DEFAULT 1,
            status ENUM('present', 'absent', 'late') DEFAULT 'present',
            notes TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    mysqli_query($link, $create_table_query);

    // Verify cadet_id column exists; if not, add it (nullable to avoid failing on existing rows)
    $colCheck = mysqli_query($link, "SHOW COLUMNS FROM attendance_records LIKE 'cadet_id'");
    if ($colCheck && mysqli_num_rows($colCheck) === 0) {
        mysqli_query($link, "ALTER TABLE attendance_records ADD COLUMN cadet_id INT NULL AFTER id");
    }

    // Ensure required columns exist for unified schema
    $requiredColumns = [
        'cadet_name' => "ALTER TABLE attendance_records ADD COLUMN cadet_name VARCHAR(255) NOT NULL DEFAULT 'Unknown'",
        'student_id' => "ALTER TABLE attendance_records ADD COLUMN student_id VARCHAR(50) NOT NULL DEFAULT ''",
        'school_year' => "ALTER TABLE attendance_records ADD COLUMN school_year VARCHAR(20) NOT NULL DEFAULT ''",
        'semester' => "ALTER TABLE attendance_records ADD COLUMN semester VARCHAR(20) NOT NULL DEFAULT ''",
        'event_name' => "ALTER TABLE attendance_records ADD COLUMN event_name VARCHAR(255) NOT NULL DEFAULT 'Attendance'",
        'recorded_at' => "ALTER TABLE attendance_records ADD COLUMN recorded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        'status' => "ALTER TABLE attendance_records ADD COLUMN status ENUM('present','absent','late') DEFAULT 'present'",
    ];
    foreach ($requiredColumns as $col => $sql) {
        $chk = mysqli_query($link, "SHOW COLUMNS FROM attendance_records LIKE '".$col."'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            try { mysqli_query($link, $sql); } catch (Exception $e) {}
        }
    }

    // Ensure helpful indexes exist (ignore errors if already present)
    try { mysqli_query($link, "CREATE INDEX idx_cadet_id ON attendance_records (cadet_id)"); } catch (Exception $e) {}
    try { mysqli_query($link, "CREATE INDEX idx_student_id ON attendance_records (student_id)"); } catch (Exception $e) {}
    try { mysqli_query($link, "CREATE INDEX idx_event ON attendance_records (event_name)"); } catch (Exception $e) {}
    try { mysqli_query($link, "CREATE INDEX idx_school_year_semester ON attendance_records (school_year, semester)"); } catch (Exception $e) {}
    // Add event_date column if missing
    $colCheckDate = mysqli_query($link, "SHOW COLUMNS FROM attendance_records LIKE 'event_date'");
    if ($colCheckDate && mysqli_num_rows($colCheckDate) === 0) {
        mysqli_query($link, "ALTER TABLE attendance_records ADD COLUMN event_date DATE NULL AFTER recorded_at");
    }
    // Add unique index to enforce one-per-day per TD/SY/Sem per cadet
    try { mysqli_query($link, "CREATE UNIQUE INDEX uniq_attendance_per_day ON attendance_records (cadet_id, school_year, semester, event_name, event_date)"); } catch (Exception $e) {}
}

/**
 * Record attendance for a cadet
 */
function recordAttendance($input) {
    global $link;
    // Ensure table and columns are present before queries that reference them
    ensureAttendanceSchema($link);

    try {
        $t = get_active_term();
        if ((!isset($input['school_year']) || $input['school_year'] === '') && !empty($t['school_year'])) {
            $input['school_year'] = $t['school_year'];
        }
        if ((!isset($input['semester']) || $input['semester'] === '') && !empty($t['semester'])) {
            $input['semester'] = $t['semester'];
        }
    } catch (Throwable $e) {
        // ignore
    }
    
    // Validate required fields (accept cadet_id or profile_id)
    $required_fields = ['school_year', 'semester', 'event_name'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    if ((!isset($input['cadet_id']) || $input['cadet_id'] === '') && (!isset($input['profile_id']) || $input['profile_id'] === '')) {
        return ['success' => false, 'message' => 'Cadet ID or Profile ID is required'];
    }
    
    $cadet_id = isset($input['cadet_id']) ? mysqli_real_escape_string($link, $input['cadet_id']) : '';
    $profile_id = isset($input['profile_id']) ? (int)$input['profile_id'] : 0;
    $cadet_name = isset($input['cadet_name']) ? mysqli_real_escape_string($link, $input['cadet_name']) : '';
    $school_year = mysqli_real_escape_string($link, $input['school_year']);
    $semester = mysqli_real_escape_string($link, $input['semester']);
    $event_name = mysqli_real_escape_string($link, $input['event_name']);
    $timestamp = isset($input['timestamp']) ? $input['timestamp'] : date('Y-m-d H:i:s');
    $event_date = date('Y-m-d', strtotime($timestamp));
    
    // Resolve cadet by profile_id first, otherwise by student_id (cadet_id)
    if ($profile_id > 0) {
        $cadet_query = "SELECT * FROM cadet_profiles WHERE id = '$profile_id' LIMIT 1";
    } else {
        $cadet_query = "SELECT * FROM cadet_profiles WHERE student_id = '$cadet_id' LIMIT 1";
    }
    $cadet_result = mysqli_query($link, $cadet_query);
    
    if (!$cadet_result || mysqli_num_rows($cadet_result) == 0) {
        return ['success' => false, 'message' => 'Cadet not found in system'];
    }
    
    $cadet_data = mysqli_fetch_assoc($cadet_result);
    $cadet_profile_id = $cadet_data['id'];
    // Use student_id from DB to ensure consistency
    $resolved_student_id = $cadet_data['student_id'];

    try {
        $enrollStatus = get_cadet_enrollment_status((int)$cadet_profile_id, $school_year, $semester);
        if ($enrollStatus !== 'enrolled') {
            return ['success' => false, 'message' => 'Cadet is not enrolled for the selected academic term'];
        }
    } catch (Throwable $e) {
        // If enrollment table is unavailable, do not block attendance
    }
    
    // Derive cadet_name if not provided
    if ($cadet_name === '' || $cadet_name === null) {
        $full_name = trim(($cadet_data['first_name'] ?? '') . ' ' . ($cadet_data['last_name'] ?? ''));
        $cadet_name = $full_name !== '' ? mysqli_real_escape_string($link, $full_name) : 'Unknown';
    }
    
    // Table/index creation is handled in ensureAttendanceSchema()
    // Pre-insert duplicate check for same cadet + SY + Sem + TD on same event_date (user requirement)
    $dupCheckSql = "SELECT id FROM attendance_records 
                    WHERE cadet_id = '$cadet_profile_id' 
                      AND school_year = '$school_year' 
                      AND semester = '$semester' 
                      AND event_name = '$event_name' 
                      AND event_date = '$event_date' 
                    LIMIT 1";
    $dupRes = mysqli_query($link, $dupCheckSql);
    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
        return ['success' => false, 'message' => 'Attendance already recorded for this cadet today for the same Training Day, School Year and Semester'];
    }
    
    // Start transaction
    mysqli_begin_transaction($link);
    
    try {
        // Insert attendance record
        $insert_query = "
            INSERT INTO attendance_records 
            (cadet_id, cadet_name, student_id, school_year, semester, event_name, recorded_at, event_date, status) 
            VALUES 
            ('$cadet_profile_id', '$cadet_name', '$resolved_student_id', '$school_year', '$semester', '$event_name', '$timestamp', '$event_date', 'present')
        ";
        
        // Run insert; with mysqli exceptions enabled, duplicates will throw with code 1062
        mysqli_query($link, $insert_query);
        
        $attendance_id = mysqli_insert_id($link);
        
        // Commit transaction
        mysqli_commit($link);
        
        // Log successful attendance recording
        $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'ATTENDANCE_RECORDED', "Attendance recorded for cadet {$cadet_name} ({$resolved_student_id}) in event: {$event_name}", [], 'low');
        
        return [
            'success' => true,
            'message' => 'Attendance recorded successfully',
            'attendance' => [
                'id' => $attendance_id,
                'cadet_id' => $cadet_id,
                'cadet_name' => $cadet_name,
                'event_name' => $event_name,
                'school_year' => $school_year,
                'semester' => $semester,
                'timestamp' => $timestamp,
                'status' => 'present'
            ]
        ];
        
    } catch (mysqli_sql_exception $e) {
        // Handle duplicate key gracefully
        if ((int)$e->getCode() === 1062) {
            mysqli_rollback($link);
            return ['success' => false, 'message' => 'Attendance already recorded for this cadet today for the same Training Day, School Year and Semester'];
        }
        mysqli_rollback($link);
        // Log failed attendance recording
        $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'ATTENDANCE_FAILED', "Failed to record attendance for cadet {$cadet_name} ({$cadet_id}) in event: {$event_name} - " . $e->getMessage(), [], 'medium');
        return ['success' => false, 'message' => 'Failed to record attendance: ' . $e->getMessage()];
    } catch (Exception $e) {
        mysqli_rollback($link);
        // Log failed attendance recording
        $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'ATTENDANCE_FAILED', "Failed to record attendance for cadet {$cadet_name} ({$cadet_id}) in event: {$event_name} - " . $e->getMessage(), [], 'medium');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Check if attendance is already recorded for a cadet
 */
function checkAttendance($input) {
    global $link;
    // Ensure schema exists to avoid unknown column errors
    ensureAttendanceSchema($link);

    try {
        $t = get_active_term();
        if ((!isset($input['school_year']) || $input['school_year'] === '') && !empty($t['school_year'])) {
            $input['school_year'] = $t['school_year'];
        }
        if ((!isset($input['semester']) || $input['semester'] === '') && !empty($t['semester'])) {
            $input['semester'] = $t['semester'];
        }
    } catch (Throwable $e) {
        // ignore
    }
    
    if ((!isset($input['cadet_id']) && !isset($input['profile_id'])) || !isset($input['event_name'])) {
        return ['success' => false, 'message' => 'Cadet identifier and Event Name are required'];
    }
    
    $cadet_id = isset($input['cadet_id']) ? mysqli_real_escape_string($link, $input['cadet_id']) : '';
    $profile_id = isset($input['profile_id']) ? (int)$input['profile_id'] : 0;
    $event_name = mysqli_real_escape_string($link, $input['event_name']);
    $school_year = isset($input['school_year']) ? mysqli_real_escape_string($link, $input['school_year']) : '';
    $semester = isset($input['semester']) ? mysqli_real_escape_string($link, $input['semester']) : '';
    
    // Get cadet profile ID
    if ($profile_id > 0) {
        $cadet_query = "SELECT id FROM cadet_profiles WHERE id = '$profile_id'";
    } else {
        $cadet_query = "SELECT id FROM cadet_profiles WHERE student_id = '$cadet_id'";
    }
    $cadet_result = mysqli_query($link, $cadet_query);
    
    if (!$cadet_result || mysqli_num_rows($cadet_result) == 0) {
        return ['success' => false, 'message' => 'Cadet not found'];
    }
    
    $cadet_data = mysqli_fetch_assoc($cadet_result);
    $cadet_profile_id = $cadet_data['id'];
    
    // Build query with optional filters
    $where_conditions = ["cadet_id = '$cadet_profile_id'", "event_name = '$event_name'"];
    
    if (!empty($school_year)) {
        $where_conditions[] = "school_year = '$school_year'";
    }
    
    if (!empty($semester)) {
        $where_conditions[] = "semester = '$semester'";
    }
    
    $query = "SELECT * FROM attendance_records WHERE " . implode(' AND ', $where_conditions);
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($link)];
    }
    
    $attendance = mysqli_fetch_assoc($result);
    
    return [
        'success' => true,
        'has_attendance' => $attendance !== null,
        'attendance' => $attendance
    ];
}

/**
 * Get attendance records with optional filters
 */
function getAttendanceRecords($input) {
    global $link;

    try {
        $t = get_active_term();
        if ((!isset($input['school_year']) || $input['school_year'] === '') && !empty($t['school_year'])) {
            $input['school_year'] = $t['school_year'];
        }
        if ((!isset($input['semester']) || $input['semester'] === '') && !empty($t['semester'])) {
            $input['semester'] = $t['semester'];
        }
    } catch (Throwable $e) {
        // ignore
    }
    
    $where_conditions = [];
    $params = [];
    
    // Optional filters
    if (isset($input['cadet_id']) && !empty($input['cadet_id'])) {
        $cadet_id = mysqli_real_escape_string($link, $input['cadet_id']);
        $where_conditions[] = "student_id = '$cadet_id'";
    }
    if (isset($input['profile_id']) && !empty($input['profile_id'])) {
        $pid = (int)$input['profile_id'];
        $where_conditions[] = "cadet_id = '$pid'";
    }
    
    if (isset($input['event_name']) && !empty($input['event_name'])) {
        $event_name = mysqli_real_escape_string($link, $input['event_name']);
        $where_conditions[] = "event_name = '$event_name'";
    }
    
    if (isset($input['school_year']) && !empty($input['school_year'])) {
        $school_year = mysqli_real_escape_string($link, $input['school_year']);
        $where_conditions[] = "school_year = '$school_year'";
    }
    
    if (isset($input['semester']) && !empty($input['semester'])) {
        $semester = mysqli_real_escape_string($link, $input['semester']);
        $where_conditions[] = "semester = '$semester'";
    }
    
    // Build query
    $query = "SELECT * FROM attendance_records";
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    $query .= " ORDER BY recorded_at DESC";
    
    // Add limit if specified
    if (isset($input['limit']) && is_numeric($input['limit'])) {
        $limit = (int)$input['limit'];
        $query .= " LIMIT $limit";
    }
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($link)];
    }
    
    $records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
    
    return [
        'success' => true,
        'records' => $records,
        'count' => count($records)
    ];
}
?>
