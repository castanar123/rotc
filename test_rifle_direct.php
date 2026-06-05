<?php
// Direct test of rifle management AJAX endpoints without session checks

// Temporarily bypass session for testing
$_SESSION['loggedin'] = true;
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'get_rifle_stats';

// Start output buffering
ob_start();

// Include the rifle management file
require_once 'includes/db.php';
require_once 'includes/rifle_functions.php';
require_once 'includes/rifle_qr_functions.php';

// Handle the request directly
header('Content-Type: application/json');

try {
    switch ($_POST['action']) {
        case 'get_rifle_stats':
            if (function_exists('getRifleStatistics')) {
                $stats = getRifleStatistics();
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
            } else {
                // Fallback to direct database query
                $stats_sql = "SELECT 
                    COUNT(*) as total_rifles,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rifles,
                    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_rifles,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rifles
                    FROM rifles";
                $result = $link->query($stats_sql);
                $stats = $result->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
            }
            break;
            
        case 'get_rifle_list':
            $page = 1;
            $limit = 5;
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT id, rifle_number, status, qr_code_path FROM rifles ORDER BY rifle_number LIMIT ? OFFSET ?";
            $stmt = $link->prepare($sql);
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $rifles = $result->fetch_all(MYSQLI_ASSOC);
            
            $count_sql = "SELECT COUNT(*) as total FROM rifles";
            $total_result = $link->query($count_sql);
            $total = $total_result->fetch_assoc()['total'];
            
            echo json_encode([
                'success' => true,
                'rifles' => $rifles,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Get the output
$output = ob_get_clean();

echo "\n=== Testing get_rifle_stats ===\n";
echo "Output: $output\n";

// Test JSON validity
$decoded = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON Valid: YES\n";
    echo "Decoded: " . print_r($decoded, true) . "\n";
} else {
    echo "JSON Valid: NO\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "First 200 chars: " . substr($output, 0, 200) . "\n";
}

echo "\nTest completed.\n";
?>