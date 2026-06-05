<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$twoFA = new TwoFactorAuth();
$logger = new SecurityLogger();
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$message = '';
$error = '';
$step = $_GET['step'] ?? '1';

// Check if 2FA is already enabled
$is2FAEnabled = $twoFA->is2FAEnabled($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_secret':
                $secret = $twoFA->generateSecret();
                $_SESSION['temp_2fa_secret'] = $secret;
                $step = '2';
                break;
                
            case 'verify_setup':
                if (isset($_SESSION['temp_2fa_secret']) && isset($_POST['verification_code'])) {
                    $secret = $_SESSION['temp_2fa_secret'];
                    $code = $_POST['verification_code'];
                    
                    if ($twoFA->verifyTOTP($secret, $code)) {
                        if ($twoFA->enable2FA($userId, $secret)) {
                            // Generate backup codes
                            $backupCodes = $twoFA->generateBackupCodes($userId);
                            $_SESSION['backup_codes'] = $backupCodes;
                            unset($_SESSION['temp_2fa_secret']);
                            $step = '3';
                            $message = 'Two-factor authentication has been successfully enabled!';
                        } else {
                            $error = 'Failed to enable two-factor authentication. Please try again.';
                        }
                    } else {
                        $error = 'Invalid verification code. Please try again.';
                    }
                } else {
                    $error = 'Missing verification code or secret.';
                }
                break;
                
            case 'disable_2fa':
                if (isset($_POST['current_password'])) {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($_POST['current_password'], $user['password'])) {
                        if ($twoFA->disable2FA($userId)) {
                            $message = 'Two-factor authentication has been disabled.';
                            $is2FAEnabled = false;
                        } else {
                            $error = 'Failed to disable two-factor authentication.';
                        }
                    } else {
                        $error = 'Invalid password.';
                    }
                } else {
                    $error = 'Password is required to disable 2FA.';
                }
                break;
                
            case 'regenerate_backup_codes':
                if ($is2FAEnabled) {
                    $backupCodes = $twoFA->generateBackupCodes($userId);
                    $_SESSION['backup_codes'] = $backupCodes;
                    $message = 'New backup codes have been generated.';
                }
                break;
        }
    }
}

// Get QR code URL if we have a temp secret
$qrCodeURL = '';
if (isset($_SESSION['temp_2fa_secret'])) {
    $qrCodeURL = $twoFA->getQRCodeURL($username, $_SESSION['temp_2fa_secret']);
}

// Get remaining backup codes count
$remainingBackupCodes = 0;
if ($is2FAEnabled) {
    $remainingBackupCodes = $twoFA->getRemainingBackupCodes($userId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Setup - ROTC System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .setup-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: #6c757d;
        }
        .step.active {
            background-color: #0d6efd;
            color: white;
        }
        .step.completed {
            background-color: #198754;
            color: white;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .backup-codes {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .backup-code {
            font-family: monospace;
            font-size: 14px;
            background-color: white;
            padding: 5px 10px;
            margin: 5px;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            display: inline-block;
        }
        .security-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <div class="card">
                <div class="card-header text-center">
                    <h3><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is2FAEnabled): ?>
                        <!-- 2FA Already Enabled -->
                        <div class="text-center mb-4">
                            <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                            <h4>Two-Factor Authentication is Enabled</h4>
                            <p class="text-muted">Your account is protected with two-factor authentication.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-key fa-2x text-info mb-2"></i>
                                        <h6>Backup Codes</h6>
                                        <p class="small text-muted"><?php echo $remainingBackupCodes; ?> codes remaining</p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="regenerate_backup_codes">
                                            <button type="submit" class="btn btn-sm btn-outline-info">
                                                Regenerate Codes
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                        <h6>Disable 2FA</h6>
                                        <p class="small text-muted">Remove two-factor authentication</p>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disable2FAModal">
                                            Disable 2FA
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (isset($_SESSION['backup_codes'])): ?>
                            <div class="backup-codes">
                                <h5><i class="fas fa-key"></i> Your Backup Codes</h5>
                                <div class="security-warning">
                                    <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
                                    Save these backup codes in a safe place. Each code can only be used once.
                                </div>
                                <div class="text-center">
                                    <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                                        <span class="backup-code"><?php echo htmlspecialchars($code); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <button onclick="printBackupCodes()" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-print"></i> Print Codes
                                    </button>
                                    <button onclick="downloadBackupCodes()" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-download"></i> Download Codes
                                    </button>
                                </div>
                            </div>
                            <?php unset($_SESSION['backup_codes']); ?>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- 2FA Setup Process -->
                        <?php if ($step === '1'): ?>
                            <!-- Step 1: Introduction -->
                            <div class="step-indicator">
                                <div class="step active">1</div>
                                <div class="step">2</div>
                                <div class="step">3</div>
                            </div>
                            
                            <div class="text-center mb-4">
                                <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                                <h4>Secure Your Account</h4>
                                <p class="text-muted">Add an extra layer of security to your account with two-factor authentication.</p>
                            </div>
                            
                            <div class="security-warning">
                                <h6><i class="fas fa-info-circle"></i> What you'll need:</h6>
                                <ul class="mb-0">
                                    <li>A smartphone or tablet</li>
                                    <li>An authenticator app (Google Authenticator, Authy, etc.)</li>
                                    <li>A few minutes to complete the setup</li>
                                </ul>
                            </div>
                            
                            <div class="text-center mt-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="generate_secret">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-arrow-right"></i> Start Setup
                                    </button>
                                </form>
                            </div>
                            
                        <?php elseif ($step === '2'): ?>
                            <!-- Step 2: QR Code Scan -->
                            <div class="step-indicator">
                                <div class="step completed">1</div>
                                <div class="step active">2</div>
                                <div class="step">3</div>
                            </div>
                            
                            <h4 class="text-center mb-4">Scan QR Code</h4>
                            
                            <div class="qr-code">
                                <div id="qrcode"></div>
                                <p class="mt-3"><strong>Manual Entry:</strong></p>
                                <code><?php echo htmlspecialchars($_SESSION['temp_2fa_secret']); ?></code>
                            </div>
                            
                            <div class="security-warning">
                                <h6><i class="fas fa-mobile-alt"></i> Instructions:</h6>
                                <ol class="mb-0">
                                    <li>Open your authenticator app</li>
                                    <li>Scan the QR code above or enter the code manually</li>
                                    <li>Enter the 6-digit code from your app below</li>
                                </ol>
                            </div>
                            
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="action" value="verify_setup">
                                <div class="mb-3">
                                    <label for="verification_code" class="form-label">Verification Code</label>
                                    <input type="text" class="form-control text-center" id="verification_code" 
                                           name="verification_code" maxlength="6" pattern="[0-9]{6}" 
                                           placeholder="000000" required autocomplete="off">
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Verify & Enable
                                    </button>
                                    <a href="?step=1" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </a>
                                </div>
                            </form>
                            
                        <?php elseif ($step === '3'): ?>
                            <!-- Step 3: Success -->
                            <div class="step-indicator">
                                <div class="step completed">1</div>
                                <div class="step completed">2</div>
                                <div class="step completed">3</div>
                            </div>
                            
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h4>Setup Complete!</h4>
                                <p class="text-muted">Two-factor authentication is now enabled for your account.</p>
                            </div>
                            
                            <?php if (isset($_SESSION['backup_codes'])): ?>
                                <div class="backup-codes">
                                    <h5><i class="fas fa-key"></i> Your Backup Codes</h5>
                                    <div class="security-warning">
                                        <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
                                        Save these backup codes in a safe place. Each code can only be used once.
                                    </div>
                                    <div class="text-center">
                                        <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                                            <span class="backup-code"><?php echo htmlspecialchars($code); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <button onclick="printBackupCodes()" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-print"></i> Print Codes
                                        </button>
                                        <button onclick="downloadBackupCodes()" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-download"></i> Download Codes
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-home"></i> Go to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Disable 2FA Modal -->
    <div class="modal fade" id="disable2FAModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Disable Two-Factor Authentication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> Disabling 2FA will make your account less secure.
                        </div>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="disable_2fa">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Disable 2FA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <script>
        // Generate QR code if we have the URL
        <?php if ($qrCodeURL): ?>
        QRCode.toCanvas(document.getElementById('qrcode'), '<?php echo $qrCodeURL; ?>', {
            width: 256,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        });
        <?php endif; ?>
        
        // Auto-format verification code input
        document.getElementById('verification_code')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Print backup codes
        function printBackupCodes() {
            const codes = <?php echo json_encode($_SESSION['backup_codes'] ?? []); ?>;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head><title>ROTC System - Backup Codes</title></head>
                <body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h2>ROTC System - Two-Factor Authentication Backup Codes</h2>
                    <p><strong>Account:</strong> <?php echo htmlspecialchars($username); ?></p>
                    <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
                    <div style="margin: 20px 0; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;">
                        ${codes.map(code => `<div style="font-family: monospace; margin: 5px 0;">${code}</div>`).join('')}
                    </div>
                    <p><em>Keep these codes in a safe place. Each code can only be used once.</em></p>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Download backup codes
        function downloadBackupCodes() {
            const codes = <?php echo json_encode($_SESSION['backup_codes'] ?? []); ?>;
            const content = `ROTC System - Two-Factor Authentication Backup Codes\n\nAccount: <?php echo htmlspecialchars($username); ?>\nGenerated: ${new Date().toLocaleString()}\n\nBackup Codes:\n${codes.join('\n')}\n\nKeep these codes in a safe place. Each code can only be used once.`;
            
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'rotc-backup-codes.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>