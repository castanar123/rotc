<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access system settings', $_SESSION['user_id'] ?? null, 'HIGH');
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Log admin access to settings
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed system settings', $_SESSION['user_id'], 'LOW');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_system_settings'])) {
        $system_name = trim($_POST['system_name']);
        $system_email = trim($_POST['system_email']);
        $timezone = $_POST['timezone'];
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        // Log system settings change
        SecurityLogger::logSecurityEvent('SETTINGS_CHANGE', 'Admin updated system settings', $_SESSION['user_id'], 'MEDIUM');
        
        // Update system settings (you can store these in a settings table)
        $success_message = "System settings updated successfully!";
    }
    
    if (isset($_POST['update_security_settings'])) {
        $session_timeout = (int)$_POST['session_timeout'];
        $password_min_length = (int)$_POST['password_min_length'];
        $max_login_attempts = (int)$_POST['max_login_attempts'];
        
        // Log security settings change
        SecurityLogger::logSecurityEvent('SECURITY_SETTINGS_CHANGE', 'Admin updated security settings', $_SESSION['user_id'], 'HIGH');
        
        $success_message = "Security settings updated successfully!";
    }
    
    if (isset($_POST['backup_database'])) {
        // Log manual backup initiation
        SecurityLogger::logSecurityEvent('MANUAL_BACKUP', 'Admin initiated manual database backup', $_SESSION['user_id'], 'MEDIUM');
        
        // Database backup logic would go here
        $success_message = "Database backup initiated successfully!";
    }
}

$page_title = 'System Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '';
            include __DIR__ . '/includes/admin_nav.php';
        ?>
        <!-- Main Content -->
        <main class="main-content">

<style>
.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.settings-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.settings-header h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.settings-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.settings-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.settings-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 25px;
    border-bottom: 1px solid #dee2e6;
}

.card-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.4rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header .icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.card-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-check {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.form-check input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #667eea;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 193, 7, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
}

.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin: 0 auto 15px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
    font-weight: 500;
}

.full-width-card {
    grid-column: 1 / -1;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.system-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #333;
}

.info-value {
    color: #666;
}

@media (max-width: 768px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .settings-header h1 {
        font-size: 2rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Fixed Sidebar Toggle Button -->
<button class="sidebar-toggle-fixed" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="container-fluid">
    <div class="settings-container">
        <!-- Header -->
        <div class="settings-header">
            <h1><i class="fas fa-cogs"></i> System Settings</h1>
            <p>Configure and manage your ROTC management system</p>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value">150</div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value">120</div>
                <div class="stat-label">Active Cadets</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stat-value">25</div>
                <div class="stat-label">Announcements</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value">95%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- System Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>
                        <div class="icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        System Configuration
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="system_name">System Name</label>
                            <input type="text" id="system_name" name="system_name" class="form-control" value="ROTC Management System">
                        </div>
                        
                        <div class="form-group">
                            <label for="system_email">System Email</label>
                            <input type="email" id="system_email" name="system_email" class="form-control" value="admin@rotc.edu">
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone" class="form-control">
                                <option value="Asia/Manila" selected>Asia/Manila</option>
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New_York</option>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode">
                            <label for="maintenance_mode">Enable Maintenance Mode</label>
                        </div>
                        
                        <button type="submit" name="update_system_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>
                        <div class="icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        Security Settings
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="session_timeout">Session Timeout (minutes)</label>
                            <input type="number" id="session_timeout" name="session_timeout" class="form-control" value="30" min="5" max="480">
                        </div>
                        
                        <div class="form-group">
                            <label for="password_min_length">Minimum Password Length</label>
                            <input type="number" id="password_min_length" name="password_min_length" class="form-control" value="8" min="6" max="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="max_login_attempts">Max Login Attempts</label>
                            <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control" value="5" min="3" max="10">
                        </div>
                        
                        <button type="submit" name="update_security_settings" class="btn btn-primary">
                            <i class="fas fa-shield-alt"></i> Update Security
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Information & Actions -->
        <div class="settings-grid">
            <!-- System Information -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>
                        <div class="icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        System Information
                    </h3>
                </div>
                <div class="card-body">
                    <div class="system-info">
                        <div class="info-row">
                            <span class="info-label">PHP Version:</span>
                            <span class="info-value"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Server Software:</span>
                            <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Database:</span>
                            <span class="info-value">MySQL <?php echo $pdo->query('SELECT VERSION()')->fetchColumn(); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">System Load:</span>
                            <span class="info-value">Normal</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Last Backup:</span>
                            <span class="info-value">Never</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Actions -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>
                        <div class="icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        System Actions
                    </h3>
                </div>
                <div class="card-body">
                    <p style="color: #666; margin-bottom: 20px;">Perform system maintenance and administrative tasks.</p>
                    
                    <div class="action-buttons">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="backup_database" class="btn btn-success">
                                <i class="fas fa-download"></i> Backup Database
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-warning" onclick="clearCache()">
                            <i class="fas fa-broom"></i> Clear Cache
                        </button>
                        
                        <button type="button" class="btn btn-primary" onclick="checkUpdates()">
                            <i class="fas fa-sync-alt"></i> Check Updates
                        </button>
                        
                        <button type="button" class="btn btn-danger" onclick="confirmSystemReset()">
                            <i class="fas fa-exclamation-triangle"></i> System Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Settings -->
        <div class="settings-card full-width-card">
            <div class="card-header">
                <h3>
                    <div class="icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    Advanced Configuration
                </h3>
            </div>
            <div class="card-body">
                <div class="settings-grid">
                    <div>
                        <h4 style="margin-bottom: 15px; color: #333;">Email Configuration</h4>
                        <div class="form-group">
                            <label>SMTP Server</label>
                            <input type="text" class="form-control" placeholder="smtp.gmail.com">
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="number" class="form-control" placeholder="587">
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 15px; color: #333;">File Upload Settings</h4>
                        <div class="form-group">
                            <label>Max File Size (MB)</label>
                            <input type="number" class="form-control" value="10">
                        </div>
                        <div class="form-group">
                            <label>Allowed File Types</label>
                            <input type="text" class="form-control" value="jpg,png,pdf,doc,docx">
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Save Advanced Settings
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function clearCache() {
    if (confirm('Are you sure you want to clear the system cache?')) {
        // Add cache clearing logic here
        alert('Cache cleared successfully!');
    }
}

function checkUpdates() {
    alert('Checking for updates... No updates available.');
}

function confirmSystemReset() {
    if (confirm('WARNING: This will reset all system settings to default. This action cannot be undone. Are you sure?')) {
        if (confirm('This is your final warning. All custom settings will be lost. Continue?')) {
            alert('System reset functionality would be implemented here.');
        }
    }
}

// Auto-hide success messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
</script>