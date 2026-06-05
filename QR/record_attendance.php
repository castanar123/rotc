<?php
// Start output buffering to prevent stray output from corrupting JSON
if (!ob_get_level()) { ob_start(); }
// Suppress notices/warnings in responses
error_reporting(0);
ini_set('display_errors', 0);
/**
 * Backend attendance recording functionality
 * Records student attendance in the database and maintains session state
 */

// Include database connection only
require_once 'db.php';

// Start or resume the PHP session (for TD/semester persistence)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Minimal DB schema helpers (no external includes) ---
function getCurrentDatabaseNamePDO($pdo) {
    try {
        $stmt = $pdo->query('SELECT DATABASE()');
        return $stmt ? $stmt->fetchColumn() : null;
    } catch (Throwable $e) { return null; }
}
function tableExistsPDO($pdo, $dbName, $table) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$dbName, $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}
function tableHasColumnPDO($pdo, $dbName, $table, $column) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$dbName, $table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}
function ensureAttendanceRecordsSchema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cadet_id INT NULL,
            cadet_name VARCHAR(255) NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            school_year VARCHAR(20) NOT NULL,
            semester VARCHAR(20) NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            recorded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            event_date DATE NULL,
            recorded_by INT DEFAULT 1,
            status ENUM('present','absent','late') DEFAULT 'present',
            notes TEXT,
            INDEX idx_cadet_id (cadet_id),
            INDEX idx_student_id (student_id),
            INDEX idx_event (event_name),
            INDEX idx_school_year_semester (school_year, semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Add event_date column if missing
        $pdo->exec("ALTER TABLE attendance_records ADD COLUMN event_date DATE NULL AFTER recorded_at");
    } catch (Throwable $e) {
        // ignore if already exists
    }
    try { $pdo->exec("CREATE UNIQUE INDEX uniq_attendance_per_day ON attendance_records (cadet_id, school_year, semester, event_name, event_date)"); } catch (Throwable $e) {}
}

// Minimal session persister (avoid including session.php to keep output clean)
if (!function_exists('createOrUpdateScannerSession')) {
    function createOrUpdateScannerSession($td, $semester) {
        if (!isset($_SESSION['scanner_session_id'])) {
            $_SESSION['scanner_session_id'] = bin2hex(random_bytes(16));
        }
        $_SESSION['td'] = $td;
        $_SESSION['semester'] = $semester;
        return $_SESSION['scanner_session_id'];
    }
}

// If DB connection failed, return JSON error
if (!isset($pdo) || !$pdo || !empty($db_connection_failed)) {
    http_response_code(500);
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Database connection error"
    ]);
    exit;
}

// Set headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *"); // In production, replace * with your domain
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the posted data (robust parsing, trim + BOM strip)
$raw = file_get_contents("php://input");
if (is_string($raw)) { $raw = trim($raw); }
if (substr($raw,0,3) === "\xEF\xBB\xBF") { $raw = substr($raw,3); }
$data = json_decode($raw);
if (!$data) {
    // Fallback to form-encoded POST
    if (!empty($_POST)) {
        $obj = new stdClass();
        foreach ($_POST as $k => $v) { $obj->$k = $v; }
        $data = $obj;
    }
}

// Check if data is complete
if (
    !empty($data->student_id) &&
    !empty($data->td) &&
    !empty($data->semester)
) {
    // Create or update the scanner session
    createOrUpdateScannerSession($data->td, $data->semester);
    
    try {
        // Resolve working path by schema
        $dbName = getCurrentDatabaseNamePDO($pdo);
        if (!$dbName && isset($database)) { $dbName = $database; }
        $legacyOK = $dbName && tableExistsPDO($pdo, $dbName, 'attendance') && tableHasColumnPDO($pdo, $dbName, 'attendance', 'td') && tableHasColumnPDO($pdo, $dbName, 'attendance', 'semester');
        
        // Check if student exists using cadet_profiles
        $student = getStudentDetails($data->student_id);
        if (!$student) {
            $response = array(
                "success" => false,
                "status" => "error",
                "message" => "Student ID {$data->student_id} not found in database. Please register the student first."
            );
            http_response_code(404);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($response);
            exit;
        }
        
        if ($legacyOK) {
            // Legacy flow using attendance table with td/semester
            if (alreadyMarkedToday($data->student_id, $data->td, $data->semester)) {
                $response = array(
                    "success" => true,
                    "status" => "info",
                    "message" => "Attendance already marked for today",
                    "data" => array(
                        "student_id" => $data->student_id,
                        "name" => $student['name'],
                        "td" => $data->td,
                        "semester" => $data->semester,
                        "timestamp" => date('Y-m-d H:i:s')
                    )
                );
                http_response_code(200);
            } else {
                if (insertAttendance($data->student_id, $data->td, $data->semester)) {
                    $response = array(
                        "success" => true,
                        "status" => "success",
                        "message" => "Attendance recorded successfully",
                        "data" => array(
                            "student_id" => $data->student_id,
                            "name" => $student['name'],
                            "td" => $data->td,
                            "semester" => $data->semester,
                            "timestamp" => date('Y-m-d H:i:s')
                        )
                    );
                    http_response_code(201);
                } else {
                    $response = array(
                        "success" => false,
                        "status" => "error",
                        "message" => "Failed to record attendance in database"
                    );
                    http_response_code(500);
                }
            }
        } else {
            // Unified flow using attendance_records
            ensureAttendanceRecordsSchema($pdo);
            $cadetProfileId = (int)$student['id'];
            $studentId = $student['student_id'];
            $cadetName = !empty($data->cadet_name) ? (string)$data->cadet_name : ($student['name'] ?? 'Unknown');
            $schoolYear = !empty($data->school_year) ? (string)$data->school_year : (date('n') >= 8 ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y'));
            $semester = (string)$data->semester;
            $eventName = !empty($data->event_name) ? (string)$data->event_name : ((string)$data->td . 'TD');
            $timestamp = !empty($data->timestamp) ? date('Y-m-d H:i:s', strtotime($data->timestamp)) : date('Y-m-d H:i:s');
            $eventDate = date('Y-m-d', strtotime($timestamp));
            
            // Duplicate check
            $dup = $pdo->prepare("SELECT id FROM attendance_records WHERE cadet_id = ? AND school_year = ? AND semester = ? AND event_name = ? AND event_date = ? LIMIT 1");
            $dup->execute([$cadetProfileId, $schoolYear, $semester, $eventName, $eventDate]);
            if ($dup->fetchColumn()) {
                $response = array(
                    "success" => true,
                    "status" => "info",
                    "message" => "Attendance already recorded for today",
                    "data" => array(
                        "student_id" => $studentId,
                        "name" => $cadetName,
                        "event_name" => $eventName,
                        "semester" => $semester,
                        "school_year" => $schoolYear,
                        "timestamp" => $timestamp
                    )
                );
                http_response_code(200);
            } else {
                $ins = $pdo->prepare("INSERT INTO attendance_records (cadet_id, cadet_name, student_id, school_year, semester, event_name, recorded_at, event_date, status) VALUES (?,?,?,?,?,?,?,?, 'present')");
                $ins->execute([$cadetProfileId, $cadetName, $studentId, $schoolYear, $semester, $eventName, $timestamp, $eventDate]);
                $response = array(
                    "success" => true,
                    "status" => "success",
                    "message" => "Attendance recorded successfully",
                    "data" => array(
                        "student_id" => $studentId,
                        "name" => $cadetName,
                        "event_name" => $eventName,
                        "semester" => $semester,
                        "school_year" => $schoolYear,
                        "timestamp" => $timestamp
                    )
                );
                http_response_code(201);
            }
        }
        
        // Optional fields kept empty to avoid heavy includes
        $response["stats"] = [];
        $response["recent_records"] = [];
        
        // Send the response (clean buffer first)
        if (ob_get_length()) { ob_clean(); }
        echo json_encode($response);
        
    } catch (PDOException $e) {
        // Database error
        http_response_code(500);
        if (ob_get_length()) { ob_clean(); }
        echo json_encode(array(
            "success" => false,
            "status" => "error", 
            "message" => "Database error: " . $e->getMessage()
        ));
        exit;
    }
} else {
    // Set response code - 400 Bad Request
    http_response_code(400);
    
    // Tell the user that the data is incomplete
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(array(
        "success" => false,
        "status" => "error", 
        "message" => "Unable to record attendance. Data is incomplete."
    ));
    exit;
}
?>