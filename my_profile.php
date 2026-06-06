<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Cadet and basic cadet access
check_login();
if (!rotc_role_in(['cadet', 'basic_cadet', 'basic-cadet', 'basic'])) {
    SecurityLogger::log('UNAUTHORIZED_ACCESS', 'HIGH', 'Non-cadet attempted to access cadet profile page', [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'anonymous',
        'role' => $_SESSION['role'] ?? 'none',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    redirect_to_dashboard();
}

// Log successful profile access
SecurityLogger::log('PROFILE_ACCESS', 'LOW', 'Cadet accessed own profile', [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);

// Fetch cadet's full profile data
$user_id = $_SESSION['user_id'];
$sql = "SELECT cp.*, u.email, u.username 
        FROM cadet_profiles cp 
        JOIN users u ON cp.user_id = u.id 
        WHERE cp.user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$profile_data = $stmt->fetch();

if (!$profile_data) {
    die('Profile not found.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-medal"></i></div>
                    <span class="logo-text">Cadet Portal</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="cadet_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'basic_cadet'): ?>
                    <li class="nav-item">
                        <a href="file_missing_id.php" class="nav-link">
                            <i class="fas fa-id-card-alt"></i>
                            <span>File Missing ID</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a href="QR/scanner.html" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Check-in</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="cadet_attendance_new.php" class="nav-link">
                             <i class="fas fa-calendar-check"></i>
                             <span>My Attendance</span>
                         </a>
                    </li>
                    <li class="nav-item">
                        <a href="grades/view_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>My Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_profile.php" class="nav-link active">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">My Profile</h1>
                        <p class="header-subtitle">View and manage your personal information</p>
                    </div>
                    <div class="header-actions">
                        <button class="action-btn" onclick="window.print()" title="Print Profile">
                            <i class="fas fa-print"></i>
                            Print Profile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Profile Header -->
                <div class="dashboard-card fade-in" style="margin-bottom: var(--spacing-lg);">
                    <div class="card-content" style="display: flex; align-items: center; gap: 2rem; padding: 2rem;">
                        <div class="profile-photo" style="flex-shrink: 0;">
                            <?php if (!empty($profile_data['photo_path']) && file_exists($profile_data['photo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($profile_data['photo_path']); ?>" alt="Profile Photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--color-primary);">
                            <?php else: ?>
                                <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--color-surface-variant); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--text-muted); border: 4px solid var(--color-outline);">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-info" style="flex: 1;">
                            <h2 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 2rem;"><?php echo htmlspecialchars(($profile_data['first_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? '')); ?></h2>
                            <p style="color: var(--text-secondary); margin: 0 0 1rem 0; font-size: 1.1rem;"><?php echo htmlspecialchars($profile_data['student_id'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($profile_data['platoon'] ?? 'Not assigned'); ?> Platoon</p>
                            <div style="display: flex; gap: 0.5rem;">
                                <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; background: var(--color-primary); color: white;">Cadet</span>
                                <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500; background: var(--color-surface-variant); color: var(--text-secondary);"><?php echo htmlspecialchars($profile_data['course'] ?? 'Not provided'); ?></span>
                            </div>
                        </div>
                        <div class="profile-qr" style="text-align: center; flex-shrink: 0;">
                            <?php if (!empty($profile_data['qr_code_path']) && file_exists($profile_data['qr_code_path'])): ?>
                                <img src="<?php echo htmlspecialchars($profile_data['qr_code_path']); ?>" alt="QR Code" style="width: 100px; height: 100px; border: 2px solid var(--color-outline); border-radius: 8px;">
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: var(--text-secondary);">My QR Code</p>
                            <?php else: ?>
                                <div style="width: 100px; height: 100px; background: var(--color-surface-variant); border: 2px solid var(--color-outline); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                                    <i class="fas fa-qrcode" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                    <p style="font-size: 0.75rem; margin: 0; text-align: center;">QR Code Not Available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Details Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--spacing-lg);">
                    <!-- Personal Information -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user"></i> Personal Information</h3>
                        </div>
                        <div class="card-content">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Full Name</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars(($profile_data['first_name'] ?? '') . ' ' . ($profile_data['middle_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? '')); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Email Address</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['email'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Contact Number</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['contact_number'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Date of Birth</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo isset($profile_data['date_of_birth']) && $profile_data['date_of_birth'] ? date('F d, Y', strtotime($profile_data['date_of_birth'])) : 'Not provided'; ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; grid-column: 1 / -1;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Address</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['address'] ?? 'Not provided'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                        </div>
                        <div class="card-content">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Student ID</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['student_id']); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">School</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['school'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Course/Program</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['course'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Platoon</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['platoon'] ?? 'Not assigned'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Year Level</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['year_level'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Section</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['section'] ?? 'Not provided'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-phone"></i> Emergency Contact</h3>
                        </div>
                        <div class="card-content">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Contact Name</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['emergency_contact_name'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Contact Number</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['emergency_contact_number'] ?? 'Not provided'); ?></span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Relationship</label>
                                    <span style="color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($profile_data['emergency_contact_relationship'] ?? 'Not provided'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                     </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Add fade-in animation to elements
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Initialize fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
        });
    </script>
</body>
</html>
