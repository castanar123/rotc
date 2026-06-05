<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to add user', $_SESSION['user_id'] ?? null, 'HIGH');
    redirect_to_dashboard();
}

// Log admin access to user creation
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed user creation page', $_SESSION['user_id'], 'MEDIUM');

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error_message = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        if ($check_stmt = $link->prepare($check_sql)) {
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = 'Username or email already exists.';
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_sql = "INSERT INTO users (username, email, password, role, full_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                
                if ($insert_stmt = $link->prepare($insert_sql)) {
                    $insert_stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $full_name);
                    
                    if ($insert_stmt->execute()) {
                        SecurityLogger::logSecurityEvent('USER_CREATED', "Admin created new user: {$username} (role: {$role})", $_SESSION['user_id'], 'MEDIUM');
                        $success_message = 'User created successfully!';
                        // Clear form data
                        $username = $email = $full_name = $role = '';
                    } else {
                        SecurityLogger::logSecurityEvent('USER_CREATION_FAILED', "Failed to create user: {$username} - " . $link->error, $_SESSION['user_id'], 'HIGH');
                        $error_message = 'Error creating user: ' . $link->error;
                    }
                    $insert_stmt->close();
                } else {
                    $error_message = 'Database error: ' . $link->error;
                }
            }
            $check_stmt->close();
        } else {
            $error_message = 'Database error: ' . $link->error;
        }
    }
}

$page_title = 'Add New User';
include 'includes/header.php';
?>

<link rel="stylesheet" href="css/tactical-theme.css">
<link rel="stylesheet" href="css/dashboard-redesigned.css">
<link rel="stylesheet" href="css/mobile-responsive.css">

<body class="tactical-dark">
    <!-- Include Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <div class="title-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="title-text">
                        <h1>Add New User</h1>
                        <p class="subtitle">Create a new user account in the system</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="user_management.php" class="action-btn secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Users
                    </a>
                </div>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="form-container">
            <div class="form-card">
                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <form action="add_user.php" method="post" class="user-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name" class="form-label">
                                <i class="fas fa-user"></i>
                                Full Name
                            </label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user-tag"></i>
                                Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="role" class="form-label">
                                <i class="fas fa-shield-alt"></i>
                                User Role
                            </label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="basic_cadet" <?php echo (isset($role) && $role == 'basic_cadet') ? 'selected' : ''; ?>>Basic Cadet</option>
                                <option value="2cl" <?php echo (isset($role) && $role == '2cl') ? 'selected' : ''; ?>>2CL</option>
                                <option value="1cl" <?php echo (isset($role) && $role == '1cl') ? 'selected' : ''; ?>>1CL</option>
                                <option value="commandant" <?php echo (isset($role) && $role == 'commandant') ? 'selected' : ''; ?>>Commandant</option>
                                <option value="instructor" <?php echo (isset($role) && $role == 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                                <option value="officer" <?php echo (isset($role) && $role == 'officer') ? 'selected' : ''; ?>>Officer</option>
                                <option value="admin" <?php echo (isset($role) && $role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="6" required>
                            <small class="form-help">Minimum 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock"></i>
                                Confirm Password
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   minlength="6" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="action-btn primary">
                            <i class="fas fa-user-plus"></i>
                            Create User
                        </button>
                        <a href="user_management.php" class="action-btn secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .form-card {
        background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-primary);
        padding: var(--spacing-xl);
        box-shadow: var(--shadow-primary);
    }

    .alert {
        padding: var(--spacing-md);
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-lg);
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        font-weight: 600;
    }

    .alert-error {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #dc3545;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.3);
        color: var(--military-green);
    }

    .user-form {
        margin-top: var(--spacing-lg);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        color: var(--text-accent);
        font-weight: 600;
        margin-bottom: var(--spacing-sm);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.9rem;
    }

    .form-control {
        padding: var(--spacing-md);
        background: rgba(15, 20, 25, 0.8);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        color: var(--text-accent);
        font-size: 1rem;
        transition: all var(--transition-fast);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--military-green);
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        background: rgba(15, 20, 25, 0.9);
    }

    .form-control::placeholder {
        color: var(--text-secondary);
    }

    .form-help {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: var(--spacing-xs);
        opacity: 0.8;
    }

    .form-actions {
        display: flex;
        gap: var(--spacing-md);
        justify-content: center;
        padding-top: var(--spacing-xl);
        border-top: 1px solid var(--border-primary);
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }
    }
    </style>

    <script>
    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (password !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });

    // Form validation
    document.querySelector('.user-form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return false;
        }
    });
    </script>

    <!-- Include mobile navigation -->
    <script src="js/mobile-navigation.js"></script>
</body>
</html>