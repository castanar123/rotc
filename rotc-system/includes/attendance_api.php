<?php
session_start();
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

// Get attendance statistics
function getAttendanceStats() {
    global $conn;
    
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
        // Get total cadets count
        $totalQuery = "SELECT COUNT(*) as total FROM cadet_profiles";
        $totalResult = $conn->query($totalQuery);
        $totalRow = $totalResult->fetch_assoc();
        $totalCadets = $totalRow['total'];
        
        // Get present cadets count for the specified date, TD, and semester
        $presentQuery = "SELECT COUNT(*) as present FROM attendance 
                        WHERE training_day = ? AND DATE(created_at) = ? AND td = ? AND semester = ?";
        $stmt = $conn->prepare($presentQuery);
        $stmt->bind_param("ssss", $td, $date, $_GET['td'], $_GET['semester']);
        $stmt->execute();
        $presentResult = $stmt->get_result();
        $presentRow = $presentResult->fetch_assoc();
        $presentCadets = $presentRow['present'];
        
        // Calculate absent cadets
        $absentCadets = $totalCadets - $presentCadets;
        
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
    global $conn;
    
    // Get total cadets of the specified gender
    $totalQuery = "SELECT COUNT(*) as total FROM cadet_profiles WHERE gender = ?";
    $stmt = $conn->prepare($totalQuery);
    $stmt->bind_param("s", $gender);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $totalCadets = $totalRow['total'];
    
    // Get present cadets of the specified gender
    $presentQuery = "SELECT COUNT(*) as present FROM attendance a 
                    JOIN cadet_profiles c ON a.cadet_id = c.id 
                    WHERE c.gender = ? AND a.training_day = ? AND DATE(a.created_at) = ? AND a.td = ? AND a.semester = ?";
    $stmt = $conn->prepare($presentQuery);
    $stmt->bind_param("sssss", $gender, $td, $date, $td, $semester);
    $stmt->execute();
    $presentResult = $stmt->get_result();
    $presentRow = $presentResult->fetch_assoc();
    $presentCadets = $presentRow['present'];
    
    // Calculate absent cadets
    $absentCadets = $totalCadets - $presentCadets;
    
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
    global $conn;
    
    $platoons = [];
    
    // Get list of platoons
    $platoonsQuery = "SELECT DISTINCT platoon FROM cadet_profiles WHERE platoon IS NOT NULL AND platoon != ''";
    $platoonsResult = $conn->query($platoonsQuery);
    
    while ($platoonRow = $platoonsResult->fetch_assoc()) {
        $platoonName = $platoonRow['platoon'];
        
        // Get total cadets in the platoon
        $totalQuery = "SELECT COUNT(*) as total FROM cadet_profiles WHERE platoon = ?";
        $stmt = $conn->prepare($totalQuery);
        $stmt->bind_param("s", $platoonName);
        $stmt->execute();
        $totalResult = $stmt->get_result();
        $totalRow = $totalResult->fetch_assoc();
        $totalCadets = $totalRow['total'];
        
        // Get present cadets in the platoon
        $presentQuery = "SELECT COUNT(*) as present FROM attendance a 
                        JOIN cadet_profiles c ON a.cadet_id = c.id 
                        WHERE c.platoon = ? AND a.training_day = ? AND DATE(a.created_at) = ? AND a.td = ? AND a.semester = ?";
        $stmt = $conn->prepare($presentQuery);
        $stmt->bind_param("sssss", $platoonName, $td, $date, $td, $semester);
        $stmt->execute();
        $presentResult = $stmt->get_result();
        $presentRow = $presentResult->fetch_assoc();
        $presentCadets = $presentRow['present'];
        
        // Calculate absent cadets
        $absentCadets = $totalCadets - $presentCadets;
        
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
    global $conn;
    
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
        // Get recent attendance records
        $query = "SELECT a.created_at as timestamp, c.student_id, CONCAT(c.first_name, ' ', c.last_name) as name, c.platoon, c.gender 
                FROM attendance a 
                JOIN cadet_profiles c ON a.cadet_id = c.id 
                WHERE a.training_day = ? AND DATE(a.created_at) = ? AND a.td = ? AND a.semester = ? 
                ORDER BY a.created_at DESC LIMIT 20";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $td, $date, $td, $semester);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
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