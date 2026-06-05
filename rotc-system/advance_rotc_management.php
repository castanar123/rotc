<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once '../includes/SecurityLogger.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access advance ROTC management (rotc-system)', $_SESSION['user_id'] ?? null, 'HIGH');
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Log successful admin access to advance ROTC management
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed advance ROTC management page (rotc-system)', $_SESSION['user_id'], 'LOW');

// Handle delete action
if (isset($_POST['delete_signup']) && isset($_POST['signup_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM advance_rotc_signups WHERE id = ?");
        $stmt->execute([$_POST['signup_id']]);
        SecurityLogger::logSecurityEvent('DATA_MODIFICATION', 'Admin deleted advance ROTC signup ID: ' . $_POST['signup_id'] . ' (rotc-system)', $_SESSION['user_id'], 'MEDIUM');
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
        SecurityLogger::logSecurityEvent('DATA_MODIFICATION', 'Admin updated advance ROTC signup ID: ' . $_POST['signup_id'] . ' (rotc-system)', $_SESSION['user_id'], 'MEDIUM');
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
    
} catch (PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
    $signups = [];
    $total_signups = 0;
    $course_stats = [];
    $recent_signups = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance ROTC Management - Admin Panel</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .management-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 5px solid #1a472a;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a472a;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .signups-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .search-box {
            padding: 8px 15px;
            border: none;
            border-radius: 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            placeholder-color: rgba(255,255,255,0.7);
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
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-edit {
            background: #007bff;
            color: white;
        }
        
        .btn-edit:hover {
            background: #0056b3;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
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
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1a472a;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #1a472a;
        }
        
        .btn-primary {
            background: #1a472a;
            color: white;
            padding: 12px 25px;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: #2d5a3d;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .management-container {
                padding: 10px;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                font-size: 0.9rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="management-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-medal"></i> Advance ROTC Management</h1>
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
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_signups; ?></div>
                <div class="stat-label">Total Signups</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $recent_signups; ?></div>
                <div class="stat-label">Recent Signups (7 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($course_stats); ?></div>
                <div class="stat-label">Different Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_signups > 0 ? round(($recent_signups / $total_signups) * 100, 1) : 0; ?>%</div>
                <div class="stat-label">Recent Activity Rate</div>
            </div>
        </div>
        
        <!-- Signups Table -->
        <div class="signups-table">
            <div class="table-header">
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
                                        <span style="color: #999;">Not provided</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($signup['created_at'])); ?></td>
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
        
        <!-- Back to Dashboard -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Signup</h3>
                <span class="close" onclick="closeModal()">&times;</span>
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
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>