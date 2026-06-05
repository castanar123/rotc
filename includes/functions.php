<?php
/**
 * Common functions for the ROTC Management System
 */

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if user has required role
 * @param array $allowed_roles
 * @return bool
 */
function check_role($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        return false;
    }
    return true;
}

/**
 * Generate CSRF token
 * @return string
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log activity
 * @param string $action
 * @param string $details
 */
function log_activity($action, $details = '') {
    global $pdo;
    
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $action, $details]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function format_date($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Get user's full name
 * @param int $user_id
 * @return string
 */
function get_user_name($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM cadet_profiles cp JOIN users u ON cp.user_id = u.id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();
        return $result ?: 'Unknown User';
    } catch (Exception $e) {
        return 'Unknown User';
    }
}

?>