<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to edit user', $_SESSION['user_id'] ?? null, 'HIGH');
    redirect_to_dashboard();
}

// Log admin access to user editing
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed user editing page', $_SESSION['user_id'], 'MEDIUM');

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("location: admin_dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $sql = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("sssi", $username, $email, $role, $user_id);
        if ($stmt->execute()) {
            SecurityLogger::logSecurityEvent('USER_MODIFIED', "Admin updated user ID: {$user_id} (username: {$username}, role: {$role})", $_SESSION['user_id'], 'MEDIUM');
            header("location: admin_dashboard.php?success=1");
        } else {
            SecurityLogger::logSecurityEvent('USER_MODIFICATION_FAILED', "Failed to update user ID: {$user_id} - " . $link->error, $_SESSION['user_id'], 'HIGH');
            echo "Error updating record: " . $link->error;
        }
        $stmt->close();
    }
} else {
    // Fetch current user data
    $sql = "SELECT username, email, role FROM users WHERE id = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($username, $email, $role);
        $stmt->fetch();
        $stmt->close();
    } else {
        die('Error fetching user data.');
    }
}

$page_title = 'Edit User';
include 'includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h2 class="display-5">Edit User</h2>
        <p>Modify the details for user ID: <?php echo htmlspecialchars($user_id); ?></p>
        <hr>

        <form action="edit_user.php?id=<?php echo $user_id; ?>" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">User Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="basic_cadet" <?php echo ($role == 'basic_cadet') ? 'selected' : ''; ?>>Basic Cadet</option>
                    <option value="2cl" <?php echo ($role == '2cl') ? 'selected' : ''; ?>>2CL</option>
                    <option value="1cl" <?php echo ($role == '1cl') ? 'selected' : ''; ?>>1CL</option>
                    <option value="commandant" <?php echo ($role == 'commandant') ? 'selected' : ''; ?>>Commandant</option>
                    <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
