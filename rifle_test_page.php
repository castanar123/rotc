<?php
session_start();
require_once 'includes/db_connection.php';

// Simple access control
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rifle_id = trim($_POST['rifle_id'] ?? '');
    $cadet_id = trim($_POST['cadet_id'] ?? '');
    
    try {
        if ($action === 'assign' && $rifle_id && $cadet_id) {
            // Check if rifle exists
            $stmt = $pdo->prepare("SELECT * FROM rifles WHERE id = ? OR rifle_number = ?");
            $stmt->execute([$rifle_id, $rifle_id]);
            $rifle = $stmt->fetch();
            
            if (!$rifle) {
                throw new Exception('Rifle not found');
            }
            
            // Check if rifle is already assigned
            $stmt = $pdo->prepare("SELECT * FROM rifle_assignments WHERE rifle_id = ? AND returned_at IS NULL");
            $stmt->execute([$rifle['id']]);
            if ($stmt->fetch()) {
                throw new Exception('Rifle is already assigned');
            }
            
            // Create assignment
            $stmt = $pdo->prepare("
                INSERT INTO rifle_assignments (rifle_id, assigned_to, assigned_by, assigned_at, status) 
                VALUES (?, ?, ?, NOW(), 'assigned')
            ");
            $stmt->execute([$rifle['id'], $cadet_id, $_SESSION['user_id']]);
            
            $message = "Rifle #{$rifle['rifle_number']} assigned successfully";
            $message_type = 'success';
            
        } elseif ($action === 'return' && $rifle_id) {
            // Check if rifle exists and is assigned
            $stmt = $pdo->prepare("
                SELECT r.*, ra.id as assignment_id, ra.assigned_to 
                FROM rifles r 
                LEFT JOIN rifle_assignments ra ON r.id = ra.rifle_id AND ra.returned_at IS NULL 
                WHERE r.id = ? OR r.rifle_number = ?
            ");
            $stmt->execute([$rifle_id, $rifle_id]);
            $rifle = $stmt->fetch();
            
            if (!$rifle) {
                throw new Exception('Rifle not found');
            }
            
            if (!$rifle['assignment_id']) {
                throw new Exception('Rifle is not currently assigned');
            }
            
            // Return rifle
            $stmt = $pdo->prepare("
                UPDATE rifle_assignments 
                SET returned_at = NOW(), returned_by = ?, status = 'returned' 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $rifle['assignment_id']]);
            
            $message = "Rifle #{$rifle['rifle_number']} returned successfully";
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current assignments
$stmt = $pdo->prepare("
    SELECT 
        r.id as rifle_id,
        r.rifle_number,
        ra.assigned_to,
        ra.assigned_at,
        u.username as assigned_to_name
    FROM rifle_assignments ra
    JOIN rifles r ON ra.rifle_id = r.id
    LEFT JOIN users u ON ra.assigned_to = u.id
    WHERE ra.returned_at IS NULL
    ORDER BY ra.assigned_at DESC
");
$stmt->execute();
$current_assignments = $stmt->fetchAll();

// Get all rifles for reference
$stmt = $pdo->prepare("SELECT * FROM rifles ORDER BY rifle_number");
$stmt->execute();
$all_rifles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="cache-control" content="max-age=0">
    <meta name="expires" content="0">
    <meta name="pragma" content="no-cache">
    <title>Rifle Test Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .nav-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-links a {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .nav-links a:hover {
            background-color: #0056b3;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid transparent;
        }
        .status-assigned {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-color: #ffc107;
        }
        .status-assigned::before {
            content: "👤";
        }
        .status-available {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #28a745;
        }
        .status-available::before {
            content: "✓";
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Rifle Test Page</h1>
                <p>Manual rifle assignment and return operations</p>
            </div>
            
            <div class="nav-links">
                <a href="simple_rifle_scanner.php">QR Scanner</a>
                <a href="rifle_qr_test_generator.php">QR Generator</a>
                <a href="rifle_test_page.php">Test Page (Current)</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-grid">
                <!-- Assign Rifle Form -->
                <div>
                    <h3>Assign Rifle</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="assign">
                        
                        <div class="form-group">
                            <label for="assign_rifle_id">Rifle ID/Number:</label>
                            <input type="text" id="assign_rifle_id" name="rifle_id" required 
                                   placeholder="Enter rifle ID or number">
                        </div>
                        
                        <div class="form-group">
                            <label for="cadet_id">Cadet ID:</label>
                            <input type="text" id="cadet_id" name="cadet_id" required 
                                   placeholder="Enter cadet ID">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Assign Rifle</button>
                    </form>
                </div>
                
                <!-- Return Rifle Form -->
                <div>
                    <h3>Return Rifle</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="return">
                        
                        <div class="form-group">
                            <label for="return_rifle_id">Rifle ID/Number:</label>
                            <input type="text" id="return_rifle_id" name="rifle_id" required 
                                   placeholder="Enter rifle ID or number">
                        </div>
                        
                        <button type="submit" class="btn btn-warning">Return Rifle</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Current Assignments -->
        <div class="card">
            <h3>Current Rifle Assignments</h3>
            <?php if (empty($current_assignments)): ?>
                <p>No rifles are currently assigned.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rifle Number</th>
                            <th>Assigned To</th>
                            <th>Assigned At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_assignments as $assignment): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($assignment['rifle_number']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['assigned_to_name'] ?: 'User ID: ' . $assignment['assigned_to']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($assignment['assigned_at'])); ?></td>
                                <td><span class="status-badge status-assigned">Assigned</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- All Rifles Reference -->
        <div class="card">
            <h3>All Rifles Reference</h3>
            <?php if (empty($all_rifles)): ?>
                <p>No rifles found in database. Please add some rifles first.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rifle Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $assigned_rifle_ids = array_column($current_assignments, 'rifle_id');
                        foreach ($all_rifles as $rifle): 
                            $is_assigned = in_array($rifle['id'], $assigned_rifle_ids);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rifle['id']); ?></td>
                                <td>#<?php echo htmlspecialchars($rifle['rifle_number']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $is_assigned ? 'status-assigned' : 'status-available'; ?>">
                                        <?php echo $is_assigned ? 'Assigned' : 'Available'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>