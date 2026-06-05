<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to delete user', $_SESSION['user_id'] ?? null, 'HIGH');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    redirect_to_dashboard();
}

// Log successful admin access
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed user deletion endpoint', $_SESSION['user_id'], 'MEDIUM');

// Handle AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    
    if (!$user_id) {
        SecurityLogger::logSecurityEvent('INVALID_REQUEST', 'User deletion attempted without user ID via AJAX', $_SESSION['user_id'], 'MEDIUM');
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        SecurityLogger::logSecurityEvent('SECURITY_VIOLATION', 'Admin attempted to delete their own account via AJAX', $_SESSION['user_id'], 'HIGH');
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        exit;
    }
    
    // Check if user exists and get user info for logging
    $check_sql = "SELECT id, username, role FROM users WHERE id = ?";
    if ($check_stmt = $link->prepare($check_sql)) {
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            SecurityLogger::logSecurityEvent('INVALID_REQUEST', "Attempted to delete non-existent user ID: $user_id", $_SESSION['user_id'], 'MEDIUM');
            echo json_encode(['success' => false, 'message' => 'User not found']);
            $check_stmt->close();
            exit;
        }
        
        $user_info = $result->fetch_assoc();
        $check_stmt->close();
    }
    
    // Delete the user
    $sql = "DELETE FROM users WHERE id = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            SecurityLogger::logSecurityEvent('USER_DELETED', "Admin deleted user via AJAX: {$user_info['username']} (Role: {$user_info['role']})", $_SESSION['user_id'], 'HIGH');
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            SecurityLogger::logSecurityEvent('DELETE_FAILED', "Failed to delete user ID: $user_id via AJAX - " . $link->error, $_SESSION['user_id'], 'MEDIUM');
            echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $link->error]);
        }
        $stmt->close();
    } else {
        SecurityLogger::logSecurityEvent('DATABASE_ERROR', "Failed to prepare delete statement for user ID: $user_id via AJAX", $_SESSION['user_id'], 'MEDIUM');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $link->error]);
    }
    
    $link->close();
    exit;
}

// Handle GET request (legacy support)
$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    SecurityLogger::logSecurityEvent('INVALID_REQUEST', 'User deletion attempted without user ID via GET', $_SESSION['user_id'], 'MEDIUM');
    header("location: user_management.php");
    exit;
}

// Prevent admin from deleting themselves
if ($user_id == $_SESSION['user_id']) {
    SecurityLogger::logSecurityEvent('SECURITY_VIOLATION', 'Admin attempted to delete their own account via GET', $_SESSION['user_id'], 'HIGH');
    header("location: user_management.php?error=self_delete");
    exit;
}

// Get user info before deletion for logging
$user_info_sql = "SELECT username, role FROM users WHERE id = ?";
if ($info_stmt = $link->prepare($user_info_sql)) {
    $info_stmt->bind_param("i", $user_id);
    $info_stmt->execute();
    $result = $info_stmt->get_result();
    $user_info = $result->fetch_assoc();
    $info_stmt->close();
}

// Delete the user
$sql = "DELETE FROM users WHERE id = ?";
if ($stmt = $link->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        SecurityLogger::logSecurityEvent('USER_DELETED', "Admin deleted user via GET: {$user_info['username']} (Role: {$user_info['role']})", $_SESSION['user_id'], 'HIGH');
        header("location: user_management.php?deleted=1");
    } else {
        SecurityLogger::logSecurityEvent('DELETE_FAILED', "Failed to delete user ID: $user_id via GET", $_SESSION['user_id'], 'MEDIUM');
        header("location: user_management.php?error=delete_failed");
    }
    $stmt->close();
} else {
    SecurityLogger::logSecurityEvent('DATABASE_ERROR', "Failed to prepare delete statement for user ID: $user_id via GET", $_SESSION['user_id'], 'MEDIUM');
    header("location: user_management.php?error=prepare_failed");
}

$link->close();
?>
