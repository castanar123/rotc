<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Admin only
if (!isset($_SESSION['user_id']) || !rotc_role_in(['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admins only.']);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $action = $_POST['action'] ?? '';
    if ($action === '') {
        throw new Exception('Missing action parameter');
    }
    
    $attendance_id = null;
    if ($action !== 'add') {
        $attendance_id = $_POST['id'] ?? '';
        if ($attendance_id === '' || !is_numeric($attendance_id)) {
            throw new Exception('Invalid attendance ID');
        }
    }
    
    // Fetch current record (for edit/delete)
    $current_record = null;
    if ($attendance_id) {
        $stmt = $pdo->prepare("SELECT ar.*, cp.student_id, CONCAT(cp.first_name,' ',cp.last_name) AS full_name
                               FROM attendance_records ar
                               LEFT JOIN cadet_profiles cp ON cp.id = ar.cadet_id
                               WHERE ar.id = ?");
        $stmt->execute([$attendance_id]);
        $current_record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current_record) {
            throw new Exception('Attendance record not found');
        }
    }
    
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM attendance_records WHERE id = ?');
        $ok = $stmt->execute([$attendance_id]);
        if (!$ok) throw new Exception('Failed to delete attendance record');
        
        // Audit (best-effort)
        try {
            $audit = $pdo->prepare("INSERT INTO attendance_logs (cadet_id, event_name, time_in, status, logged_by, action_type, original_data, created_at)
                                    VALUES (?, ?, ?, ?, ?, 'DELETE', ?, NOW())");
            $audit->execute([
                $current_record['cadet_id'] ?? null,
                ($current_record['event_name'] ?? '') . ' (DELETED)',
                $current_record['recorded_at'] ?? date('Y-m-d H:i:s'),
                'deleted',
                $_SESSION['user_id'],
                json_encode($current_record)
            ]);
        } catch (Throwable $e) { /* ignore */ }
        
        echo json_encode(['success' => true, 'message' => 'Attendance record deleted successfully']);
        exit;
    }
    
    // Shared validations for add/edit
    $event_name = trim($_POST['event_name'] ?? '');
    $date = $_POST['date'] ?? '';
    $time_in = $_POST['time_in'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if ($event_name === '' || $date === '' || $time_in === '' || $semester === '' || $status === '') {
        throw new Exception('All fields are required');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format');
    }
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_in)) {
        throw new Exception('Invalid time format');
    }
    if (!in_array($status, ['present','late','absent','excused'])) {
        throw new Exception('Invalid status value');
    }
    $recorded_at = $date . ' ' . $time_in . ':00';
    
    if ($action === 'edit') {
        $stmt = $pdo->prepare('UPDATE attendance_records
                               SET event_name = ?, recorded_at = ?, semester = ?, status = ?
                               WHERE id = ?');
        $ok = $stmt->execute([$event_name, $recorded_at, $semester, $status, $attendance_id]);
        if (!$ok) throw new Exception('Failed to update attendance record');
        
        // Audit (best-effort)
        try {
            $audit = $pdo->prepare("INSERT INTO attendance_logs (cadet_id, event_name, time_in, status, logged_by, action_type, original_data, created_at)
                                    VALUES (?, ?, ?, ?, ?, 'EDIT', ?, NOW())");
            $audit->execute([
                $current_record['cadet_id'] ?? null,
                $event_name . ' (EDITED)',
                $recorded_at,
                $status,
                $_SESSION['user_id'],
                json_encode(['original'=>$current_record,'updated'=>['event_name'=>$event_name,'recorded_at'=>$recorded_at,'semester'=>$semester,'status'=>$status]])
            ]);
        } catch (Throwable $e) { /* ignore */ }
        
        echo json_encode(['success' => true, 'message' => 'Attendance record updated successfully']);
        exit;
    }
    
    if ($action === 'add') {
        $cadet_id = $_POST['cadet_id'] ?? '';
        if ($cadet_id === '' || !is_numeric($cadet_id)) {
            throw new Exception('Invalid cadet ID');
        }
        // Ensure cadet exists (id == cadet_profiles.id)
        $chk = $pdo->prepare('SELECT id FROM cadet_profiles WHERE id = ?');
        $chk->execute([$cadet_id]);
        if (!$chk->fetchColumn()) {
            throw new Exception('Cadet not found');
        }
        
        $stmt = $pdo->prepare('INSERT INTO attendance_records (cadet_id, event_name, recorded_at, semester, status)
                               VALUES (?, ?, ?, ?, ?)');
        $ok = $stmt->execute([$cadet_id, $event_name, $recorded_at, $semester, $status]);
        if (!$ok) throw new Exception('Failed to add attendance record');
        
        // Audit (best-effort)
        try {
            $audit = $pdo->prepare("INSERT INTO attendance_logs (cadet_id, event_name, time_in, status, logged_by, action_type, original_data, created_at)
                                    VALUES (?, ?, ?, ?, ?, 'ADD', ?, NOW())");
            $audit->execute([
                $cadet_id,
                $event_name . ' (ADDED)',
                $recorded_at,
                $status,
                $_SESSION['user_id'],
                json_encode(['cadet_id'=>$cadet_id,'event_name'=>$event_name,'recorded_at'=>$recorded_at,'semester'=>$semester,'status'=>$status])
            ]);
        } catch (Throwable $e) { /* ignore */ }
        
        echo json_encode(['success' => true, 'message' => 'Attendance record added successfully']);
        exit;
    }
    
    throw new Exception('Invalid action');
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    error_log('Database error in edit_attendance.php: ' . $e->getMessage());
}
?>
