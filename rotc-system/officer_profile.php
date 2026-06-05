<?php
require_once 'includes/session.php';

// Officer-only access
check_login();
$allowed_roles = ['1cl', '2cl'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    redirect_to_dashboard();
}

// Get officer role for display
$officer_role = $_SESSION["role"];
$role_display = ($officer_role == '1cl') ? 'First Class Officer' : 'Second Class Officer';

$page_title = 'Officer Profile';
include 'includes/header.php';

// Fetch officer data
require_once 'includes/db.php';
$user_id = $_SESSION['id'];
$sql = "SELECT username, email, role, created_at FROM users WHERE id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$officer_data = $result->fetch_assoc();
$stmt->close();
$link->close();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Officer Profile Card -->
            <div class="card shadow mb-4 border-left-<?php echo ($officer_role == '1cl') ? 'primary' : 'success'; ?>">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-<?php echo ($officer_role == '1cl') ? 'primary' : 'success'; ?>"><?php echo htmlspecialchars($role_display); ?> Profile</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="avatar-circle mx-auto" style="width: 150px; height: 150px; background-color: <?php echo ($officer_role == '1cl') ? '#4e73df' : '#1cc88a'; ?>; border-radius: 50%; display: flex; justify-content: center; align-items: center;">
                                <span class="initials" style="color: white; font-size: 60px;"><?php echo strtoupper(substr($officer_data['username'], 0, 1)); ?></span>
                            </div>
                            <h4 class="mt-3"><?php echo htmlspecialchars($officer_data['username']); ?></h4>
                            <span class="badge badge-<?php echo ($officer_role == '1cl') ? 'primary' : 'success'; ?>"><?php echo htmlspecialchars($role_display); ?></span>
                        </div>
                        <div class="col-md-8">
                            <h4>Officer Information</h4>
                            <hr>
                            <div class="row mb-3">
                                <div class="col-md-4 font-weight-bold">Email:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($officer_data['email']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 font-weight-bold">Role:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($role_display); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 font-weight-bold">Account Created:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars(date('F j, Y', strtotime($officer_data['created_at']))); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 font-weight-bold">Status:</div>
                                <div class="col-md-8"><span class="badge badge-pill badge-success">Active</span></div>
                            </div>
                            
                            <?php if ($officer_role == '1cl'): ?>
                            <div class="alert alert-danger mt-4">
                                <h5 class="alert-heading"><i class="fas fa-shield-alt"></i> Command Privileges</h5>
                                <p>As a First Class Officer, you have full command privileges over the battalion management system.</p>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success mt-4">
                                <h5 class="alert-heading"><i class="fas fa-user-shield"></i> Officer Privileges</h5>
                                <p>As a Second Class Officer, you have access to cadet management and attendance tracking.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="dashboard/officer.php" class="btn btn-<?php echo ($officer_role == '1cl') ? 'primary' : 'success'; ?>"><i class="fas fa-tachometer-alt"></i> Return to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>