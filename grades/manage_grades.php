<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Handle form submission for adding grades
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_grade') {
    try {
        $cadet_id = $_POST['cadet_id'];
        $semester = $_POST['semester'];
        $academic_year = $_POST['academic_year'];
        $written_work = $_POST['written_work'];
        $performance_task = $_POST['performance_task'];
        $quarterly_exam = $_POST['quarterly_exam'];
        
        // Calculate total grade (30% WW, 50% PT, 20% QE)
        $total_grade = ($written_work * 0.30) + ($performance_task * 0.50) + ($quarterly_exam * 0.20);
        
        $stmt = $pdo->prepare("
            INSERT INTO grades (cadet_id, semester, academic_year, written_work, performance_task, quarterly_exam, total_grade, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $cadet_id,
            $semester,
            $academic_year,
            $written_work,
            $performance_task,
            $quarterly_exam,
            $total_grade,
            $_SESSION['user_id']
        ]);
        
        // Process quiz scores if provided
        if (isset($_POST['quiz_names']) && isset($_POST['quiz_scores']) && isset($_POST['quiz_max_scores'])) {
            $quiz_names = $_POST['quiz_names'];
            $quiz_scores = $_POST['quiz_scores'];
            $quiz_max_scores = $_POST['quiz_max_scores'];
            
            $quiz_stmt = $pdo->prepare("
                INSERT INTO quiz_scores (cadet_id, quiz_name, score, max_score, semester, academic_year, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            for ($i = 0; $i < count($quiz_names); $i++) {
                // Only insert if quiz name and score are provided
                if (!empty($quiz_names[$i]) && !empty($quiz_scores[$i])) {
                    $max_score = !empty($quiz_max_scores[$i]) ? $quiz_max_scores[$i] : 100;
                    
                    $quiz_stmt->execute([
                        $cadet_id,
                        trim($quiz_names[$i]),
                        floatval($quiz_scores[$i]),
                        floatval($max_score),
                        $semester,
                        $academic_year,
                        $_SESSION['user_id']
                    ]);
                }
            }
        }
        
        $success_message = 'Grade and quiz scores added successfully!';
        
        // Refresh the page to show updated data
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $error_message = 'Error adding grade: ' . $e->getMessage();
    }
}

// Pending registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];

// Fetch all cadets with their platoon information
$sql = "SELECT u.id, p.first_name, p.last_name, p.platoon FROM users u JOIN cadet_profiles p ON u.id = p.user_id WHERE u.role IN ('cadet', 'basic_cadet') ORDER BY p.platoon, p.last_name, p.first_name";
$cadets = [];
if($result = mysqli_query($link, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        $cadets[] = $row;
    }
}

// Fetch existing grades for display
$grades_sql = "SELECT g.*, u.id as user_id, p.first_name, p.last_name, p.platoon 
               FROM grades g 
               JOIN users u ON g.cadet_id = u.id 
               JOIN cadet_profiles p ON u.id = p.user_id 
               ORDER BY g.created_at DESC LIMIT 50";
$grades = [];
if($result = mysqli_query($link, $grades_sql)){
    while($row = mysqli_fetch_assoc($result)){
        $grades[] = $row;
    }
}

// Fetch quiz scores for display
$quiz_sql = "SELECT qs.*, u.id as user_id, p.first_name, p.last_name, p.platoon 
             FROM quiz_scores qs 
             JOIN users u ON qs.cadet_id = u.id 
             JOIN cadet_profiles p ON u.id = p.user_id 
             ORDER BY qs.created_at DESC LIMIT 50";
$quiz_scores = [];
if($result = mysqli_query($link, $quiz_sql)){
    while($row = mysqli_fetch_assoc($result)){
        $quiz_scores[] = $row;
    }
}

// Get statistics for dashboard
$total_grades_sql = "SELECT COUNT(*) as total FROM grades";
$total_grades_result = mysqli_query($link, $total_grades_sql);
$total_grades = mysqli_fetch_assoc($total_grades_result)['total'] ?? 0;

$recent_grades_sql = "SELECT COUNT(*) as total FROM grades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$recent_grades_result = mysqli_query($link, $recent_grades_sql);
$recent_grades = mysqli_fetch_assoc($recent_grades_result)['total'] ?? 0;

// Check if grades table exists first
$table_check = "SHOW TABLES LIKE 'grades'";
$table_exists = mysqli_query($link, $table_check);

if($table_exists && mysqli_num_rows($table_exists) > 0) {
    $avg_grade_sql = "SELECT AVG(total_grade) as avg_grade FROM grades WHERE total_grade IS NOT NULL";
    $avg_grade_result = mysqli_query($link, $avg_grade_sql);
    $avg_grade = round(mysqli_fetch_assoc($avg_grade_result)['avg_grade'] ?? 0, 1);
} else {
    $avg_grade = 0;
}

$total_cadets = count($cadets);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
   <button class="sidebar-toggle-fixed" id="sidebarToggle">
         <i class="fas fa-bars"></i>
     </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="header-left">
                    <h1 class="page-title">Grades Management</h1>
                    <p class="page-subtitle">Manage cadet academic performance and assessments</p>
                </div>
                <div class="header-actions">
                    <!-- Form is now directly on the page -->
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_grades; ?></h3>
                        <p>Total Grades</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_cadets; ?></h3>
                        <p>Total Cadets</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $avg_grade; ?>%</h3>
                        <p>Average Grade</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $recent_grades; ?></h3>
                        <p>Recent Grades (7 days)</p>
                    </div>
                </div>
            </div>

            <!-- Grades Table -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Grades</h2>
                    <div class="card-actions">
                        <input type="text" id="gradeSearch" placeholder="Search grades..." class="search-input">
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cadet Name</th>
                                <th>Platoon</th>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="gradesTableBody">
                            <?php foreach($grades as $grade): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['platoon']); ?></td>
                                <td><?php echo htmlspecialchars($grade['subject'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="grade-badge <?php echo $grade['total_grade'] >= 75 ? 'passing' : 'failing'; ?>">
                                        <?php echo $grade['total_grade'] ?? 'N/A'; ?>%
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($grade['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="editGrade(<?php echo $grade['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteGrade(<?php echo $grade['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Quiz Scores Table -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Quiz Scores</h2>
                    <div class="card-actions">
                        <input type="text" id="quizSearch" placeholder="Search quiz scores..." class="search-input">
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cadet Name</th>
                                <th>Platoon</th>
                                <th>Quiz Name</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Semester</th>
                                <th>Academic Year</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="quizTableBody">
                            <?php foreach($quiz_scores as $quiz): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($quiz['first_name'] . ' ' . $quiz['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($quiz['platoon']); ?></td>
                                <td><?php echo htmlspecialchars($quiz['quiz_name']); ?></td>
                                <td><?php echo $quiz['score']; ?>/<?php echo $quiz['max_score']; ?></td>
                                <td>
                                    <span class="grade-badge <?php echo $quiz['percentage'] >= 75 ? 'passing' : 'failing'; ?>">
                                        <?php echo number_format($quiz['percentage'], 1); ?>%
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($quiz['semester']); ?></td>
                                <td><?php echo htmlspecialchars($quiz['academic_year']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="editQuiz(<?php echo $quiz['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Add Grade Form -->
            <div class="add-grade-section">
                <div class="section-header">
                    <h2>Add New Grade</h2>
                    <p>Enter grade information for a cadet</p>
                </div>
                
                <form id="addGradeForm" method="POST" class="grade-form">
                    <input type="hidden" name="action" value="add_grade">
                    
                    <div class="form-group">
                        <label for="cadet_id">Select Cadet:</label>
                        <select name="cadet_id" id="cadet_id" required>
                            <option value="">Choose a cadet...</option>
                            <?php foreach($cadets as $cadet): ?>
                                <option value="<?php echo $cadet['id']; ?>">
                                    <?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name'] . ' - ' . $cadet['platoon']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="semester">Semester:</label>
                            <select name="semester" id="semester" required>
                                <option value="">Select Semester</option>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="academic_year">Academic Year:</label>
                            <input type="text" name="academic_year" id="academic_year" placeholder="e.g., 2023-2024" required>
                        </div>
                    </div>
                    
                    <div class="grade-components">
                        <h4>Grade Components</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="written_work">Written Work (30%):</label>
                                <input type="number" name="written_work" id="written_work" min="0" max="100" step="0.1" required>
                            </div>
                            <div class="form-group">
                                <label for="performance_task">Performance Task (50%):</label>
                                <input type="number" name="performance_task" id="performance_task" min="0" max="100" step="0.1" required>
                            </div>
                            <div class="form-group">
                                <label for="quarterly_exam">Quarterly Exam (20%):</label>
                                <input type="number" name="quarterly_exam" id="quarterly_exam" min="0" max="100" step="0.1" required>
                            </div>
                        </div>
                        
                        <div class="calculated-grade">
                            <label>Calculated Total Grade:</label>
                            <div id="total_grade_display" class="grade-display">0.0%</div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">Clear Form</button>
                        <button type="submit" class="btn btn-primary">Add Grade</button>
                    </div>
                </form>
                
                <!-- Separate Quiz Scores Form -->
                <div class="quiz-scores-section">
                    <div class="section-header">
                        <h2>Add Quiz Scores</h2>
                        <p>Add individual quiz scores separately</p>
                    </div>
                    
                    <form id="addQuizForm" method="POST" class="quiz-form">
                        <input type="hidden" name="action" value="add_quiz">
                        
                        <div class="form-group">
                            <label for="quiz_cadet_id">Select Cadet:</label>
                            <select name="cadet_id" id="quiz_cadet_id" required>
                                <option value="">Choose a cadet...</option>
                                <?php foreach($cadets as $cadet): ?>
                                    <option value="<?php echo $cadet['id']; ?>">
                                        <?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name'] . ' - ' . $cadet['platoon']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="quiz_semester">Semester:</label>
                                <select name="semester" id="quiz_semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="1">1st Semester</option>
                                    <option value="2">2nd Semester</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quiz_academic_year">Academic Year:</label>
                                <input type="text" name="academic_year" id="quiz_academic_year" placeholder="e.g., 2023-2024" required>
                            </div>
                        </div>
                        
                        <div class="quiz-section">
                            <h4>Quiz Scores</h4>
                            <p>Add individual quiz scores for this cadet</p>
                            <div id="quiz-container">
                                <div class="quiz-entry">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="quiz_name_1">Quiz Name:</label>
                                            <input type="text" name="quiz_names[]" id="quiz_name_1" placeholder="e.g., Quiz 1, Midterm Quiz" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="quiz_score_1">Score:</label>
                                            <input type="number" name="quiz_scores[]" id="quiz_score_1" min="0" max="100" step="0.1" placeholder="0.0" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="quiz_max_1">Max Score:</label>
                                            <input type="number" name="quiz_max_scores[]" id="quiz_max_1" min="1" max="100" step="0.1" value="100" placeholder="100" required>
                                        </div>
                                        <div class="form-group quiz-actions">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeQuiz(this)" style="margin-top: 1.5rem;">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addQuizEntry()">+ Add Another Quiz</button>
                        </div>
                        
                        <div class="form-actions">
                            <button type="reset" class="btn btn-secondary">Clear Quiz Form</button>
                            <button type="submit" class="btn btn-success">Add Quiz Scores</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-primary);
        border-radius: 8px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .grade-components {
        margin: 1.5rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        border: 1px solid var(--border-primary);
    }
    
    .grade-components h4 {
        margin: 0 0 1rem 0;
        color: var(--text-primary);
    }
    
    .quiz-section {
        margin: 1.5rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 8px;
        border: 1px solid var(--border-primary);
    }
    
    .quiz-section h4 {
        margin: 0 0 0.5rem 0;
        color: var(--text-primary);
    }
    
    .quiz-section p {
        margin: 0 0 1rem 0;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .quiz-entry {
        margin-bottom: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .quiz-actions {
        display: flex;
        align-items: flex-end;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }
    
    .quiz-section .form-row {
        grid-template-columns: 2fr 1fr 1fr auto;
    }
    
    .quiz-scores-section {
        margin-top: 2rem;
        padding: 1.5rem;
        background: var(--bg-secondary);
        border: 1px solid var(--border-primary);
        border-radius: 8px;
    }
    
    .quiz-scores-section .section-header {
        margin-bottom: 1.5rem;
    }
    
    .quiz-scores-section .section-header h2 {
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }
    
    .quiz-scores-section .section-header p {
        color: var(--text-secondary);
        margin: 0;
    }
    
    .quiz-form {
        background: transparent;
    }
    
    .calculated-grade {
        margin-top: 1rem;
        padding: 1rem;
        background: rgba(40, 167, 69, 0.1);
        border-radius: 6px;
        border: 1px solid rgba(40, 167, 69, 0.3);
    }
    
    .calculated-grade label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .grade-display {
        font-size: 1.5rem;
        font-weight: bold;
        color: #28a745;
        text-align: center;
        padding: 0.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
        gap: 1rem;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: var(--military-green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-content h3 {
        font-size: 2rem;
        font-weight: bold;
        color: var(--text-primary);
        margin: 0;
    }

    .stat-content p {
        color: var(--text-secondary);
        margin: 0;
        font-size: 0.9rem;
    }

    .grade-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.85rem;
    }

    .grade-badge.passing {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .grade-badge.failing {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    /* Removed modal styles - no longer needed */

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border-primary);
        border-radius: 4px;
        color: var(--text-primary);
        font-size: 1rem;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--military-green);
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }

    /* Alert Messages */
    .alert {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
    }
    
    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.3);
        color: #28a745;
    }
    
    .alert-error {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #dc3545;
    }
    
    .alert i {
        font-size: 1.2rem;
    }
    
    /* Add Grade Section */
    .add-grade-section {
        background: var(--bg-secondary);
        border: 1px solid var(--border-primary);
        border-radius: 8px;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .section-header {
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--border-primary);
        padding-bottom: 1rem;
    }
    
    .section-header h2 {
        margin: 0 0 0.5rem 0;
        color: var(--text-primary);
        font-size: 1.5rem;
    }
    
    .section-header p {
        margin: 0;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .grade-form {
        max-width: 800px;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            width: 95%;
            margin: 1rem;
        }
    }
    </style>

    <script>
    /* Removed modal functions - no longer needed */

    function editGrade(gradeId) {
        // Implement edit functionality
        alert('Edit grade functionality to be implemented');
    }

    function deleteGrade(gradeId) {
        if(confirm('Are you sure you want to delete this grade?')) {
            // Implement delete functionality
            alert('Delete grade functionality to be implemented');
        }
    }

    // Real-time grade calculation
    function calculateTotalGrade() {
        const writtenWork = parseFloat(document.getElementById('written_work').value) || 0;
        const performanceTask = parseFloat(document.getElementById('performance_task').value) || 0;
        const quarterlyExam = parseFloat(document.getElementById('quarterly_exam').value) || 0;
        
        // Calculate weighted average (30% WW, 50% PT, 20% QE)
        const totalGrade = (writtenWork * 0.30) + (performanceTask * 0.50) + (quarterlyExam * 0.20);
        
        document.getElementById('total_grade_display').textContent = totalGrade.toFixed(1) + '%';
        
        // Update color based on passing/failing
        const display = document.getElementById('total_grade_display');
        if (totalGrade >= 75) {
            display.style.color = '#28a745';
        } else {
            display.style.color = '#dc3545';
        }
    }

    // Add event listeners for grade calculation
    document.addEventListener('DOMContentLoaded', function() {
        const gradeInputs = ['written_work', 'performance_task', 'quarterly_exam'];
        gradeInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', calculateTotalGrade);
                input.addEventListener('change', calculateTotalGrade);
            }
        });
    });

    // Search functionality for grades
    document.getElementById('gradeSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#gradesTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    // Search functionality for quiz scores
    document.getElementById('quizSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#quizTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    // Quiz management functions
    function editQuiz(quizId) {
        // Implement edit quiz functionality
        alert('Edit quiz functionality to be implemented');
    }
    
    function deleteQuiz(quizId) {
        if(confirm('Are you sure you want to delete this quiz score?')) {
            // Implement delete quiz functionality
            alert('Delete quiz functionality to be implemented');
        }
    }

    // Quiz functionality
    let quizCounter = 1;
    
    function addQuizEntry() {
        quizCounter++;
        const container = document.getElementById('quiz-container');
        const newQuizEntry = document.createElement('div');
        newQuizEntry.className = 'quiz-entry';
        newQuizEntry.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label for="quiz_name_${quizCounter}">Quiz Name:</label>
                    <input type="text" name="quiz_names[]" id="quiz_name_${quizCounter}" placeholder="e.g., Quiz ${quizCounter}, Midterm Quiz">
                </div>
                <div class="form-group">
                    <label for="quiz_score_${quizCounter}">Score:</label>
                    <input type="number" name="quiz_scores[]" id="quiz_score_${quizCounter}" min="0" max="100" step="0.1" placeholder="0.0">
                </div>
                <div class="form-group">
                    <label for="quiz_max_${quizCounter}">Max Score:</label>
                    <input type="number" name="quiz_max_scores[]" id="quiz_max_${quizCounter}" min="1" max="100" step="0.1" value="100" placeholder="100">
                </div>
                <div class="form-group quiz-actions">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeQuiz(this)" style="margin-top: 1.5rem;">Remove</button>
                </div>
            </div>
        `;
        container.appendChild(newQuizEntry);
    }
    
    function removeQuiz(button) {
        const quizEntry = button.closest('.quiz-entry');
        const container = document.getElementById('quiz-container');
        
        // Don't allow removing the last quiz entry
        if (container.children.length > 1) {
            quizEntry.remove();
        } else {
            alert('At least one quiz entry must remain.');
        }
    }
    
    // Clear quiz entries when form is reset
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('addGradeForm');
        if (form) {
            form.addEventListener('reset', function() {
                // Reset quiz counter and remove extra quiz entries
                const container = document.getElementById('quiz-container');
                const entries = container.querySelectorAll('.quiz-entry');
                
                // Remove all but the first entry
                for (let i = 1; i < entries.length; i++) {
                    entries[i].remove();
                }
                
                // Reset counter
                quizCounter = 1;
                
                // Clear the first entry's values
                const firstEntry = container.querySelector('.quiz-entry');
                if (firstEntry) {
                    firstEntry.querySelectorAll('input').forEach(input => {
                        if (input.type === 'number' && input.name === 'quiz_max_scores[]') {
                            input.value = '100';
                        } else {
                            input.value = '';
                        }
                    });
                }
            });
        }
    });
    
    /* Removed modal event listener - no longer needed */
    </script>

    <!-- Include mobile navigation -->
    <script src="../js/mobile-navigation.js"></script>
</body>
</html>
