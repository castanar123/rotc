<?php
require_once 'includes/db.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laguna State Polytechnic University - Los Baños ROTC UNIT</title>
    <meta name="description" content="Join the ROTC Cadet Management System for comprehensive military training and leadership development.">
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/landing-styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <!-- Navigation Header -->
    <nav class="landing-nav">
        <div class="nav-container">
            <div class="nav-logo">
                <i class="fas fa-shield-alt"></i>
                <span>LSPU-LB ROTC UNIT</span>
            </div>
            <div class="nav-links">
                <a href="#about" class="nav-link">About</a>
                <a href="#platoons" class="nav-link">Platoons</a>
                <a href="#register" class="nav-link">Register</a>
                <a href="login.php" class="btn btn-primary btn-sm">Login</a>
            </div>
            <div class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-background"></div>
        <div class="hero-content fade-in">
            <div class="hero-badge">
                <i class="fas fa-medal"></i>
            </div>
            <h1 class="hero-title text-military">HONOR.PATRIOTISM.DUTY</h1>
            <p class="hero-subtitle">ROTC CADETS TODAY, LEADERS OF TOMORROW</p>
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number">500+</span>
                    <span class="stat-label">Active Cadets</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">4</span>
                    <span class="stat-label">Elite Platoons</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">98%</span>
                    <span class="stat-label">Success Rate</span>
                </div>
            </div>
            <div class="hero-actions">
                <a href="#register" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus"></i>
                    Join the Corps
                </a>
                <a href="#about" class="btn btn-secondary btn-lg">
                    <i class="fas fa-info-circle"></i>
                    Learn More
                </a>
            </div>
        </div>
        <div class="hero-scroll-indicator">
            <i class="fas fa-chevron-down pulse"></i>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>About ROTC Program</h2>
                <p>Building tomorrow's leaders through discipline, honor, and excellence</p>
            </div>
            <div class="about-grid">
                <div class="about-card">
                    <div class="card-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Leadership Training</h3>
                    <p>Comprehensive leadership development program designed to build character and command skills essential for military and civilian success.</p>
                </div>
                <div class="about-card">
                    <div class="card-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Physical Fitness</h3>
                    <p>Rigorous physical training regimen that builds strength, endurance, and mental toughness while promoting teamwork and discipline.</p>
                </div>
                <div class="about-card">
                    <div class="card-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <h3>Honor & Service</h3>
                    <p>Instilling core values of honor, integrity, and service to country while preparing cadets for future military or civilian leadership roles.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Platoons Section -->
    <section id="platoons" class="platoons-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Elite Platoons</h2>
                <p>Choose your path to excellence</p>
            </div>
            <div class="platoons-grid">
                <div class="platoon-card platoon-alpha">
                    <div class="platoon-header">
                        <div class="platoon-badge">
                            <i class="fas fa-mountain"></i>
                        </div>
                        <h3>Alpha Platoon</h3>
                        <span class="platoon-motto">"First to Fight"</span>
                    </div>
                    <div class="platoon-info">
                        <p>Elite reconnaissance and leadership unit specializing in tactical operations and advanced training.</p>
                        <div class="platoon-stats">
                            <span><i class="fas fa-users"></i> 125 Cadets</span>
                            <span><i class="fas fa-trophy"></i> 15 Awards</span>
                        </div>
                    </div>
                </div>
                <div class="platoon-card platoon-bravo">
                    <div class="platoon-header">
                        <div class="platoon-badge">
                            <i class="fas fa-fire"></i>
                        </div>
                        <h3>Bravo Platoon</h3>
                        <span class="platoon-motto">"Brave & Bold"</span>
                    </div>
                    <div class="platoon-info">
                        <p>Combat-focused unit emphasizing tactical excellence, physical fitness, and battlefield leadership.</p>
                        <div class="platoon-stats">
                            <span><i class="fas fa-users"></i> 118 Cadets</span>
                            <span><i class="fas fa-trophy"></i> 12 Awards</span>
                        </div>
                    </div>
                </div>
                <div class="platoon-card platoon-charlie">
                    <div class="platoon-header">
                        <div class="platoon-badge">
                            <i class="fas fa-anchor"></i>
                        </div>
                        <h3>Charlie Platoon</h3>
                        <span class="platoon-motto">"Courage & Honor"</span>
                    </div>
                    <div class="platoon-info">
                        <p>Naval-inspired unit focusing on maritime operations, navigation, and strategic planning excellence.</p>
                        <div class="platoon-stats">
                            <span><i class="fas fa-users"></i> 132 Cadets</span>
                            <span><i class="fas fa-trophy"></i> 18 Awards</span>
                        </div>
                    </div>
                </div>
                <div class="platoon-card platoon-delta">
                    <div class="platoon-header">
                        <div class="platoon-badge">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Delta Platoon</h3>
                        <span class="platoon-motto">"Duty & Excellence"</span>
                    </div>
                    <div class="platoon-info">
                        <p>Special operations unit specializing in advanced tactics, technology integration, and elite training.</p>
                        <div class="platoon-stats">
                            <span><i class="fas fa-users"></i> 125 Cadets</span>
                            <span><i class="fas fa-trophy"></i> 20 Awards</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section id="register" class="register-section">
        <div class="container">
            <div class="register-content">
                <div class="register-info">
                    <h2>Join the Elite</h2>
                    <p>Ready to begin your journey of excellence? Register now to become part of our distinguished cadet corps.</p>
                    <div class="requirements-list">
                        <h4>Requirements:</h4>
                        <ul>
                            <li><i class="fas fa-check"></i> Valid student enrollment</li>
                            <li><i class="fas fa-check"></i> Physical fitness assessment</li>
                            <li><i class="fas fa-check"></i> Character reference</li>
                            <li><i class="fas fa-check"></i> Commitment to excellence</li>
                        </ul>
                    </div>
                </div>
                <div class="register-form-container">
                    <div class="form-header">
                        <h3>Begin Your Application</h3>
                        <p>Complete your registration to start your ROTC journey</p>
                    </div>
                    <a href="register.php" class="btn btn-primary btn-lg register-btn">
                        <i class="fas fa-clipboard-list"></i>
                        Start Registration
                    </a>
                    <div class="login-link">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-shield-alt"></i>
                        <span>ROTC CMS</span>
                    </div>
                    <p>Excellence in Military Training and Leadership Development</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#about">About Program</a></li>
                        <li><a href="#platoons">Platoons</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="login.php">Login</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <ul>
                        <li><i class="fas fa-envelope"></i> rotc@university.edu</li>
                        <li><i class="fas fa-phone"></i> (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt"></i> Military Science Building</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 ROTC Cadet Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/landing.js">
</body>
</html>
</script>