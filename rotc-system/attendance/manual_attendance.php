<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['loggedin'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Only allow admin and instructors to manually record attendance
if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
    header('Location: ../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $attendance_date = $_POST['attendance_date'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        // Verify user exists
        $stmt = $pdo->prepare("SELECT cp.first_name, cp.last_name FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'User not found';
        } else {
            // Check if attendance already exists for this date
            $stmt = $pdo->prepare("
                SELECT id FROM attendance_logs 
                WHERE user_id = ? AND DATE(timestamp) = ?
            ");
            $stmt->execute([$user_id, $attendance_date]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error = 'Attendance already recorded for ' . $user['first_name'] . ' ' . $user['last_name'] . ' on this date';
            } else {
                // Record attendance
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_logs (user_id, timestamp, method, recorded_by, notes) 
                    VALUES (?, ?, 'manual', ?, ?)
                ");
                $stmt->execute([$user_id, $attendance_date . ' ' . date('H:i:s'), $_SESSION['user_id'], $notes]);
                
                // Log the activity
                $activity_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, timestamp) 
                    VALUES (?, 'attendance_recorded', ?, NOW())
                ");
                $activity_details = json_encode([
                    'method' => 'manual',
                    'recorded_by' => $_SESSION['user_id'],
                    'target_user' => $user_id,
                    'target_name' => $user['first_name'] . ' ' . $user['last_name'],
                    'date' => $attendance_date,
                    'notes' => $notes
                ]);
                $activity_stmt->execute([$_SESSION['user_id'], $activity_details]);
                
                $message = 'Attendance recorded successfully for ' . $user['first_name'] . ' ' . $user['last_name'];
            }
        }
    } catch (PDOException $e) {
        error_log("Manual attendance error: " . $e->getMessage());
        $error = 'Database error occurred';
    }
}

// Get all users for the dropdown
try {
    $stmt = $pdo->query("
        SELECT u.id, cp.first_name, cp.last_name, u.role, cp.platoon 
        FROM users u 
        JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' AND u.status = 'active' 
        ORDER BY cp.last_name, cp.first_name
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get users error: " . $e->getMessage());
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Attendance Entry - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-role="<?php echo $_SESSION['role']; ?>">
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Attendance
                </a>
                <h1 class="page-title">Manual Attendance Entry</h1>
            </div>
            
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                        <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-edit"></i>
                        Record Attendance Manually
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="form">
                        <div class="form-group">
                            <label for="user_id" class="form-label">
                                <i class="fas fa-user"></i>
                                Select Cadet/Officer
                            </label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Choose a person...</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['last_name'] . ', ' . $user['first_name']); ?>
                                    (<?php echo ucfirst($user['role']); ?>)
                                    <?php if ($user['platoon']): ?>
                                        - Platoon <?php echo htmlspecialchars($user['platoon']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="attendance_date" class="form-label">
                                <i class="fas fa-calendar"></i>
                                Attendance Date
                            </label>
                            <input type="date" name="attendance_date" id="attendance_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note"></i>
                                Notes (Optional)
                            </label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" 
                                      placeholder="Add any additional notes about this attendance record..."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Record Attendance
                            </button>
                            <a href="dashboard.php" class="btn btn-outline">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Manual Entries -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Recent Manual Entries
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Recorded By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("
                                        SELECT al.*, cp.first_name, cp.last_name, u.role,
                                               rcp.first_name as recorder_first, rcp.last_name as recorder_last
                                        FROM attendance_logs al 
                                        JOIN users u ON al.user_id = u.id 
                                        JOIN cadet_profiles cp ON u.id = cp.user_id
                                        LEFT JOIN users r ON al.recorded_by = r.id
                                        LEFT JOIN cadet_profiles rcp ON r.id = rcp.user_id
                                        WHERE al.method = 'manual'
                                        ORDER BY al.timestamp DESC 
                                        LIMIT 10
                                    ");
                                    $manual_entries = $stmt->fetchAll();
                                    
                                    foreach ($manual_entries as $entry):
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($entry['timestamp'])); ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($entry['first_name'], 0, 1) . substr($entry['last_name'], 0, 1)); ?>
                                            </div>
                                            <span class="user-name"><?php echo htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo ucfirst($entry['role']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($entry['recorder_first']): ?>
                                            <?php echo htmlspecialchars($entry['recorder_first'] . ' ' . $entry['recorder_last']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($entry['notes']): ?>
                                            <span class="text-truncate" title="<?php echo htmlspecialchars($entry['notes']); ?>">
                                                <?php echo htmlspecialchars(substr($entry['notes'], 0, 50)); ?>
                                                <?php if (strlen($entry['notes']) > 50): ?>...
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No notes</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="5" class="text-center text-muted">Error loading recent entries</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on user selection
        document.addEventListener('DOMContentLoaded', function() {
            const userSelect = document.getElementById('user_id');
            if (userSelect) {
                userSelect.focus();
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const userId = document.getElementById('user_id').value;
            const date = document.getElementById('attendance_date').value;
            
            if (!userId) {
                e.preventDefault();
                alert('Please select a cadet or officer.');
                return;
            }
            
            if (!date) {
                e.preventDefault();
                alert('Please select an attendance date.');
                return;
            }
            
            // Confirm submission
            const userName = document.getElementById('user_id').selectedOptions[0].text;
            if (!confirm(`Record attendance for ${userName} on ${date}?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
