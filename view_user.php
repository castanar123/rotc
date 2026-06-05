<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    redirect_to_dashboard();
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("location: user_management.php");
    exit;
}

// Fetch user data
$sql = "SELECT id, username, email, role, created_at FROM users WHERE id = ?";
if ($stmt = $link->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
    } else {
        header("location: user_management.php?error=user_not_found");
        exit;
    }
    $stmt->close();
} else {
    die('Error fetching user data.');
}

$page_title = 'View User Details';
include 'includes/header.php';
?>

<link rel="stylesheet" href="css/tactical-theme.css">
<link rel="stylesheet" href="css/dashboard-redesigned.css">
<link rel="stylesheet" href="css/mobile-responsive.css">

<body class="tactical-dark">
    <!-- Include Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <div class="title-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="title-text">
                        <h1>User Details</h1>
                        <p class="subtitle">View user information and account details</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="user_management.php" class="action-btn secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Users
                    </a>
                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-btn primary">
                        <i class="fas fa-edit"></i>
                        Edit User
                    </a>
                </div>
            </div>
        </div>

        <!-- User Details Card -->
        <div class="user-details-container">
            <div class="user-details-card">
                <div class="user-avatar-section">
                    <div class="user-avatar-large">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-basic-info">
                        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="modern-badge role-<?php echo strtolower($user['role']); ?>">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))); ?>
                        </span>
                    </div>
                </div>

                <div class="user-details-grid">
                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-id-badge"></i>
                            User ID
                        </div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['id']); ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-user-tag"></i>
                            Username
                        </div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['username']); ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-shield-alt"></i>
                            Role
                        </div>
                        <div class="detail-value">
                            <span class="modern-badge role-<?php echo strtolower($user['role']); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))); ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-calendar-plus"></i>
                            Account Created
                        </div>
                        <div class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($user['created_at'])); ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">
                            <i class="fas fa-info-circle"></i>
                            Account Status
                        </div>
                        <div class="detail-value">
                            <span class="modern-badge status-active">
                                Active
                            </span>
                        </div>
                    </div>
                </div>

                <div class="user-actions-section">
                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-btn primary">
                        <i class="fas fa-edit"></i>
                        Edit User
                    </a>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <button class="action-btn danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                        <i class="fas fa-trash"></i>
                        Delete User
                    </button>
                    <?php endif; ?>
                    <a href="user_management.php" class="action-btn secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Users
                    </a>
                </div>
            </div>
        </div>
    </main>

    <style>
    .user-details-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .user-details-card {
        background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-primary);
        padding: var(--spacing-xl);
        box-shadow: var(--shadow-primary);
    }

    .user-avatar-section {
        display: flex;
        align-items: center;
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
        padding-bottom: var(--spacing-xl);
        border-bottom: 1px solid var(--border-primary);
    }

    .user-avatar-large {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--military-green) 0%, #20c997 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: white;
        box-shadow: var(--shadow-primary);
    }

    .user-basic-info h2 {
        margin: 0 0 var(--spacing-sm) 0;
        color: var(--text-accent);
        font-family: 'Orbitron', sans-serif;
        font-size: 1.8rem;
    }

    .user-email {
        margin: 0 0 var(--spacing-md) 0;
        color: var(--text-secondary);
        font-size: 1.1rem;
    }

    .user-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
    }

    .detail-item {
        background: rgba(15, 20, 25, 0.5);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
    }

    .detail-label {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: var(--spacing-sm);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .detail-value {
        color: var(--text-accent);
        font-size: 1.1rem;
        font-weight: 600;
    }

    .user-actions-section {
        display: flex;
        gap: var(--spacing-md);
        justify-content: center;
        flex-wrap: wrap;
        padding-top: var(--spacing-xl);
        border-top: 1px solid var(--border-primary);
    }

    .action-btn.danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        box-shadow: var(--shadow-primary);
    }

    .action-btn.danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-secondary);
    }

    @media (max-width: 768px) {
        .user-avatar-section {
            flex-direction: column;
            text-align: center;
        }

        .user-details-grid {
            grid-template-columns: 1fr;
        }

        .user-actions-section {
            flex-direction: column;
        }
    }
    </style>

    <script>
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
                    window.location.href = 'user_management.php?deleted=1';
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
    </script>

    <!-- Include mobile navigation -->
    <script src="js/mobile-navigation.js"></script>
</body>
</html>