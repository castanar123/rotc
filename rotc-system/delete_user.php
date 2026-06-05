<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to delete user', $_SESSION['user_id'] ?? null, 'HIGH');
    redirect_to_dashboard();
}

// Log successful admin access
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed user deletion page', $_SESSION['user_id'], 'MEDIUM');

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    SecurityLogger::logSecurityEvent('INVALID_REQUEST', 'User deletion attempted without user ID', $_SESSION['user_id'], 'MEDIUM');
    header("location: admin_dashboard.php");
    exit;
}

// Prevent admin from deleting themselves
if ($user_id == $_SESSION['id']) {
    SecurityLogger::logSecurityEvent('SECURITY_VIOLATION', 'Admin attempted to delete their own account', $_SESSION['user_id'], 'HIGH');
    header("location: admin_dashboard.php?error=self_delete");
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
        SecurityLogger::logSecurityEvent('USER_DELETED', "Admin deleted user: {$user_info['username']} (Role: {$user_info['role']})", $_SESSION['user_id'], 'HIGH');
        header("location: admin_dashboard.php?deleted=1");
    } else {
        SecurityLogger::logSecurityEvent('DELETE_FAILED', "Failed to delete user ID: $user_id", $_SESSION['user_id'], 'MEDIUM');
        header("location: admin_dashboard.php?error=delete_failed");
    }
    $stmt->close();
} else {
    SecurityLogger::logSecurityEvent('DATABASE_ERROR', "Failed to prepare delete statement for user ID: $user_id", $_SESSION['user_id'], 'MEDIUM');
    header("location: admin_dashboard.php?error=prepare_failed");
}

$link->close();
?>
