<?php
require_once 'includes/db.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $facebook_link = trim($_POST['facebook_link'] ?? '');
    
    // Validation
    if (empty($full_name) || empty($course) || empty($facebook_link)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($facebook_link, FILTER_VALIDATE_URL)) {
        $error_message = 'Please enter a valid Facebook URL.';
    } else {
        try {
            // Check for duplicate submissions by name or Facebook link
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM advance_rotc_signups WHERE full_name = ? OR facebook_link = ?");
            $stmt->execute([$full_name, $facebook_link]);
            $duplicate_count = $stmt->fetch()['count'];
            
            if ($duplicate_count > 0) {
                // Check which field is duplicate for specific message
                $stmt = $pdo->prepare("SELECT full_name, facebook_link FROM advance_rotc_signups WHERE full_name = ? OR facebook_link = ?");
                $stmt->execute([$full_name, $facebook_link]);
                $existing = $stmt->fetch();
                
                if ($existing['full_name'] === $full_name) {
                    $error_message = 'You have already submitted your application for Advanced ROTC. Please wait patiently to be contacted and added to the official Advance ROTC group chat.';
                } else {
                    $error_message = 'This Facebook profile has already been used for an Advanced ROTC application. Please use a different Facebook profile or contact the administrator if this is an error.';
                }
            } else {
                // No duplicates found, proceed with insertion
                $stmt = $pdo->prepare("INSERT INTO advance_rotc_signups (full_name, course, facebook_link) VALUES (?, ?, ?)");
                $stmt->execute([$full_name, $course, $facebook_link]);
                $success_message = 'Registration successful! Welcome to Advanced ROTC. Please wait to be contacted and added to the official Advance ROTC group chat.';
                // Clear form data
                $_POST = [];
            }
        } catch (PDOException $e) {
            error_log("Advance ROTC signup error: " . $e->getMessage());
            $error_message = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced ROTC Registration - LSPU-LB ROTC UNIT</title>
    <meta name="description" content="Join the Advanced ROTC Program for elite military leadership training and development.">
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/landing-styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        /* Advanced ROTC Page Specific Styles */
        .advance-rotc-page {
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 50%, var(--dark-olive) 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .advance-rotc-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(40, 167, 69, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(85, 107, 47, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(45, 62, 45, 0.1) 0%, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }

        /* Navigation */
        .advance-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            background: rgba(15, 20, 25, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-primary);
            z-index: 1000;
            transition: all var(--transition-normal);
        }

        .advance-nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-md);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60px;
        }

        .advance-nav-logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .advance-nav-logo img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            filter: drop-shadow(0 0 5px rgba(40, 167, 69, 0.5));
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--spacing-md) var(--spacing-lg);
            position: relative;
            z-index: 1;
            padding-top: 70px;
        }

        /* Back to Home Button - Moved to header area */
        .advance-back-container {
            text-align: center;
            margin-bottom: var(--spacing-md);
            padding-top: var(--spacing-sm);
        }

        .advance-back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            background: linear-gradient(135deg, var(--military-green), #28a745);
            color: var(--tactical-black);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all var(--transition-normal);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 120px;
            max-width: 200px;
            text-align: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .advance-back-link {
                font-size: 0.8rem;
                padding: 6px 12px;
                min-width: 100px;
                max-width: 150px;
            }
        }

        @media (max-width: 480px) {
            .advance-back-link {
                font-size: 0.75rem;
                padding: 6px 10px;
                min-width: 90px;
                max-width: 120px;
                letter-spacing: 0.3px;
            }
        }

        .advance-back-link:hover {
            background: linear-gradient(135deg, #28a745, var(--military-green));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .advance-header {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-lg) 0;
        }

        .advance-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--military-green), #28a745);
            color: var(--tactical-black);
            padding: var(--spacing-md);
            border-radius: 50%;
            margin-bottom: var(--spacing-md);
            font-size: 1.5rem;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.6), var(--shadow-accent);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }

        .advance-logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.8));
        }

        .advance-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-accent);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: var(--spacing-sm);
            text-shadow: 0 0 15px rgba(40, 167, 69, 0.5);
            line-height: 1.1;
        }

        .advance-subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .advance-tagline {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-style: italic;
        }

        .advance-main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-xl);
            align-items: start;
            max-width: 1200px;
            margin: 0 auto;
        }

        .advance-info-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-primary);
            transition: all var(--transition-normal);
            height: fit-content;
        }

        .advance-info-section:hover {
            border-color: var(--border-accent);
            box-shadow: var(--shadow-accent);
        }

        .advance-info-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            color: var(--text-accent);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .advance-info-title i {
            font-size: 1.5rem;
            color: var(--military-green);
        }

        .advance-benefits {
            list-style: none;
            margin-bottom: var(--spacing-lg);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-sm);
        }

        .advance-benefits li {
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 0.95rem;
            transition: color var(--transition-fast);
            padding: var(--spacing-xs);
        }

        .advance-benefits li:hover {
            color: var(--text-primary);
        }

        .advance-benefits li i {
            color: var(--military-green);
            font-size: 1rem;
            width: 16px;
            flex-shrink: 0;
        }

        .advance-form-section {
            background: var(--bg-card);
            backdrop-filter: blur(15px);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-primary);
            position: relative;
            transition: all var(--transition-normal);
            height: fit-content;
        }

        .advance-form-section:hover {
            border-color: var(--border-accent);
            box-shadow: var(--shadow-accent);
        }

        .advance-form-content {
            position: relative;
            z-index: 1;
        }

        .advance-form-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.8rem;
            color: var(--text-accent);
            margin-bottom: var(--spacing-lg);
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .advance-form-group {
            margin-bottom: var(--spacing-md);
        }

        .advance-form-group label {
            display: block;
            color: var(--text-accent);
            margin-bottom: var(--spacing-xs);
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .advance-form-group label i {
            color: var(--military-green);
            font-size: 0.9rem;
        }

        .advance-form-group input {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all var(--transition-normal);
            font-family: 'Rajdhani', sans-serif;
            box-sizing: border-box;
        }

        .advance-form-group input:focus {
            outline: none;
            border-color: var(--border-accent);
            background: var(--bg-secondary);
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
        }

        .advance-form-group input::placeholder {
            color: var(--text-muted);
        }

        .advance-submit-btn {
            width: 100%;
            padding: var(--spacing-md);
            background: linear-gradient(135deg, var(--military-green), #28a745);
            color: var(--tactical-black);
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition-normal);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: var(--spacing-md);
            font-family: 'Orbitron', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-xs);
        }

        .advance-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-accent);
            background: linear-gradient(135deg, #28a745, var(--military-green));
        }

        .advance-submit-btn:active {
            transform: translateY(0);
        }

        .advance-alert {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            font-weight: 600;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
        }

        .advance-alert-success {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
            border: 2px solid rgba(34, 197, 94, 0.3);
        }

        .advance-alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .redirect-btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .redirect-now {
            background: linear-gradient(135deg, var(--military-green), #28a745);
            color: var(--tactical-black);
        }

        .redirect-now:hover {
            background: linear-gradient(135deg, #28a745, var(--military-green));
            transform: translateY(-1px);
        }

        .redirect-cancel {
            background: rgba(107, 114, 128, 0.8);
            color: white;
        }

        .redirect-cancel:hover {
            background: rgba(107, 114, 128, 1);
            transform: translateY(-1px);
            border: 2px solid rgba(239, 68, 68, 0.3);
        }

        .advance-back-link {
            color: var(--text-accent);
            text-decoration: none;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            background: var(--bg-card);
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(10px);
            border: 2px solid var(--border-primary);
            transition: all var(--transition-normal);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .advance-back-link:hover {
            background: var(--bg-secondary);
            border-color: var(--border-accent);
            transform: translateX(-5px);
            box-shadow: var(--shadow-accent);
        }

        .advance-requirements {
            background: rgba(40, 167, 69, 0.1);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            border-left: 3px solid var(--military-green);
            margin-top: var(--spacing-md);
        }

        .advance-requirements h3 {
            color: var(--text-accent);
            margin-bottom: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 1rem;
        }

        .advance-requirements p {
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
                padding: var(--spacing-sm) var(--spacing-md);
            }
            
            .advance-main-content {
                gap: var(--spacing-lg);
            }
        }

        @media (max-width: 992px) {
            .advance-main-content {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
                max-width: 600px;
            }
            
            .advance-title {
                font-size: 2rem;
            }
            
            .advance-benefits {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .advance-nav-container {
                padding: 0 var(--spacing-sm);
                height: 55px;
            }
            
            .advance-nav-logo {
                font-size: 0.9rem;
                letter-spacing: 0.5px;
                gap: var(--spacing-xs);
            }
            
            .advance-nav-logo img {
                width: 24px;
                height: 24px;
            }
            
            .advance-nav-logo span {
                display: block;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 200px;
            }
            
            .container {
                padding-top: 65px;
                padding-left: var(--spacing-sm);
                padding-right: var(--spacing-sm);
            }
            
            .advance-main-content {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .advance-title {
                font-size: 2rem;
            }
            
            .advance-form-title {
                font-size: 1.2rem;
            }
            
            .advance-back-link {
                font-size: 0.85rem;
                padding: var(--spacing-xs) var(--spacing-sm);
            }
        }



        @media (max-width: 480px) {
            .advance-nav-container {
                padding: 0 var(--spacing-xs);
                height: 50px;
            }
            
            .advance-nav-logo {
                font-size: 0.8rem;
                letter-spacing: 0px;
            }
            
            .advance-nav-logo img {
                width: 20px;
                height: 20px;
            }
            
            .advance-nav-logo span {
                max-width: 150px;
            }
            
            .container {
                padding: var(--spacing-xs);
                padding-top: 60px;
            }
            
            .advance-title {
                font-size: 1.5rem;
            }
            
            .advance-form-group input {
                padding: var(--spacing-xs) var(--spacing-sm);
            }
            
            .advance-submit-btn {
                padding: var(--spacing-sm);
                font-size: 0.9rem;
            }
            
            .advance-back-link {
                font-size: 0.8rem;
                padding: var(--spacing-xs) var(--spacing-sm);
            }
        }
    </style>
</head>
<body class="advance-rotc-page">
    <!-- Navigation Header -->
    <nav class="advance-nav">
        <div class="advance-nav-container">
            <div class="advance-nav-logo">
                <img src="IMG/GIDEON.png" alt="GIDEON Logo">
                <span>LSPU-LB ROTC UNIT</span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="advance-back-container">
            <a href="index.php" class="advance-back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
        </div>
        <div class="advance-header">
            <div class="advance-badge">
                <img src="IMG/MANRILAG.png" alt="MANRILAG Logo" class="advance-logo">
            </div>
            <h1 class="advance-title">ADVANCED ROTC</h1>
            <p class="advance-subtitle">Elite Military Leadership Program</p>
            <p class="advance-tagline">"Forging Tomorrow's Military Leaders"</p>
        </div>

        <!-- Success/Error Messages - Outside containers for better visibility -->
        <?php if ($success_message): ?>
            <div class="advance-alert advance-alert-success" id="successAlert" style="max-width: 800px; margin: 0 auto var(--spacing-lg) auto; background: rgba(34, 197, 94, 0.15); border: 2px solid rgba(34, 197, 94, 0.4); box-shadow: 0 4px 20px rgba(34, 197, 94, 0.2);">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(34, 197, 94, 0.3);">
                    <p style="margin: 0 0 10px 0; font-size: 0.9rem;">You will be redirected to the landing page in <span id="countdown">10</span> seconds.</p>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="redirectNow()" class="redirect-btn redirect-now">
                            <i class="fas fa-home"></i> Go to Landing Page
                        </button>
                        <button onclick="cancelRedirect()" class="redirect-btn redirect-cancel">
                            <i class="fas fa-times"></i> Stay Here
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="advance-alert advance-alert-error" style="max-width: 800px; margin: 0 auto var(--spacing-lg) auto; background: rgba(239, 68, 68, 0.15); border: 2px solid rgba(239, 68, 68, 0.4); box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="advance-main-content">
            <div class="advance-info-section">
                <h2 class="advance-info-title">
                    <i class="fas fa-star"></i>
                    Program Benefits
                </h2>
                <ul class="advance-benefits">
                    <li><i class="fas fa-trophy"></i> Advanced Leadership Training</li>
                    <li><i class="fas fa-graduation-cap"></i> Scholarship Opportunities</li>
                    <li><i class="fas fa-users"></i> Elite Network Access</li>
                    <li><i class="fas fa-certificate"></i> Professional Certifications</li>
                    <li><i class="fas fa-rocket"></i> Career Advancement</li>
                    <li><i class="fas fa-shield-alt"></i> National Service Priority</li>
                    <li><i class="fas fa-globe"></i> International Opportunities</li>
                    <li><i class="fas fa-handshake"></i> Mentorship Program</li>
                </ul>
                
                <div class="advance-requirements">
                    <h3><i class="fas fa-info-circle"></i> Requirements</h3>
                    <p>Open to all college students with strong academic standing and leadership potential. Previous ROTC experience preferred but not required.</p>
                </div>
            </div>

            <div class="advance-form-section">
                <div class="advance-form-content">
                    <h2 class="advance-form-title">Join the Elite</h2>

                    <form method="POST" action="">
                        <div class="advance-form-group">
                            <label for="full_name">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <input type="text" id="full_name" name="full_name" 
                                   placeholder="Enter your complete name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="advance-form-group">
                            <label for="course">
                                <i class="fas fa-graduation-cap"></i> Course/Program
                            </label>
                            <input type="text" id="course" name="course" 
                                   placeholder="e.g., Computer Science, Engineering" 
                                   value="<?php echo htmlspecialchars($_POST['course'] ?? ''); ?>" required>
                        </div>

                        <div class="advance-form-group">
                            <label for="facebook_link">
                                <i class="fab fa-facebook"></i> Facebook Profile URL
                            </label>
                            <input type="url" id="facebook_link" name="facebook_link" 
                                   placeholder="https://facebook.com/yourprofile" 
                                   value="<?php echo htmlspecialchars($_POST['facebook_link'] ?? ''); ?>" required>
                        </div>

                        <button type="submit" class="advance-submit-btn">
                            <i class="fas fa-rocket"></i> Begin Elite Training
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let redirectTimer;
        let countdownInterval;
        let timeLeft = 10;

        // Redirect functionality
        function redirectNow() {
            window.location.href = 'index.php';
        }

        function cancelRedirect() {
            clearTimeout(redirectTimer);
            clearInterval(countdownInterval);
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                const redirectSection = successAlert.querySelector('div[style*="margin-top: 15px"]');
                if (redirectSection) {
                    redirectSection.innerHTML = '<p style="margin: 0; font-size: 0.9rem; color: rgba(34, 197, 94, 0.8);"><i class="fas fa-check"></i> Auto-redirect cancelled. You can go to <a href="index.php" style="color: inherit; text-decoration: underline;">the landing page</a> anytime.</p>';
                }
            }
        }

        // Initialize countdown and redirect
        <?php if ($success_message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const countdownElement = document.getElementById('countdown');
            
            countdownInterval = setInterval(function() {
                timeLeft--;
                if (countdownElement) {
                    countdownElement.textContent = timeLeft;
                }
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    redirectNow();
                }
            }, 1000);
            
            redirectTimer = setTimeout(function() {
                redirectNow();
            }, 10000);
        });
        <?php endif; ?>

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fbLink = document.getElementById('facebook_link').value;
            if (fbLink && !fbLink.includes('facebook.com')) {
                e.preventDefault();
                alert('Please enter a valid Facebook URL');
            }
        });

        // Add floating particles effect
        function createParticle() {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: #ffd700;
                border-radius: 50%;
                pointer-events: none;
                z-index: -1;
                opacity: 0.7;
                animation: float-up 6s linear infinite;
            `;
            
            particle.style.left = Math.random() * 100 + 'vw';
            particle.style.animationDelay = Math.random() * 6 + 's';
            
            document.body.appendChild(particle);
            
            setTimeout(() => particle.remove(), 6000);
        }

        // Create particles periodically
        setInterval(createParticle, 300);

        // Add CSS for particle animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float-up {
                0% {
                    transform: translateY(100vh) rotate(0deg);
                    opacity: 0;
                }
                10% {
                    opacity: 0.7;
                }
                90% {
                    opacity: 0.7;
                }
                100% {
                    transform: translateY(-100px) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>