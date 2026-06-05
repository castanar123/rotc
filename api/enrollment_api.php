<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once '../includes/db.php';

global $link;

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_GET['endpoint'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($request);
            break;
        case 'POST':
            handlePostRequest($request);
            break;
        case 'PUT':
            handlePutRequest($request);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($endpoint) {
    global $link;
    
    switch ($endpoint) {
        case 'statistics':
            getEnrollmentStatistics();
            break;
        case 'daily-stats':
            getDailyStatistics();
            break;
        case 'enrollees':
            getEnrollees();
            break;
        case 'config':
            getConfiguration();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePostRequest($endpoint) {
    global $link;
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'update-config':
            updateConfiguration($input);
            break;
        case 'update-enrollee-status':
            updateEnrolleeStatus($input);
            break;
        case 'bulk-approve':
            bulkApproveEnrollees($input);
            break;
        case 'export-data':
            exportEnrollmentData($input);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function getEnrollmentStatistics() {
    global $link;
    
    $query = "
        SELECT 
            COUNT(*) as total_enrollees,
            SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals,
            SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_enrollees,
            SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_enrollees,
            SUM(CASE WHEN paper_form_submitted = 1 THEN 1 ELSE 0 END) as paper_forms_submitted,
            SUM(CASE WHEN paper_form_submitted = 0 AND approval_status = 'approved' THEN 1 ELSE 0 END) as paper_forms_pending
        FROM users 
        WHERE role IN ('cadet', 'basic-cadet')
    ";
    
    $result = $link->query($query);
    $stats = $result->fetch_assoc();
    
    // Get today's new enrollments
    $today_query = "
        SELECT COUNT(*) as today_enrollments 
        FROM users 
        WHERE role IN ('cadet', 'basic-cadet') AND DATE(created_at) = CURDATE()
    ";
    $today_result = $link->query($today_query);
    $today_stats = $today_result->fetch_assoc();
    
    $stats['today_enrollments'] = $today_stats['today_enrollments'];
    
    echo json_encode($stats);
}

function getDailyStatistics() {
    global $link;
    
    $days = $_GET['days'] ?? 30;
    
    $query = "
        SELECT 
            date_recorded,
            total_enrollees,
            pending_approvals,
            approved_enrollees,
            rejected_enrollees,
            paper_forms_submitted,
            paper_forms_pending
        FROM enrollment_statistics 
        WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY date_recorded ASC
    ";
    
    $stmt = $link->prepare($query);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_stats = [];
    while ($row = $result->fetch_assoc()) {
        $daily_stats[] = $row;
    }
    
    echo json_encode($daily_stats);
}

function getEnrollees() {
    global $link;
    
    $status = $_GET['status'] ?? '';
    $course = $_GET['course'] ?? '';
    $platoon = $_GET['platoon'] ?? '';
    $limit = $_GET['limit'] ?? 100;
    $offset = $_GET['offset'] ?? 0;
    
    $where_conditions = ["u.role = 'cadet'"];
    $params = [];
    $types = "";
    
    if ($status) {
        $where_conditions[] = "u.approval_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($course) {
        $where_conditions[] = "cp.course = ?";
        $params[] = $course;
        $types .= "s";
    }
    
    if ($platoon) {
        $where_conditions[] = "cp.platoon = ?";
        $params[] = $platoon;
        $types .= "s";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $query = "
        SELECT 
            u.id,
            u.username,
            u.email,
            u.approval_status,
            u.paper_form_submitted,
            u.created_at,
            u.updated_at,
            cp.full_name,
            cp.student_number,
            cp.course,
            cp.platoon,
            cp.year_level,
            cp.contact_number
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE $where_clause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $link->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $enrollees = [];
    while ($row = $result->fetch_assoc()) {
        $enrollees[] = $row;
    }
    
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE $where_clause
    ";
    
    $count_stmt = $link->prepare($count_query);
    if ($types && count($params) > 2) {
        $count_params = array_slice($params, 0, -2); // Remove limit and offset
        $count_types = substr($types, 0, -2);
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
    
    echo json_encode([
        'enrollees' => $enrollees,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function getConfiguration() {
    global $link;
    
    $query = "SELECT setting_name, setting_value, description, updated_at FROM enrollment_tracking_config";
    $result = $link->query($query);
    
    $config = [];
    while ($row = $result->fetch_assoc()) {
        $config[$row['setting_name']] = [
            'value' => $row['setting_value'],
            'description' => $row['description'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode($config);
}

function updateConfiguration($input) {
    global $link;
    
    if (!isset($input['setting_name']) || !isset($input['setting_value'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $stmt = $link->prepare("
        UPDATE enrollment_tracking_config 
        SET setting_value = ?, updated_at = NOW(), updated_by = ? 
        WHERE setting_name = ?
    ");
    
    $stmt->bind_param("sss", $input['setting_value'], $_SESSION['username'], $input['setting_name']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Configuration updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update configuration']);
    }
}

function updateEnrolleeStatus($input) {
    global $link;
    
    if (!isset($input['user_id']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $valid_statuses = ['pending', 'approved', 'rejected'];
    if (!in_array($input['status'], $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    
    $stmt = $link->prepare("UPDATE users SET approval_status = ?, updated_at = NOW() WHERE id = ? AND role = 'cadet'");
    $stmt->bind_param("si", $input['status'], $input['user_id']);
    
    if ($stmt->execute()) {
        // Update paper form status if provided
        if (isset($input['paper_form_submitted'])) {
            $paper_stmt = $link->prepare("UPDATE users SET paper_form_submitted = ? WHERE id = ?");
            $paper_stmt->bind_param("ii", $input['paper_form_submitted'], $input['user_id']);
            $paper_stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Enrollee status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update enrollee status']);
    }
}

function bulkApproveEnrollees($input) {
    global $link;
    
    if (!isset($input['user_ids']) || !is_array($input['user_ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user IDs']);
        return;
    }
    
    $user_ids = array_map('intval', $input['user_ids']);
    $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
    
    $stmt = $link->prepare("
        UPDATE users 
        SET approval_status = 'approved', updated_at = NOW() 
        WHERE id IN ($placeholders) AND role = 'cadet'
    ");
    
    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        echo json_encode([
            'success' => true, 
            'message' => "$affected_rows enrollees approved successfully",
            'affected_rows' => $affected_rows
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to bulk approve enrollees']);
    }
}

function exportEnrollmentData($input) {
    global $link;
    
    $format = $input['format'] ?? 'csv';
    $status = $input['status'] ?? '';
    
    $where_conditions = ["u.role = 'cadet'"];
    $params = [];
    $types = "";
    
    if ($status) {
        $where_conditions[] = "u.approval_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $query = "
        SELECT 
            u.id,
            u.username,
            u.email,
            u.approval_status,
            u.paper_form_submitted,
            u.created_at,
            cp.full_name,
            cp.student_number,
            cp.course,
            cp.platoon,
            cp.year_level,
            cp.contact_number
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE $where_clause
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $link->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    if ($format === 'csv') {
        $filename = 'enrollment_data_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Username', 'Email', 'Full Name', 'Student Number', 
            'Course', 'Platoon', 'Year Level', 'Contact Number', 
            'Status', 'Paper Form Submitted', 'Enrolled Date'
        ]);
        
        // CSV data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['email'],
                $row['full_name'],
                $row['student_number'],
                $row['course'],
                $row['platoon'],
                $row['year_level'],
                $row['contact_number'],
                $row['approval_status'],
                $row['paper_form_submitted'] ? 'Yes' : 'No',
                $row['created_at']
            ]);
        }
        
        fclose($output);
    } else {
        echo json_encode($data);
    }
}
?>