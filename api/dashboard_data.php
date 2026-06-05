<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/SecurityLogger.php';

// Initialize SecurityLogger
$securityLogger = new SecurityLogger();

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    $securityLogger->logSecurityEvent(null, 'UNAUTHORIZED_ACCESS', 'Unauthorized access attempt to dashboard API', [], 'high');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Log successful dashboard data access
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'DATA_ACCESS', 'User accessed dashboard data API', [], 'low');

try {
    $response = [
        'success' => true,
        'stats' => [
            'total_cadets' => 0,
            'present_today' => 0,
            'attendance_rate' => 0,
            'total_activities' => 0
        ],
        'recent_activity' => [],
        'notifications' => []
    ];

    // Get basic stats based on user role
    if (in_array($_SESSION['role'], ['cadet', 'basic_cadet'])) {
        // For cadets, get their personal stats
        $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cadet_profile = $stmt->fetch();
        
        if ($cadet_profile) {
            // Check which attendance table exists
            $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
            $use_attendance_logs = $table_check->rowCount() > 0;
            
            if ($use_attendance_logs) {
                // Use attendance_logs table
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_days,
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                        ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
                    FROM attendance_logs 
                    WHERE cadet_profile_id = ?
                ");
                $stmt->execute([$cadet_profile['id']]);
                $stats = $stmt->fetch();
                
                $response['stats'] = [
                    'total_days' => $stats['total_days'] ?? 0,
                    'present_days' => $stats['present_days'] ?? 0,
                    'attendance_rate' => $stats['attendance_rate'] ?? 0,
                    'total_activities' => $stats['total_days'] ?? 0
                ];
            } else {
                // Use attendance table
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_days,
                        COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present_days,
                        ROUND((COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
                    FROM attendance 
                    WHERE cadet_id = ?
                ");
                $stmt->execute([$cadet_profile['id']]);
                $stats = $stmt->fetch();
                
                $response['stats'] = [
                    'total_days' => $stats['total_days'] ?? 0,
                    'present_days' => $stats['present_days'] ?? 0,
                    'attendance_rate' => $stats['attendance_rate'] ?? 0,
                    'total_activities' => $stats['total_days'] ?? 0
                ];
            }
        }
        
        // Get recent activities for cadet
        $response['recent_activity'] = [
            [
                'icon' => 'fas fa-check-circle',
                'title' => 'Attendance Updated',
                'time' => date('H:i')
            ],
            [
                'icon' => 'fas fa-user-check',
                'title' => 'Profile Viewed',
                'time' => date('H:i', strtotime('-1 hour'))
            ]
        ];
        
    } else {
        // For officers/admins, get overall stats
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM cadet_profiles");
        $total_cadets = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as present FROM attendance WHERE DATE(created_at) = CURDATE() AND status IN ('Present', 'present')");
        $present_today = $stmt->fetch()['present'] ?? 0;
        
        $attendance_rate = $total_cadets > 0 ? round(($present_today / $total_cadets) * 100, 2) : 0;
        
        $response['stats'] = [
            'total_cadets' => $total_cadets,
            'present_today' => $present_today,
            'attendance_rate' => $attendance_rate,
            'total_activities' => $present_today
        ];
        
        // Get recent activities for officers
        $response['recent_activity'] = [
            [
                'icon' => 'fas fa-users',
                'title' => 'Cadets Checked In',
                'time' => date('H:i')
            ],
            [
                'icon' => 'fas fa-chart-line',
                'title' => 'Reports Generated',
                'time' => date('H:i', strtotime('-30 minutes'))
            ]
        ];
    }
    
    // Add sample notifications
    $response['notifications'] = [
        [
            'title' => 'System Update',
            'message' => 'Dashboard data refreshed',
            'type' => 'info',
            'shown' => false
        ]
    ];
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'Failed to fetch dashboard data',
        'stats' => [
            'total_cadets' => 0,
            'present_today' => 0,
            'attendance_rate' => 0,
            'total_activities' => 0
        ],
        'recent_activity' => [],
        'notifications' => []
    ];
}

echo json_encode($response);
?>