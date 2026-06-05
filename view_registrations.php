<?php
require_once 'includes/db.php';

// Get all users and their cadet profiles
$query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.role,
        u.created_at as user_created,
        cp.student_id,
        cp.first_name,
        cp.last_name,
        cp.middle_name,
        cp.gender,
        cp.course,
        cp.section,
        cp.platoon,
        cp.contact_number,
        cp.created_at as profile_created
    FROM users u
    LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .role-cadet {
            background: #e3f2fd;
            color: #1976d2;
        }
        .role-officer {
            background: #fff3e0;
            color: #f57c00;
        }
        .role-admin {
            background: #fce4ec;
            color: #c2185b;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Registration Viewer</h1>
            <div>
                <a href="test_auto_register.php" class="btn">Back to Tester</a>
                <button onclick="location.reload()" class="btn">Refresh</button>
            </div>
        </div>
        
        <?php
        // Calculate statistics
        $totalUsers = count($registrations);
        $cadets = array_filter($registrations, function($r) { return $r['role'] === 'cadet'; });
        $officers = array_filter($registrations, function($r) { return $r['role'] === 'officer'; });
        $admins = array_filter($registrations, function($r) { return $r['role'] === 'admin'; });
        $testUsers = array_filter($registrations, function($r) { return strpos($r['email'], '@test.com') !== false; });
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($cadets); ?></div>
                <div class="stat-label">Cadets</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($officers); ?></div>
                <div class="stat-label">Officers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($testUsers); ?></div>
                <div class="stat-label">Test Accounts</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Student ID</th>
                    <th>Course/Section</th>
                    <th>Platoon</th>
                    <th>Contact</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrations as $reg): ?>
                <tr>
                    <td><?php echo htmlspecialchars($reg['id']); ?></td>
                    <td><?php echo htmlspecialchars($reg['username']); ?></td>
                    <td>
                        <?php 
                        $fullName = trim($reg['first_name'] . ' ' . $reg['middle_name'] . ' ' . $reg['last_name']);
                        echo htmlspecialchars($fullName ?: 'N/A');
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($reg['email']); ?></td>
                    <td>
                        <span class="role-badge role-<?php echo $reg['role']; ?>">
                            <?php echo ucfirst($reg['role']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($reg['student_id'] ?: 'N/A'); ?></td>
                    <td>
                        <?php 
                        $courseSection = '';
                        if ($reg['course']) {
                            $courseSection = $reg['course'];
                            if ($reg['section']) {
                                $courseSection .= '-' . $reg['section'];
                            }
                        }
                        echo htmlspecialchars($courseSection ?: 'N/A');
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($reg['platoon'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($reg['contact_number'] ?: 'N/A'); ?></td>
                    <td><?php echo date('M j, Y H:i', strtotime($reg['user_created'])); ?></td>
                    <td>
                        <div class="actions">
                            <?php if (strpos($reg['email'], '@test.com') !== false): ?>
                                <button onclick="deleteUser(<?php echo $reg['id']; ?>)" class="btn btn-danger" title="Delete Test User">
                                    Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($registrations)): ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 40px; color: #666;">
                        No registrations found. Run some tests first!
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (count($testUsers) > 0): ?>
        <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 5px;">
            <h3>Cleanup Options</h3>
            <p>Found <?php echo count($testUsers); ?> test accounts. You can clean them up:</p>
            <button onclick="deleteAllTestUsers()" class="btn btn-danger">
                Delete All Test Users
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        async function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user?')) {
                return;
            }
            
            try {
                const response = await fetch('delete_test_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('User deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting user: ' + error.message);
            }
        }
        
        async function deleteAllTestUsers() {
            if (!confirm('Are you sure you want to delete ALL test users? This cannot be undone!')) {
                return;
            }
            
            try {
                const response = await fetch('delete_test_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ delete_all_test: true })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Deleted ${result.deleted_count} test users successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting users: ' + error.message);
            }
        }
    </script>
</body>
</html>