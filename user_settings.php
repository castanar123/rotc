<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';

check_login();

$twoFA = new TwoFactorAuth();
$logger = new SecurityLogger();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'enable_2fa':
                try {
                    $secret = $twoFA->generateSecret();
                    $twoFA->enableTwoFactor($user_id, $secret);
                    
                    $qrCodeUrl = $twoFA->getQRCodeUrl($user_id, $_SESSION['username'], $secret);
                    $backupCodes = $twoFA->generateBackupCodes($user_id);
                    
                    $logger->logEvent($user_id, 'two_factor_enabled', '2FA enabled for user');
                    
                    $_SESSION['2fa_setup'] = [
                        'secret' => $secret,
                        'qr_url' => $qrCodeUrl,
                        'backup_codes' => $backupCodes
                    ];
                    
                    header('Location: setup_2fa.php');
                    exit();
                } catch (Exception $e) {
                    $error_message = 'Failed to enable 2FA: ' . $e->getMessage();
                }
                break;
                
            case 'disable_2fa':
                try {
                    $twoFA->disableTwoFactor($user_id);
                    $logger->logEvent($user_id, 'two_factor_disabled', '2FA disabled for user');
                    $success_message = '2FA has been disabled successfully.';
                } catch (Exception $e) {
                    $error_message = 'Failed to disable 2FA: ' . $e->getMessage();
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } elseif (strlen($new_password) < 8) {
                    $error_message = 'Password must be at least 8 characters long.';
                } else {
                    try {
                        // Verify current password
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if ($user && password_verify($current_password, $user['password'])) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                            $stmt->execute([$hashed_password, $user_id]);
                            
                            $logger->logEvent($user_id, 'password_changed', 'User changed password');
                            $success_message = 'Password changed successfully.';
                        } else {
                            $error_message = 'Current password is incorrect.';
                        }
                    } catch (Exception $e) {
                        $error_message = 'Failed to change password: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get user information
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.role, u.created_at, u.last_login,
               cp.first_name, cp.last_name, cp.student_id, cp.course, cp.section
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.user_id = cp.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if 2FA is enabled
    $is2FAEnabled = $twoFA->is2FAEnabled($user_id);
    
    // Get recent security logs
    $recent_logs = $logger->getSecurityLogs($user_id, 10);
    
} catch (Exception $e) {
    $error_message = 'Failed to load user information.';
    $user_info = [];
    $is2FAEnabled = false;
    $recent_logs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .settings-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .security-status {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-enabled {
            background: #27ae60;
        }
        .status-disabled {
            background: #e74c3c;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .info-label {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .log-entry {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-event {
            font-weight: bold;
        }
        .log-time {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="page-header">
            <h1><i class="fas fa-user-cog"></i> User Settings</h1>
            <p>Manage your account settings and security preferences</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- User Information -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-user"></i> User Information
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <div><?php echo htmlspecialchars($user_info['username'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div><?php echo htmlspecialchars($user_info['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user_info['role'] ?? 'N/A'))); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div><?php echo htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '') ?: 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Student ID</div>
                        <div><?php echo htmlspecialchars($user_info['student_id'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Course & Section</div>
                        <div><?php echo htmlspecialchars(($user_info['course'] ?? '') . ' - ' . ($user_info['section'] ?? '') ?: 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div><?php echo $user_info['created_at'] ? date('F j, Y', strtotime($user_info['created_at'])) : 'N/A'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div><?php echo $user_info['last_login'] ? date('F j, Y g:i A', strtotime($user_info['last_login'])) : 'N/A'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Two-Factor Authentication -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-shield-alt"></i> Two-Factor Authentication
            </div>
            <div class="card-body">
                <div class="security-status">
                    <div class="status-indicator <?php echo $is2FAEnabled ? 'status-enabled' : 'status-disabled'; ?>"></div>
                    <strong>2FA Status: <?php echo $is2FAEnabled ? 'Enabled' : 'Disabled'; ?></strong>
                </div>
                
                <p>Two-factor authentication adds an extra layer of security to your account by requiring a verification code from your mobile device.</p>
                
                <?php if ($is2FAEnabled): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.')">
                        <input type="hidden" name="action" value="disable_2fa">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-shield-alt"></i> Disable 2FA
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="enable_2fa">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-shield-alt"></i> Enable 2FA
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-key"></i> Change Password
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                        <small>Password must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Recent Security Activity -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-history"></i> Recent Security Activity
            </div>
            <div class="card-body">
                <?php if (!empty($recent_logs)): ?>
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="log-entry">
                            <div>
                                <div class="log-event"><?php echo htmlspecialchars($log['event_type']); ?></div>
                                <div><?php echo htmlspecialchars($log['description']); ?></div>
                                <div>IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                            </div>
                            <div class="log-time">
                                <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent security activity found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>