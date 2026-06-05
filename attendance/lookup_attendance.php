<?php
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', '../logs/lookup_attendance_errors.log');

// Start output buffering to prevent any unwanted output
ob_start();

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Log the start of the request
error_log("=== LOOKUP ATTENDANCE REQUEST START === " . date('Y-m-d H:i:s'));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
try {
    check_login();
    error_log("User login check passed");
} catch (Exception $e) {
    error_log("Login check failed: " . $e->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Login check failed']);
    exit;
}

// Access control: Admin and basic users
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'basic'])) {
    error_log("Access denied - User role: " . ($_SESSION['role'] ?? 'not set') . ", Logged in: " . ($_SESSION['loggedin'] ?? 'not set'));
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

error_log("Access control passed - User role: " . $_SESSION['role']);

// Clean any output buffer before sending JSON
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['action']) || $_POST['action'] !== 'lookup') {
    error_log("Invalid action: " . ($_POST['action'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

error_log("Request validation passed - Action: lookup");

try {
    $searchName = isset($_POST['search_name']) ? trim($_POST['search_name']) : '';
    $searchStudentId = isset($_POST['search_student_id']) ? trim($_POST['search_student_id']) : '';
    $singleDate = isset($_POST['date']) ? trim($_POST['date']) : '';
    $dateFrom = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
    $dateTo = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
    // Time filters deprecated for lookup UI; kept for backward-compat but ignored
    $timeFrom = '';
    $timeTo = '';
    $tdFilter = isset($_POST['td']) ? trim($_POST['td']) : '';
    $semFilter = isset($_POST['semester']) ? trim($_POST['semester']) : '';
    $statusFilter = isset($_POST['status']) ? trim($_POST['status']) : '';
    $platoonFilter = isset($_POST['platoon']) ? trim($_POST['platoon']) : '';
    $genderFilter = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $exactName = isset($_POST['exact_name']) && $_POST['exact_name'] == '1';
    $exactId = isset($_POST['exact_id']) && $_POST['exact_id'] == '1';

    error_log("Search parameters - Name: '$searchName', Student ID: '$searchStudentId', Dates: '$dateFrom'..'$dateTo', Times: '$timeFrom'..'$timeTo', TD: '$tdFilter', Sem: '$semFilter', Status: '$statusFilter'");

    $globalMode = (empty($searchName) && empty($searchStudentId));
    if ($globalMode && empty($singleDate) && empty($dateFrom) && empty($dateTo) && empty($tdFilter) && empty($semFilter) && empty($statusFilter) && empty($platoonFilter) && empty($genderFilter)) {
        error_log("No search or filter criteria provided");
        echo json_encode(['success' => false, 'message' => 'Please provide at least one filter or a name/student ID']);
        exit;
    }

    $cadet = null;
    $cadetId = null;

    if (!$globalMode) {
        // Build the search query for cadet_profiles
        $whereConditions = [];
        $params = [];
        
        if (!empty($searchName)) {
            if ($exactName) {
                // Exact match on full name (first + last), case-insensitive
                $whereConditions[] = "LOWER(CONCAT(cp.first_name, ' ', cp.last_name)) = LOWER(?)";
                $params[] = $searchName;
            } else {
                $whereConditions[] = "(cp.first_name LIKE ? OR cp.last_name LIKE ? OR CONCAT(cp.first_name, ' ', cp.last_name) LIKE ?)";
                $searchPattern = '%' . $searchName . '%';
                $params[] = $searchPattern;
                $params[] = $searchPattern;
                $params[] = $searchPattern;
            }
        }
        
        if (!empty($searchStudentId)) {
            if ($exactId) {
                $whereConditions[] = "cp.student_id = ?";
                $params[] = $searchStudentId;
            } else {
                $whereConditions[] = "cp.student_id LIKE ?";
                $params[] = '%' . $searchStudentId . '%';
            }
        }
        
        $whereClause = implode(' OR ', $whereConditions);
        
        // First, find the cadet
        $cadetQuery = "SELECT cp.id as cadet_id, cp.first_name, cp.last_name, cp.student_id, 
                              CONCAT(cp.first_name, ' ', cp.last_name) AS full_name
                       FROM cadet_profiles cp 
                       WHERE $whereClause
                       LIMIT 1";
        
        error_log("Cadet query: " . $cadetQuery);
        error_log("Query parameters: " . print_r($params, true));
        
        $stmt = $pdo->prepare($cadetQuery);
        $stmt->execute($params);
        $cadet = $stmt->fetch(PDO::FETCH_ASSOC);
        $cadetId = $cadet['cadet_id'] ?? null;
        
        error_log("Cadet query result: " . print_r($cadet, true));
        
        if (!$cadet) {
            error_log("No cadet found with search criteria");
            echo json_encode(['success' => false, 'message' => 'No cadet found with the provided search criteria']);
            exit;
        }

        error_log("Cadet found: " . $cadet['full_name'] . " (ID: " . $cadet['cadet_id'] . ")");
        
        // For basic users, ensure they can only view their own records
        if ($_SESSION['role'] === 'basic') {
            error_log("Basic user access control check for user_id: " . $_SESSION['user_id']);
            
            // Map logged-in user to their cadet_profiles.id
            $userQuery = "SELECT cp.id FROM cadet_profiles cp WHERE cp.user_id = ?";
            $userStmt = $pdo->prepare($userQuery);
            $userStmt->execute([$_SESSION['user_id']]);
            $userCadetId = $userStmt->fetchColumn();
            
            error_log("User's cadet_profile_id: " . $userCadetId . ", Requested cadet_id: " . $cadet['cadet_id']);
            
            if ($userCadetId != $cadet['cadet_id']) {
                error_log("Access denied - User trying to view other cadet's records");
                echo json_encode(['success' => false, 'message' => 'You can only view your own attendance records']);
                exit;
            }
            
            error_log("Basic user access control passed");
        }
    } else {
        // Global mode: restrict basic users to their own records only
        if ($_SESSION['role'] === 'basic') {
            $userQuery = "SELECT cp.id FROM cadet_profiles cp WHERE cp.user_id = ?";
            $userStmt = $pdo->prepare($userQuery);
            $userStmt->execute([$_SESSION['user_id']]);
            $cadetId = $userStmt->fetchColumn();
        }
    }

    // Build attendance_records query with optional filters
    $where = [];
    $binds = [];
    if (!empty($cadetId)) {
        $where[] = "(ar.cadet_id = ? OR ar.student_id = (SELECT student_id FROM cadet_profiles WHERE id = ? LIMIT 1))";
        $binds[] = $cadetId;
        $binds[] = $cadetId;
    }
    // Date filters
    $dateExpr = "DATE(COALESCE(ar.event_date, ar.recorded_at))";
    if ($singleDate !== '') {
        $where[] = "$dateExpr = ?";
        $binds[] = $singleDate;
    } else {
        if ($dateFrom !== '') { $where[] = "$dateExpr >= ?"; $binds[] = $dateFrom; }
        if ($dateTo !== '') { $where[] = "$dateExpr <= ?"; $binds[] = $dateTo; }
    }
    // TD filter via event_name heuristic
    if ($tdFilter !== '') {
        $where[] = "(ar.event_name LIKE ? OR ar.event_name LIKE ?)";
        $binds[] = "%TD " . $tdFilter . "%";
        $binds[] = "%" . $tdFilter . "TD%";
    }
    // Semester filter with variants
    if ($semFilter !== '') {
        $s = strtolower(trim($semFilter));
        if ($s === '1' || $s === '1st') { $semVars = ['1','1st','1st Semester','First Semester','first semester']; }
        elseif ($s === '2' || $s === '2nd') { $semVars = ['2','2nd','2nd Semester','Second Semester','second semester']; }
        else { $semVars = [$semFilter]; }
        $where[] = 'ar.semester IN (' . implode(',', array_fill(0, count($semVars), '?')) . ')';
        foreach ($semVars as $sv) { $binds[] = $sv; }
    }
    // Status filter
    if ($statusFilter !== '') {
        $where[] = 'LOWER(COALESCE(ar.status,\'present\')) = ?';
        $binds[] = strtolower($statusFilter);
    }
    // Platoon filter (from cadet profile)
    if ($platoonFilter !== '') {
        $where[] = 'cp.platoon = ?';
        $binds[] = $platoonFilter;
    }
    // Gender filter (from cadet profile)
    if ($genderFilter !== '') {
        $where[] = 'cp.gender = ?';
        $binds[] = $genderFilter;
    }

    $whereSql = empty($where) ? '1=1' : implode(' AND ', $where);

    $attendanceQuery = "
        SELECT 
            ar.id,
            ar.event_name,
            ar.recorded_at,
            ar.event_date,
            ar.semester,
            COALESCE(ar.status,'present') AS status,
            COALESCE(ar.cadet_name, CONCAT(cp.first_name, ' ', COALESCE(cp.middle_name,''), ' ', cp.last_name)) AS cadet_name
        FROM attendance_records ar
        LEFT JOIN cadet_profiles cp ON (cp.id = ar.cadet_id OR cp.student_id = ar.student_id)
        WHERE $whereSql
        ORDER BY COALESCE(ar.recorded_at, ar.event_date, NOW()) DESC
        LIMIT 1000
    ";

    error_log("Attendance query (attendance_records, filtered): " . $attendanceQuery);
    error_log("Attendance binds: " . print_r($binds, true));
    $stmt = $pdo->prepare($attendanceQuery);
    $stmt->execute($binds);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($records) . " attendance records");
    
    // Format the records for display
    $formattedRecords = [];
    foreach ($records as $record) {
        // Parse TD from event_name if it matches like "1TD"
        $tdValue = '';
        if (!empty($record['event_name']) && preg_match('/(\d+)\s*TD/i', $record['event_name'], $m)) {
            $tdValue = $m[1];
        }
        $formattedRecords[] = [
            'id' => $record['id'],
            'date' => date('Y-m-d', strtotime($record['event_date'] ?: $record['recorded_at'])),
            'event_name' => $record['event_name'],
            'time' => $record['recorded_at'] ? date('H:i:s', strtotime($record['recorded_at'])) : '',
            'td' => $tdValue,
            'semester' => $record['semester'],
            'status' => $record['status'],
            'cadet_name' => $record['cadet_name']
        ];
    }
    
    $response = [
        'success' => true,
        'records' => $formattedRecords,
        'total_records' => count($formattedRecords)
    ];
    if ($cadet) {
        $response['cadet_info'] = [
            'cadet_id' => $cadet['cadet_id'],
            'full_name' => $cadet['full_name'],
            'student_id' => $cadet['student_id']
        ];
    } else {
        // Echo back filters for UI
        $response['filters'] = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'td' => $tdFilter,
            'semester' => $semFilter,
            'status' => $statusFilter
        ];
    }
    
    error_log("Sending successful response with " . count($formattedRecords) . " records");
    error_log("Response data: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("=== LOOKUP ATTENDANCE ERROR ===");
    error_log("Exception message: " . $e->getMessage());
    error_log("Exception file: " . $e->getFile());
    error_log("Exception line: " . $e->getLine());
    error_log("Exception trace: " . $e->getTraceAsString());
    error_log("=== END ERROR ===");
    
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}

error_log("=== LOOKUP ATTENDANCE REQUEST END === " . date('Y-m-d H:i:s'));
?>