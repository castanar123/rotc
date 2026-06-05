<?php
require_once 'includes/db.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    redirect_to_dashboard();
}

$errors = [];
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username or email is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (empty($errors)) {
        try {
            // Check if user exists (by username or email)
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, u.password, u.role,
                       CONCAT(cp.first_name, ' ', cp.last_name) as full_name, 
                       cp.platoon, cp.status as profile_status
                FROM users u 
                LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
                WHERE u.username = ? OR u.email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Check profile status if exists
                if ($user['profile_status'] === 'Inactive' || $user['profile_status'] === 'inactive') {
                    $errors[] = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    // Successful login
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['platoon'] = $user['platoon'];
                    
                    // Log the login activity (skip if audit_logs table doesn't exist)
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO audit_logs (user_id, action, ip_address, user_agent) 
                            VALUES (?, 'login', ?, ?)
                        ");
                        $stmt->execute([
                            $user['id'], 
                            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                        ]);
                    } catch (Exception $e) {
                        // Ignore if audit_logs table doesn't exist
                    }
                    
                    // Redirect to appropriate dashboard
                    redirect_to_dashboard();
                }
            } else {
                $errors[] = 'Invalid username/email or password';
            }
        } catch (Exception $e) {
            $errors[] = 'Login failed. Please try again.';
        }
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
    <title>Login - ROTC Cadet Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/login-form.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <!-- Background Elements -->
        <div class="background-elements">
            <div class="tactical-grid"></div>
            <div class="floating-elements">
                <div class="element element-1">🛡️</div>
                <div class="element element-2">⭐</div>
                <div class="element element-3">🎖️</div>
                <div class="element element-4">🏅</div>
            </div>
        </div>

        <!-- Main Login Content -->
        <div class="login-content">
            <!-- Header Section -->
            <div class="login-header">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
                
                <div class="logo-section">
                    <div class="logo-badge">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1 class="text-military">ROTC LOGIN</h1>
                    <p class="subtitle">Access Your Command Center</p>
                </div>
            </div>

            <!-- Login Form -->
            <div class="login-form-container">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="alert-content">
                            <h4>Login Failed</h4>
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
                        <i class="fas fa-check-circle"></i>
                        <div class="alert-content">
                            <h4>Registration Successful!</h4>
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i>
                            Username or Email
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Enter your username or email"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="password-input-container">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" id="passwordToggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me" id="remember_me">
                            <span class="checkmark"></span>
                            <span class="checkbox-text">Remember me</span>
                        </label>
                        
                        <a href="forgot-password.php" class="forgot-password-link">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>LOGIN</span>
                        <div class="btn-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </button>
                </form>

                <!-- Role Information -->
                <div class="role-info">
                    <h3>Access Levels</h3>
                    <div class="role-grid">
                        <div class="role-card role-cadet">
                            <div class="role-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h4>Basic Cadet</h4>
                            <p>View profile, attendance, announcements, and grades</p>
                        </div>
                        
                        <div class="role-card role-officer">
                            <div class="role-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h4>2CL Officer</h4>
                            <p>Scanner access, attendance monitoring, announcements</p>
                        </div>
                        
                        <div class="role-card role-admin">
                            <div class="role-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h4>Administrator</h4>
                            <p>Full system access, user management, reports</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Link -->
            <div class="register-section">
                <p>Don't have an account?</p>
                <a href="register.php" class="register-link">
                    <i class="fas fa-user-plus"></i>
                    Register as Cadet
                </a>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="side-panel">
            <div class="panel-content">
                <div class="mission-statement">
                    <h3>MISSION</h3>
                    <p>"To develop citizens of character dedicated to serving the nation and humanity."</p>
                </div>
                
                <div class="core-values">
                    <h3>CORE VALUES</h3>
                    <div class="values-list">
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
                
                <div class="contact-info">
                    <h3>NEED HELP?</h3>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>rotc@university.edu.ph</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>(02) 8123-4567</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/login-form.js"></script>
</body>
</html>
