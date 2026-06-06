<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Admin-only access
check_login();
if (!rotc_role_in(['admin'])) {
    $securityLogger = new SecurityLogger($pdo);
    $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access account management', 'high');
    header('Location: ' . rotc_relative_url('login.php'));
    exit();
}

// Log admin access to account management
$securityLogger = new SecurityLogger($pdo);
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_ACCESS', 'Admin accessed account management', 'low');

// Handle unlock account action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_account') {
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId > 0) {
        try {
            // Reset failed login attempts and unlock account
            $stmt = $pdo->prepare("UPDATE user_security SET failed_pin_attempts = 0, pin_locked_until = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Also reset any login attempts in users table if that column exists
            try {
                $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                $stmt->execute([$userId]);
            } catch (Exception $e) {
                // Column might not exist, that's okay
            }
            
            // Log the unlock action
            $securityLogger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_ACCOUNT_UNLOCK', 'Admin unlocked user account', ['target_user' => $userId], 'medium');
            
            $success = "Account unlocked successfully!";
        } catch (Exception $e) {
            $error = "Error unlocking account: " . $e->getMessage();
        }
    }
}

// Get all users with their security status - check multiple possible lock mechanisms
$users_query = "
    SELECT 
        u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.status, u.created_at,
        COALESCE(us.failed_pin_attempts, 0) as failed_pin_attempts,
        COALESCE(u.failed_login_attempts, 0) as failed_login_attempts,
        us.pin_locked_until,
        u.locked_until,
        CASE 
            WHEN (us.pin_locked_until IS NOT NULL AND us.pin_locked_until > NOW()) OR 
                 (u.locked_until IS NOT NULL AND u.locked_until > NOW()) THEN 'Locked'
            WHEN COALESCE(us.failed_pin_attempts, 0) > 0 OR COALESCE(u.failed_login_attempts, 0) > 0 THEN 
                'Failed Attempts: ' || GREATEST(COALESCE(us.failed_pin_attempts, 0), COALESCE(u.failed_login_attempts, 0))
            ELSE 'Good'
        END as lock_status,
        GREATEST(
            COALESCE(us.pin_locked_until, '1970-01-01'),
            COALESCE(u.locked_until, '1970-01-01')
        ) as latest_lock_until
    FROM users u
    LEFT JOIN user_security us ON u.id = us.user_id
    ORDER BY u.created_at DESC
";

$users = $pdo->query($users_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-locked {
            background: rgba(248, 113, 113, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(248, 113, 113, 0.5);
        }
        .status-warning {
            background: rgba(251, 191, 36, 0.2);
            color: #fde047;
            border: 1px solid rgba(251, 191, 36, 0.5);
        }
        .status-good {
            background: rgba(52, 211, 153, 0.2);
            color: #86efac;
            border: 1px solid rgba(52, 211, 153, 0.5);
        }
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-unlock {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.5);
        }
        .btn-force-unlock {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.5);
        }
        .btn-force-unlock:hover {
            background: rgba(239, 68, 68, 0.3);
            transform: translateY(-1px);
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        .users-table th {
            background: rgba(30, 64, 175, 0.1);
            font-weight: 600;
            color: #e2e8f0;
        }
        .users-table tr:hover {
            background: rgba(30, 64, 175, 0.05);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success {
            background: rgba(52, 211, 153, 0.2);
            border: 1px solid rgba(52, 211, 153, 0.5);
            color: #86efac;
        }
        .alert-warning {
            background: rgba(251, 191, 36, 0.2);
            border: 1px solid rgba(251, 191, 36, 0.5);
            color: #fde047;
        }
        .debug-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #93c5fd;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
        $NAV_BASE = '';
        include 'includes/admin_nav.php'; 
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-user-shield"></i> Account Management</h1>
                <p>Manage user accounts, unlock locked accounts, and monitor login security</p>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Debug Notice:</strong> Some accounts may show as "Good" here but still be locked due to different locking mechanisms. 
                    Use "Force Unlock" if regular unlock doesn't work.
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> User Accounts Status</h2>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Account Status</th>
                                    <th>Failed Attempts</th>
                                    <th>Locked Until</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                    <div style="font-size: 0.8rem; color: #94a3b8;">ID: <?php echo $user['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="status-badge" style="background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.5);">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge" style="background: rgba(52, 211, 153, 0.2); color: #86efac; border: 1px solid rgba(52, 211, 153, 0.5);">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = 'status-good';
                                            if ($user['lock_status'] === 'Locked') {
                                                $statusClass = 'status-locked';
                                            } elseif (strpos($user['lock_status'], 'Failed Attempts') !== false) {
                                                $statusClass = 'status-warning';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($user['lock_status']); ?>
                                            </span>
                                            <div class="debug-info">
                                                PIN: <?php echo $user['failed_pin_attempts']; ?> | Login: <?php echo $user['failed_login_attempts']; ?>
                                            </div>
                                        </td>
                                        <td><?php echo $user['failed_pin_attempts'] ?? '0'; ?></td>
                                        <td>
                                            <?php 
                                            if ($user['latest_lock_until'] && $user['latest_lock_until'] !== '1970-01-01') {
                                                $lockedUntil = new DateTime($user['latest_lock_until']);
                                                $now = new DateTime();
                                                if ($lockedUntil > $now) {
                                                    echo htmlspecialchars($lockedUntil->format('M j, Y H:i'));
                                                } else {
                                                    echo '<span style="color: #86efac;">Not locked</span>';
                                                }
                                            } else {
                                                echo '<span style="color: #86efac;">Never</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                <?php if ($user['lock_status'] === 'Locked'): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to unlock this account?');">
                                                        <input type="hidden" name="action" value="unlock_account">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-unlock">
                                                            <i class="fas fa-unlock"></i> Unlock
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Force unlock will reset ALL lock mechanisms. Continue?');">
                                                        <input type="hidden" name="action" value="unlock_account">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-force-unlock">
                                                            <i class="fas fa-unlock-alt"></i> Force
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: #64748b; font-size: 0.8rem;">
                                                        <i class="fas fa-check-circle"></i> OK
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="js/mobile-navigation.js"></script>
    <script>
        // Auto-refresh locked accounts every 30 seconds
        setInterval(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>
