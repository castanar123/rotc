<?php
/**
 * Rifle Management Functions
 * Core backend functions for rifle borrowing and return system
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Determine which cadet foreign key column rifle_logs uses
function rifleLogsCadetCol() {
    global $link;
    static $cached = null;
    if ($cached !== null) return $cached;
    // Prefer new schema first, then legacy mapping, then old schema
    try { $res = $link->query("SHOW COLUMNS FROM rifle_logs LIKE 'cadet_profile_id'"); if ($res && $res->num_rows > 0) { $cached = 'cadet_profile_id'; return $cached; } } catch (Exception $e) { }
    try { $res = $link->query("SHOW COLUMNS FROM rifle_logs LIKE 'borrower_id'"); if ($res && $res->num_rows > 0) { $cached = 'borrower_id'; return $cached; } } catch (Exception $e) { }
    try { $res = $link->query("SHOW COLUMNS FROM rifle_logs LIKE 'cadet_id'"); if ($res && $res->num_rows > 0) { $cached = 'cadet_id'; return $cached; } } catch (Exception $e) { }
    // Default fallback to new schema
    $cached = 'cadet_profile_id';
    return $cached;
}

// Check if a column exists (PDO alt for compatibility)
function rf_column_exists($table, $column) {
    global $link;
    try {
        $tbl = $link->real_escape_string($table);
        $col = $link->real_escape_string($column);
        $res = $link->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
        return $res && $res->num_rows > 0;
    } catch (Exception $e) { return false; }
}

// Ensure a borrowers.id exists for a given cadet_profiles.id (when logs use borrower_id)
function rf_ensure_borrower_for_cadet($cadet_profile_id) {
    global $link;
    $pid = (int)$cadet_profile_id;
    if ($pid <= 0) return null;
    // If borrowers table missing, nothing to do
    try { $res = $link->query("SHOW TABLES LIKE 'borrowers'"); if (!$res || $res->num_rows === 0) return null; } catch (Exception $e) { return null; }
    $hasTemp = rf_column_exists('borrowers', 'temp_id');
    $hasName = rf_column_exists('borrowers', 'name');
    $hasCourse = rf_column_exists('borrowers', 'course');
    $hasStatus = rf_column_exists('borrowers', 'status');
    $temp_id = 'CADET_PROFILE_' . $pid;
    // Try find existing
    if ($hasTemp) {
        $esc = $link->real_escape_string($temp_id);
        $res = $link->query("SELECT id FROM borrowers WHERE temp_id = '$esc' LIMIT 1");
        if ($res && $res->num_rows > 0) { $row = $res->fetch_assoc(); return (int)$row['id']; }
    } elseif ($hasName) {
        $esc = $link->real_escape_string($temp_id);
        $res = $link->query("SELECT id FROM borrowers WHERE name = '$esc' LIMIT 1");
        if ($res && $res->num_rows > 0) { $row = $res->fetch_assoc(); return (int)$row['id']; }
    }
    // Load cadet info
    $cres = $link->query("SELECT first_name, middle_name, last_name, course FROM cadet_profiles WHERE id = '$pid' LIMIT 1");
    $full_name = 'Unknown Cadet'; $course = '';
    if ($cres && $cres->num_rows > 0) {
        $c = $cres->fetch_assoc();
        $fn = $c['first_name'] ?? ''; $mn = $c['middle_name'] ?? ''; $ln = $c['last_name'] ?? '';
        $full_name = trim($fn . ' ' . ($mn ? ($mn . ' ') : '') . $ln); if ($full_name === '') $full_name = 'Unknown Cadet';
        $course = $c['course'] ?? '';
    }
    // Build insert
    $cols = []; $vals = [];
    if ($hasTemp) { $cols[] = 'temp_id'; $vals[] = "'" . $link->real_escape_string($temp_id) . "'"; }
    if ($hasName) { $cols[] = 'name'; $vals[] = "'" . $link->real_escape_string($temp_id) . "'"; }
    if ($hasCourse) { $cols[] = 'course'; $vals[] = "'" . $link->real_escape_string($course) . "'"; }
    if ($hasStatus) { $cols[] = 'status'; $vals[] = "'active'"; }
    if (empty($cols)) return null;
    $ins = $link->query("INSERT INTO borrowers (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
    if ($ins) return (int)$link->insert_id;
    // Fallback reselect
    if ($hasTemp) {
        $esc = $link->real_escape_string($temp_id);
        $res = $link->query("SELECT id FROM borrowers WHERE temp_id = '$esc' LIMIT 1");
        if ($res && $res->num_rows > 0) { $row = $res->fetch_assoc(); return (int)$row['id']; }
    } elseif ($hasName) {
        $esc = $link->real_escape_string($temp_id);
        $res = $link->query("SELECT id FROM borrowers WHERE name = '$esc' LIMIT 1");
        if ($res && $res->num_rows > 0) { $row = $res->fetch_assoc(); return (int)$row['id']; }
    }
    return null;
}
// Determine which cadet foreign key column rifle_assignments uses
function rifleAssignmentsCadetCol() {
    global $link;
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $res = $link->query("SHOW COLUMNS FROM rifle_assignments LIKE 'cadet_profile_id'");
        if ($res && $res->num_rows > 0) { $cached = 'cadet_profile_id'; return $cached; }
    } catch (Exception $e) { /* ignore */ }
    try {
        $res = $link->query("SHOW COLUMNS FROM rifle_assignments LIKE 'cadet_id'");
        if ($res && $res->num_rows > 0) { $cached = 'cadet_id'; return $cached; }
    } catch (Exception $e) { /* ignore */ }
    try {
        $res = $link->query("SHOW COLUMNS FROM rifle_assignments LIKE 'borrower_id'");
        if ($res && $res->num_rows > 0) { $cached = 'borrower_id'; return $cached; }
    } catch (Exception $e) { /* ignore */ }
    // Default fallback
    $cached = 'cadet_id';
    return $cached;
}

// Determine which notes column exists on rifles table
function riflesNotesColumn() {
    global $link;
    // Default to 'notes' if exists, else 'condition_notes' if exists
    try {
        $res = $link->query("SHOW COLUMNS FROM rifles LIKE 'notes'");
        if ($res && $res->num_rows > 0) return 'notes';
    } catch (Exception $e) { /* ignore */ }
    try {
        $res = $link->query("SHOW COLUMNS FROM rifles LIKE 'condition_notes'");
        if ($res && $res->num_rows > 0) return 'condition_notes';
    } catch (Exception $e) { /* ignore */ }
    return null;
}

/**
 * Assign a rifle to a cadet
 * @param int $rifle_id The rifle ID
 * @param int $cadet_id The cadet profile ID
 * @param int $assigned_by The admin user ID who assigned the rifle
 * @return array Result with success status and message
 */
function assignRifle($rifle_id, $cadet_id, $assigned_by) {
    global $link;
    
    try {
        // Start transaction
        $link->begin_transaction();
        
        // Check if rifle exists and is available
        $rifle_check = $link->prepare("SELECT id, rifle_number, status FROM rifles WHERE id = ?");
        $rifle_check->bind_param("i", $rifle_id);
        $rifle_check->execute();
        $rifle_result = $rifle_check->get_result();
        
        if ($rifle_result->num_rows === 0) {
            throw new Exception("Rifle not found");
        }
        
        $rifle = $rifle_result->fetch_assoc();
        if ($rifle['status'] !== 'available') {
            throw new Exception("Rifle is not available for assignment");
        }
        
        // Check if cadet exists
        $cadet_check = $link->prepare("SELECT id, first_name, last_name FROM cadet_profiles WHERE id = ?");
        $cadet_check->bind_param("i", $cadet_id);
        $cadet_check->execute();
        $cadet_result = $cadet_check->get_result();
        
        if ($cadet_result->num_rows === 0) {
            throw new Exception("Cadet not found");
        }
        
        $cadet = $cadet_result->fetch_assoc();
        
        // Check if cadet already has a rifle assigned
        $existing_assignment = $link->prepare("SELECT id FROM rifle_assignments WHERE borrower_id = ? AND status = 'active'");
        $existing_assignment->bind_param("i", $cadet_id);
        $existing_assignment->execute();
        
        if ($existing_assignment->get_result()->num_rows > 0) {
            throw new Exception("Cadet already has a rifle assigned");
        }
        
        // Create assignment record
        $assignment_stmt = $link->prepare("INSERT INTO rifle_assignments (rifle_id, borrower_id, assigned_by, assigned_at, status) VALUES (?, ?, ?, NOW(), 'active')");
        $assignment_stmt->bind_param("iii", $rifle_id, $cadet_id, $assigned_by);
        $assignment_stmt->execute();
        
        $assignment_id = $link->insert_id;
        
        // Update rifle status
        $update_rifle = $link->prepare("UPDATE rifles SET status = 'assigned' WHERE id = ?");
        $update_rifle->bind_param("i", $rifle_id);
        $update_rifle->execute();
        
        // Log the action
        logRifleAction($rifle_id, $cadet_id, 'assigned', $assigned_by, "Rifle {$rifle['rifle_number']} assigned to {$cadet['first_name']} {$cadet['last_name']}");
        
        // Commit transaction
        $link->commit();
        
        return [
            'success' => true,
            'message' => "Rifle {$rifle['rifle_number']} successfully assigned to {$cadet['first_name']} {$cadet['last_name']}",
            'assignment_id' => $assignment_id
        ];
        
    } catch (Exception $e) {
        $link->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Return a rifle from a cadet
 * @param int $rifle_id The rifle ID
 * @param int $returned_by The admin user ID who processed the return
 * @param string $condition The condition of the returned rifle
 * @param string $notes Additional notes about the return
 * @return array Result with success status and message
 */
function returnRifle($rifle_id, $returned_by, $condition = 'good', $notes = '') {
    global $link;
    
    try {
        // Start transaction
        $link->begin_transaction();
        
        // Get active assignment
        $assignment_query = $link->prepare("
            SELECT ra.id, ra.borrower_id, r.rifle_number, b.name as borrower_name
            FROM rifle_assignments ra 
            JOIN rifles r ON ra.rifle_id = r.id 
            JOIN borrowers b ON ra.borrower_id = b.id 
            WHERE ra.rifle_id = ? AND ra.status = 'active'
        ");
        $assignment_query->bind_param("i", $rifle_id);
        $assignment_query->execute();
        $assignment_result = $assignment_query->get_result();
        
        if ($assignment_result->num_rows === 0) {
            throw new Exception("No active assignment found for this rifle");
        }
        
        $assignment = $assignment_result->fetch_assoc();
        
        // Update assignment record
        $update_assignment = $link->prepare("UPDATE rifle_assignments SET status = 'returned', returned_at = NOW(), returned_by = ?, return_condition = ?, return_notes = ? WHERE id = ?");
        $update_assignment->bind_param("issi", $returned_by, $condition, $notes, $assignment['id']);
        $update_assignment->execute();
        
        // Update rifle status
        $rifle_status = ($condition === 'damaged') ? 'maintenance' : 'available';
        $update_rifle = $link->prepare("UPDATE rifles SET status = ? WHERE id = ?");
        $update_rifle->bind_param("si", $rifle_status, $rifle_id);
        $update_rifle->execute();
        
        // Log the action
        $log_message = "Rifle {$assignment['rifle_number']} returned by {$assignment['first_name']} {$assignment['last_name']} in {$condition} condition";
        if ($notes) {
            $log_message .= ". Notes: {$notes}";
        }
        logRifleAction($rifle_id, $assignment['cadet_id'], 'returned', $returned_by, $log_message);
        
        // Commit transaction
        $link->commit();
        
        return [
            'success' => true,
            'message' => "Rifle {$assignment['rifle_number']} successfully returned by {$assignment['first_name']} {$assignment['last_name']}"
        ];
        
    } catch (Exception $e) {
        $link->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Return a rifle using assignment ID
 * @param int $assignment_id The assignment ID
 * @param int $returned_by The admin user ID who processed the return
 * @param string $condition The condition of the returned rifle
 * @param string $notes Additional notes about the return
 * @return array Result with success status and message
 */
function returnRifleByAssignment($assignment_id, $returned_by, $condition = 'good', $notes = '') {
    global $link;
    
    try {
        // Start transaction
        $link->begin_transaction();
        
        // Get assignment details
        $assignment_query = $link->prepare("
            SELECT ra.id, ra.rifle_id, ra.borrower_id, r.rifle_number, b.name as borrower_name
            FROM rifle_assignments ra 
            JOIN rifles r ON ra.rifle_id = r.id 
            JOIN borrowers b ON ra.borrower_id = b.id 
            WHERE ra.id = ? AND ra.status = 'active'
        ");
        $assignment_query->bind_param("i", $assignment_id);
        $assignment_query->execute();
        $assignment_result = $assignment_query->get_result();
        
        if ($assignment_result->num_rows === 0) {
            throw new Exception("No active assignment found with this ID");
        }
        
        $assignment = $assignment_result->fetch_assoc();
        
        // Update assignment record
        $update_assignment = $link->prepare("UPDATE rifle_assignments SET status = 'returned', returned_at = NOW(), returned_by = ?, notes = ? WHERE id = ?");
        $update_assignment->bind_param("isi", $returned_by, $notes, $assignment_id);
        $update_assignment->execute();
        
        // Update rifle status
        $rifle_status = ($condition === 'damaged') ? 'maintenance' : 'available';
        $update_rifle = $link->prepare("UPDATE rifles SET status = ? WHERE id = ?");
        $update_rifle->bind_param("si", $rifle_status, $assignment['rifle_id']);
        $update_rifle->execute();
        
        // Log the action
        $log_message = "Rifle {$assignment['rifle_number']} returned by {$assignment['first_name']} {$assignment['last_name']} in {$condition} condition";
        if ($notes) {
            $log_message .= ". Notes: {$notes}";
        }
        logRifleAction($assignment['rifle_id'], $assignment['cadet_id'], 'returned', $returned_by, $log_message);
        
        // Commit transaction
        $link->commit();
        
        return [
            'success' => true,
            'message' => "Rifle {$assignment['rifle_number']} successfully returned by {$assignment['first_name']} {$assignment['last_name']}"
        ];
        
    } catch (Exception $e) {
        $link->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Log rifle action to the rifle_logs table
 * @param int $rifle_id The rifle ID
 * @param int $cadet_id The cadet ID
 * @param string $action The action performed (assigned, returned, etc.)
 * @param int $performed_by The user ID who performed the action
 * @param string $details Additional details about the action
 */
function logRifleAction($rifle_id, $cadet_profile_id, $action, $performed_by, $details = '') {
    global $link;
    // Determine logs cadet column
    $col = rifleLogsCadetCol();
    $value = null;
    if ($col === 'borrower_id') {
        $value = rf_ensure_borrower_for_cadet((int)$cadet_profile_id);
        if ($value === null) { $value = 0; }
    } else {
        // Use cadet_profiles.id for cadet_id or cadet_profile_id schemas
        $value = (int)$cadet_profile_id;
    }
    $sql = "INSERT INTO rifle_logs (rifle_id, $col, action, performed_by, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $link->prepare($sql);
    // Bind types: i (int rifle), i (int cadet/borrower), s (action), i (performed_by), s (details)
    $stmt->bind_param("iisis", $rifle_id, $value, $action, $performed_by, $details);
    $stmt->execute();
}

/**
 * Get rifle statistics with 24-hour reset cycle
 * @return array Statistics array
 */
function getRifleStatistics() {
    global $link;
    
    $stats = array(
        'total' => 0,
        'available' => 0,
        'assigned' => 0,
        'maintenance' => 0,
        'returned' => 0
    );
    
    // Get basic rifle counts by status
    $sql = "SELECT status, COUNT(*) as count FROM rifles GROUP BY status";
    $result = $link->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }
    }
    
    // Get today's date for 24-hour reset cycle
    $today = date('Y-m-d');
    
    // Get assigned count (currently assigned rifles)
    $assigned_sql = "SELECT COUNT(*) as assigned FROM rifle_assignments WHERE returned_at IS NULL";
    $assigned_result = $link->query($assigned_sql);
    if ($assigned_result) {
        $assigned_row = $assigned_result->fetch_assoc();
        $stats['assigned'] = (int)$assigned_row['assigned'];
    }
    
    // Get returned count (rifles returned today only - 24-hour cycle)
    $returned_sql = "SELECT COUNT(DISTINCT rifle_id) as returned FROM rifle_assignments WHERE returned_at IS NOT NULL AND DATE(returned_at) = ?";
    $returned_stmt = $link->prepare($returned_sql);
    $returned_stmt->bind_param("s", $today);
    $returned_stmt->execute();
    $returned_result = $returned_stmt->get_result();
    if ($returned_result) {
        $returned_row = $returned_result->fetch_assoc();
        $stats['returned'] = (int)$returned_row['returned'];
    }
    $returned_stmt->close();
    
    return $stats;
}

/**
 * Delete a rifle from the database
 * @param int $rifle_id The rifle ID to delete
 * @return array Result with success status and message
 */
function deleteRifle($rifle_id) {
    global $link;
    
    try {
        // Start transaction
        $link->autocommit(false);
        
        // Check if rifle exists and get details
        $check_sql = "SELECT rifle_number, qr_code_path FROM rifles WHERE id = ?";
        $check_stmt = $link->prepare($check_sql);
        $check_stmt->bind_param("i", $rifle_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            $link->rollback();
            return ['success' => false, 'message' => 'Rifle not found'];
        }
        
        $rifle_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // Check if rifle is currently assigned
        $assignment_sql = "SELECT COUNT(*) as active_assignments FROM rifle_assignments WHERE rifle_id = ? AND returned_at IS NULL";
        $assignment_stmt = $link->prepare($assignment_sql);
        $assignment_stmt->bind_param("i", $rifle_id);
        $assignment_stmt->execute();
        $assignment_result = $assignment_stmt->get_result();
        $assignment_row = $assignment_result->fetch_assoc();
        $assignment_stmt->close();
        
        if ($assignment_row['active_assignments'] > 0) {
            $link->rollback();
            return ['success' => false, 'message' => 'Cannot delete rifle that is currently assigned'];
        }
        
        // Delete related records first (foreign key constraints)
        // Delete rifle assignments
        $delete_assignments_sql = "DELETE FROM rifle_assignments WHERE rifle_id = ?";
        $delete_assignments_stmt = $link->prepare($delete_assignments_sql);
        $delete_assignments_stmt->bind_param("i", $rifle_id);
        $delete_assignments_stmt->execute();
        $delete_assignments_stmt->close();
        
        // Delete rifle logs
        $delete_logs_sql = "DELETE FROM rifle_logs WHERE rifle_id = ?";
        $delete_logs_stmt = $link->prepare($delete_logs_sql);
        $delete_logs_stmt->bind_param("i", $rifle_id);
        $delete_logs_stmt->execute();
        $delete_logs_stmt->close();
        
        // Delete the rifle
        $delete_rifle_sql = "DELETE FROM rifles WHERE id = ?";
        $delete_rifle_stmt = $link->prepare($delete_rifle_sql);
        $delete_rifle_stmt->bind_param("i", $rifle_id);
        $delete_rifle_stmt->execute();
        $delete_rifle_stmt->close();
        
        // Delete QR code file if it exists
        if (!empty($rifle_data['qr_code_path']) && file_exists($rifle_data['qr_code_path'])) {
            unlink($rifle_data['qr_code_path']);
        }
        
        // Commit transaction
        $link->commit();
        $link->autocommit(true);
        
        return [
            'success' => true,
            'message' => "Rifle {$rifle_data['rifle_number']} deleted successfully"
        ];
        
    } catch (Exception $e) {
        $link->rollback();
        $link->autocommit(true);
        error_log("Delete rifle error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to delete rifle: ' . $e->getMessage()
        ];
    }
}

/**
 * Update an existing rifle's basic details (currently rifle_number only)
 * @param int $rifle_id The rifle ID to update
 * @param string $rifle_number New rifle number
 * @return array Result with success status and message
 */
function updateRifle($rifle_id, $rifle_number) {
    global $link;

    if (!$link) {
        return ['success' => false, 'message' => 'Database connection not available'];
    }

    $rifle_id = (int)$rifle_id;
    $rifle_number = trim((string)$rifle_number);

    if ($rifle_id <= 0) {
        return ['success' => false, 'message' => 'Invalid rifle ID'];
    }

    if ($rifle_number === '') {
        return ['success' => false, 'message' => 'Rifle number cannot be empty'];
    }

    // Validate format similar to rifle creation (letters, numbers, dashes, underscores)
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $rifle_number)) {
        return ['success' => false, 'message' => 'Rifle number can contain letters, numbers, dashes, and underscores only'];
    }

    try {
        // Check that rifle exists and get current number
        $check_sql = "SELECT id, rifle_number FROM rifles WHERE id = ?";
        $check_stmt = $link->prepare($check_sql);
        $check_stmt->bind_param("i", $rifle_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            return ['success' => false, 'message' => 'Rifle not found'];
        }

        $rifle = $check_result->fetch_assoc();
        $check_stmt->close();

        // If number didn't change, nothing to do
        if ($rifle['rifle_number'] === $rifle_number) {
            return ['success' => true, 'message' => 'No changes to apply'];
        }

        // Ensure new rifle_number is not already used by another rifle
        $dup_sql = "SELECT id FROM rifles WHERE rifle_number = ? AND id <> ? LIMIT 1";
        $dup_stmt = $link->prepare($dup_sql);
        $dup_stmt->bind_param("si", $rifle_number, $rifle_id);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        if ($dup_result && $dup_result->num_rows > 0) {
            $dup_stmt->close();
            return ['success' => false, 'message' => 'Another rifle already uses this number'];
        }
        $dup_stmt->close();

        // Perform update
        $update_sql = "UPDATE rifles SET rifle_number = ? WHERE id = ?";
        $update_stmt = $link->prepare($update_sql);
        $update_stmt->bind_param("si", $rifle_number, $rifle_id);

        if ($update_stmt->execute()) {
            $update_stmt->close();
            return [
                'success' => true,
                'message' => "Rifle {$rifle['rifle_number']} updated to {$rifle_number}",
            ];
        }

        $update_stmt->close();
        return ['success' => false, 'message' => 'Failed to update rifle'];
    } catch (Exception $e) {
        error_log('Update rifle error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update rifle: ' . $e->getMessage()];
    }
}

/**
 * Get all rifles with pagination
 * @param int $page Page number (default: 1)
 * @param int $limit Items per page (default: 20)
 * @param string $search Search term (optional)
 * @return array Rifles data with pagination info
 */
function getAllRifles($page = 1, $limit = 20, $search = '') {
    global $link;
    
    $offset = ($page - 1) * $limit;
    $where_clause = "";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $notesCol = riflesNotesColumn();
        $where_clause = "WHERE r.rifle_number LIKE ? OR r.status LIKE ? OR " . ($notesCol ? ("r." . $notesCol) : "''") . " LIKE ? OR r.rifle_type LIKE ?";
        $search_term = "%{$search}%";
        $params = [$search_term, $search_term, $search_term, $search_term];
        $types = "ssss";
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM rifles r {$where_clause}";
    if (!empty($params)) {
        $count_stmt = $link->prepare($count_sql);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $count_result = $link->query($count_sql);
        $total = $count_result->fetch_assoc()['total'];
    }
    
    // Get rifles data
    $sql = "SELECT r.*, 
                   CASE WHEN ra.rifle_id IS NOT NULL THEN 'assigned' ELSE r.status END as current_status,
                   ra.borrower_id, ra.assigned_at
            FROM rifles r 
            LEFT JOIN rifle_assignments ra ON r.id = ra.rifle_id AND ra.returned_at IS NULL 
            {$where_clause}
            ORDER BY r.rifle_type, r.rifle_number 
            LIMIT ? OFFSET ?";
    
    $stmt = $link->prepare($sql);
    if (!empty($params)) {
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rifles = [];
    while ($row = $result->fetch_assoc()) {
        $rifles[] = $row;
    }
    $stmt->close();
    
    return [
        'rifles' => $rifles,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ];
}

/**
 * Get current rifle assignments
 * @param int $limit Number of records to return
 * @param int $offset Offset for pagination
 * @return array Current assignments data
 */
function getCurrentAssignments($limit = 50, $offset = 0) {
    global $link;
    
    $cadetCol = rifleAssignmentsCadetCol();
    $sql = "
        SELECT 
            ra.id,
            r.rifle_number,
            r.rifle_number as serial_number,
            CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) AS cadet_name,
            cp.course,
            COALESCE(cp.platoon, cp.section) AS platoon,
            ra.assigned_at,
            u.username as assigned_by_username
        FROM rifle_assignments ra
        JOIN rifles r ON ra.rifle_id = r.id
        JOIN cadet_profiles cp ON ra.$cadetCol = cp.id
        JOIN users u ON ra.assigned_by = u.id
        WHERE ra.status = 'active'
        ORDER BY ra.assigned_at DESC
        LIMIT ? OFFSET ?
    ";
    $query = $link->prepare($sql);
    
    $query->bind_param("ii", $limit, $offset);
    $query->execute();
    $result = $query->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    
    return $assignments;
}

/**
 * Search rifles by various criteria
 * @param string $search_term Search term
 * @param string $status Filter by status (optional)
 * @return array Search results
 */
function searchRifles($search_term = '', $status = '') {
    global $link;
    
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search_term)) {
        $notesCol = riflesNotesColumn();
        $where_conditions[] = "(r.rifle_number LIKE ? OR r.rifle_type LIKE ? OR " . ($notesCol ? ("r." . $notesCol) : "''") . " LIKE ?)";
        $search_param = "%{$search_term}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
    
    if (!empty($status)) {
        $where_conditions[] = "r.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT 
            r.*,
            CASE 
                WHEN ra.id IS NOT NULL THEN b.name
                ELSE NULL
            END as assigned_to
        FROM rifles r
        LEFT JOIN rifle_assignments ra ON r.id = ra.rifle_id AND ra.status = 'active'
        LEFT JOIN borrowers b ON ra.borrower_id = b.id
        {$where_clause}
        ORDER BY r.rifle_type, r.rifle_number
    ";
    
    if (!empty($params)) {
        $stmt = $link->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $link->query($sql);
    }
    
    $rifles = [];
    while ($row = $result->fetch_assoc()) {
        $rifles[] = $row;
    }
    
    return $rifles;
}

/**
 * Generate daily backup of rifle transactions
 * @param string $date Date in Y-m-d format (optional, defaults to today)
 * @return string Backup content
 */
function generateDailyBackup($date = null) {
    global $link;
    if (!$link) {
        return "No DB connection available.\n";
    }
    if ($date === null) {
        $date = date('Y-m-d');
    }

    // Build schema-aware query for logs backup
    $logsCol = rifleLogsCadetCol();
    if ($logsCol === 'borrower_id') {
        $hasTemp = rf_column_exists('borrowers', 'temp_id');
        if ($hasTemp) {
            $sql = "
                SELECT 
                    rl.created_at,
                    r.rifle_number,
                    r.rifle_number AS serial_number,
                    cp.first_name,
                    cp.last_name,
                    COALESCE(cp.platoon, cp.section) AS platoon,
                    cp.course,
                    rl.action,
                    rl.details,
                    u.username AS performed_by
                FROM rifle_logs rl
                JOIN rifles r ON rl.rifle_id = r.id
                LEFT JOIN borrowers b ON rl.borrower_id = b.id
                LEFT JOIN cadet_profiles cp ON b.temp_id = CONCAT('CADET_PROFILE_', cp.id)
                LEFT JOIN users u ON rl.performed_by = u.id
                WHERE DATE(rl.created_at) = ?
                ORDER BY rl.created_at
            ";
        } else {
            // No mapping to cadet_profiles available; export borrower name only
            $sql = "
                SELECT 
                    rl.created_at,
                    r.rifle_number,
                    r.rifle_number AS serial_number,
                    NULL AS first_name,
                    b.name AS last_name,
                    NULL AS platoon,
                    NULL AS course,
                    rl.action,
                    rl.details,
                    u.username AS performed_by
                FROM rifle_logs rl
                JOIN rifles r ON rl.rifle_id = r.id
                LEFT JOIN borrowers b ON rl.borrower_id = b.id
                LEFT JOIN users u ON rl.performed_by = u.id
                WHERE DATE(rl.created_at) = ?
                ORDER BY rl.created_at
            ";
        }
    } elseif ($logsCol === 'cadet_profile_id') {
        $sql = "
            SELECT 
                rl.created_at,
                r.rifle_number,
                r.rifle_number AS serial_number,
                cp.first_name,
                cp.last_name,
                COALESCE(cp.platoon, cp.section) AS platoon,
                cp.course,
                rl.action,
                rl.details,
                u.username AS performed_by
            FROM rifle_logs rl
            JOIN rifles r ON rl.rifle_id = r.id
            LEFT JOIN cadet_profiles cp ON rl.cadet_profile_id = cp.id
            LEFT JOIN users u ON rl.performed_by = u.id
            WHERE DATE(rl.created_at) = ?
            ORDER BY rl.created_at
        ";
    } else { // cadet_id or unknown
        $sql = "
            SELECT 
                rl.created_at,
                r.rifle_number,
                r.rifle_number AS serial_number,
                cp.first_name,
                cp.last_name,
                COALESCE(cp.platoon, cp.section) AS platoon,
                cp.course,
                rl.action,
                rl.details,
                u.username AS performed_by
            FROM rifle_logs rl
            JOIN rifles r ON rl.rifle_id = r.id
            LEFT JOIN cadet_profiles cp ON cp.student_id = rl.cadet_id
            LEFT JOIN users u ON rl.performed_by = u.id
            WHERE DATE(rl.created_at) = ?
            ORDER BY rl.created_at
        ";
    }

    try {
        $stmt = $link->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $backup_content = "Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= str_repeat("=", 80) . "\n\n";

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $backup_content .= "Time: {$row['created_at']}\n";
                $backup_content .= "Rifle: {$row['rifle_number']} (S/N: {$row['serial_number']})\n";
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if ($name === '') { $name = $row['last_name'] ?? 'Unknown'; }
                $platoon = $row['platoon'] ?? '';
                $course = $row['course'] ?? '';
                $backup_content .= "Cadet: {$name} (" . ($platoon ?: 'N/A') . ", " . ($course ?: 'N/A') . ")\n";
                $backup_content .= "Action: {$row['action']}\n";
                $backup_content .= "Details: {$row['details']}\n";
                $backup_content .= "Performed by: {$row['performed_by']}\n";
                $backup_content .= str_repeat("-", 40) . "\n\n";
            }
        } else {
            $backup_content .= "No rifle transactions recorded for this date.\n";
        }

        return $backup_content;
    } catch (Exception $e) {
        return "Backup generation failed: " . $e->getMessage();
    }
}

/**
 * Get rifle by QR code
 * @param string $qr_code The QR code value
 * @return array|null Rifle data or null if not found
 */
function getRifleByQR($qr_code) {
    global $link;
    
    $query = $link->prepare("SELECT * FROM rifles WHERE qr_code = ?");
    $query->bind_param("s", $qr_code);
    $query->execute();
    $result = $query->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Get cadet by QR code
 * @param string $qr_code The QR code value
 * @return array|null Cadet data or null if not found
 */
function getCadetByQR($qr_code) {
    global $link;
    
    $query = $link->prepare("SELECT * FROM cadet_profiles WHERE qr_code = ?");
    $query->bind_param("s", $qr_code);
    $query->execute();
    $result = $query->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

?>