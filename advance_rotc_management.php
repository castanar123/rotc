<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    $securityLogger = new SecurityLogger($pdo);
    $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access advance ROTC management', 'high');
    header('Location: login.php');
    exit;
}

// Log successful admin access to advance ROTC management
$securityLogger = new SecurityLogger($pdo);
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_ACCESS', 'Admin accessed advance ROTC management page', 'low');

// Handle delete action
if (isset($_POST['delete_signup']) && isset($_POST['signup_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM advance_rotc_signups WHERE id = ?");
        $stmt->execute([$_POST['signup_id']]);
        $securityLogger = new SecurityLogger($pdo);
        $securityLogger->logSecurityEvent($_SESSION['user_id'], 'DATA_MODIFICATION', 'Admin deleted advance ROTC signup ID: ' . $_POST['signup_id'], 'medium');
        $success_message = "Signup deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting signup: " . $e->getMessage();
    }
}

// Handle edit action
if (isset($_POST['edit_signup'])) {
    try {
        $stmt = $pdo->prepare("UPDATE advance_rotc_signups SET full_name = ?, course = ?, facebook_link = ? WHERE id = ?");
        $stmt->execute([
            $_POST['full_name'],
            $_POST['course'],
            $_POST['facebook_link'],
            $_POST['signup_id']
        ]);
        $securityLogger = new SecurityLogger($pdo);
        $securityLogger->logSecurityEvent($_SESSION['user_id'], 'DATA_MODIFICATION', 'Admin updated advance ROTC signup ID: ' . $_POST['signup_id'], 'medium');
        $success_message = "Signup updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating signup: " . $e->getMessage();
    }
}

// Get all advance ROTC signups
try {
    $stmt = $pdo->query("SELECT * FROM advance_rotc_signups ORDER BY created_at DESC");
    $signups = $stmt->fetchAll();
    
    // Get summary statistics
    $total_signups = count($signups);
    
    // Get signups by course
    $stmt = $pdo->query("SELECT course, COUNT(*) as count FROM advance_rotc_signups GROUP BY course ORDER BY count DESC");
    $course_stats = $stmt->fetchAll();
    
    // Get recent signups (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM advance_rotc_signups WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recent_signups = $stmt->fetch()['count'];
    
    // Get pending registrations count for sidebar badge
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
    $pending_registrations = $stmt->fetch()['total'];
    
    // Get advance ROTC count for sidebar badge
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM advance_rotc_signups");
    $advance_rotc_count = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
    $signups = [];
    $total_signups = 0;
    $course_stats = [];
    $recent_signups = 0;
    $pending_registrations = 0;
    $advance_rotc_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Officer Respondents - Admin Panel</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        /* Remove custom styles - use admin dashboard classes */
        .advance-rotc-content {
            /* Styles handled by main-content class */
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: var(--spacing-lg) var(--spacing-xl);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .search-box {
            padding: 8px 15px;
            border: none;
            border-radius: 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            width: 250px;
        }
        
        .search-box::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: var(--spacing-md) var(--spacing-lg);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        
        .data-table tr:hover {
            background: var(--bg-secondary);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        
        .btn-edit {
            background: var(--info-color);
            color: white;
        }
        
        .btn-edit:hover {
            background: var(--info-dark);
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
        }
        
        .facebook-link {
            color: #1877f2;
            text-decoration: none;
            font-weight: 500;
        }
        
        .facebook-link:hover {
            text-decoration: underline;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            margin: 5% auto;
            padding: var(--spacing-xl);
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--text-accent);
            font-size: 1.5rem;
        }
        
        .close {
            color: var(--text-secondary);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: var(--text-primary);
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            font-size: 1rem;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--military-green);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: var(--success-light);
            color: var(--success-dark);
            border: 1px solid var(--success-color);
        }
        
        .alert-error {
            background: var(--danger-light);
            color: var(--danger-dark);
            border: 1px solid var(--danger-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .advance-rotc-content {
                padding: var(--spacing-md);
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                font-size: 0.9rem;
            }
            
            .data-table th,
            .data-table td {
                padding: var(--spacing-sm);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '';
            include __DIR__ . '/includes/admin_nav.php';
        ?>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="dashboard-header">
                <h1><i class="fas fa-star-of-life"></i> Advance Officer Respondents</h1>
                <p>Manage and monitor advance ROTC program signups</p>
            </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_signups; ?></div>
                        <div class="stat-label">Total Signups</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $recent_signups; ?></div>
                        <div class="stat-label">Recent (7 Days)</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($course_stats); ?></div>
                        <div class="stat-label">Different Courses</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_signups > 0 ? round(($recent_signups / $total_signups) * 100, 1) : 0; ?>%</div>
                        <div class="stat-label">Recent Activity</div>
                    </div>
                </div>
                
                <!-- Signups Table -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> All Signups</h2>
                        <input type="text" class="search-box" placeholder="Search signups..." id="searchInput">
                    </div>
                    
                    <?php if (empty($signups)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No signups yet</h3>
                            <p>Advance ROTC signups will appear here once students start registering.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table" id="signupsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Course</th>
                                    <th>Facebook Link</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($signups as $signup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($signup['id']); ?></td>
                                        <td><?php echo htmlspecialchars($signup['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($signup['course']); ?></td>
                                        <td>
                                            <?php if (!empty($signup['facebook_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($signup['facebook_link']); ?>" target="_blank" class="facebook-link">
                                                    <i class="fab fa-facebook"></i> View Profile
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($signup['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-edit" onclick="editSignup(<?php echo $signup['id']; ?>, '<?php echo htmlspecialchars($signup['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($signup['course'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($signup['facebook_link'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this signup?')">
                                                    <input type="hidden" name="signup_id" value="<?php echo $signup['id']; ?>">
                                                    <button type="submit" name="delete_signup" class="btn btn-delete">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Signup</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="signup_id" id="editSignupId">
                
                <div class="form-group">
                    <label for="editFullName">Full Name:</label>
                    <input type="text" name="full_name" id="editFullName" required>
                </div>
                
                <div class="form-group">
                    <label for="editCourse">Course:</label>
                    <input type="text" name="course" id="editCourse" required>
                </div>
                
                <div class="form-group">
                    <label for="editFacebookLink">Facebook Link:</label>
                    <input type="url" name="facebook_link" id="editFacebookLink">
                </div>
                
                <button type="submit" name="edit_signup" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <script src="js/dashboard-modern.js"></script>
    <script src="js/mobile-navigation.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('signupsTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) {
                    if (cells[j].textContent.toLowerCase().includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });
        
        // Edit modal functions
        function editSignup(id, fullName, course, facebookLink) {
            document.getElementById('editSignupId').value = id;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editCourse').value = course;
            document.getElementById('editFacebookLink').value = facebookLink;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>