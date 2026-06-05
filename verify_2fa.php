<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

// Check if user is in 2FA verification state
if (!isset($_SESSION['2fa_user_id']) || !isset($_SESSION['2fa_username'])) {
    header('Location: login.php');
    exit();
}

$twoFA = new TwoFactorAuth();
$logger = new SecurityLogger();
$userId = $_SESSION['2fa_user_id'];
$username = $_SESSION['2fa_username'];

$error = '';
$attempts = $_SESSION['2fa_attempts'] ?? 0;
const MAX_ATTEMPTS = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verification_code'])) {
        $code = trim($_POST['verification_code']);
        
        if (empty($code)) {
            $error = 'Please enter a verification code.';
        } else {
            // Get user's 2FA secret
            $secret = $twoFA->getUserSecret($userId);
            
            if ($secret) {
                $isValid = false;
                $codeType = 'totp';
                
                // First try TOTP verification
                if ($twoFA->verifyTOTP($secret, $code)) {
                    $isValid = true;
                } else {
                    // Try backup code verification
                    if ($twoFA->verifyBackupCode($userId, $code)) {
                        $isValid = true;
                        $codeType = 'backup';
                    }
                }
                
                if ($isValid) {
                    // Log successful 2FA verification
                    $logger->logEvent(
                        $userId,
                        'two_factor_success',
                        'Two-factor authentication successful',
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        ['code_type' => $codeType]
                    );
                    
                    // Complete login process
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;

                    try {
                        $stmtU = $pdo->prepare("SELECT id, username, email, role, first_name, last_name, platoon FROM users WHERE id = ? LIMIT 1");
                        $stmtU->execute([$userId]);
                        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
                        if ($u) {
                            $_SESSION['username'] = $u['username'] ?? $_SESSION['username'];
                            $_SESSION['email'] = $u['email'] ?? ($_SESSION['email'] ?? null);
                            $_SESSION['role'] = $u['role'] ?? ($_SESSION['role'] ?? 'cadet');
                            $_SESSION['first_name'] = $u['first_name'] ?? ($_SESSION['first_name'] ?? '');
                            $_SESSION['last_name'] = $u['last_name'] ?? ($_SESSION['last_name'] ?? '');
                            $_SESSION['platoon'] = $u['platoon'] ?? ($_SESSION['platoon'] ?? '');
                            $computedFullName = trim((string)($_SESSION['first_name'] ?? '') . ' ' . (string)($_SESSION['last_name'] ?? ''));
                            $_SESSION['full_name'] = $computedFullName !== '' ? $computedFullName : ($_SESSION['username'] ?? $username);
                        }
                    } catch (Throwable $e) {
                        // continue with existing session values
                    }
                    $_SESSION['pin_verified'] = false;
                    $_SESSION['require_pin'] = false;
                    
                    // Update last login time
                    $currentTime = date('Y-m-d H:i:s');
                    $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                    $stmt->execute([$currentTime, $userId]);

                    try {
                        ensure_term_enrollment_schema();
                        ensure_user_security_row($userId);
                        $sec = get_user_security($userId);
                        $hasPin = $sec && !empty($sec['pin_hash']);
                        if ($hasPin) {
                            $_SESSION['require_pin'] = true;
                            $_SESSION['post_pin_redirect'] = null;
                        }
                    } catch (Throwable $e) {
                        // If term/pin tables are unavailable, continue without PIN requirement
                    }

                    try {
                        $t = get_active_term();
                        $sy = $t['school_year'] ?? '';
                        $sem = $t['semester'] ?? '';
                        $role = $_SESSION['role'] ?? '';
                        if ($sem === '2nd' && in_array($role, ['cadet', 'basic_cadet', 'basic-cadet'], true)) {
                            $stmtCp = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ? LIMIT 1");
                            $stmtCp->execute([(int)$userId]);
                            $cpid = (int)($stmtCp->fetchColumn() ?: 0);
                            if ($cpid > 0) {
                                $enroll = get_cadet_enrollment_status($cpid, $sy, $sem);
                                if ($enroll !== 'enrolled') {
                                    $_SESSION['require_pin'] = false;
                                    header('Location: cadet_reenroll.php');
                                    exit();
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        // ignore reenrollment guard on failure
                    }
                    
                    // Create user session record
                    $sessionToken = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $sessionToken,
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        $expiresAt
                    ]);
                    
                    $_SESSION['session_token'] = $sessionToken;
                    
                    // Clean up 2FA session variables
                    unset($_SESSION['2fa_user_id']);
                    unset($_SESSION['2fa_username']);
                    unset($_SESSION['2fa_attempts']);
                    
                    // Redirect to dashboard
                    if (isset($_SESSION['require_pin']) && $_SESSION['require_pin'] === true) {
                        header('Location: verify_pin.php');
                        exit();
                    }
                    redirect_to_dashboard();
                    exit();
                } else {
                    // Invalid code
                    $attempts++;
                    $_SESSION['2fa_attempts'] = $attempts;
                    
                    // Log failed 2FA attempt
                    $logger->logEvent(
                        $userId,
                        'two_factor_failed',
                        'Two-factor authentication failed',
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        ['attempts' => $attempts]
                    );
                    
                    if ($attempts >= MAX_ATTEMPTS) {
                        // Too many failed attempts, lock account temporarily
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $stmt = $pdo->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
                        $stmt->execute([$lockUntil, $userId]);
                        
                        // Log account lock
                        $logger->logEvent(
                            $userId,
                            'account_locked',
                            'Account locked due to too many failed 2FA attempts',
                            $_SERVER['REMOTE_ADDR'],
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        );
                        
                        // Clean up session
                        unset($_SESSION['2fa_user_id']);
                        unset($_SESSION['2fa_username']);
                        unset($_SESSION['2fa_attempts']);
                        
                        header('Location: login.php?error=account_locked');
                        exit();
                    } else {
                        $error = 'Invalid verification code. ' . (MAX_ATTEMPTS - $attempts) . ' attempts remaining.';
                    }
                }
            } else {
                $error = 'Two-factor authentication is not properly configured.';
            }
        }
    }
}

// Check if we should show backup code option
$showBackupOption = $attempts >= 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - ROTC System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-container {
            max-width: 400px;
            width: 100%;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            text-align: center;
            padding: 30px 20px;
        }
        .verification-code {
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            font-family: monospace;
        }
        .btn-verify {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .backup-option {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .attempts-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-container mx-auto">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-shield-alt fa-2x mb-3"></i>
                    <h4 class="mb-0">Two-Factor Authentication</h4>
                    <p class="mb-0 mt-2 opacity-75">Enter the code from your authenticator app</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($attempts > 0 && $attempts < MAX_ATTEMPTS): ?>
                        <div class="attempts-warning">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <strong>Warning:</strong> <?php echo $attempts; ?> failed attempt(s). 
                            Account will be locked after <?php echo MAX_ATTEMPTS; ?> failed attempts.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="verificationForm">
                        <div class="mb-4">
                            <label for="verification_code" class="form-label">
                                <i class="fas fa-mobile-alt"></i> Verification Code
                            </label>
                            <input type="text" class="form-control verification-code" 
                                   id="verification_code" name="verification_code" 
                                   maxlength="6" pattern="[0-9]{6}" 
                                   placeholder="000000" required autocomplete="off" autofocus>
                            <div class="form-text text-center mt-2">
                                Enter the 6-digit code from your authenticator app
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-verify">
                                <i class="fas fa-check"></i> Verify Code
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($showBackupOption): ?>
                        <div class="backup-option">
                            <div class="text-center">
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-question-circle"></i> 
                                    Can't access your authenticator app?
                                </p>
                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                        onclick="toggleBackupCode()">
                                    <i class="fas fa-key"></i> Use Backup Code
                                </button>
                            </div>
                            
                            <div id="backupCodeForm" style="display: none;" class="mt-3">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="backup_code" class="form-label">
                                            <i class="fas fa-key"></i> Backup Code
                                        </label>
                                        <input type="text" class="form-control text-center" 
                                               id="backup_code" name="verification_code" 
                                               placeholder="Enter backup code" autocomplete="off">
                                        <div class="form-text text-center">
                                            Enter one of your saved backup codes
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="fas fa-check"></i> Verify Backup Code
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-link text-muted">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <small class="text-white-50">
                    <i class="fas fa-shield-alt"></i> 
                    Your account is protected by two-factor authentication
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-format verification code input
        document.getElementById('verification_code').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Auto-submit when 6 digits are entered
        document.getElementById('verification_code').addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                setTimeout(() => {
                    document.getElementById('verificationForm').submit();
                }, 500);
            }
        });
        
        // Toggle backup code form
        function toggleBackupCode() {
            const backupForm = document.getElementById('backupCodeForm');
            const mainForm = document.getElementById('verificationForm');
            
            if (backupForm.style.display === 'none') {
                backupForm.style.display = 'block';
                mainForm.style.display = 'none';
                document.getElementById('backup_code').focus();
            } else {
                backupForm.style.display = 'none';
                mainForm.style.display = 'block';
                document.getElementById('verification_code').focus();
            }
        }
        
        // Auto-format backup code input
        document.getElementById('backup_code')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        });
        
        // Focus on input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('verification_code').focus();
        });
    </script>
</body>
</html>