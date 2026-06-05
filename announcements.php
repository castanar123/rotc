<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
check_login();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $priority = $_POST['priority'] ?? 'normal';
        $target_audience = $_POST['target_audience'] ?? 'all';
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        
        if (!empty($title) && !empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author_id, priority, target_audience, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $content, $_SESSION['user_id'], $priority, $target_audience, $expires_at])) {
                $success_message = "Announcement created successfully!";
            } else {
                $error_message = "Failed to create announcement.";
            }
        } else {
            $error_message = "Title and content are required.";
        }
    }
    
    if (isset($_POST['delete_announcement'])) {
        $announcement_id = $_POST['announcement_id'];
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND author_id = ?");
        if ($stmt->execute([$announcement_id, $_SESSION['user_id']])) {
            $success_message = "Announcement deleted successfully!";
        } else {
            $error_message = "Failed to delete announcement.";
        }
    }
}

// Fetch announcements
$stmt = $pdo->prepare("
    SELECT a.*, u.username as author_name 
    FROM announcements a 
    LEFT JOIN users u ON a.author_id = u.id 
    WHERE (a.expires_at IS NULL OR a.expires_at > NOW())
    ORDER BY a.priority DESC, a.created_at DESC
");
$stmt->execute();
$announcements = $stmt->fetchAll();

$page_title = 'Announcements';
include 'includes/header.php';
?>

<style>
.announcement-system {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}

.create-announcement-form {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.announcements-grid {
    display: grid;
    gap: 20px;
}

.announcement-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.announcement-card:hover {
    transform: translateY(-5px);
}

.announcement-card-header {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.announcement-title {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 18px;
    font-weight: 600;
}

.announcement-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #6c757d;
}

.priority-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-urgent { background: #dc3545; color: white; }
.priority-high { background: #fd7e14; color: white; }
.priority-normal { background: #28a745; color: white; }
.priority-low { background: #6c757d; color: white; }

.announcement-content {
    padding: 20px;
    line-height: 1.6;
}

.announcement-actions {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.toggle-form-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggle-form-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.create-form-container {
    display: none;
}

.create-form-container.active {
    display: block;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .announcement-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .announcement-meta {
        flex-direction: column;
        gap: 5px;
        align-items: flex-start;
    }
}
</style>

<!-- Fixed Sidebar Toggle Button -->
<button class="sidebar-toggle-fixed" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="container-fluid">
    <div class="announcement-system">
        <!-- Header -->
        <div class="announcement-header">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
                <p>Stay updated with the latest news and information</p>
            </div>
            <?php if (in_array($_SESSION['role'], ['admin', 'commandant', '1cl', '2cl'])): ?>
                <button class="toggle-form-btn" onclick="toggleCreateForm()">
                    <i class="fas fa-plus"></i> Create Announcement
                </button>
            <?php endif; ?>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Create Announcement Form -->
        <?php if (in_array($_SESSION['role'], ['admin', 'commandant', '1cl', '2cl'])): ?>
            <div class="create-form-container" id="createFormContainer">
                <div class="create-announcement-form">
                    <h3><i class="fas fa-edit"></i> Create New Announcement</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">Content *</label>
                            <textarea id="content" name="content" class="form-control" required placeholder="Enter your announcement content here..."></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" class="form-control">
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="target_audience">Target Audience</label>
                                <select id="target_audience" name="target_audience" class="form-control">
                                    <option value="all" selected>All Users</option>
                                    <option value="cadets">Cadets Only</option>
                                    <option value="officers">Officers Only</option>
                                    <option value="admin">Admin Only</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="expires_at">Expires At (Optional)</label>
                                <input type="datetime-local" id="expires_at" name="expires_at" class="form-control">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" name="create_announcement" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Announcement
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleCreateForm()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <div class="announcements-grid">
            <?php if (empty($announcements)): ?>
                <div class="announcement-card">
                    <div class="announcement-content" style="text-align: center; padding: 40px;">
                        <i class="fas fa-bullhorn" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #666;">No Announcements Yet</h3>
                        <p style="color: #999;">Check back later for updates and important information.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card">
                        <div class="announcement-card-header">
                            <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="announcement-meta">
                                <div>
                                    <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                        <?php echo ucfirst($announcement['priority']); ?>
                                    </span>
                                    <span style="margin-left: 10px;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['author_name']); ?>
                                    </span>
                                </div>
                                <div>
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($announcement['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                        
                        <?php if ($announcement['author_id'] == $_SESSION['user_id'] || in_array($_SESSION['role'], ['admin', 'commandant'])): ?>
                            <div class="announcement-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?')">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" name="delete_announcement" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCreateForm() {
    const container = document.getElementById('createFormContainer');
    container.classList.toggle('active');
    
    if (container.classList.contains('active')) {
        container.scrollIntoView({ behavior: 'smooth' });
        document.getElementById('title').focus();
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

<?php include 'includes/footer.php'; ?>
