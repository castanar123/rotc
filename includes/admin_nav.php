<?php
// Centralized Admin Sidebar Navigation
// Usage: include 'includes/admin_nav.php';
// Optional: set $NAV_BASE (e.g., '../') before including to adjust paths when used from subdirectories.

if (!isset($NAV_BASE)) {
    $NAV_BASE = '';
}

// Helper to build href with optional base
function nav_href($path) {
    global $NAV_BASE;
    // If path already absolute (http/https), return as is
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    // Normalize base + path
    $base = rtrim($NAV_BASE, '/');
    $path = ltrim($path, '/');
    return ($base !== '' ? $base . '/' : '') . $path;
}

// Determine current script for active state
$currentScript = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Safe counters for badges; compute if not provided by parent
$__needDb = !isset($pdo);
try {
    if ($__needDb) {
        require_once __DIR__ . '/db.php';
    }
} catch (Throwable $e) {
    // silently ignore; badges will be zero
}

function safe_count_query($sql) {
    global $pdo;
    try {
        if (!isset($pdo)) return 0;
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch();
        return isset($row['total']) ? (int)$row['total'] : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

if (!isset($active_missing_ids)) {
    $active_missing_ids = safe_count_query("SELECT COUNT(*) AS total FROM missing_id_requests WHERE status = 'active' AND expiry_date > NOW()");
}
// Compute a robust pending registrations count for badge even if parent provided an array
if (isset($pending_registrations)) {
    if (is_array($pending_registrations)) {
        $pending_registrations_count = count($pending_registrations);
    } else {
        $pending_registrations_count = (int)$pending_registrations;
    }
} else {
    $pending_registrations_count = safe_count_query("SELECT COUNT(*) AS total FROM users WHERE approval_status = 'pending'");
}
if (!isset($advance_rotc_count)) {
    $advance_rotc_count = safe_count_query("SELECT COUNT(*) AS total FROM advance_rotc_signups");
}

function is_active_link($targets) {
    // $targets can be string or array of basenames to match
    global $currentScript;
    $targets = (array)$targets;
    foreach ($targets as $t) {
        if ($currentScript === basename($t)) return true;
    }
    return false;
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
            <span class="logo-text">Admin Command</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin_dashboard.php')) ?>" class="nav-link <?= is_active_link('admin_dashboard.php') ? 'active' : '' ?>" data-tooltip="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('document_generation.php')) ?>" class="nav-link <?= is_active_link('document_generation.php') ? 'active' : '' ?>" data-tooltip="Document Generation">
                    <i class="fas fa-file-alt"></i>
                    <span>Document Generation</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('QR/home.php')) ?>" class="nav-link <?= is_active_link('home.php') ? 'active' : '' ?>" data-tooltip="QR Attendance">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('QR/dashboard.php')) ?>" class="nav-link <?= is_active_link('dashboard.php') ? 'active' : '' ?>" data-tooltip="Attendance Dashboard">
                    <i class="fas fa-chart-bar"></i>
                    <span>Attendance Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('rifle_management.php')) ?>" class="nav-link <?= is_active_link('rifle_management.php') ? 'active' : '' ?>" data-tooltip="Rifle Management">
                    <i class="fas fa-crosshairs"></i>
                    <span>Rifle Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('rifle_scanner.php')) ?>" class="nav-link <?= is_active_link('rifle_scanner.php') ? 'active' : '' ?>" data-tooltip="QR Scanner">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Scanner</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('user_management.php')) ?>" class="nav-link <?= is_active_link('user_management.php') ? 'active' : '' ?>" data-tooltip="User Management">
                    <i class="fas fa-users-cog"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('account_management.php')) ?>" class="nav-link <?= is_active_link('account_management.php') ? 'active' : '' ?>" data-tooltip="Account Management">
                    <i class="fas fa-user-shield"></i>
                    <span>Account Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin/missing_ids.php')) ?>" class="nav-link <?= is_active_link('missing_ids.php') ? 'active' : '' ?>" data-tooltip="Missing IDs">
                    <i class="fas fa-id-card-alt"></i>
                    <span>Missing IDs</span>
                    <?php if ($active_missing_ids > 0): ?>
                        <span class="badge badge-danger"><?= (int)$active_missing_ids ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin/id_card.php')) ?>" class="nav-link <?= is_active_link('id_card.php') ? 'active' : '' ?>" data-tooltip="ID Card Generator">
                    <i class="fas fa-id-badge"></i>
                    <span>ID Card Generator</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin_second_sem_enrollment.php')) ?>" class="nav-link <?= is_active_link('admin_second_sem_enrollment.php') ? 'active' : '' ?>" data-tooltip="2nd Sem Enrollment">
                    <i class="fas fa-user-check"></i>
                    <span>2nd Sem Enrollment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin_dashboard.php#registration-approvals')) ?>" class="nav-link <?= is_active_link('admin_dashboard.php') ? 'active' : '' ?>" data-tooltip="Registration Approvals">
                    <i class="fas fa-user-check"></i>
                    <span>Registration Approvals</span>
                    <?php if ($pending_registrations_count > 0): ?>
                        <span class="badge"><?= (int)$pending_registrations_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('advance_rotc_management.php')) ?>" class="nav-link <?= is_active_link('advance_rotc_management.php') ? 'active' : '' ?>" data-tooltip="Advance Officer Respondents">
                    <i class="fas fa-star-of-life"></i>
                    <span>Advance Officer Respondents</span>
                    <?php if ($advance_rotc_count > 0): ?>
                        <span class="badge"><?= (int)$advance_rotc_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('reports/view_report.php')) ?>" class="nav-link <?= is_active_link('view_report.php') ? 'active' : '' ?>" data-tooltip="Reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('announcements/view.php')) ?>" class="nav-link <?= is_active_link('view.php') ? 'active' : '' ?>" data-tooltip="Announcements">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('grades/manage_grades.php')) ?>" class="nav-link <?= is_active_link('manage_grades.php') ? 'active' : '' ?>" data-tooltip="Grades">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Grades</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin/security_dashboard.php')) ?>" class="nav-link <?= is_active_link('security_dashboard.php') ? 'active' : '' ?>" data-tooltip="Security Dashboard">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('admin/backup_management.php')) ?>" class="nav-link <?= is_active_link('backup_management.php') ? 'active' : '' ?>" data-tooltip="Backup Management">
                    <i class="fas fa-database"></i>
                    <span>Backup Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('QR/setup.php')) ?>" class="nav-link <?= is_active_link('setup.php') ? 'active' : '' ?>" data-tooltip="System Setup">
                    <i class="fas fa-cog"></i>
                    <span>System Setup</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('QR/https_test.php')) ?>" class="nav-link <?= is_active_link('https_test.php') ? 'active' : '' ?>" data-tooltip="HTTPS Setup">
                    <i class="fas fa-lock"></i>
                    <span>HTTPS Setup</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('settings.php')) ?>" class="nav-link <?= is_active_link('settings.php') ? 'active' : '' ?>" data-tooltip="Settings">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(nav_href('logout.php')) ?>" class="nav-link" data-tooltip="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
