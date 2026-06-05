<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Check if viewing specific cadet (for officers) or own grades (for cadets)
$cadet_id = $_GET['cadet_id'] ?? null;
$cadet_profile_id = null;
$cadet_info = null;

if ($cadet_id && in_array($_SESSION['role'], ['admin', 'instructor', 'officer', '1cl', '2cl', 'commandant'])) {
    // Officer viewing specific cadet's grades
    $cadet_profile_id = $cadet_id;
    
    // Get cadet information
    $sql_info = "SELECT cp.*, u.username, u.email FROM cadet_profiles cp 
                 JOIN users u ON cp.user_id = u.id WHERE cp.id = ?";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute([$cadet_profile_id]);
    $cadet_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
    if (!$cadet_info) {
        $cadet_info = null;
    }
} else {
    // Cadet viewing own grades
    $user_id = $_SESSION['user_id'];
    $sql_profile = "SELECT id, first_name, last_name, student_id, platoon FROM cadet_profiles WHERE user_id = ?";
    $stmt_profile = $pdo->prepare($sql_profile);
    $stmt_profile->execute([$user_id]);
    $row_profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);
    if ($row_profile) {
        $cadet_profile_id = $row_profile['id'];
        $cadet_info = $row_profile;
        $cadet_info['username'] = $_SESSION['username'];
        $cadet_info['email'] = $_SESSION['email'] ?? '';
    }
}

if (!$cadet_profile_id || !$cadet_info) {
    die('Cadet not found.');
}

// Fetch grades for the cadet
$grades = [];

// Check which grades table structure exists
$table_check = $pdo->query("SHOW COLUMNS FROM grades LIKE 'event_name'");
if ($table_check->rowCount() > 0) {
    // Old grades table structure
    $sql_grades = "SELECT g.event_name, g.grade, g.grade_date, g.max_grade, g.comments, u.username as officer_username, 'grade' as record_type
                   FROM grades g
                   JOIN users u ON g.recorded_by = u.id
                   WHERE g.cadet_id = ?";
} else {
    // New grades table structure
    $sql_grades = "SELECT 
                       g.semester as event_name,
                       g.total_grade as grade,
                       g.created_at as grade_date,
                       100 as max_grade,
                       CONCAT('Drill: ', COALESCE(g.drill_grade, 'N/A'), ', Conduct: ', COALESCE(g.conduct_grade, 'N/A'), ', Academics: ', COALESCE(g.academics_grade, 'N/A')) as comments,
                       'System' as officer_username,
                       'grade' as record_type
                   FROM grades g
                   WHERE g.cadet_id = ?";
}

// Quiz scores query
$sql_quiz = "SELECT 
                 q.quiz_name as event_name,
                 q.score as grade,
                 q.created_at as grade_date,
                 q.max_score as max_grade,
                 CONCAT('Quiz Score: ', q.score, '/', q.max_score, ' (', ROUND(q.percentage, 1), '%)') as comments,
                 'System' as officer_username,
                 'quiz' as record_type
             FROM quiz_scores q
             WHERE q.cadet_id = ?";

// Combine both queries with UNION
$sql_combined = "(" . $sql_grades . ") UNION (" . $sql_quiz . ") ORDER BY grade_date DESC";

$stmt_combined = $pdo->prepare($sql_combined);
$stmt_combined->execute([$cadet_profile_id, $cadet_profile_id]);
while ($row = $stmt_combined->fetch(PDO::FETCH_ASSOC)) {
    $grades[] = $row;
}

// Calculate grade statistics
$total_grades = count($grades);
$average_grade = 0;
$highest_grade = 0;
$lowest_grade = 100;

if ($total_grades > 0) {
    $sum = 0;
    foreach ($grades as $grade) {
        $percentage = ($grade['max_grade'] > 0) ? ($grade['grade'] / $grade['max_grade']) * 100 : 0;
        $sum += $percentage;
        $highest_grade = max($highest_grade, $percentage);
        $lowest_grade = min($lowest_grade, $percentage);
    }
    $average_grade = $sum / $total_grades;
}

$page_title = $cadet_id ? 'Cadet Grades' : 'My Grades';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
</head>

<body data-role="<?php echo $_SESSION['role']; ?>">
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-medal"></i></div>
                    <span class="logo-text">Cadet Portal</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../cadet_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../cadet_attendance_new.php" class="nav-link">
                            <i class="fas fa-calendar-check"></i>
                            <span>My Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="view_grades.php" class="nav-link active">
                            <i class="fas fa-graduation-cap"></i>
                            <span>My Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../my_profile.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile</span>
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
                    <h1><i class="fas fa-graduation-cap"></i> Grades</h1>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <span class="user-name"><?php echo htmlspecialchars($cadet_info['first_name'] . ' ' . $cadet_info['last_name']); ?></span>
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Cadet Info Header -->
                <div class="cadet-header">
                    <div class="cadet-info">
                        <h2><?php echo htmlspecialchars($cadet_info['first_name'] . ' ' . $cadet_info['last_name']); ?></h2>
                        <p class="cadet-details">
                            <?php echo htmlspecialchars($cadet_info['student_id'] ?? 'N/A'); ?> • 
                            <?php echo htmlspecialchars($cadet_info['platoon'] ?? 'N/A'); ?> Platoon
                        </p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline" onclick="goBack()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                    </div>
                </div>

                <!-- Grade Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($average_grade, 1); ?>%</div>
                            <div class="stat-label">Average Grade</div>
                            <div class="stat-change">Overall performance</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($highest_grade, 1); ?>%</div>
                            <div class="stat-label">Highest Grade</div>
                            <div class="stat-change">Best performance</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-list-ol"></i>
                            </div>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_grades; ?></div>
                            <div class="stat-label">Total Grades</div>
                            <div class="stat-change">Recorded assessments</div>
                        </div>
                    </div>
                </div>

                <!-- Grades Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Grade History</h3>
                        <div class="card-actions">
                            <select class="form-select" id="filterPeriod">
                                <option value="">All Time</option>
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 3 Months</option>
                                <option value="365">Last Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-content">
                        <?php if (empty($grades)): ?>
                            <div class="empty-state">
                                <i class="fas fa-graduation-cap"></i>
                                <h4>No Grades Found</h4>
                                <p>No grades have been recorded yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table" id="gradesTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Event/Assessment</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Recorded By</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): 
                                            $percentage = ($grade['max_grade'] > 0) ? ($grade['grade'] / $grade['max_grade']) * 100 : 0;
                                            $grade_class = '';
                                            if ($percentage >= 90) $grade_class = 'grade-excellent';
                                            elseif ($percentage >= 80) $grade_class = 'grade-good';
                                            elseif ($percentage >= 70) $grade_class = 'grade-fair';
                                            else $grade_class = 'grade-poor';
                                        ?>
                                            <tr data-date="<?php echo $grade['grade_date']; ?>">
                                                <td><?php echo date('M d, Y', strtotime($grade['grade_date'])); ?></td>
                                                <td>
                                                    <?php if ($grade['record_type'] === 'quiz'): ?>
                                                        <i class="fas fa-question-circle" style="color: #007bff; margin-right: 5px;"></i>
                                                        <strong><?php echo htmlspecialchars($grade['event_name']); ?></strong>
                                                        <span class="badge badge-quiz">Quiz</span>
                                                    <?php else: ?>
                                                        <i class="fas fa-clipboard-list" style="color: #28a745; margin-right: 5px;"></i>
                                                        <strong><?php echo htmlspecialchars($grade['event_name']); ?></strong>
                                                        <span class="badge badge-grade">Grade</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="grade-score">
                                                        <?php echo htmlspecialchars($grade['grade']); ?>
                                                        <?php if ($grade['max_grade']): ?>
                                                            / <?php echo htmlspecialchars($grade['max_grade']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="grade-percentage <?php echo $grade_class; ?>">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($grade['officer_username']); ?></td>
                                                <td>
                                                    <?php if (!empty($grade['comments'])): ?>
                                                        <span class="grade-comments" title="<?php echo htmlspecialchars($grade['comments']); ?>">
                                                            <i class="fas fa-comment"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/dashboard-redesigned.js"></script>
    <script>
        // Search functionality
        document.getElementById('gradeSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#gradesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter by period
        document.getElementById('filterPeriod').addEventListener('change', function(e) {
            const days = parseInt(e.target.value);
            const rows = document.querySelectorAll('#gradesTable tbody tr');
            
            if (!days) {
                rows.forEach(row => row.style.display = '');
                return;
            }
            
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - days);
            
            rows.forEach(row => {
                const dateStr = row.dataset.date;
                const rowDate = new Date(dateStr);
                row.style.display = rowDate >= cutoffDate ? '' : 'none';
            });
        });

        function goBack() {
            if (document.referrer) {
                window.history.back();
            } else {
                window.location.href = '../my_platoons.php';
            }
        }

        // Tooltip for comments
        document.querySelectorAll('.grade-comments').forEach(comment => {
            comment.addEventListener('click', function() {
                alert(this.title);
            });
        });
    </script>

    <style>
        .cadet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: var(--card-bg);
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .cadet-info h2 {
            margin: 0;
            color: var(--text-primary);
        }

        .cadet-details {
            margin: 0.5rem 0 0 0;
            color: var(--text-secondary);
        }

        .grade-score {
            font-weight: 600;
        }

        .grade-percentage {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            color: white;
        }

        .grade-excellent {
            background: var(--success);
        }

        .grade-good {
            background: var(--primary);
        }

        .grade-fair {
            background: #28a745;
        }

        .grade-poor {
            background: var(--danger);
        }

        .grade-comments {
            cursor: pointer;
            color: var(--primary);
        }

        .grade-comments:hover {
            color: var(--primary-dark);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 12px;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-quiz {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .badge-grade {
            background-color: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        @media (max-width: 768px) {
            .cadet-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }
        }
    </style>
</body>
</html>
