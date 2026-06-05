<?php
// Debug version of admin dashboard to force refresh and show actual values
session_start();
require_once 'includes/db.php';
require_once 'includes/session.php';

// Force no caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit();
}

try {
    // Basic cadets count
    $basic_cadets_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' AND u.status = 'active'
    ");
    $basic_cadets_stmt->execute();
    $basic_cadets = $basic_cadets_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // 2CL cadets count
    $cl2_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = '2cl' AND status = 'active'");
    $cl2_stmt->execute();
    $cl2_count = $cl2_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Officers count
    $officers_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role IN ('1cl', 'officer') AND status = 'active'");
    $officers_stmt->execute();
    $officers_count = $officers_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Command staff count
    $command_staff_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
    $command_staff_stmt->execute();
    $command_staff = $command_staff_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending registrations
    $pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
    $pending_stmt->execute();
    $pending_registrations = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    error_log("Admin dashboard query error: " . $e->getMessage());
    $basic_cadets = $cl2_count = $officers_count = $command_staff = $pending_registrations = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Debug Version</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .debug-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .stat-card { background: white; padding: 20px; margin: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: inline-block; min-width: 200px; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-title { color: #7f8c8d; font-size: 0.9em; text-transform: uppercase; }
        .container { max-width: 1200px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard - Debug Version</h1>
        
        <div class="debug-info">
            <h3>Debug Information</h3>
            <p><strong>Generated at:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>Session User:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></p>
            <p><strong>Session Role:</strong> <?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?></p>
            <p><strong>Database Connection:</strong> <?php echo $pdo ? 'Connected' : 'Failed'; ?></p>
        </div>
        
        <h2>Statistics</h2>
        
        <div class="stat-card">
            <div class="stat-title">Basic Cadets</div>
            <div class="stat-value"><?php echo $basic_cadets; ?></div>
            <small>Active basic cadets in system</small>
        </div>
        
        <div class="stat-card">
            <div class="stat-title">2CL Cadets</div>
            <div class="stat-value"><?php echo $cl2_count; ?></div>
            <small>Second class cadets</small>
        </div>
        
        <div class="stat-card">
            <div class="stat-title">Officers</div>
            <div class="stat-value"><?php echo $officers_count; ?></div>
            <small>1CL and officers</small>
        </div>
        
        <div class="stat-card">
            <div class="stat-title">Command Staff</div>
            <div class="stat-value"><?php echo $command_staff; ?></div>
            <small>Admin users</small>
        </div>
        
        <div class="stat-card">
            <div class="stat-title">Pending Registrations</div>
            <div class="stat-value"><?php echo $pending_registrations; ?></div>
            <small>Awaiting approval</small>
        </div>
        
        <div class="debug-info">
            <h3>Raw Values Debug</h3>
            <p><strong>$basic_cadets:</strong> <?php var_dump($basic_cadets); ?></p>
            <p><strong>$cl2_count:</strong> <?php var_dump($cl2_count); ?></p>
            <p><strong>$officers_count:</strong> <?php var_dump($officers_count); ?></p>
            <p><strong>$command_staff:</strong> <?php var_dump($command_staff); ?></p>
            <p><strong>$pending_registrations:</strong> <?php var_dump($pending_registrations); ?></p>
        </div>
        
        <p><a href="admin_dashboard.php">← Back to Regular Admin Dashboard</a></p>
    </div>
</body>
</html>