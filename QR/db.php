<?php
// Database configuration - Integrated with ROTC CMS
// Defaults (XAMPP typical)
$host = 'localhost:3306';
$username = 'root';
$password = '';
$database = 'rotc_db';

// Optional overrides via environment variables
$host = getenv('ROTC_DB_SERVER') ?: $host;
$username = getenv('ROTC_DB_USER') ?: $username;
$password = getenv('ROTC_DB_PASS') ?: $password;
$database = getenv('ROTC_DB_NAME') ?: $database;

// Optional overrides via local config files
$cfgLocal = __DIR__ . '/db_config.php';
$cfgIncludes = __DIR__ . '/../includes/db_config.php';
foreach ([$cfgLocal, $cfgIncludes] as $cfg) {
    if (file_exists($cfg)) {
        // The config file may define $DB_SERVER, $DB_USERNAME, $DB_PASSWORD, $DB_NAME
        // or constants DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME
        try { include $cfg; } catch (Throwable $e) { error_log('Failed to include db_config.php: ' . $e->getMessage()); }
        if (isset($DB_SERVER)) { $host = $DB_SERVER; }
        if (isset($DB_USERNAME)) { $username = $DB_USERNAME; }
        if (isset($DB_PASSWORD)) { $password = $DB_PASSWORD; }
        if (isset($DB_NAME)) { $database = $DB_NAME; }
        if (defined('DB_SERVER')) { $host = DB_SERVER; }
        if (defined('DB_USERNAME')) { $username = DB_USERNAME; }
        if (defined('DB_PASSWORD')) { $password = DB_PASSWORD; }
        if (defined('DB_NAME')) { $database = DB_NAME; }
        break;
    }
}

// Create connection
try {
    // Parse host:port into DSN components
    $dsnHost = $host;
    $dsnPort = null;
    if (strpos($host, ':') !== false) {
        list($hostOnly, $portPart) = explode(':', $host, 2);
        if ($hostOnly !== '') { $dsnHost = $hostOnly; }
        if ($portPart !== '') { $dsnPort = $portPart; }
    }
    $dsn = "mysql:host={$dsnHost};dbname={$database};charset=utf8mb4";
    if ($dsnPort) { $dsn = "mysql:host={$dsnHost};port={$dsnPort};dbname={$database};charset=utf8mb4"; }
    $pdo = new PDO($dsn, $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // For backward compatibility
    $conn = $pdo;
} catch(PDOException $e) {
    // Log error instead of echoing to avoid interfering with JSON responses
    error_log("Database connection failed: " . $e->getMessage());
    // Set a flag to indicate connection failure
    $db_connection_failed = true;
}

// Fallback attempt: try empty password if initial connection failed
if (!empty($db_connection_failed)) {
    try {
        $altPassword = '';
        $dsn = "mysql:host={$dsnHost};dbname={$database};charset=utf8mb4";
        if ($dsnPort) { $dsn = "mysql:host={$dsnHost};port={$dsnPort};dbname={$database};charset=utf8mb4"; }
        $pdo = new PDO($dsn, $username, $altPassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn = $pdo;
        unset($db_connection_failed);
        error_log('QR/db.php: DB connection succeeded with empty password fallback.');
    } catch (PDOException $e2) {
        // Keep failure flag; report in caller as JSON
        error_log("QR/db.php fallback connection failed: " . $e2->getMessage());
    }
}

// Function to check if student attendance is already marked for this TD/semester
function alreadyMarkedToday($student_id, $td, $semester) {
    global $pdo;
    
    // Get cadet_id from student_id
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $cadet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cadet) {
        return false; // Student not found
    }
    
    // Check if already marked for this TD/semester for TODAY only (avoid historical false positives)
    // Handle possible schemas: prefer log_date, else fallback to created_at/timestamp
    $sql = "
        SELECT 1 
        FROM attendance 
        WHERE cadet_id = ? 
          AND td = ? 
          AND semester = ? 
          AND (
                log_date = CURDATE()
             OR DATE(created_at) = CURDATE()
             OR DATE(`timestamp`) = CURDATE()
          )
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cadet['id'], $td, $semester]);
    return (bool)$stmt->fetchColumn();
}

// Function to insert attendance record
function insertAttendance($student_id, $td, $semester) {
    global $pdo;
    
    // Get cadet_id from student_id
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $cadet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cadet) {
        return false; // Student not found
    }
    
    $stmt = $pdo->prepare("INSERT INTO attendance (cadet_id, student_id, td, semester, log_date, log_time, status, timestamp) VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), 'Present', NOW())");
    return $stmt->execute([$cadet['id'], $student_id, $td, $semester]);
}

// Function to get student details
function getStudentDetails($student_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, student_id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) as name, NOW() as created_at FROM cadet_profiles WHERE student_id = ?");
    $stmt->execute([$student_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get all attendance records for a specific TD/semester
function getAttendanceForTdSemester($td, $semester) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.cadet_id,
            a.student_id,
            CONCAT(cp.first_name, ' ', IFNULL(cp.middle_name, ''), ' ', cp.last_name) as cadet_name,
            a.td,
            a.semester,
            a.log_date,
            a.log_time,
            a.status,
            a.created_at,
            a.recorded_by
        FROM attendance a
        JOIN cadet_profiles cp ON a.cadet_id = cp.id
        WHERE a.td = ? AND a.semester = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$td, $semester]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get attendance statistics for a TD/semester
function getAttendanceStatsForTdSemester($td, $semester) {
    global $pdo;
    
    // Get total cadets
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cadet_profiles WHERE status = 'active'");
    $stmt->execute();
    $totalCadets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get present count for this TD/semester
    $stmt = $pdo->prepare("SELECT COUNT(*) as present FROM attendance WHERE td = ? AND semester = ? AND status = 'Present'");
    $stmt->execute([$td, $semester]);
    $presentCount = $stmt->fetch(PDO::FETCH_ASSOC)['present'];
    
    // Get absent count
    $absentCount = $totalCadets - $presentCount;
    
    return [
        'total' => $totalCadets,
        'present' => $presentCount,
        'absent' => $absentCount,
        'attendance_rate' => $totalCadets > 0 ? round(($presentCount / $totalCadets) * 100, 1) : 0
    ];
}
?>
