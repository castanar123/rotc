<?php
// Debug version of login.php with comprehensive logging
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug function to log information
function debug_log($message, $data = null) {
    echo "<div style='background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin: 5px 0; font-family: monospace;'>";
    echo "<strong>DEBUG:</strong> " . htmlspecialchars($message);
    if ($data !== null) {
        echo "<br><pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    }
    echo "</div>";
}

debug_log("Starting login debug session");
debug_log("Request method", $_SERVER['REQUEST_METHOD']);
debug_log("POST data received", $_POST);
debug_log("Session data", $_SESSION);

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    debug_log("User already logged in, redirecting to dashboard");
    redirect_to_dashboard();
}

// Handle account locked message
if (isset($_GET['error']) && $_GET['error'] === 'account_locked') {
    $errors[] = 'Account temporarily locked due to too many failed login attempts. Please try again in 15 minutes.';
}

$errors = [];
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("Processing POST request");
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    debug_log("Username received", $username);
    debug_log("Password length", strlen($password));
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username or email is required';
        debug_log("Validation error: Username empty");
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
        debug_log("Validation error: Password empty");
    }
    
    if (empty($errors)) {
        debug_log("Validation passed, proceeding with authentication");
        
        try {
            $twoFA = new TwoFactorAuth();
            $logger = new SecurityLogger();
            debug_log("Security objects initialized");
            
            // Check if user exists (by username or email)
            $sql = "SELECT id as user_id, username, email, password, role, failed_login_attempts, locked_until FROM users WHERE username = ? OR email = ?";
            debug_log("SQL Query", $sql);
            debug_log("Query parameters", [$username, $username]);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            debug_log("Database query executed");
            debug_log("User found", $user ? 'Yes' : 'No');
            
            if ($user) {
                debug_log("User data retrieved", [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'failed_attempts' => $user['failed_login_attempts'],
                    'locked_until' => $user['locked_until']
                ]);
                
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    debug_log("Account is locked until", $user['locked_until']);
                    $errors[] = 'Account is temporarily locked. Please try again later.';
                } else {
                    debug_log("Account not locked, proceeding with password verification");
                    
                    // Clear lock if expired
                    if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
                        debug_log("Clearing expired lock");
                        $stmt = $pdo->prepare("UPDATE users SET locked_until = NULL, failed_login_attempts = 0 WHERE id = ?");
                        $stmt->execute([$user['user_id']]);
                    }
                    
                    debug_log("Verifying password");
                    debug_log("Stored password hash", substr($user['password'], 0, 20) . '...');
                    
                    $password_valid = password_verify($password, $user['password']);
                    debug_log("Password verification result", $password_valid ? 'VALID' : 'INVALID');
                    
                    if ($password_valid) {
                        debug_log("Password verified successfully");
                        
                        // Reset failed login attempts on successful password verification
                        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                        $stmt->execute([$user['user_id']]);
                        debug_log("Reset failed login attempts");
                        
                        // Log successful login attempt
                        $logger->logLoginAttempt(
                            $user['user_id'],
                            $user['username'],
                            true
                        );
                        debug_log("Logged successful login attempt");
                        
                        // Check if 2FA is enabled
                        $twofa_enabled = $twoFA->is2FAEnabled($user['user_id']);
                        debug_log("2FA enabled", $twofa_enabled ? 'Yes' : 'No');
                        
                        if ($twofa_enabled) {
                            debug_log("Redirecting to 2FA verification");
                            // Store user info in session for 2FA verification
                            $_SESSION['2fa_user_id'] = $user['user_id'];
                            $_SESSION['2fa_username'] = $user['username'];
                            $_SESSION['2fa_attempts'] = 0;
                            
                            // Redirect to 2FA verification
                            header('Location: verify_2fa.php');
                            exit();
                        } else {
                            debug_log("Completing login without 2FA");
                            
                            // Complete login without 2FA
                            $_SESSION['loggedin'] = true;
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];
                            
                            debug_log("Session variables set", [
                                'loggedin' => $_SESSION['loggedin'],
                                'user_id' => $_SESSION['user_id'],
                                'username' => $_SESSION['username'],
                                'role' => $_SESSION['role']
                            ]);
                            
                            // Update last login time
                            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $stmt->execute([$user['user_id']]);
                            debug_log("Updated last login time");
                            
                            // Create user session record
                            $sessionToken = bin2hex(random_bytes(32));
                            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                            
                            debug_log("Creating session record", [
                                'session_token' => substr($sessionToken, 0, 10) . '...',
                                'expires_at' => $expiresAt
                            ]);
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $user['user_id'],
                                $sessionToken,
                                $_SERVER['REMOTE_ADDR'],
                                $_SERVER['HTTP_USER_AGENT'] ?? '',
                                $expiresAt
                            ]);
                            
                            $_SESSION['session_token'] = $sessionToken;
                            debug_log("Session token stored in session");
                            
                            // Log the login activity (skip if audit_logs table doesn't exist)
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO audit_logs (user_id, action, ip_address, user_agent) 
                                    VALUES (?, 'login', ?, ?)
                                ");
                                $stmt->execute([
                                    $user['user_id'], 
                                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                                ]);
                                debug_log("Audit log entry created");
                            } catch (Exception $e) {
                                debug_log("Audit log creation failed (table may not exist)", $e->getMessage());
                            }
                            
                            debug_log("Login successful, redirecting to dashboard");
                            
                            // Show success message instead of redirecting for debugging
                            $success_message = "Login successful! User ID: {$user['user_id']}, Role: {$user['role']}";
                            debug_log("SUCCESS: Login completed successfully");
                            
                            // Uncomment the line below to enable actual redirect
                            // redirect_to_dashboard();
                        }
                    } else {
                        debug_log("Password verification failed");
                        
                        // Increment failed login attempts
                        $failedAttempts = $user['failed_login_attempts'] + 1;
                        $lockUntil = null;
                        
                        // Lock account after 5 failed attempts
                        if ($failedAttempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                            debug_log("Account will be locked until", $lockUntil);
                        }
                        
                        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$failedAttempts, $lockUntil, $user['user_id']]);
                        debug_log("Updated failed login attempts", $failedAttempts);
                        
                        // Log failed login attempt
                        $logger->logLoginAttempt(
                            $user['user_id'],
                            $user['username'],
                            false,
                            'Invalid password'
                        );
                        
                        if ($lockUntil) {
                            $errors[] = 'Too many failed login attempts. Account locked for 15 minutes.';
                        } else {
                            $errors[] = 'Invalid username/email or password. ' . (5 - $failedAttempts) . ' attempts remaining.';
                        }
                    }
                }
            } else {
                debug_log("User not found in database");
                
                // Log failed login attempt for non-existent user
                $logger->logLoginAttempt(
                    null,
                    $username,
                    false,
                    'User not found'
                );
                
                $errors[] = 'Invalid username/email or password';
            }
        } catch (Exception $e) {
            debug_log("Exception occurred", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $errors[] = 'Login failed. Please try again.';
        }
    } else {
        debug_log("Validation errors found", $errors);
    }
}

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success_message = 'Registration successful! Your application is pending approval. You will be notified once your account is activated.';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Debug - ROTC Cadet Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a87; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .debug { background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin: 5px 0; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login Debug Page</h1>
        <p><strong>Note:</strong> This is a debug version with detailed logging. Use test_admin / admin123 for testing.</p>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <h4>Authentication Failed</h4>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success">
                <h4>Success!</h4>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <hr>
        <h3>Test Credentials:</h3>
        <p><strong>Username:</strong> test_admin<br><strong>Password:</strong> admin123</p>
        
        <hr>
        <h3>Quick Test Links:</h3>
        <p><a href="login.php">Go to Regular Login Page</a></p>
    </div>
</body>
</html>