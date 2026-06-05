<?php
/**
 * Borrowing Management Interface
 * Shows all active rifle borrowings and allows management
 */

require_once 'includes/db.php';
require_once 'includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Get user info from session
$user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role']
];

// Handle AJAX requests
if (isset($_GET['action']) || (isset($_POST['action']) && $_POST['action'] !== 'return_rifle')) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'];
    
    try {
        switch ($action) {
            case 'get_stats':
                error_log("[DEBUG] get_stats action called");
                // Get borrowing statistics
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM rifle_borrowings");
                $total = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) as active FROM rifle_borrowings WHERE status = 'active'");
                $active = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) as returned_today FROM rifle_borrowings WHERE status = 'returned' AND DATE(returned_at) = CURDATE()");
                $returned_today = $stmt->fetchColumn();
                
                $stats = [
                    'total' => $total,
                    'active' => $active,
                    'returned_today' => $returned_today
                ];
                error_log("[DEBUG] get_stats: " . json_encode($stats));
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
                exit;
                
            case 'get_active':
                error_log("[DEBUG] get_active action called");
                // Get active borrowings
                $stmt = $pdo->query("
                    SELECT 
                        rb.*,
                        GROUP_CONCAT(r.rifle_number ORDER BY r.rifle_number SEPARATOR ', ') as rifle_numbers
                    FROM rifle_borrowings rb
                    LEFT JOIN rifles r ON FIND_IN_SET(r.id, REPLACE(REPLACE(rb.rifle_ids, '[', ''), ']', ''))
                    WHERE rb.status = 'active'
                    GROUP BY rb.id
                    ORDER BY rb.borrowed_at DESC
                ");
                $borrowings = $stmt->fetchAll();
                error_log("[DEBUG] get_active: Found " . count($borrowings) . " active borrowings");
                
                echo json_encode([
                    'success' => true,
                    'borrowings' => $borrowings
                ]);
                exit;
                
            case 'get_history':
                error_log("[DEBUG] get_history action called");
                // Get return history
                $stmt = $pdo->query("
                    SELECT 
                        rb.*,
                        GROUP_CONCAT(r.rifle_number ORDER BY r.rifle_number SEPARATOR ', ') as rifle_numbers
                    FROM rifle_borrowings rb
                    LEFT JOIN rifles r ON FIND_IN_SET(r.id, REPLACE(REPLACE(rb.rifle_ids, '[', ''), ']', ''))
                    WHERE rb.status = 'returned'
                    GROUP BY rb.id
                    ORDER BY rb.returned_at DESC
                    LIMIT 20
                ");
                $history = $stmt->fetchAll();
                error_log("[DEBUG] get_history: Found " . count($history) . " history records");
                
                echo json_encode([
                    'success' => true,
                    'history' => $history
                ]);
                exit;
                
            case 'get_all_history':
                error_log("[DEBUG] get_all_history action called");
                $stmt = $pdo->prepare("
                    SELECT 
                        rb.*,
                        GROUP_CONCAT(r.rifle_number ORDER BY r.rifle_number SEPARATOR ', ') as rifle_numbers
                    FROM rifle_borrowings rb
                    LEFT JOIN rifles r ON FIND_IN_SET(r.id, REPLACE(REPLACE(rb.rifle_ids, '[', ''), ']', ''))
                    GROUP BY rb.id
                    ORDER BY rb.borrowed_at DESC
                ");
                $all_history = $stmt->fetchAll();
                error_log("[DEBUG] get_all_history: Found " . count($all_history) . " total history records");
                
                echo json_encode([
                    'success' => true,
                    'history' => $all_history
                ]);
                exit;
                
            case 'return_rifles':
                error_log("[DEBUG] return_rifles action called");
                if (!isset($_POST['borrowing_id'])) {
                    error_log("[DEBUG] return_rifles: Missing borrowing_id");
                    throw new Exception('Borrowing ID is required');
                }
                
                $pdo->beginTransaction();
                
                $borrowing_id = $_POST['borrowing_id'];
                error_log("[DEBUG] return_rifles: Processing borrowing_id = $borrowing_id");
                
                // Get borrowing details
                $stmt = $pdo->prepare("SELECT * FROM rifle_borrowings WHERE id = ? AND status = 'active'");
                $stmt->execute([$borrowing_id]);
                $borrowing = $stmt->fetch();
                
                if (!$borrowing) {
                    error_log("[DEBUG] return_rifles: Borrowing not found or already returned for ID $borrowing_id");
                    throw new Exception('Borrowing not found or already returned');
                }
                error_log("[DEBUG] return_rifles: Found borrowing for {$borrowing['borrower_name']}");
                
                // Update borrowing status
                $stmt = $pdo->prepare("UPDATE rifle_borrowings SET status = 'returned', returned_at = NOW() WHERE id = ?");
                $stmt->execute([$borrowing_id]);
                
                // Update rifle statuses back to available
                $rifle_ids_str = trim($borrowing['rifle_ids'], '[]');
                if (!empty($rifle_ids_str)) {
                    $rifle_ids = array_map('trim', explode(',', $rifle_ids_str));
                    $rifle_ids = array_filter($rifle_ids, 'is_numeric');
                    if (!empty($rifle_ids)) {
                        $placeholders = str_repeat('?,', count($rifle_ids) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE rifles SET status = 'available' WHERE id IN ($placeholders)");
                        $stmt->execute($rifle_ids);
                    }
                }
                
                // Log the return
                $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, action, details, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $user['id'],
                    'rifle_return',
                    "Returned rifles for borrowing ID: $borrowing_id, Borrower: {$borrowing['borrower_name']}",
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $pdo->commit();
                error_log("[DEBUG] return_rifles: Successfully returned rifles for {$borrowing['borrower_name']}");
                
                echo json_encode([
                    'success' => true,
                    'message' => "Rifles returned successfully for {$borrowing['borrower_name']}"
                ]);
                exit;
                
            default:
                error_log("[DEBUG] Invalid action requested: " . ($_GET['action'] ?? 'none'));
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        error_log("[DEBUG] Exception in borrowing_management: " . $e->getMessage());
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle return rifle action
if (isset($_POST['action']) && $_POST['action'] === 'return_rifle' && isset($_POST['borrowing_id'])) {
    try {
        $pdo->beginTransaction();
        
        $borrowing_id = $_POST['borrowing_id'];
        
        // Get borrowing details
        $stmt = $pdo->prepare("SELECT * FROM rifle_borrowings WHERE id = ? AND status = 'active'");
        $stmt->execute([$borrowing_id]);
        $borrowing = $stmt->fetch();
        
        if ($borrowing) {
            // Update borrowing status
            $stmt = $pdo->prepare("UPDATE rifle_borrowings SET status = 'returned', return_date = NOW() WHERE id = ?");
            $stmt->execute([$borrowing_id]);
            
            // Update rifle statuses back to available
            $rifle_ids_str = trim($borrowing['rifle_ids'], '[]');
            if (!empty($rifle_ids_str)) {
                $rifle_ids = array_map('trim', explode(',', $rifle_ids_str));
                $rifle_ids = array_filter($rifle_ids, 'is_numeric');
                if (!empty($rifle_ids)) {
                    $placeholders = str_repeat('?,', count($rifle_ids) - 1) . '?';
                    $stmt = $pdo->prepare("UPDATE rifles SET status = 'available' WHERE id IN ($placeholders)");
                    $stmt->execute($rifle_ids);
                }
            }
            
            // Log the return
            $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, action, details, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $user['id'],
                'rifle_return',
                "Returned rifles for borrowing ID: $borrowing_id, Borrower: {$borrowing['borrower_name']}",
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $pdo->commit();
            $success_message = "Rifles returned successfully for {$borrowing['borrower_name']}";
        } else {
            $error_message = "Borrowing not found or already returned";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error returning rifles: " . $e->getMessage();
    }
}

// Get all borrowings with rifle details
$stmt = $pdo->query("
    SELECT 
        rb.*,
        GROUP_CONCAT(r.rifle_number ORDER BY r.rifle_number SEPARATOR ', ') as rifle_numbers,
        (LENGTH(rb.rifle_ids) - LENGTH(REPLACE(rb.rifle_ids, ',', '')) + 1) as rifle_count
    FROM rifle_borrowings rb
    LEFT JOIN rifles r ON FIND_IN_SET(r.id, REPLACE(REPLACE(rb.rifle_ids, '[', ''), ']', ''))
    GROUP BY rb.id
    ORDER BY rb.borrowed_at DESC
");
$borrowings = $stmt->fetchAll();

// Separate active and returned borrowings
$active_borrowings = array_filter($borrowings, function($b) { return $b['status'] === 'active'; });
$returned_borrowings = array_filter($borrowings, function($b) { return $b['status'] === 'returned'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Management - ROTC Rifle Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-clipboard-list mr-3 text-blue-600"></i>
                        Borrowing Management
                    </h1>
                    <p class="text-gray-600 mt-2">Manage rifle borrowings and returns</p>
                </div>
                <div class="flex space-x-4">
                    <a href="rifle_management.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-gun mr-2"></i>Rifle Management
                    </a>
                    <a href="scanner.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-qrcode mr-2"></i>QR Scanner
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-hand-holding text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Active Borrowings</h3>
                        <p class="text-2xl font-bold text-blue-600"><?php echo count($active_borrowings); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-undo text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Returned Today</h3>
                        <p class="text-2xl font-bold text-green-600">
                            <?php 
                            $today_returns = array_filter($returned_borrowings, function($b) {
                                return date('Y-m-d', strtotime($b['return_date'])) === date('Y-m-d');
                            });
                            echo count($today_returns);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Total Borrowings</h3>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo count($borrowings); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Borrowings -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-hand-holding mr-2 text-blue-600"></i>
                    Active Borrowings (<?php echo count($active_borrowings); ?>)
                </h2>
            </div>
            
            <?php if (empty($active_borrowings)): ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>No active borrowings at the moment</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rifles</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrow Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QR Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($active_borrowings as $borrowing): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="bg-blue-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($borrowing['borrower_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($borrowing['rifle_numbers'] ?: 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo $borrowing['rifle_count']; ?> rifles
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, Y g:i A', strtotime($borrowing['borrowed_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <code class="bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($borrowing['qr_code_id']); ?></code>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to return these rifles?')">
                                            <input type="hidden" name="action" value="return_rifle">
                                            <input type="hidden" name="borrowing_id" value="<?php echo $borrowing['id']; ?>">
                                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition">
                                                <i class="fas fa-undo mr-1"></i>Return
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Returns -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-history mr-2 text-green-600"></i>
                    Recent Returns (Last 10)
                </h2>
            </div>
            
            <?php 
            $recent_returns = array_slice($returned_borrowings, 0, 10);
            if (empty($recent_returns)): 
            ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>No returned borrowings yet</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rifles</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Returned</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_returns as $borrowing): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="bg-green-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-user text-green-600"></i>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($borrowing['borrower_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($borrowing['rifle_numbers'] ?: 'N/A'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $borrowing['rifle_count']; ?> rifles
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, Y g:i A', strtotime($borrowing['borrowed_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, Y g:i A', strtotime($borrowing['return_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        $borrow_time = strtotime($borrowing['borrowed_at']);
                                        $return_time = strtotime($borrowing['return_date']);
                                        $duration = $return_time - $borrow_time;
                                        
                                        $hours = floor($duration / 3600);
                                        $minutes = floor(($duration % 3600) / 60);
                                        
                                        if ($hours > 0) {
                                            echo "{$hours}h {$minutes}m";
                                        } else {
                                            echo "{$minutes}m";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Redirect to rifle management page since borrowing is now integrated there
        if (!window.location.search.includes('action=')) {
            window.location.href = 'rifle_management.php';
        }
        
        // Auto-refresh page every 30 seconds to show real-time updates
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>