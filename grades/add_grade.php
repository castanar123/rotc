<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
check_login();

// Access control: Allow admins, instructors, officers, and higher ranks
if(!in_array($_SESSION['role'], ['admin', 'instructor', 'officer', '1cl', '2cl', 'commandant'])){
    die('Access Denied. You do not have permission to add grades.');
}

// Get pending registrations count for badge
try {
    $pending_sql = "SELECT COUNT(*) as count FROM registration_requests WHERE status = 'pending'";
    $pending_result = $pdo->query($pending_sql);
    $pending_registrations = $pending_result->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // If table doesn't exist, set to 0
    $pending_registrations = 0;
}

// Fetch all cadets for the dropdown
$sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles ORDER BY platoon, first_name, last_name";
$cadets = $pdo->query($sql)->fetchAll();

// Get statistics for dashboard
$total_grades_sql = "SELECT COUNT(*) as total FROM grades";
$total_grades_result = $pdo->query($total_grades_sql);
$total_grades = $total_grades_result->fetch()['total'] ?? 0;

$recent_grades_sql = "SELECT COUNT(*) as total FROM grades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$recent_grades_result = $pdo->query($recent_grades_sql);
$recent_grades = $recent_grades_result->fetch()['total'] ?? 0;

$total_cadets = count($cadets);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cadet_id = $_POST['cadet_id'];
        $semester = $_POST['semester'];
        $academic_year = $_POST['academic_year'];
        $leadership_score = $_POST['leadership_score'];
        $academic_score = $_POST['academic_score'];
        $physical_fitness_score = $_POST['physical_fitness_score'];
        $military_bearing_score = $_POST['military_bearing_score'];
        $participation_score = $_POST['participation_score'];
        
        // Calculate total score
        $total_score = $leadership_score + $academic_score + $physical_fitness_score + $military_bearing_score + $participation_score;
        
        // Determine letter grade
        if ($total_score >= 450) $letter_grade = 'A';
        elseif ($total_score >= 400) $letter_grade = 'B';
        elseif ($total_score >= 350) $letter_grade = 'C';
        elseif ($total_score >= 300) $letter_grade = 'D';
        else $letter_grade = 'F';
        
        $insert_sql = "INSERT INTO grades (cadet_id, semester, academic_year, leadership_score, academic_score, physical_fitness_score, military_bearing_score, participation_score, total_score, letter_grade, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([$cadet_id, $semester, $academic_year, $leadership_score, $academic_score, $physical_fitness_score, $military_bearing_score, $participation_score, $total_score, $letter_grade]);
        
        $success_message = "Grade added successfully!";
    } catch (Exception $e) {
        $error_message = "Error adding grade: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Grade - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .grade-form {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .score-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body data-role="<?php echo $_SESSION['role']; ?>">
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                    <span>ROTC System</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../admin_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../QR/home.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../QR/dashboard.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Attendance Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../rifle_management.php" class="nav-link">
                            <i class="fas fa-gun"></i>
                            <span>Rifle Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../rifle_scanner.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Scanner</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../user_management.php" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/missing_ids.php" class="nav-link">
                            <i class="fas fa-id-card-alt"></i>
                            <span>Missing IDs</span>
                            <?php if (isset($missing_ids_count) && $missing_ids_count > 0): ?>
                                <span class="badge badge-danger"><?php echo $missing_ids_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/registration_approvals.php" class="nav-link">
                            <i class="fas fa-user-check"></i>
                            <span>Registration Approvals</span>
                            <?php if (isset($pending_registrations) && $pending_registrations > 0): ?>
                                <span class="badge badge-warning"><?php echo $pending_registrations; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../advance_rotc_management.php" class="nav-link">
                            <i class="fas fa-user-graduate"></i>
                            <span>Advance Officer Respondents</span>
                            <?php if (isset($advance_officer_count) && $advance_officer_count > 0): ?>
                                <span class="badge badge-success"><?php echo $advance_officer_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../reports/view_report.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="manage_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../QR/setup.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>System Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../QR/https_test.php" class="nav-link">
                            <i class="fas fa-lock"></i>
                            <span>HTTPS Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../settings.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
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
            <header class="header">
                <div class="header-left">
                    <h1 class="page-title">Add Grade</h1>
                </div>
                
                <div class="header-right">
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $total_grades; ?></div>
                            <div class="stat-label">Total Grades</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $recent_grades; ?></div>
                            <div class="stat-label">This Week</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $total_cadets; ?></div>
                            <div class="stat-label">Total Cadets</div>
                        </div>
                    </div>
                </div>

                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Grade Entry Form -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add New Grade</h3>
                        <div class="card-actions">
                            <a href="manage_grades.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Grades
                            </a>
                        </div>
                    </div>
                    <div class="card-content">
                        <form method="post" class="grade-form" id="gradeForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="cadet_id">
                                        <i class="fas fa-user-graduate"></i> Select Cadet
                                    </label>
                                    <select name="cadet_id" id="cadet_id" class="form-control" required>
                                        <option value="">-- Select a Cadet --</option>
                                        <?php foreach($cadets as $cadet): ?>
                                            <option value="<?php echo $cadet['id']; ?>">
                                                <?php echo htmlspecialchars($cadet['full_name'] . ' - ' . $cadet['platoon']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="semester">
                                        <i class="fas fa-calendar"></i> Semester
                                    </label>
                                    <select name="semester" id="semester" class="form-control" required>
                                        <option value="">-- Select Semester --</option>
                                        <option value="1st">1st Semester</option>
                                        <option value="2nd">2nd Semester</option>
                                        <option value="Summer">Summer</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="academic_year">
                                        <i class="fas fa-graduation-cap"></i> Academic Year
                                    </label>
                                    <select name="academic_year" id="academic_year" class="form-control" required>
                                        <option value="">-- Select Academic Year --</option>
                                        <?php 
                                        $current_year = date('Y');
                                        for($i = $current_year - 2; $i <= $current_year + 2; $i++): 
                                        ?>
                                            <option value="<?php echo $i . '-' . ($i + 1); ?>">
                                                <?php echo $i . '-' . ($i + 1); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <h4><i class="fas fa-star"></i> Grade Components (Max 100 points each)</h4>
                            <div class="score-inputs">
                                <div class="form-group">
                                    <label for="leadership_score">
                                        <i class="fas fa-crown"></i> Leadership Score
                                    </label>
                                    <input type="number" name="leadership_score" id="leadership_score" class="form-control" min="0" max="100" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="academic_score">
                                        <i class="fas fa-book"></i> Academic Score
                                    </label>
                                    <input type="number" name="academic_score" id="academic_score" class="form-control" min="0" max="100" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="physical_fitness_score">
                                        <i class="fas fa-dumbbell"></i> Physical Fitness Score
                                    </label>
                                    <input type="number" name="physical_fitness_score" id="physical_fitness_score" class="form-control" min="0" max="100" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="military_bearing_score">
                                        <i class="fas fa-medal"></i> Military Bearing Score
                                    </label>
                                    <input type="number" name="military_bearing_score" id="military_bearing_score" class="form-control" min="0" max="100" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="participation_score">
                                        <i class="fas fa-hand-paper"></i> Participation Score
                                    </label>
                                    <input type="number" name="participation_score" id="participation_score" class="form-control" min="0" max="100" required>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i> Add Grade
                                </button>
                                <a href="manage_grades.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <script src="../js/mobile-navigation.js"></script>
    <script>
        // Form validation and calculation
        document.getElementById('gradeForm').addEventListener('submit', function(e) {
            const scores = [
                'leadership_score',
                'academic_score', 
                'physical_fitness_score',
                'military_bearing_score',
                'participation_score'
            ];
            
            let totalScore = 0;
            let isValid = true;
            
            scores.forEach(scoreId => {
                const input = document.getElementById(scoreId);
                const value = parseInt(input.value);
                
                if (value < 0 || value > 100) {
                    alert(`${input.previousElementSibling.textContent} must be between 0 and 100`);
                    isValid = false;
                    return;
                }
                
                totalScore += value;
            });
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show total score confirmation
            const letterGrade = totalScore >= 450 ? 'A' : 
                               totalScore >= 400 ? 'B' : 
                               totalScore >= 350 ? 'C' : 
                               totalScore >= 300 ? 'D' : 'F';
            
            if (!confirm(`Total Score: ${totalScore}/500 (${letterGrade})\nDo you want to submit this grade?`)) {
                e.preventDefault();
            }
        });
        
        // Real-time score calculation
        const scoreInputs = document.querySelectorAll('input[type="number"]');
        scoreInputs.forEach(input => {
            input.addEventListener('input', function() {
                let total = 0;
                scoreInputs.forEach(inp => {
                    total += parseInt(inp.value) || 0;
                });
                
                const letterGrade = total >= 450 ? 'A' : 
                                   total >= 400 ? 'B' : 
                                   total >= 350 ? 'C' : 
                                   total >= 300 ? 'D' : 'F';
                
                // Update page title with current total
                document.title = `Add Grade (${total}/500 - ${letterGrade}) - ROTC Management System`;
            });
        });
    </script>
</body>
</html>
