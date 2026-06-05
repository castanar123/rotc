<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    $securityLogger = new SecurityLogger($pdo);
    $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access user management', 'high');
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit();
}

// Log admin access to user management
$securityLogger = new SecurityLogger($pdo);
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_ACCESS', 'Admin accessed user management', 'low');

// Get user statistics
$total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $total_users_stmt->fetch()['total'];

// Since there's no status column, we'll count all users as active
$active_users = $total_users;

$recent_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$recent_users = $recent_users_stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
      <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '';
            include __DIR__ . '/includes/admin_nav.php';
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <div class="header-title">
                        <div class="title-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="title-text">
                            <h1>User Management</h1>
                            <p class="subtitle">Manage system users and permissions</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="userSearch" placeholder="Search users by name, ID, or email..." onkeyup="searchUsers()">
                        </div>
                        <button class="action-btn primary" onclick="window.location.href='register.php'">
                            <i class="fas fa-user-plus"></i>
                            Add User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-header">
                        <div class="metric-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12%</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h2><?php echo $total_users; ?></h2>
                        <p>Total Users</p>
                        <div class="metric-footer">
                            <span class="metric-label">All registered users</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card active">
                    <div class="stat-header">
                        <div class="metric-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8%</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h2><?php echo $active_users; ?></h2>
                        <p>Active Users</p>
                        <div class="metric-footer">
                            <span class="metric-label">Currently active</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card recent">
                    <div class="stat-header">
                        <div class="metric-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+5</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h2><?php echo $recent_users; ?></h2>
                        <p>New This Week</p>
                        <div class="metric-footer">
                            <span class="metric-label">Recent registrations</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card pending">
                    <div class="stat-header">
                        <div class="metric-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="metric-trend neutral">
                            <i class="fas fa-minus"></i>
                            <span>0</span>
                        </div>
                    </div>
                    <div class="metric-content">
                        <h2>0</h2>
                        <p>Pending Approval</p>
                        <div class="metric-footer">
                            <span class="metric-label">Awaiting review</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content">

                <!-- Modern User Directory -->
                <div class="user-directory">
                    <div class="directory-header">
                        <div class="directory-title">
                            <h2><i class="fas fa-address-book"></i> User Directory</h2>
                            <p class="directory-subtitle">Manage all system users and their permissions</p>
                        </div>
                        <div class="directory-filters">
                            <div class="filter-group">
                                <select class="filter-select" id="roleFilter" onchange="filterUsers()">
                                    <option value="">All Roles</option>
                                    <option value="admin">Admin</option>
                                    <option value="commandant">Commandant</option>
                                    <option value="1cl">1CL</option>
                                    <option value="2cl">2CL</option>
                                    <option value="basic_cadet">Basic Cadet</option>
                                </select>
                                <select class="filter-select" id="statusFilter" onchange="filterUsers()">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div class="action-group">
                                <button class="action-btn secondary" onclick="exportUsers()">
                                    <i class="fas fa-download"></i>
                                    Export
                                </button>
                                <button class="action-btn primary" onclick="window.location.href='register.php'">
                                    <i class="fas fa-user-plus"></i>
                                    Add User
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced View Controls -->
                    <div class="view-controls-panel">
                        <div class="view-toggle">
                            <button class="view-btn active" data-view="table">
                                <i class="fas fa-table"></i>
                                <span>Table</span>
                            </button>
                            <button class="view-btn" data-view="grid">
                                <i class="fas fa-th-large"></i>
                                <span>Grid</span>
                            </button>
                        </div>
                        <div class="sort-controls">
                            <select class="sort-select" id="sortBy">
                                <option value="id">Sort by ID</option>
                                <option value="username">Sort by Name</option>
                                <option value="role">Sort by Role</option>
                                <option value="created_at">Sort by Date</option>
                            </select>
                            <button class="sort-order-btn" id="sortOrder" data-order="asc">
                                <i class="fas fa-sort-amount-up"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="data-container" id="tableView">
                        <div class="modern-table-wrapper">
                            <table class="modern-table" id="usersTable">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="id">
                                            <span>ID</span>
                                            <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="username">
                                            <span>Username</span>
                                            <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="email">
                                            <span>Email</span>
                                            <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="role">
                                            <span>Role</span>
                                            <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" data-sort="created_at">
                                            <span>Registration</span>
                                            <i class="fas fa-sort"></i>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT id, username, email, role, created_at FROM users ORDER BY id ASC";
                                    $result = $pdo->query($sql);

                                    if ($result->rowCount() > 0) {
                                        while($row = $result->fetch()) {
                                            echo "<tr class='table-row' data-role='" . strtolower($row['role']) . "'>";
                                            echo "<td class='user-id'>" . htmlspecialchars($row['id']) . "</td>";
                                            echo "<td class='username'>" . htmlspecialchars($row['username']) . "</td>";
                                            echo "<td class='email'>" . htmlspecialchars($row['email']) . "</td>";
                                            echo "<td><span class='modern-badge role-" . strtolower($row['role']) . "'>" . htmlspecialchars(ucfirst($row['role'])) . "</span></td>";
                                            echo "<td class='date'>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
                                            echo "<td class='actions'>";
                                            echo "<div class='action-group'>";
                                            echo "<button class='action-btn-sm edit' onclick=\"editUser(" . $row['id'] . ")\" title='Edit User'>";
                                            echo "<i class='fas fa-edit'></i>";
                                            echo "</button>";
                                            echo "<button class='action-btn-sm view' onclick=\"viewUser(" . $row['id'] . ")\" title='View Details'>";
                                            echo "<i class='fas fa-eye'></i>";
                                            echo "</button>";
                                            if ($row['id'] != $_SESSION['user_id']) {
                                                echo "<button class='action-btn-sm delete' onclick=\"deleteUser(" . $row['id'] . ")\" title='Delete User'>";
                                                echo "<i class='fas fa-trash'></i>";
                                                echo "</button>";
                                            }
                                            echo "</div>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='no-data'>No users found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Grid View (Hidden by default) -->
                    <div class="data-container hidden" id="gridView">
                        <div class="users-grid">
                            <?php
                            $result = $pdo->query($sql);
                            if ($result->rowCount() > 0) {
                                while($row = $result->fetch()) {
                                    echo "<div class='user-card' data-role='" . strtolower($row['role']) . "'>";
                                    echo "<div class='user-avatar'>";
                                    echo "<i class='fas fa-user'></i>";
                                    echo "</div>";
                                    echo "<div class='user-info'>";
                                    echo "<h4>" . htmlspecialchars($row['username']) . "</h4>";
                                    echo "<p>" . htmlspecialchars($row['email']) . "</p>";
                                    echo "<span class='modern-badge role-" . strtolower($row['role']) . "'>" . htmlspecialchars(ucfirst($row['role'])) . "</span>";
                                    echo "</div>";
                                    echo "<div class='user-actions'>";
                                    echo "<button class='action-btn-sm edit' onclick=\"editUser(" . $row['id'] . ")\">";
                                    echo "<i class='fas fa-edit'></i>";
                                    echo "</button>";
                                    if ($row['id'] != $_SESSION['user_id']) {
                                        echo "<button class='action-btn-sm delete' onclick=\"deleteUser(" . $row['id'] . ")\">";
                                        echo "<i class='fas fa-trash'></i>";
                                        echo "</button>";
                                    }
                                    echo "</div>";
                                    echo "</div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
    /* Admin Dashboard Matching Styles */
    .dashboard-header {
        background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-xl);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-primary);
        box-shadow: var(--shadow-primary);
        position: relative;
        overflow: hidden;
    }
    
    .dashboard-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(40, 167, 69, 0.1) 0%, rgba(0, 123, 255, 0.1) 100%);
        z-index: 1;
    }
    
    .header-content {
        position: relative;
        z-index: 2;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--spacing-lg);
    }
    
    .header-title {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }
    
    .title-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--military-green) 0%, #20c997 100%);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        box-shadow: var(--shadow-primary);
    }
    
    .title-text h1 {
        margin: 0;
        font-family: 'Orbitron', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-accent);
        text-transform: uppercase;
        letter-spacing: 2px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }
    
    .subtitle {
        margin: 0.25rem 0 0 0;
        color: var(--text-secondary);
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .header-actions {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }
    
    .search-box {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .search-box i {
        position: absolute;
        left: var(--spacing-md);
        color: var(--text-secondary);
        z-index: 1;
    }
    
    .search-box input {
        padding: var(--spacing-md) var(--spacing-md) var(--spacing-md) 2.5rem;
        background: rgba(15, 20, 25, 0.8);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        color: var(--text-accent);
        font-size: 0.9rem;
        width: 300px;
        backdrop-filter: blur(10px);
        transition: all var(--transition-fast);
    }
    
    .search-box input:focus {
        outline: none;
        background: rgba(15, 20, 25, 0.9);
        border-color: var(--military-green);
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
    }
    
    .search-box input::placeholder {
        color: var(--text-secondary);
    }
    
    .action-btn {
        padding: var(--spacing-md) var(--spacing-lg);
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition-fast);
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        font-size: 0.9rem;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .action-btn.primary {
        background: linear-gradient(135deg, var(--military-green) 0%, #20c997 100%);
        color: white;
        box-shadow: var(--shadow-primary);
    }
    
    .action-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-secondary);
    }
    
    .action-btn.secondary {
        background: rgba(0, 123, 255, 0.1);
        color: var(--text-accent);
        border: 1px solid var(--border-primary);
    }
    
    .action-btn.secondary:hover {
        background: rgba(0, 123, 255, 0.2);
        border-color: #007bff;
        transform: translateY(-2px);
    }
    
    /* Stats Grid - Admin Dashboard Style */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
    }
    
    .stat-card {
        background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        border-radius: var(--radius-lg);
        padding: var(--spacing-xl);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--border-primary);
        transition: all var(--transition-fast);
        backdrop-filter: blur(10px);
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-secondary);
        border-color: var(--military-green);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--military-green), #20c997);
    }
    
    .stat-card.total::before {
        background: linear-gradient(90deg, #007bff, #0056b3);
    }
    
    .stat-card.active::before {
        background: linear-gradient(90deg, var(--military-green), #20c997);
    }
    
    .stat-card.recent::before {
        background: linear-gradient(90deg, #6f42c1, #5a2d91);
    }
    
    .stat-card.pending::before {
        background: linear-gradient(90deg, #fd7e14, #e55a00);
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-md);
    }
    
    .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
    }
    
    .stat-card.total .metric-icon {
        background: linear-gradient(135deg, #007bff, #0056b3);
    }
    
    .stat-card.active .metric-icon {
        background: linear-gradient(135deg, var(--military-green), #20c997);
    }
    
    .stat-card.recent .metric-icon {
        background: linear-gradient(135deg, #6f42c1, #5a2d91);
    }
    
    .stat-card.pending .metric-icon {
        background: linear-gradient(135deg, #fd7e14, #e55a00);
    }
    
    .metric-trend {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        padding: var(--spacing-xs) var(--spacing-sm);
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .metric-trend.up {
        background: rgba(40, 167, 69, 0.2);
        color: var(--military-green);
    }
    
    .metric-trend.neutral {
        background: rgba(107, 114, 128, 0.2);
        color: #6b7280;
    }
    
    .metric-content h2 {
        margin: 0 0 var(--spacing-sm) 0;
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text-accent);
        line-height: 1;
        font-family: 'Orbitron', sans-serif;
    }
    
    .metric-content p {
        margin: 0 0 var(--spacing-md) 0;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.9rem;
    }
    
    .metric-footer {
        padding-top: var(--spacing-md);
        border-top: 1px solid var(--border-primary);
    }
    
    .metric-label {
        color: var(--text-secondary);
        font-size: 0.8rem;
        opacity: 0.7;
    }
    
    /* Modern User Directory */
    .user-directory {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }

    .directory-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: var(--spacing-xl);
        border-bottom: 1px solid var(--border-primary);
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(0, 123, 255, 0.15) 100%);
    }

    .directory-title h2 {
        margin: 0 0 var(--spacing-sm) 0;
        font-family: 'Orbitron', sans-serif;
        font-weight: 700;
        color: var(--text-accent);
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 1.5rem;
    }

    .directory-title i {
        color: var(--military-green);
        margin-right: var(--spacing-sm);
    }

    .directory-subtitle {
        margin: 0;
        color: var(--text-secondary);
        font-size: 0.9rem;
        opacity: 0.8;
    }

    .directory-filters {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
        align-items: flex-end;
    }

    .action-group {
        display: flex;
        gap: var(--spacing-sm);
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-md) var(--spacing-lg);
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        cursor: pointer;
        transition: all var(--transition-fast);
        backdrop-filter: blur(10px);
    }

    .action-btn.primary {
        background: linear-gradient(135deg, var(--military-green) 0%, #20c997 100%);
        color: white;
        box-shadow: var(--shadow-primary);
    }

    .action-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-secondary);
    }

    .action-btn.secondary {
        background: rgba(0, 123, 255, 0.1);
        color: var(--text-accent);
        border: 1px solid var(--border-primary);
    }

    .action-btn.secondary:hover {
        background: rgba(0, 123, 255, 0.2);
        border-color: #007bff;
    }

    .view-controls-panel {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-lg) var(--spacing-xl);
        background: rgba(0, 0, 0, 0.3);
        border-bottom: 1px solid var(--border-primary);
    }

    .filter-group {
        display: flex;
        gap: var(--spacing-sm);
    }

    .filter-select {
        padding: var(--spacing-sm) var(--spacing-md);
        background: rgba(15, 20, 25, 0.9);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        font-size: 0.85rem;
        cursor: pointer;
        transition: all var(--transition-fast);
        min-width: 120px;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--military-green);
        color: var(--text-accent);
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
    }

    .view-toggle {
        display: flex;
        gap: var(--spacing-xs);
        background: rgba(15, 20, 25, 0.5);
        border-radius: var(--radius-sm);
        padding: 4px;
    }

    .view-btn {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        padding: var(--spacing-sm) var(--spacing-md);
        background: transparent;
        border: none;
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all var(--transition-fast);
        font-size: 0.85rem;
    }

    .view-btn.active,
    .view-btn:hover {
        background: var(--military-green);
        color: white;
        transform: translateY(-1px);
    }

    .sort-controls {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }

    .sort-select {
        padding: var(--spacing-sm) var(--spacing-md);
        background: rgba(15, 20, 25, 0.9);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        font-size: 0.85rem;
        cursor: pointer;
        transition: all var(--transition-fast);
    }

    .sort-order-btn {
        padding: var(--spacing-sm);
        background: rgba(15, 20, 25, 0.9);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all var(--transition-fast);
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sort-order-btn:hover {
        background: var(--military-green);
        color: white;
        border-color: var(--military-green);
    }

    .data-container {
        padding: var(--spacing-xl);
    }

    .data-container.hidden {
        display: none;
    }

    .modern-table-wrapper {
        overflow-x: auto;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-primary);
    }

    .modern-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(15, 20, 25, 0.8);
    }

    .modern-table th {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.2) 0%, rgba(0, 123, 255, 0.2) 100%);
        padding: var(--spacing-lg);
        text-align: left;
        font-weight: 700;
        color: var(--text-accent);
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 2px solid var(--border-primary);
        position: relative;
        cursor: pointer;
    }

    .modern-table th.sortable:hover {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.3) 0%, rgba(0, 123, 255, 0.3) 100%);
    }

    .modern-table th i {
        margin-left: var(--spacing-sm);
        opacity: 0.6;
    }

    .modern-table td {
        padding: var(--spacing-lg);
        border-bottom: 1px solid var(--border-primary);
        color: var(--text-secondary);
        transition: all var(--transition-fast);
    }

    .table-row:hover {
        background: rgba(40, 167, 69, 0.05);
    }

    .table-row:hover td {
        color: var(--text-accent);
    }

    .modern-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .role-admin { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
    .role-commandant { background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); color: white; }
    .role-1cl { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
    .role-2cl { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; }
    .role-basic_cadet { background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); color: white; }
    .role-instructor { background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%); color: white; }
    .role-officer { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; }

    .action-group {
        display: flex;
        gap: var(--spacing-xs);
    }

    .action-btn-sm {
        padding: var(--spacing-sm);
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all var(--transition-fast);
        font-size: 0.9rem;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .action-btn-sm.edit {
        background: rgba(0, 123, 255, 0.1);
        color: #007bff;
        border: 1px solid rgba(0, 123, 255, 0.3);
    }

    .action-btn-sm.edit:hover {
        background: #007bff;
        color: white;
        transform: scale(1.1);
    }

    .action-btn-sm.view {
        background: rgba(40, 167, 69, 0.1);
        color: var(--military-green);
        border: 1px solid rgba(40, 167, 69, 0.3);
    }

    .action-btn-sm.view:hover {
        background: var(--military-green);
        color: white;
        transform: scale(1.1);
    }

    .action-btn-sm.delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.3);
    }

    .action-btn-sm.delete:hover {
        background: #dc3545;
        color: white;
        transform: scale(1.1);
    }

    .no-data {
        text-align: center;
        color: var(--text-secondary);
        font-style: italic;
        padding: var(--spacing-xl) !important;
    }

    /* Grid View Styles */
    .users-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: var(--spacing-lg);
    }

    .user-card {
        background: rgba(15, 20, 25, 0.8);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
        transition: all var(--transition-fast);
        backdrop-filter: blur(10px);
    }

    .user-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-secondary);
        border-color: var(--military-green);
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--military-green) 0%, #20c997 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto var(--spacing-md);
        font-size: 1.5rem;
        color: white;
    }

    .user-info {
        text-align: center;
        margin-bottom: var(--spacing-md);
    }

    .user-info h4 {
        margin: 0 0 var(--spacing-sm);
        color: var(--text-accent);
        font-weight: 600;
    }

    .user-info p {
        margin: 0 0 var(--spacing-sm);
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .user-actions {
        display: flex;
        justify-content: center;
        gap: var(--spacing-sm);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .directory-header {
            flex-direction: column;
            gap: var(--spacing-md);
            align-items: stretch;
        }

        .directory-filters {
            align-items: stretch;
        }

        .view-controls-panel {
            flex-direction: column;
            gap: var(--spacing-md);
        }

        .filter-group {
            width: 100%;
        }

        .filter-select {
            flex: 1;
            min-width: auto;
        }

        .view-toggle {
            justify-content: center;
        }

        .sort-controls {
            justify-content: center;
        }

        .action-group {
            width: 100%;
            justify-content: space-between;
        }

        .action-btn {
            flex: 1;
            justify-content: center;
        }

        .modern-table-wrapper {
            font-size: 0.8rem;
        }

        .users-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    // Enhanced search functionality
    document.getElementById('userSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#usersTable tbody tr');
        const gridCards = document.querySelectorAll('.user-card');
        
        // Search in table view
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });

        // Search in grid view
        gridCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(searchTerm) ? 'block' : 'none';
        });
    });

    // View switching functionality
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const viewType = this.dataset.view;
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            
            // Remove active class from all buttons
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            if (viewType === 'table') {
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
            } else {
                tableView.classList.add('hidden');
                gridView.classList.remove('hidden');
            }
        });
    });

    // Filter functionality
    function filterUsers() {
        const roleFilter = document.getElementById('roleFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const tableRows = document.querySelectorAll('#usersTable tbody tr');
        const gridCards = document.querySelectorAll('.user-card');

        // Filter table rows
        tableRows.forEach(row => {
            const roleElement = row.querySelector('.modern-badge');
            let showRow = true;
            
            if (roleFilter && roleElement) {
                const roleClass = Array.from(roleElement.classList).find(cls => cls.startsWith('role-'));
                if (roleClass !== `role-${roleFilter}`) {
                    showRow = false;
                }
            }
            
            row.style.display = showRow ? '' : 'none';
        });

        // Filter grid cards
        gridCards.forEach(card => {
            const roleElement = card.querySelector('.modern-badge');
            let showCard = true;
            
            if (roleFilter && roleElement) {
                const roleClass = Array.from(roleElement.classList).find(cls => cls.startsWith('role-'));
                if (roleClass !== `role-${roleFilter}`) {
                    showCard = false;
                }
            }
            
            card.style.display = showCard ? 'block' : 'none';
        });
    }

    // Table sorting functionality
    document.querySelectorAll('.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const table = document.getElementById('usersTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const columnIndex = Array.from(this.parentNode.children).indexOf(this);
            const isAscending = !this.classList.contains('sort-desc');
            
            // Remove all sort classes
            table.querySelectorAll('th').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
                th.querySelector('i').className = 'fas fa-sort';
            });
            
            // Add appropriate sort class
            this.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
            this.querySelector('i').className = isAscending ? 'fas fa-sort-up' : 'fas fa-sort-down';
            
            // Sort rows
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].textContent.trim();
                const bText = b.cells[columnIndex].textContent.trim();
                
                // Handle numeric sorting for ID column
                if (columnIndex === 0) {
                    return isAscending ? parseInt(aText) - parseInt(bText) : parseInt(bText) - parseInt(aText);
                }
                
                // Handle text sorting
                return isAscending ? aText.localeCompare(bText) : bText.localeCompare(aText);
            });
            
            // Reorder rows in DOM
            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Enhanced sort functionality
    document.getElementById('sortBy').addEventListener('change', function() {
        sortUsers(this.value);
    });

    document.getElementById('sortOrder').addEventListener('click', function() {
        const currentOrder = this.dataset.order;
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        this.dataset.order = newOrder;
        this.innerHTML = newOrder === 'asc' ? '<i class="fas fa-sort-amount-up"></i>' : '<i class="fas fa-sort-amount-down"></i>';
        sortUsers(document.getElementById('sortBy').value);
    });

    function sortUsers(sortBy) {
        const table = document.getElementById('usersTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const order = document.getElementById('sortOrder').dataset.order;
        const isAscending = order === 'asc';
        
        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortBy) {
                case 'id':
                    aValue = parseInt(a.querySelector('.user-id').textContent);
                    bValue = parseInt(b.querySelector('.user-id').textContent);
                    break;
                case 'username':
                    aValue = a.querySelector('.username').textContent.toLowerCase();
                    bValue = b.querySelector('.username').textContent.toLowerCase();
                    break;
                case 'role':
                    aValue = a.querySelector('.modern-badge').textContent.toLowerCase();
                    bValue = b.querySelector('.modern-badge').textContent.toLowerCase();
                    break;
                case 'created_at':
                    aValue = new Date(a.querySelector('.date').textContent);
                    bValue = new Date(b.querySelector('.date').textContent);
                    break;
                default:
                    return 0;
            }
            
            if (typeof aValue === 'string') {
                return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            } else {
                return isAscending ? aValue - bValue : bValue - aValue;
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }

    // Initialize filters and controls on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('roleFilter').addEventListener('change', filterUsers);
        document.getElementById('statusFilter').addEventListener('change', filterUsers);
    });

    // User management functions
    function editUser(userId) {
        window.location.href = `edit_user.php?id=${userId}`;
    }

    function viewUser(userId) {
        window.location.href = `view_user.php?id=${userId}`;
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting user: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the user.');
            });
        }
    }

    function exportUsers() {
        // Show loading state
        const exportBtn = event.target.closest('.action-btn');
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        exportBtn.disabled = true;
        
        // Simulate export process
        setTimeout(() => {
            window.location.href = 'export_users.php';
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 1000);
    }

    function refreshUsers() {
        location.reload();
    }
    </script>
    
    <!-- Include mobile navigation -->
    <script src="js/mobile-navigation.js"></script>
</body>
</html>
