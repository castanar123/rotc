<?php
// Start output buffering for caching
ob_start();

// Set cache headers for better performance
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');
header('ETag: "' . md5_file(__FILE__) . '"');

// Check if client has cached version
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;
    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';
    $file_modified_time = filemtime(__FILE__);
    $current_etag = '"' . md5_file(__FILE__) . '"';
    
    if ($if_modified_since >= $file_modified_time || $if_none_match === $current_etag) {
        header('HTTP/1.1 304 Not Modified');
        exit();
    }
}

require_once 'includes/session.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LSPU- LB ROTC UNIT</title>
    <meta name="description" content="Join the ROTC Cadet Management System for comprehensive military training and leadership development.">
    <link rel="stylesheet" href="css/tactical-theme.css?v=<?php echo filemtime('css/tactical-theme.css'); ?>">
    <link rel="stylesheet" href="css/landing-styles.css?v=<?php echo filemtime('css/landing-styles.css'); ?>">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="css/tactical-theme.css?v=<?php echo filemtime('css/tactical-theme.css'); ?>" as="style">
    <link rel="preload" href="js/landing.js?v=<?php echo filemtime('js/landing.js'); ?>" as="script">
    
    <!-- DNS prefetch for external resources -->
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <!-- Fast Loader -->
    <div id="fastLoader" class="fast-loader">
        <div class="loader-content">
            <div class="loader-logo">
                <img src="IMG/MANRILAG.png" alt="MANRILAG Logo" class="loader-logo-img">
            </div>
            <div class="loader-spinner"></div>
            <div class="loader-text">Loading ROTC System...</div>
        </div>
    </div>

    <!-- Navigation Header -->
    <nav class="landing-nav">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="IMG/GIDEON.png" alt="GIDEON Logo" class="nav-logo-img">
                <span>LSPU-LB ROTC UNIT</span>
            </div>
            <div class="nav-links">
                <a href="#benefits" class="nav-link">Benefits</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#activities" class="nav-link">Activities</a>
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
        <div class="hero-images">
            <img src="IMG/Gulay (2).png" alt="ROTC Cadet" class="hero-image hero-image-left">
            <img src="IMG/Gulay (3).png" alt="ROTC Cadet" class="hero-image hero-image-right">
        </div>
        <div class="hero-content fade-in">
            <div class="hero-badge">
                <img src="IMG/MANRILAG.png" alt="MANRILAG Logo" class="hero-logo">
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

    <!-- ROTC Benefits Section -->
    <section id="benefits" class="benefits-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>ROTC Benefits & Opportunities</h2>
                <p>Discover the advantages of joining our elite cadet corps</p>
            </div>
            
            <!-- ROTC Benefits Section -->
            <div class="benefits-wrapper">
                <!-- Section Divider -->
                <div class="section-divider">
                    <div class="divider-line"></div>
                    <div class="divider-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div class="divider-line"></div>
                </div>
                
                <div class="benefits-grid enhanced">
                    <!-- General ROTC Benefits -->
                    <div class="general-benefits">
                        <div class="category-header">
                            <div class="category-icon">
                                <div class="icon-glow"></div>
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h4>General ROTC Benefits</h4>
                        </div>
                        <div class="benefits-list">
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Instill the love of country</span>
                                    <p class="benefit-desc">Develop deep patriotism and commitment to serving the nation with honor and dedication.</p>
                                    <span class="benefit-highlight">Patriotism</span>
                                </div>
                            </div>
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Boost leadership skills</span>
                                    <p class="benefit-desc">Comprehensive training in leadership principles and practical application in real-world scenarios.</p>
                                    <span class="benefit-highlight">Leadership</span>
                                </div>
                            </div>
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Build Confidence & Discipline</span>
                                    <p class="benefit-desc">Building strong moral character, integrity, and self-discipline for life success.</p>
                                    <span class="benefit-highlight">Character</span>
                                </div>
                            </div>
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Develop Technical Skills</span>
                                    <p class="benefit-desc">Learn advanced technical and tactical skills essential for modern military operations.</p>
                                    <span class="benefit-highlight">Skills</span>
                                </div>
                            </div>
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-marching"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Learn Basic Military Drills</span>
                                    <p class="benefit-desc">Master fundamental military procedures, formations, and tactical movements.</p>
                                    <span class="benefit-highlight">Training</span>
                                </div>
                            </div>
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-dumbbell"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Maintain physical stamina</span>
                                    <p class="benefit-desc">Structured fitness programs to maintain peak physical condition and mental resilience.</p>
                                    <span class="benefit-highlight">Fitness</span>
                                </div>
                            </div>
                            <div class="benefit-item animated">
                                <div class="benefit-icon">
                                    <i class="fas fa-compass"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Give long-term career guidance</span>
                                    <p class="benefit-desc">Receive mentorship and guidance for successful military and civilian career paths.</p>
                                    <span class="benefit-highlight">Guidance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced ROTC Benefits -->
                    <div class="premium-card">
                        <div class="category-header">
                            <div class="category-icon advance-icon">
                                <div class="icon-glow premium-glow"></div>
                                <i class="fas fa-crown"></i>
                                <div class="premium-badge">Elite</div>
                            </div>
                            <div class="premium-header">
                                <h4>Advanced ROTC Benefits</h4>
                            </div>
                        </div>
                        <div class="benefits-list premium-list">
                            <div class="benefit-item premium animated">
                                <div class="benefit-icon premium-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Subsistence Allowance</span>
                                    <p class="benefit-desc">Entitled to receive Subsistence Allowance for 15 training days per semester for 2 years.</p>
                                    <span class="benefit-highlight">Financial</span>
                                </div>
                            </div>
                            <div class="benefit-item premium animated">
                                <div class="benefit-icon premium-icon">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Individual Equipment</span>
                                    <p class="benefit-desc">Entitled for 9 line items Individual Clothing & Individual Equipment.</p>
                                    <span class="benefit-highlight">Equipment</span>
                                </div>
                            </div>
                            <div class="benefit-item premium animated">
                                <div class="benefit-icon premium-icon">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">Cash Incentive</span>
                                    <p class="benefit-desc">If qualified, entitled to receive Cash Incentive Amounting to PhP12,000.00 per semester for 2 years.</p>
                                    <span class="benefit-highlight">Reward</span>
                                </div>
                            </div>
                            <div class="benefit-item premium animated">
                                <div class="benefit-icon premium-icon">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div class="benefit-content">
                                    <span class="benefit-title">AFP Facilities Access</span>
                                    <p class="benefit-desc">Access to AFP Commissary and Exchange Stores and AFP Transient Facilities under the management of Philippine Army.</p>
                                    <span class="benefit-highlight">Access</span>
                                </div>
                            </div>
                        </div>
                        <div class="advance-cta">
                            <a href="advancerotc.php" class="premium-btn">
                                <div class="btn-glow"></div>
                                <i class="fas fa-rocket"></i>
                                <span>Join the Advance Officer</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About ROTC Program Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>About ROTC Program</h2>
                <p>Building tomorrow's leaders through discipline, honor, and excellence</p>
            </div>
            
            <!-- Program Overview -->
            <div class="about-content-wrapper">
                <div class="about-gallery-image">
                    <img src="IMG/Manrilag Galeri.png" alt="ROTC Gallery" class="gallery-image">
                    <img src="IMG/Manrilag Galeri (1).png" alt="ROTC Gallery Additional" class="gallery-image gallery-image-secondary">
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
        </div>
    </section>

    <!-- ROTC Activities Gallery Section -->
    <section id="activities" class="activities-gallery-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>ROTC Training Activities</h2>
                <p>Experience the comprehensive training that builds tomorrow's leaders</p>
            </div>
            
            <div class="gallery-carousel-container">
                <div class="gallery-carousel" id="rotcCarousel">
                    <div class="carousel-track">
                        <!-- Lecture Activity -->
                        <div class="carousel-slide active">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Lecture.jpg" alt="ROTC Lecture Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Lecture</h3>
                                    <p>Not all training happens in the field. Lectures provide valuable lessons on leadership, national defense, citizenship, disaster preparedness, and essential life skills. These sessions aim to develop knowledge, character, and sense of responsibility to the community and nation.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Education</span>
                                        <span class="tag">Leadership</span>
                                        <span class="tag">Character Building</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assembly Disassembly Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/assembly disassembly.jpg" alt="M16 Assembly Disassembly Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-cogs"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Assembly & Disassembly</h3>
                                    <p>Hands-on training in M16 and other military weapons maintenance. Cadets learn proper procedures for assembling and disassembling firearms, focusing on safety protocols, technical precision, and understanding weapon mechanics for responsible handling.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Technical Skills</span>
                                        <span class="tag">Weapon Safety</span>
                                        <span class="tag">Precision</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SUT Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/SUT.jpg" alt="Special Unit Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>SUT</h3>
                                    <p>Special Unit Training focuses on advanced tactical operations, specialized military skills, and elite combat techniques. This intensive program develops exceptional capabilities, strategic thinking, and leadership under the most challenging conditions.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Advanced Training</span>
                                        <span class="tag">Tactical Operations</span>
                                        <span class="tag">Elite Skills</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Life Support Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Basic Life Support.jpg" alt="Basic Life Support Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-heartbeat"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Basic Life Support</h3>
                                    <p>Emergencies can happen anytime. BLS training equips you with first aid skills, CPR, and other lifesaving procedures. You'll learn how to stay calm under pressure and respond quickly to help save lives in critical situations.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Medical Training</span>
                                        <span class="tag">Emergency Response</span>
                                        <span class="tag">Life-saving Skills</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Blood Letting Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Blood letting.JPG" alt="Blood Donation Drive" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-tint"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Blood Donations</h3>
                                    <p>A single bag of blood can save multiple lives. By participating in blood donation drives, cadets practice compassion, volunteerism, and community service - values that go beyond military training and create unforgettable memories.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Community Service</span>
                                        <span class="tag">Compassion</span>
                                        <span class="tag">Volunteerism</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Clean Up Drive Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Clean up drive.jpg" alt="Environmental Clean-Up Drive" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-leaf"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Clean-Up Drive</h3>
                                    <p>Serving the country also means caring for its environment. Clean-up drives promote environmental awareness, community engagement, and civic responsibility, creating cleaner and healthier surroundings for everyone.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Environmental Care</span>
                                        <span class="tag">Community Engagement</span>
                                        <span class="tag">Civic Responsibility</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Colors Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Colors.jpg" alt="Colors Ceremony Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-flag"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Colors</h3>
                                    <p>This is where discipline begins. During inspections, cadets are checked for proper uniform, complete equipment, and personal cleanliness. It teaches attention to detail, self-respect, and instills essential traits of a future leader.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Discipline</span>
                                        <span class="tag">Attention to Detail</span>
                                        <span class="tag">Leadership Traits</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Drillings Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Drillings.jpg" alt="Military Drilling Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Drillings</h3>
                                    <p>Drills are synchronized marching exercises that enhance discipline, teamwork, and precision. These group exercises teach cadets about teamwork, following commands efficiently, and moving as one unit.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Synchronized Movement</span>
                                        <span class="tag">Team Coordination</span>
                                        <span class="tag">Military Discipline</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Marching Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Marching.jpg" alt="Military Marching Training" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-walking"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Marching</h3>
                                    <p>Fundamental military movement training emphasizing proper posture, rhythm, and unit cohesion. Marching builds physical endurance while developing the discipline and coordination essential for military operations and ceremonies.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Military Movement</span>
                                        <span class="tag">Physical Endurance</span>
                                        <span class="tag">Unit Cohesion</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Theoretical Exam Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Theoretical exam.jpg" alt="ROTC Theoretical Examination" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-clipboard-check"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Theoretical Examination</h3>
                                    <p>Comprehensive assessment testing cadets' understanding of military knowledge, protocols, leadership principles, and strategic concepts. These examinations ensure mastery of essential theoretical foundations for future military service.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Academic Assessment</span>
                                        <span class="tag">Military Knowledge</span>
                                        <span class="tag">Strategic Understanding</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leadership Excellence Activity -->
                        <div class="carousel-slide">
                            <div class="activity-card">
                                <div class="activity-image-container">
                                    <img src="IMG/Javier.JPG" alt="ROTC Leadership Excellence" class="activity-image" loading="lazy">
                                    <div class="activity-overlay">
                                        <div class="activity-icon">
                                            <i class="fas fa-medal"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <h3>Leadership Excellence</h3>
                                    <p>Recognizing outstanding cadets who demonstrate exceptional leadership, dedication, and service. These individuals embody the core values of ROTC and serve as role models for their peers and future military leaders.</p>
                                    <div class="activity-tags">
                                        <span class="tag">Outstanding Performance</span>
                                        <span class="tag">Role Model</span>
                                        <span class="tag">Military Excellence</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Carousel Navigation -->
                <div class="carousel-navigation">
                    <button class="carousel-btn carousel-prev" id="prevBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="carousel-btn carousel-next" id="nextBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <!-- Carousel Indicators -->
                <div class="carousel-indicators" id="carouselIndicators">
                    <!-- Dots will be generated by JavaScript -->
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
                        <li><a href="#benefits">Benefits</a></li>
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

    <!-- Fullscreen Image Modal -->
    <div id="fullscreenModal" class="fullscreen-modal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close-btn" id="modalCloseBtn">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-image-container">
                <img id="modalImage" src="" alt="" class="modal-image">
            </div>
            <div class="modal-info">
                <h3 id="modalTitle"></h3>
                <p id="modalDescription"></p>
                <div id="modalTags" class="modal-tags"></div>
            </div>
        </div>
    </div>

    <script src="js/landing.js?v=<?php echo filemtime('js/landing.js'); ?>" defer></script>
    <script src="js/gallery-carousel.js?v=<?php echo filemtime('js/gallery-carousel.js'); ?>" defer></script>
    
    <!-- Fast Loader Styles -->
    <style>
        .fast-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
            overflow: hidden;
            /* Mobile optimizations */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .fast-loader.fade-out {
            opacity: 0;
            visibility: hidden;
        }
        
        .loader-content {
            text-align: center;
            position: relative;
        }
        
        .loader-logo {
            margin-bottom: 30px;
        }
        
        .loader-logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            opacity: 0.9;
        }
        
        .loader-spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto 20px;
            border: 3px solid rgba(40, 167, 69, 0.2);
            border-top: 3px solid var(--military-green);
            border-radius: 50%;
            animation: simpleSpin 1s linear infinite;
        }
        
        .loader-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-accent);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        @keyframes simpleSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @-webkit-keyframes simpleSpin {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }
        

        
        /* Hide main content initially */
        body.loading .landing-nav,
        body.loading .hero-section,
        body.loading .benefits-section,
        body.loading .about-section,
        body.loading .activities-section,
        body.loading .platoons-section,
        body.loading .register-section,
        body.loading .landing-footer {
            opacity: 0;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .loader-logo-img {
                width: 60px;
                height: 60px;
            }
            
            .loader-spinner {
                width: 30px;
                height: 30px;
                border-width: 2px;
            }
            
            .loader-text {
                font-size: 12px;
                letter-spacing: 0.5px;
            }
        }
        
        @media (max-width: 480px) {
            .loader-logo-img {
                width: 50px;
                height: 50px;
            }
            
            .loader-spinner {
                width: 25px;
                height: 25px;
            }
            
            .loader-text {
                font-size: 11px;
            }
        }
        
        /* Reduce motion for accessibility */
        @media (prefers-reduced-motion: reduce) {
            .loader-spinner {
                animation: none !important;
            }
        }
    </style>
    
    <!-- Service Worker Registration -->
    <script>
        // Register service worker for caching
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('Service Worker registered successfully:', registration.scope);
                        
                        // Handle updates
                        registration.addEventListener('updatefound', function() {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', function() {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New content available, reload page
                                    if (confirm('New version available! Reload to update?')) {
                                        newWorker.postMessage({ type: 'SKIP_WAITING' });
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    })
                    .catch(function(error) {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
    </script>
    
    <!-- Fast Loader Script -->
    <script>
        // Add loading class to body initially
        document.body.classList.add('loading');
        
        // Simple mobile detection
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        // Simplified timing
        const loadTime = isMobile ? 600 : 800;
        const maxWaitTime = 3000;
        
        // Simple loader hide function
        function hideLoader() {
            const loader = document.getElementById('fastLoader');
            if (!loader) return;
            
            loader.classList.add('fade-out');
            document.body.classList.remove('loading');
            
            // Remove loader after fade animation
            setTimeout(function() {
                if (loader && loader.parentNode) {
                    loader.remove();
                }
            }, 500);
        }
        
        // Wait for page load
        window.addEventListener('load', function() {
            setTimeout(hideLoader, loadTime);
        });
        
        // Fallback timeout
        setTimeout(hideLoader, maxWaitTime);
        
        // Prevent scroll during loading
        if (isMobile) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('load', function() {
                setTimeout(function() {
                    document.body.style.overflow = '';
                }, loadTime + 100);
            });
        }
    </script>
</body>
</html>
<?php
// Flush output buffer with compression
if (ob_get_level()) {
    ob_end_flush();
}
?>
