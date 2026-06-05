<?php
require_once 'includes/db.php';

// --- Configuration ---
$username_to_reset = 'admin';
$new_password = 'password123';

// Hash the new password for security
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h1>Admin Password Reset</h1>";
echo "<pre>";

// --- Check if the admin user exists ---
$check_stmt = $link->prepare("SELECT id FROM users WHERE username = ?");
$check_stmt->bind_param("s", $username_to_reset);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    die("Error: The user '{$username_to_reset}' was not found in the database.");
}
$user = $check_result->fetch_assoc();
$user_id = $user['id'];
$check_stmt->close();

echo "Found user '{$username_to_reset}'. Proceeding with password reset...\n";

// --- Update the password ---
$update_stmt = $link->prepare("UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $hashed_password, $user_id);

if ($update_stmt->execute()) {
    echo "\nSUCCESS: The password for '{$username_to_reset}' has been reset.\n";
    echo "The new password is: {$new_password}\n";
} else {
    echo "\nERROR: Failed to update the password. " . htmlspecialchars($update_stmt->error);
}

$update_stmt->close();

echo "\nIMPORTANT: For security, please delete this script (reset_admin_password.php) now.";
echo "</pre>";

$link->close();
?>
