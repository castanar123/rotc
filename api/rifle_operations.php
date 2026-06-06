<?php
/**
 * Resolve cadet details by profile_id or student_id (cadet_id)
 */
function resolveCadetAPI($input) {
    global $link;
    
    if ((!isset($input['profile_id']) || $input['profile_id'] === '') && (!isset($input['cadet_id']) || $input['cadet_id'] === '')) {
        return ['success' => false, 'message' => 'Cadet identifier is required'];
    }
 
    
    $row = null;
    if (isset($input['profile_id']) && $input['profile_id'] !== '') {
        $pid = (int)$input['profile_id'];
        $q = "SELECT id, student_id, first_name, middle_name, last_name, course, platoon, section FROM cadet_profiles WHERE id = '$pid' LIMIT 1";
        $res = mysqli_query($link, $q);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        }
    }
    if ($row === null && isset($input['cadet_id']) && $input['cadet_id'] !== '') {
        $sid = mysqli_real_escape_string($link, $input['cadet_id']);
        $q = "SELECT id, student_id, first_name, middle_name, last_name, course, platoon, section FROM cadet_profiles WHERE student_id = '$sid' LIMIT 1";
        $res = mysqli_query($link, $q);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        }
    }
    
    if ($row === null) {
        return ['success' => false, 'message' => 'Cadet not found'];
    }
    
    $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ? ($row['middle_name'] . ' ') : '') . ($row['last_name'] ?? ''));
    $platoon = $row['platoon'] ?? null;
    if (!$platoon && !empty($row['section'])) {
        $platoon = $row['section'];
    }
    
    return [
        'success' => true,
        'cadet' => [
            'profile_id' => (int)$row['id'],
            'student_id' => $row['student_id'],
            'first_name' => $row['first_name'] ?? '',
            'middle_name' => $row['middle_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'full_name' => $full_name !== '' ? $full_name : null,
            'course' => $row['course'] ?? null,
            'platoon' => $platoon ?? null,
        ]
    ];
}
// rifle_operations.php - API endpoints for rifle management operations

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Include database connection and rifle functions
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/rifle_functions.php';
require_once '../includes/SecurityLogger.php';

// Initialize SecurityLogger
$securityLogger = new SecurityLogger();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $securityLogger->logSecurityEvent(null, 'UNAUTHORIZED_ACCESS', 'Unauthorized API access to rifle operations', [], 'high');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Log API access
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'API_ACCESS', 'User accessed rifle operations API', [], 'low');

// Get the request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $input['action'];
$response = ['success' => false, 'message' => 'Unknown action'];

try {
    // Log the specific action being performed
    $securityLogger->logSecurityEvent($_SESSION['user_id'], 'API_OPERATION', "Rifle API action: {$action}", [], 'medium');
    
    switch ($action) {
        case 'check_rifle_status':
            $response = checkRifleStatus($input);
            break;
            
        case 'assign_rifle':
            $response = assignRifleOperation($input);
            break;
            
        case 'return_rifle':
            $response = returnRifleOperation($input);
            break;
            
        case 'get_cadet_rifle':
            $response = getCadetRifleAssignment($input);
            break;
            
        case 'get_recent_activities':
            $response = getRecentActivities($input);
            break;
            
        case 'get_rifle_statistics':
            $response = getRifleStatisticsAPI();
            break;
            
        case 'get_current_assignments':
            $response = getCurrentAssignmentsAPI();
            break;
            
        case 'resolve_cadet':
            $response = resolveCadetAPI($input);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    error_log('Rifle Operations API Error: ' . $e->getMessage());
}

echo json_encode($response);

/**
 * Determine the cadet foreign key column name in rifle_assignments table
 * Supports schemas that use either 'cadet_id' or legacy 'borrower_id'.
 */
function getAssignmentsCadetColumn() {
    static $cached = null;
    if ($cached !== null) return $cached;
    global $link;
    // Prefer explicit cadet_profile_id if present, then cadet_id, then legacy borrower_id
    $check0 = mysqli_query($link, "SHOW COLUMNS FROM rifle_assignments LIKE 'cadet_profile_id'");
    if ($check0 && mysqli_num_rows($check0) > 0) { $cached = 'cadet_profile_id'; return $cached; }
    $check1 = mysqli_query($link, "SHOW COLUMNS FROM rifle_assignments LIKE 'cadet_id'");
    if ($check1 && mysqli_num_rows($check1) > 0) { $cached = 'cadet_id'; return $cached; }
    $check2 = mysqli_query($link, "SHOW COLUMNS FROM rifle_assignments LIKE 'borrower_id'");
    if ($check2 && mysqli_num_rows($check2) > 0) { $cached = 'borrower_id'; return $cached; }
    // Default fallback to new schema
    $cached = 'cadet_profile_id';
    return $cached;
}

/**
 * Check if a column exists in a table (MySQL)
 */
function columnExists($table, $column) {
    global $link;
    $col = mysqli_real_escape_string($link, $column);
    $tbl = mysqli_real_escape_string($link, $table);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$tbl` LIKE '$col'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Check if a table exists in the current database
 */
function tableExists($table) {
    global $link;
    $tbl = mysqli_real_escape_string($link, $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '$tbl'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Ensure there is a borrowers row corresponding to the given cadet profile.
 * Returns a valid borrowers.id, creating one if needed.
 */
function ensureBorrowerForCadet($cadet_profile_id) {
    global $link;
    if (!tableExists('borrowers')) return null;
    $pid = (int)$cadet_profile_id;
    if ($pid <= 0) return null;
    // Build deterministic keys for mapping
    $temp_id = 'CADET_PROFILE_' . $pid; // preferred if borrowers.temp_id exists
    $deterministic_name = 'CADET_PROFILE_' . $pid; // fallback if no temp_id
    $hasTempId = columnExists('borrowers', 'temp_id');
    $hasName   = columnExists('borrowers', 'name');
    $hasCourse = columnExists('borrowers', 'course');
    $hasStatus = columnExists('borrowers', 'status');

    // Try to find existing borrower by preferred column
    if ($hasTempId) {
        $esc_temp = mysqli_real_escape_string($link, $temp_id);
        $res = mysqli_query($link, "SELECT id FROM borrowers WHERE temp_id = '$esc_temp' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            return (int)$row['id'];
        }
    } elseif ($hasName) {
        $esc_name = mysqli_real_escape_string($link, $deterministic_name);
        $res = mysqli_query($link, "SELECT id FROM borrowers WHERE name = '$esc_name' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            return (int)$row['id'];
        }
    }
    // Fetch cadet info to populate borrower record
    $cadet = null;
    $cres = mysqli_query($link, "SELECT student_id, first_name, middle_name, last_name, course FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
    if ($cres && mysqli_num_rows($cres) > 0) {
        $cadet = mysqli_fetch_assoc($cres);
    }
    $full_name = 'Unknown Cadet';
    $course = '';
    if ($cadet) {
        $fn = $cadet['first_name'] ?? '';
        $mn = $cadet['middle_name'] ?? '';
        $ln = $cadet['last_name'] ?? '';
        $full_name = trim($fn . ' ' . ($mn ? ($mn . ' ') : '') . $ln);
        if ($full_name === '') $full_name = 'Unknown Cadet';
        $course = $cadet['course'] ?? '';
    }
    $esc_nameReal = mysqli_real_escape_string($link, $full_name);
    $esc_course   = mysqli_real_escape_string($link, (string)$course);
    $esc_temp     = mysqli_real_escape_string($link, $temp_id);
    $esc_detName  = mysqli_real_escape_string($link, $deterministic_name);

    // Build dynamic insert according to available columns
    $cols = [];
    $vals = [];
    if ($hasTempId) { $cols[] = 'temp_id'; $vals[] = "'$esc_temp'"; }
    if ($hasName)   { $cols[] = 'name';    $vals[] = "'$esc_detName'"; }
    if ($hasCourse) { $cols[] = 'course';  $vals[] = "'$esc_course'"; }
    if ($hasStatus) { $cols[] = 'status';  $vals[] = "'active'"; }
    // If no recognized columns to insert, abort
    if (empty($cols)) return null;

    $ins = mysqli_query($link, "INSERT INTO borrowers (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
    if ($ins) {
        return (int)mysqli_insert_id($link);
    }
    // As a fallback, try to select again in case of race condition
    if ($hasTempId) {
        $res2 = mysqli_query($link, "SELECT id FROM borrowers WHERE temp_id = '$esc_temp' LIMIT 1");
        if ($res2 && mysqli_num_rows($res2) > 0) {
            $row = mysqli_fetch_assoc($res2);
            return (int)$row['id'];
        }
    } elseif ($hasName) {
        $res2 = mysqli_query($link, "SELECT id FROM borrowers WHERE name = '$esc_detName' LIMIT 1");
        if ($res2 && mysqli_num_rows($res2) > 0) {
            $row = mysqli_fetch_assoc($res2);
            return (int)$row['id'];
        }
    }
    return null;
}

/**
 * Determine the cadet foreign key column name in rifle_logs table
 * Supports schemas that use 'cadet_id' or 'cadet_profile_id' (and legacy 'borrower_id').
 */
function getLogsCadetColumn() {
    static $cached = null;
    if ($cached !== null) return $cached;
    global $link;
    // Prefer new schema first, then legacy borrower_id, then oldest cadet_id
    $checkProfile = mysqli_query($link, "SHOW COLUMNS FROM rifle_logs LIKE 'cadet_profile_id'");
    if ($checkProfile && mysqli_num_rows($checkProfile) > 0) { $cached = 'cadet_profile_id'; return $cached; }
    $checkBorrower = mysqli_query($link, "SHOW COLUMNS FROM rifle_logs LIKE 'borrower_id'");
    if ($checkBorrower && mysqli_num_rows($checkBorrower) > 0) { $cached = 'borrower_id'; return $cached; }
    $checkStudent = mysqli_query($link, "SHOW COLUMNS FROM rifle_logs LIKE 'cadet_id'");
    if ($checkStudent && mysqli_num_rows($checkStudent) > 0) { $cached = 'cadet_id'; return $cached; }
    // Default fallback to new schema
    $cached = 'cadet_profile_id';
    return $cached;
}

/**
 * Resolve rifle input (id or serial/rifle_number) to rifles.id
 */
function resolveRifleIdFromInput($rifleInput) {
    global $link;
    if (!isset($rifleInput)) return null;
    $val = trim((string)$rifleInput);
    if ($val === '') return null;
    // If purely numeric, verify it exists as an id first
    if (ctype_digit($val)) {
        $id = (int)$val;
        $res = mysqli_query($link, "SELECT id FROM rifles WHERE id = '$id' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            return (int)$id;
        }
    }
    // Try known identifier columns
    $candidates = [];
    if (columnExists('rifles', 'serial_number')) { $candidates[] = 'serial_number'; }
    if (columnExists('rifles', 'rifle_number')) { $candidates[] = 'rifle_number'; }
    if (columnExists('rifles', 'serial')) { $candidates[] = 'serial'; }
    if (columnExists('rifles', 'number')) { $candidates[] = 'number'; }
    foreach ($candidates as $col) {
        $esc = mysqli_real_escape_string($link, $val);
        $q = "SELECT id FROM rifles WHERE `$col` = '$esc' LIMIT 1";
        $res = mysqli_query($link, $q);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if (isset($row['id'])) return (int)$row['id'];
        }
    }
    return null;
}

/**
 * Check rifle status
 */
function checkRifleStatus($input) {
    global $link;
    
    if (!isset($input['rifle_id'])) {
        return ['success' => false, 'message' => 'Rifle ID is required'];
    }
    // Resolve input (numeric id or serial/rifle_number) to rifles.id
    $resolvedId = resolveRifleIdFromInput($input['rifle_id']);
    if ($resolvedId === null) {
        return ['success' => false, 'message' => 'Rifle not found'];
    }
    $rifle_id = mysqli_real_escape_string($link, (string)$resolvedId);
    
    $query = "SELECT * FROM rifles WHERE id = '$rifle_id'";
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($link)];
    }
    
    $rifle = mysqli_fetch_assoc($result);
    
    if (!$rifle) {
        return ['success' => false, 'message' => 'Rifle not found'];
    }
    
    // Add rifle_id field for compatibility with frontend
    $rifle['rifle_id'] = $rifle['id'];
    
    return [
        'success' => true,
        'rifle' => $rifle
    ];
}

/**
 * Assign rifle to cadet
 */
function assignRifleOperation($input) {
    global $link;
    $securityLogger = new SecurityLogger();
    
    if (!isset($input['rifle_id']) || ($input['rifle_id'] === '')) {
        return ['success' => false, 'message' => 'Rifle ID is required'];
    }
    if (!isset($input['cadet_id']) && !isset($input['profile_id'])) {
        return ['success' => false, 'message' => 'Cadet identifier is required'];
    }
    
    // Resolve rifle id (supports numeric id, serial_number, rifle_number)
    $resolvedId = resolveRifleIdFromInput($input['rifle_id']);
    if ($resolvedId === null) {
        return ['success' => false, 'message' => 'Rifle not found'];
    }
    $rifle_id = mysqli_real_escape_string($link, (string)$resolvedId);
    $cadet_profile_id = null;
    $student_id = null;

    // Resolve cadet by profile_id first, else by cadet_id (student_id or numeric cadet_profiles.id)
    if (isset($input['profile_id']) && $input['profile_id'] !== '') {
        $pid = (int)$input['profile_id'];
        $res = mysqli_query($link, "SELECT id, student_id FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $cadet_profile_id = $row['id'];
            $student_id = $row['student_id'];
        }
    }
    if ($cadet_profile_id === null && isset($input['cadet_id'])) {
        $cadet_id_raw = $input['cadet_id'];
        // If numeric, it might be a profile ID
        if (ctype_digit((string)$cadet_id_raw)) {
            $pid = (int)$cadet_id_raw;
            $res = mysqli_query($link, "SELECT id, student_id FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $cadet_profile_id = $row['id'];
                $student_id = $row['student_id'];
            }
        }
        // Otherwise, or if not found, treat as student_id
        if ($cadet_profile_id === null) {
            $sid = mysqli_real_escape_string($link, $cadet_id_raw);
            $res = mysqli_query($link, "SELECT id, student_id FROM cadet_profiles WHERE student_id = '$sid' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $cadet_profile_id = $row['id'];
                $student_id = $row['student_id'];
            }
        }
    }

    if ($cadet_profile_id === null) {
        return ['success' => false, 'message' => 'Cadet not found in system'];
    }
    
    // Check if rifle exists and is available
    $rifle_query = "SELECT * FROM rifles WHERE id = '$rifle_id' AND status = 'available'";
    $rifle_result = mysqli_query($link, $rifle_query);
    if (!$rifle_result || mysqli_num_rows($rifle_result) == 0) {
        return ['success' => false, 'message' => 'Rifle not available for assignment'];
    }

    // Prevent duplicate active assignments: check all present cadet columns
    $whereParts = [];
    foreach (['cadet_profile_id', 'cadet_id', 'borrower_id'] as $colName) {
        if (columnExists('rifle_assignments', $colName)) {
            if ($colName === 'borrower_id') {
                $bid = ensureBorrowerForCadet($cadet_profile_id);
                if ($bid !== null) { $whereParts[] = "$colName = '" . (int)$bid . "'"; }
            } else {
                $whereParts[] = "$colName = '" . (int)$cadet_profile_id . "'";
            }
        }
    }
    if (empty($whereParts)) {
        $cadetCol = getAssignmentsCadetColumn();
        if ($cadetCol === 'borrower_id') {
            $bid = ensureBorrowerForCadet($cadet_profile_id);
            if ($bid === null) { return ['success' => false, 'message' => 'Unable to resolve borrower mapping']; }
            $whereParts[] = "$cadetCol = '" . (int)$bid . "'";
        } else {
            $whereParts[] = "$cadetCol = '" . (int)$cadet_profile_id . "'";
        }
    }
    $existing_query = "SELECT * FROM rifle_assignments WHERE (" . implode(' OR ', $whereParts) . ") AND status IN ('active','assigned')";
    $existing_result = mysqli_query($link, $existing_query);
    if ($existing_result && mysqli_num_rows($existing_result) > 0) {
        return ['success' => false, 'message' => 'Cadet already has a rifle assigned'];
    }

    // Start transaction
    mysqli_begin_transaction($link);

    try {
        // Update rifle status to assigned
        $update_rifle = "UPDATE rifles SET status = 'assigned' WHERE id = '$rifle_id'";
        if (!mysqli_query($link, $update_rifle)) {
            throw new Exception('Failed to update rifle status');
        }

        // Create assignment record: populate all present cadet columns to satisfy NOT NULL/FKs
        $assigned_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
        $columns = ["rifle_id"];
        $values  = ["'$rifle_id'"];
        if (columnExists('rifle_assignments', 'cadet_profile_id')) { $columns[] = 'cadet_profile_id'; $values[] = "'" . (int)$cadet_profile_id . "'"; }
        if (columnExists('rifle_assignments', 'cadet_id')) { $columns[] = 'cadet_id'; $values[] = "'" . (int)$cadet_profile_id . "'"; }
        if (columnExists('rifle_assignments', 'borrower_id')) {
            $borrower_id = ensureBorrowerForCadet($cadet_profile_id);
            if ($borrower_id === null) {
                // If FK enforced but mapping failed, abort gracefully
                throw new Exception('Failed to resolve borrower mapping');
            }
            $columns[] = 'borrower_id';
            $values[] = "'" . (int)$borrower_id . "'";
        }
        $columns[] = 'assigned_by';
        $values[]  = "'" . (int)$assigned_by . "'";
        $columns[] = 'assigned_at';
        $values[]  = 'NOW()';
        $columns[] = 'status';
        $values[]  = "'active'";
        $assignment_query = "INSERT INTO rifle_assignments (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
        if (!mysqli_query($link, $assignment_query)) {
            throw new Exception('Failed to create assignment record');
        }

        // Log the action (use cadet_profiles.id for foreign key)
        $logsCadetCol = getLogsCadetColumn();
        $log_query = "INSERT INTO rifle_logs (rifle_id, $logsCadetCol, action, performed_by, timestamp, details) VALUES ('$rifle_id', '$cadet_profile_id', 'assigned', '$assigned_by', NOW(), 'Rifle assigned via QR scanner')";
        mysqli_query($link, $log_query);
        
        // Commit transaction
        mysqli_commit($link);
        
        // Log successful rifle assignment
        $securityLogger->logSecurityEvent($_SESSION['user_id'], 'DATA_MODIFICATION', "Rifle {$rifle_id} assigned to cadet profile {$cadet_profile_id}", [], 'medium');
        
        return [
            'success' => true,
            'message' => 'Rifle assigned successfully',
            'assignment' => [
                'rifle_id' => $rifle_id,
                'cadet_profile_id' => $cadet_profile_id,
                'student_id' => $student_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($link);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Return rifle from cadet
 */
function returnRifleOperation($input) {
    global $link;
    $securityLogger = new SecurityLogger();
    
    if (!isset($input['rifle_id']) || $input['rifle_id'] === '') {
        return ['success' => false, 'message' => 'Rifle ID is required'];
    }
    if (!isset($input['cadet_id']) && !isset($input['profile_id'])) {
        return ['success' => false, 'message' => 'Cadet identifier is required'];
    }
    
    $rifle_id = mysqli_real_escape_string($link, $input['rifle_id']);
    $cadet_profile_id = null;
    
    if (isset($input['profile_id']) && $input['profile_id'] !== '') {
        $pid = (int)$input['profile_id'];
        $res = mysqli_query($link, "SELECT id FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $cadet_profile_id = $row['id'];
        }
    }
    if ($cadet_profile_id === null && isset($input['cadet_id'])) {
        $cadet_id_raw = $input['cadet_id'];
        if (ctype_digit((string)$cadet_id_raw)) {
            $pid = (int)$cadet_id_raw;
            $res = mysqli_query($link, "SELECT id FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $cadet_profile_id = $row['id'];
            }
        }
        if ($cadet_profile_id === null) {
            $sid = mysqli_real_escape_string($link, $cadet_id_raw);
            $res = mysqli_query($link, "SELECT id FROM cadet_profiles WHERE student_id = '$sid' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $cadet_profile_id = $row['id'];
            }
        }
    }
    
    if ($cadet_profile_id === null) {
        return ['success' => false, 'message' => 'Cadet not found in system'];
    }
    
    // Check if assignment exists (use any present cadet columns)
    $parts = [];
    foreach (['cadet_profile_id', 'cadet_id', 'borrower_id'] as $colName) {
        if (columnExists('rifle_assignments', $colName)) {
            if ($colName === 'borrower_id') {
                $bid = ensureBorrowerForCadet($cadet_profile_id);
                if ($bid !== null) { $parts[] = "$colName = '" . (int)$bid . "'"; }
            } else {
                $parts[] = "$colName = '" . (int)$cadet_profile_id . "'";
            }
        }
    }
    if (empty($parts)) {
        $cadetCol = getAssignmentsCadetColumn();
        if ($cadetCol === 'borrower_id') {
            $bid = ensureBorrowerForCadet($cadet_profile_id);
            if ($bid === null) { return ['success' => false, 'message' => 'Unable to resolve borrower mapping']; }
            $parts[] = "$cadetCol = '" . (int)$bid . "'";
        } else {
            $parts[] = "$cadetCol = '" . (int)$cadet_profile_id . "'";
        }
    }
    $assignment_query = "SELECT * FROM rifle_assignments WHERE rifle_id = '$rifle_id' AND (" . implode(' OR ', $parts) . ") AND status = 'active'";
    $assignment_result = mysqli_query($link, $assignment_query);
    
    if (!$assignment_result || mysqli_num_rows($assignment_result) == 0) {
        return ['success' => false, 'message' => 'No active assignment found for this rifle and cadet'];
    }
    
    // Start transaction
    mysqli_begin_transaction($link);
    
    try {
        // Update rifle status to available
        $update_rifle = "UPDATE rifles SET status = 'available' WHERE id = '$rifle_id'";
        if (!mysqli_query($link, $update_rifle)) {
            throw new Exception('Failed to update rifle status');
        }
        
        // Update assignment record (match using any present cadet column)
        $whereParts = [];
        foreach (['cadet_profile_id', 'cadet_id', 'borrower_id'] as $colName) {
            if (columnExists('rifle_assignments', $colName)) {
                if ($colName === 'borrower_id') {
                    $bid = ensureBorrowerForCadet($cadet_profile_id);
                    if ($bid !== null) { $whereParts[] = "$colName = '" . (int)$bid . "'"; }
                } else {
                    $whereParts[] = "$colName = '" . (int)$cadet_profile_id . "'";
                }
            }
        }
        if (empty($whereParts)) {
            $cadetCol = getAssignmentsCadetColumn();
            if ($cadetCol === 'borrower_id') {
                $bid = ensureBorrowerForCadet($cadet_profile_id);
                if ($bid !== null) { $whereParts[] = "$cadetCol = '" . (int)$bid . "'"; }
            } else {
                $whereParts[] = "$cadetCol = '" . (int)$cadet_profile_id . "'";
            }
        }
        $where = implode(' OR ', $whereParts);
        $update_assignment = "UPDATE rifle_assignments SET status = 'returned', returned_at = NOW() WHERE rifle_id = '$rifle_id' AND (" . $where . ") AND status = 'active'";
        if (!mysqli_query($link, $update_assignment)) {
            throw new Exception('Failed to update assignment record');
        }
        
        // Log the action (use cadet_profiles.id)
        $performed_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
        $logsCadetCol = getLogsCadetColumn();
        $log_query = "INSERT INTO rifle_logs (rifle_id, $logsCadetCol, action, performed_by, timestamp, details) VALUES ('$rifle_id', '$cadet_profile_id', 'returned', '$performed_by', NOW(), 'Rifle returned via QR scanner')";
        mysqli_query($link, $log_query);
        
        // Commit transaction
        mysqli_commit($link);
        
        return [
            'success' => true,
            'message' => 'Rifle returned successfully',
            'return' => [
                'rifle_id' => $rifle_id,
                'cadet_profile_id' => $cadet_profile_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($link);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get cadet's assigned rifle
 */
function getCadetRifleAssignment($input) {
    global $link;
    
    if (!isset($input['cadet_id']) && !isset($input['profile_id'])) {
        return ['success' => false, 'message' => 'Cadet identifier is required'];
    }
    
    // Resolve to cadet_profiles.id
    $cadet_profile_id = null;
    if (isset($input['profile_id']) && $input['profile_id'] !== '') {
        $pid = (int)$input['profile_id'];
        $res = mysqli_query($link, "SELECT id FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $cadet_profile_id = $row['id'];
        }
    }
    if ($cadet_profile_id === null && isset($input['cadet_id'])) {
        $cadet_id_raw = $input['cadet_id'];
        if (ctype_digit((string)$cadet_id_raw)) {
            $pid = (int)$cadet_id_raw;
            $res = mysqli_query($link, "SELECT id FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $cadet_profile_id = $row['id'];
            }
        }
        if ($cadet_profile_id === null) {
            $sid = mysqli_real_escape_string($link, $cadet_id_raw);
            $res = mysqli_query($link, "SELECT id FROM cadet_profiles WHERE student_id = '$sid' LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $cadet_profile_id = $row['id'];
            }
        }
    }
    
    if ($cadet_profile_id === null) {
        return ['success' => false, 'message' => 'Cadet not found in system'];
    }
    
    $parts = [];
    foreach (['cadet_profile_id', 'cadet_id', 'borrower_id'] as $colName) {
        if (columnExists('rifle_assignments', $colName)) {
            if ($colName === 'borrower_id') {
                $bid = ensureBorrowerForCadet($cadet_profile_id);
                if ($bid !== null) { $parts[] = "ra.$colName = '" . (int)$bid . "'"; }
            } else {
                $parts[] = "ra.$colName = '" . (int)$cadet_profile_id . "'";
            }
        }
    }
    if (empty($parts)) {
        $cadetCol = getAssignmentsCadetColumn();
        if ($cadetCol === 'borrower_id') {
            $bid = ensureBorrowerForCadet($cadet_profile_id);
            if ($bid === null) { return ['success' => false, 'message' => 'Unable to resolve borrower mapping']; }
            $parts[] = "ra.$cadetCol = '" . (int)$bid . "'";
        } else {
            $parts[] = "ra.$cadetCol = '" . (int)$cadet_profile_id . "'";
        }
    }
    $query = "SELECT r.*, r.id as rifle_id, ra.assigned_at 
              FROM rifles r 
              JOIN rifle_assignments ra ON r.id = ra.rifle_id 
              WHERE (" . implode(' OR ', $parts) . ") AND ra.status = 'active'";
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($link)];
    }
    
    $rifle = mysqli_fetch_assoc($result);
    
    if (!$rifle) {
        return ['success' => false, 'message' => 'No rifle assigned to this cadet'];
    }
    
    return [
        'success' => true,
        'rifle' => $rifle
    ];
}

/**
 * Get recent rifle activities
 */
function getRecentActivities($input) {
    global $link;
    
    $limit = isset($input['limit']) ? (int)$input['limit'] : 10;
    $limit = min($limit, 50); // Maximum 50 records
    
    $logsCadetCol = getLogsCadetColumn();
    if ($logsCadetCol === 'borrower_id') {
        $hasTempId = columnExists('borrowers', 'temp_id');
        if ($hasTempId) {
            $query = "SELECT rl.*, r.rifle_number as serial_number, 
                             CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as cadet_name 
                      FROM rifle_logs rl 
                      LEFT JOIN rifles r ON rl.rifle_id = r.id 
                      LEFT JOIN borrowers b ON rl.borrower_id = b.id 
                      LEFT JOIN cadet_profiles cp ON b.temp_id = CONCAT('CADET_PROFILE_', cp.id) 
                      ORDER BY COALESCE(rl.timestamp, rl.created_at) DESC 
                      LIMIT $limit";
        } else {
            $query = "SELECT rl.*, r.rifle_number as serial_number, 
                             b.name as cadet_name 
                      FROM rifle_logs rl 
                      LEFT JOIN rifles r ON rl.rifle_id = r.id 
                      LEFT JOIN borrowers b ON rl.borrower_id = b.id 
                      ORDER BY COALESCE(rl.timestamp, rl.created_at) DESC 
                      LIMIT $limit";
        }
    } else {
        $query = "SELECT rl.*, r.rifle_number as serial_number, 
                         CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as cadet_name 
                  FROM rifle_logs rl 
                  LEFT JOIN rifles r ON rl.rifle_id = r.id 
                  LEFT JOIN cadet_profiles cp ON rl.$logsCadetCol = cp.id 
                  ORDER BY COALESCE(rl.timestamp, rl.created_at) DESC 
                  LIMIT $limit";
    }
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($link)];
    }
    
    $activities = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $activities[] = $row;
    }
    
    return [
        'success' => true,
        'activities' => $activities
    ];
}

/**
 * Get rifle statistics
 */
function getRifleStatisticsAPI() {
    $stats = getRifleStatistics();
    
    if ($stats) {
        return [
            'success' => true,
            'statistics' => $stats
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to retrieve statistics'];
    }
}

/**
 * Get current rifle assignments
 */
function getCurrentAssignmentsAPI() {
    global $link;
    
    $cadetCol = getAssignmentsCadetColumn();
    if ($cadetCol === 'borrower_id') {
        $hasTempId = columnExists('borrowers', 'temp_id');
        $hasBorrowerCourse = columnExists('borrowers', 'course');
        if ($hasTempId) {
            $query = "SELECT ra.*, r.rifle_number, r.serial_number, r.model,
                             CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) AS cadet_name,
                             cp.course,
                             COALESCE(cp.platoon, cp.section) AS platoon,
                             u.username AS assigned_by
                      FROM rifle_assignments ra
                      LEFT JOIN rifles r ON ra.rifle_id = r.id
                      LEFT JOIN borrowers b ON ra.borrower_id = b.id
                      LEFT JOIN cadet_profiles cp ON b.temp_id = CONCAT('CADET_PROFILE_', cp.id)
                      LEFT JOIN users u ON ra.assigned_by = u.id
                      WHERE ra.status = 'active'
                      ORDER BY ra.assigned_at DESC";
        } else {
            $selectCourse = $hasBorrowerCourse ? 'b.course' : 'NULL';
            $query = "SELECT ra.*, r.rifle_number, r.serial_number, r.model,
                             b.name AS cadet_name,
                             $selectCourse AS course,
                             NULL AS platoon,
                             u.username AS assigned_by
                      FROM rifle_assignments ra
                      LEFT JOIN rifles r ON ra.rifle_id = r.id
                      LEFT JOIN borrowers b ON ra.borrower_id = b.id
                      LEFT JOIN users u ON ra.assigned_by = u.id
                      WHERE ra.status = 'active'
                      ORDER BY ra.assigned_at DESC";
        }
    } else {
        $query = "SELECT ra.*, r.rifle_number, r.serial_number, r.model,
                         CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) AS cadet_name,
                         cp.course,
                         COALESCE(cp.platoon, cp.section) AS platoon,
                         u.username AS assigned_by
                  FROM rifle_assignments ra
                  LEFT JOIN rifles r ON ra.rifle_id = r.id
                  LEFT JOIN cadet_profiles cp ON ra.$cadetCol = cp.id
                  LEFT JOIN users u ON ra.assigned_by = u.id
                  WHERE ra.status = 'active'
                  ORDER BY ra.assigned_at DESC";
    }
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($link)];
    }
    
    $assignments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $assignments[] = $row;
    }
    
    return [
        'success' => true,
        'assignments' => $assignments
    ];
}
?>
