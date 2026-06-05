<?php
// Start output buffering to prevent header issues
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display, only log errors
ini_set('log_errors', 1);

require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

$logger = new SecurityLogger();
$dbAvailable = isset($pdo) && $pdo instanceof PDO;
$dbDebugEnabled = getenv('ROTC_DEBUG') === 'true';

// Debug logging function
function debug_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] login.php: $message");
}

$errors = [];
$success_message = '';

debug_log("Login page accessed");

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    redirect_to_dashboard();
}

// Handle account locked message
if (isset($_GET['error']) && $_GET['error'] === 'account_locked') {
    $errors[] = 'Account temporarily locked due to too many failed login attempts. Please try again in 15 minutes.';
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting: Track login attempts per IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_limit_key = 'login_attempts_' . str_replace(['.', ':'], '_', $client_ip);

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'last_attempt' => 0];
}

$rate_data = $_SESSION[$rate_limit_key];
$current_time = time();

// Reset counter if 15 minutes have passed
if ($current_time - $rate_data['last_attempt'] > 900) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'last_attempt' => $current_time];
}

// Check rate limit (max 20 attempts per 15 minutes per IP)
if ($_SESSION[$rate_limit_key]['count'] >= 20) {
    $errors[] = 'Too many login attempts from this IP. Please try again later.';
    debug_log("Rate limit exceeded for IP: $client_ip");
    $logger->logSecurityEvent(null, 'RATE_LIMIT_EXCEEDED', 'IP rate limit exceeded', ['ip' => $client_ip], 'medium');
}

if (!$dbAvailable) {
    $errors[] = 'The database is not reachable right now. Please check the hosted database settings and try again.';
    if ($dbDebugEnabled && isset($GLOBALS['DB_CONNECTION_ERROR'])) {
        $errors[] = 'Database detail: ' . $GLOBALS['DB_CONNECTION_ERROR'];
    }
    debug_log('Database unavailable on login page');
}

// Handle login form submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    debug_log("POST request received");
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Security validation failed. Please try again.';
        debug_log("CSRF token validation failed");
        $logger->logSecurityEvent(null, 'CSRF_FAILURE', 'Invalid CSRF token in login form', [], 'high');
    } else {
        // Increment rate limit counter
        $_SESSION[$rate_limit_key]['count']++;
        $_SESSION[$rate_limit_key]['last_attempt'] = $current_time;
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        debug_log("Username: $username");
        debug_log("Password length: " . strlen($password));

        if (!$dbAvailable) {
            $errors[] = 'Login cannot continue until the application can connect to the database.';
        }
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username or email is required';
        debug_log("Error: Username is empty");
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
        debug_log("Error: Password is empty");
    }
    
    if (empty($errors)) {
        debug_log("Starting user lookup");
        try {
            $twoFA = new TwoFactorAuth();
            
            // Check if user exists (by username or email)
            $stmt = $pdo->prepare("
                SELECT id as user_id, username, email, password, role, failed_login_attempts, locked_until
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            debug_log("User lookup completed. User found: " . ($user ? 'Yes' : 'No'));
            if ($user) {
                debug_log("User ID: " . $user['user_id']);
                debug_log("User role: " . $user['role']);
                debug_log("Password hash starts with: " . substr($user['password'], 0, 10));
            }
            
            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $errors[] = 'Account is temporarily locked. Please try again later.';
                } else {
                    // Clear lock if expired
                    if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
                        $stmt = $pdo->prepare("UPDATE users SET locked_until = NULL, failed_login_attempts = 0 WHERE id = ?");
                        $stmt->execute([$user['user_id']]);
                    }
                    
                    // Verify password
                    debug_log("Attempting password verification");
                    $password_valid = password_verify($password, $user['password']);
                    debug_log("Password verification result: " . ($password_valid ? 'Success' : 'Failed'));
                    
                    if ($password_valid) {
                        // Reset failed login attempts on successful password verification
                        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                        $stmt->execute([$user['user_id']]);
                        
                        // Log successful login attempt
                        $logger->logLoginAttempt(
                            $user['user_id'],
                            $user['username'],
                            true
                        );
                        
                        // Check if 2FA is enabled
                        if ($twoFA->is2FAEnabled($user['user_id'])) {
                            // Store user info in session for 2FA verification
                            $_SESSION['2fa_user_id'] = $user['user_id'];
                            $_SESSION['2fa_username'] = $user['username'];
                            $_SESSION['2fa_attempts'] = 0;
                            
                            // Redirect to 2FA verification
                            header('Location: verify_2fa.php');
                            exit();
                        } else {
                            // Complete login without 2FA
                            debug_log("Creating session for user: " . $user['username']);
                            $_SESSION['loggedin'] = true;
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                            $_SESSION['pin_verified'] = false;
                            $_SESSION['require_pin'] = false;
                            
                            debug_log("Session created. Role: " . $_SESSION['role']);

                            try {
                                ensure_term_enrollment_schema();
                                ensure_user_security_row($user['user_id']);
                                $sec = get_user_security($user['user_id']);
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
                                if ($sem === '2nd' && in_array($role, ['cadet', 'basic_cadet', 'basic-cadet', 'basic-cadet', 'basic-cadet', 'basic-cadet', 'basic-cadet'], true)) {
                                    $stmtCp = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ? LIMIT 1");
                                    $stmtCp->execute([(int)$user['user_id']]);
                                    $cpid = (int)($stmtCp->fetchColumn() ?: 0);
                                    if ($cpid > 0) {
                                        $enroll = get_cadet_enrollment_status($cpid, $sy, $sem);
                                        if ($enroll !== 'enrolled') {
                                            $_SESSION['require_pin'] = false;
                                            header('Location: cadet_reenroll.php');
                                            exit;
                                        }
                                    }
                                }
                            } catch (Throwable $e) {
                                // ignore reenrollment guard on failure
                            }
                            
                            // Update last login time (database-agnostic)
                            $currentTime = date('Y-m-d H:i:s');
                            $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                            $stmt->execute([$currentTime, $user['user_id']]);
                            
                            // Create user session record
                            $sessionToken = bin2hex(random_bytes(32));
                            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                            
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
                            } catch (Exception $e) {
                                // Ignore if audit_logs table doesn't exist
                            }
                            
                            // Redirect to appropriate dashboard
                            debug_log("Redirecting to dashboard");
                            if (isset($_SESSION['require_pin']) && $_SESSION['require_pin'] === true) {
                                header('Location: verify_pin.php');
                                exit;
                            }
                            redirect_to_dashboard();
                        }
                    } else {
                        // Increment failed login attempts
                        $failedAttempts = $user['failed_login_attempts'] + 1;
                        $lockUntil = null;
                        
                        // Lock account after 5 failed attempts
                        if ($failedAttempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        }
                        
                        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$failedAttempts, $lockUntil, $user['user_id']]);
                        
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
            $errors[] = 'Login failed. Please try again.';
        }
    }
    } // Close CSRF validation else block
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
    <title>Login - ROTC Cadet Management System</title>
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;">
    
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/login-form.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-background">
        <div class="geometric-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
            <div class="shape shape-5"></div>
        </div>
        <div class="particle-field">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="modern-nav">
        <a href="index.php" class="nav-back">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Home</span>
        </a>
    </nav>

    <!-- Main Container -->
    <main class="login-main">
        <div class="login-container">
            <!-- Login Card -->
            <div class="login-card">
                <!-- Header Section -->
                <header class="login-header">
                    <div class="logo-container">
                        <div class="logo-icon">
                            <img src="IMG/MANRILAG.png" alt="Manrilag Logo" class="manrilag-logo">
                        </div>
                        <div class="logo-glow"></div>
                    </div>
                    <h1 class="login-title">Cadet Portal</h1>
                    <p class="login-subtitle">Secure Access to Training Systems</p>
                </header>

                <!-- Alerts Section -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="alert-content">
                            <h4>Authentication Failed</h4>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <div class="alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert-content">
                            <h4>Registration Successful!</h4>
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" class="login-form" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-section">
                        <div class="input-group">
                            <div class="input-wrapper">
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="form-input" 
                                    placeholder=" "
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                    required
                                    autocomplete="username"
                                >
                                <label for="username" class="input-label">Username or Email</label>
                                <div class="input-border"></div>
                                <div class="input-focus-effect"></div>
                            </div>
                        </div>

                        <div class="input-group">
                            <div class="input-wrapper password-wrapper">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-input" 
                                    placeholder=" "
                                    required
                                    autocomplete="current-password"
                                >
                                <label for="password" class="input-label">Password</label>
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                                <div class="input-border"></div>
                                <div class="input-focus-effect"></div>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="checkbox-container">
                                <input type="checkbox" name="remember_me" id="remember_me">
                                <span class="checkbox-custom">
                                    <i class="fas fa-check"></i>
                                </span>
                                <span class="checkbox-label">Remember me</span>
                            </label>
                            
                            <a href="forgot-password.php" class="forgot-link">
                                Forgot Password?
                            </a>
                        </div>

                        <button type="submit" class="login-btn" id="loginBtn">
                            <span class="btn-text">Sign In</span>
                            <span class="btn-icon">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                            <div class="btn-loader">
                                <div class="loader-spinner"></div>
                            </div>
                            <div class="btn-ripple"></div>
                        </button>
                    </div>
                </form>

                <!-- Register Link -->
                <div class="register-section">
                    <p class="register-text">Don't have an account?</p>
                    <a href="register.php" class="register-link">
                        <span>Join the Academy</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Info Panel -->
            <aside class="info-panel">
            <div class="info-card">
                <div class="info-header">
                    <h3>Access Levels</h3>
                </div>
                <div class="access-levels">
                    <div class="access-item">
                        <div class="access-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="access-content">
                            <h4>Basic Cadet</h4>
                            <p>Training modules & attendance</p>
                        </div>
                        <span class="access-badge">L1</span>
                    </div>
                    <div class="access-item">
                        <div class="access-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="access-content">
                            <h4>2CL Officer</h4>
                            <p>Squad management & analytics</p>
                        </div>
                        <span class="access-badge">L2</span>
                    </div>
                    <div class="access-item">
                        <div class="access-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="access-content">
                            <h4>Administrator</h4>
                            <p>Full system access</p>
                        </div>
                        <span class="access-badge">L3</span>
                    </div>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-header">
                    <h3>Core Values</h3>
                </div>
                <div class="values-grid">
                    <div class="value-item">
                        <i class="fas fa-medal"></i>
                        <span>Honor</span>
                    </div>
                    <div class="value-item">
                        <i class="fas fa-fist-raised"></i>
                        <span>Courage</span>
                    </div>
                    <div class="value-item">
                        <i class="fas fa-handshake"></i>
                        <span>Commitment</span>
                    </div>
                    <div class="value-item">
                        <i class="fas fa-balance-scale"></i>
                        <span>Integrity</span>
                    </div>
                </div>
            </div>
            </aside>
        </div>
    </main>

    <script>
        // Modern password toggle with enhanced animations
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            const toggleBtn = document.querySelector('.password-toggle');
            
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'toggle-ripple';
            toggleBtn.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
                toggleBtn.classList.add('active');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
                toggleBtn.classList.remove('active');
            }
        }

        // Enhanced form interactions and animations
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const inputs = document.querySelectorAll('.form-input');
            const submitBtn = document.getElementById('loginBtn');
            const loginCard = document.querySelector('.login-card');
            
            // Entrance animation
            setTimeout(() => {
                loginCard.classList.add('animate-in');
            }, 100);
            
            // Enhanced input interactions
            inputs.forEach((input, index) => {
                const wrapper = input.closest('.input-wrapper');
                
                // Focus effects
                input.addEventListener('focus', function() {
                    wrapper.classList.add('focused');
                    wrapper.classList.add('active');
                });
                
                input.addEventListener('blur', function() {
                    wrapper.classList.remove('active');
                    if (!this.value.trim()) {
                        wrapper.classList.remove('focused');
                    }
                });
                
                // Input validation effects
                input.addEventListener('input', function() {
                    if (this.value.trim()) {
                        wrapper.classList.add('has-value');
                    } else {
                        wrapper.classList.remove('has-value');
                    }
                });
                
                // Check initial values
                if (input.value.trim()) {
                    wrapper.classList.add('focused', 'has-value');
                }
                
                // Staggered animation for inputs
                setTimeout(() => {
                    wrapper.classList.add('animate-in');
                }, 200 + (index * 100));
            });
            
            // Enhanced form submission
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    
                    // Create ripple effect
                    const ripple = submitBtn.querySelector('.btn-ripple');
                    ripple.classList.add('active');
                    
                    // Reset on error
                    const errorAlert = document.querySelector('.alert-error');
                    if (errorAlert) {
                        setTimeout(() => {
                            submitBtn.classList.remove('loading');
                            submitBtn.disabled = false;
                            ripple.classList.remove('active');
                            form.classList.add('shake');
                            setTimeout(() => form.classList.remove('shake'), 500);
                        }, 1000);
                    }
                });
            }
            
            // Particle animation
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                setTimeout(() => {
                    particle.classList.add('animate');
                }, index * 200);
            });
            
            // Geometric shapes animation
            const shapes = document.querySelectorAll('.shape');
            shapes.forEach((shape, index) => {
                setTimeout(() => {
                    shape.classList.add('animate');
                }, index * 300);
            });
        });
    </script>
    <script src="js/login-form.js"></script>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>
