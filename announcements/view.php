<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Pending registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];
// Check if announcements table exists first
$table_check = "SHOW TABLES LIKE 'announcements'";
$table_exists = mysqli_query($link, $table_check);

$announcements = [];
if($table_exists && mysqli_num_rows($table_exists) > 0) {
    $sql = "SELECT a.title, a.content, a.created_at, u.username as author_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC";
    
    if($result = mysqli_query($link, $sql)){
        while($row = mysqli_fetch_assoc($result)){
            $announcements[] = $row;
        }
    }
} else {
    // Table doesn't exist, we'll show a message later
    $announcements = [];
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>

<body>
     <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Announcements</h1>
                </div>
                
                <div class="header-center">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search announcements..." class="search-input" id="announcementSearch">
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <?php if (in_array($_SESSION['role'], ['admin', 'instructor', 'officer'])): ?>
                            <button class="action-btn" onclick="openCreateModal()" title="Create Announcement">
                                <i class="fas fa-plus"></i>
                                <span>Create</span>
                            </button>
                        <?php endif; ?>
                        <button class="action-btn btn-secondary" onclick="refreshAnnouncements()" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($announcements); ?></div>
                            <div class="stat-label">Total Announcements</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php 
                                $recent_count = 0;
                                foreach($announcements as $announcement) {
                                    if(strtotime($announcement['created_at']) >= strtotime('-7 days')) {
                                        $recent_count++;
                                    }
                                }
                                echo $recent_count;
                            ?></div>
                            <div class="stat-label">This Week</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo ucfirst($_SESSION['role']); ?></div>
                            <div class="stat-label">Your Role</div>
                        </div>
                    </div>
                </div>

                <!-- Announcements Management Section -->
                <div class="management-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-bullhorn"></i>
                            Announcements Management
                        </h2>
                        <div class="controls-panel">
                            <div class="filter-controls">
                                <select class="modern-select" id="filterPeriod">
                                    <option value="">All Time</option>
                                    <option value="7">Last 7 Days</option>
                                    <option value="30">Last 30 Days</option>
                                    <option value="90">Last 3 Months</option>
                                </select>
                                <select class="modern-select" id="filterCategory">
                                    <option value="">All Categories</option>
                                    <option value="general">General</option>
                                    <option value="training">Training</option>
                                    <option value="event">Event</option>
                                    <option value="academic">Academic</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="data-container">
                        <?php if (empty($announcements)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <h3>No Announcements Found</h3>
                                <p>No announcements have been posted yet. Be the first to share important information!</p>
                                <?php if (in_array($_SESSION['role'], ['admin', 'instructor', 'officer'])): ?>
                                    <button class="action-btn" onclick="openCreateModal()">
                                        <i class="fas fa-plus"></i>
                                        Create First Announcement
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="announcements-grid" id="announcementsGrid">
                                <?php foreach ($announcements as $index => $announcement): ?>
                                    <div class="modern-announcement-card" data-date="<?php echo $announcement['created_at']; ?>" data-category="general">
                                        <div class="card-header">
                                            <div class="card-title-section">
                                                <h4 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                                <div class="card-badges">
                                                    <span class="modern-badge priority-normal">
                                                        <i class="fas fa-info-circle"></i>
                                                        Normal
                                                    </span>
                                                    <span class="modern-badge category-general">
                                                        <i class="fas fa-tag"></i>
                                                        General
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-actions">
                                                <?php if (in_array($_SESSION['role'], ['admin', 'instructor', 'officer'])): ?>
                                                    <button class="card-action-btn" onclick="editAnnouncement(<?php echo $index; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="card-action-btn delete" onclick="deleteAnnouncement(<?php echo $index; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="announcement-text">
                                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="author-info">
                                                <div class="author-avatar">
                                                    <?php echo strtoupper(substr($announcement['author_name'], 0, 2)); ?>
                                                </div>
                                                <div class="author-details">
                                                    <span class="author-name"><?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                                    <span class="publish-date"><?php echo date('M d, Y \\a\\t g:i A', strtotime($announcement['created_at'])); ?></span>
                                                </div>
                                            </div>
                                            <div class="card-stats">
                                                <span class="stat-item">
                                                    <i class="fas fa-eye"></i>
                                                    <?php echo rand(10, 100); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Announcement Modal -->
    <div id="createAnnouncementModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Announcement</h3>
                <button type="button" class="modal-close" onclick="closeCreateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="createAnnouncementForm" method="post" action="process_announcement.php">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="modalTitle">
                                <i class="fas fa-heading"></i> Announcement Title
                            </label>
                            <input type="text" name="title" id="modalTitle" class="modern-input" 
                                   placeholder="Enter a clear and descriptive title..." 
                                   maxlength="200" required>
                            <div class="form-help">Keep it concise and informative (max 200 characters)</div>
                        </div>

                        <div class="form-group">
                            <label for="modalPriority">
                                <i class="fas fa-exclamation-circle"></i> Priority Level
                            </label>
                            <select name="priority" id="modalPriority" class="modern-select">
                                <option value="normal">📢 Normal</option>
                                <option value="important">⚠️ Important</option>
                                <option value="urgent">🚨 Urgent</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modalCategory">
                                <i class="fas fa-tags"></i> Category
                            </label>
                            <select name="category" id="modalCategory" class="modern-select">
                                <option value="general">📋 General</option>
                                <option value="training">🎯 Training</option>
                                <option value="event">📅 Event</option>
                                <option value="academic">🎓 Academic</option>
                                <option value="administrative">📊 Administrative</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="modalContent">
                                <i class="fas fa-align-left"></i> Announcement Content
                            </label>
                            <textarea name="content" id="modalContent" class="modern-textarea" 
                                      rows="6" placeholder="Write your announcement content here..." required></textarea>
                            <div class="form-help">
                                <span id="modalCharCount">0</span> characters | Use clear and professional language
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="modalTargetAudience">
                                <i class="fas fa-users"></i> Target Audience
                            </label>
                            <select name="target_audience" id="modalTargetAudience" class="modern-select">
                                <option value="all">👥 All Personnel</option>
                                <option value="cadets">🎓 Cadets Only</option>
                                <option value="officers">⭐ Officers Only</option>
                                <option value="instructors">👨‍🏫 Instructors Only</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modalExpiresAt">
                                <i class="fas fa-calendar-times"></i> Expiration Date (Optional)
                            </label>
                            <input type="datetime-local" name="expires_at" id="modalExpiresAt" class="modern-input">
                            <div class="form-help">Leave empty for permanent announcement</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="action-btn btn-secondary" onclick="closeCreateModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="action-btn" onclick="previewAnnouncement()">
                    <i class="fas fa-eye"></i> Preview
                </button>
                <button type="submit" form="createAnnouncementForm" class="action-btn">
                    <i class="fas fa-bullhorn"></i> Publish Announcement
                </button>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Announcement Preview</h3>
                <button type="button" class="modal-close" onclick="closePreviewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="action-btn btn-secondary" onclick="closePreviewModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="action-btn" onclick="closePreviewModal(); submitAnnouncement()">
                    <i class="fas fa-bullhorn"></i> Publish Now
                </button>
            </div>
        </div>
    </div>

    <script src="../js/mobile-navigation.js"></script>
    <script>
        // Search functionality
        document.getElementById('announcementSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const announcements = document.querySelectorAll('.modern-announcement-card');
            
            announcements.forEach(announcement => {
                const text = announcement.textContent.toLowerCase();
                announcement.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter by period
        document.getElementById('filterPeriod').addEventListener('change', function(e) {
            const days = parseInt(e.target.value);
            const announcements = document.querySelectorAll('.modern-announcement-card');
            
            if (!days) {
                announcements.forEach(announcement => announcement.style.display = '');
                return;
            }
            
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - days);
            
            announcements.forEach(announcement => {
                const dateStr = announcement.dataset.date;
                const announcementDate = new Date(dateStr);
                announcement.style.display = announcementDate >= cutoffDate ? '' : 'none';
            });
        });

        // Modal functions
        function openCreateModal() {
            document.getElementById('createAnnouncementModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeCreateModal() {
            document.getElementById('createAnnouncementModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            resetForm();
        }

        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function resetForm() {
            document.getElementById('createAnnouncementForm').reset();
            updateCharCount();
        }

        function refreshAnnouncements() {
            location.reload();
        }

        function editAnnouncement(index) {
            // Edit functionality to be implemented
            console.log('Edit announcement:', index);
        }

        function deleteAnnouncement(index) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                // Delete functionality to be implemented
                console.log('Delete announcement:', index);
            }
        }

        // Character count for content
        function updateCharCount() {
            const content = document.getElementById('modalContent');
            if (content) {
                document.getElementById('modalCharCount').textContent = content.value.length;
            }
        }

        // Preview functionality
        function previewAnnouncement() {
            const title = document.getElementById('modalTitle').value;
            const priority = document.getElementById('modalPriority').value;
            const category = document.getElementById('modalCategory').value;
            const content = document.getElementById('modalContent').value;
            const targetAudience = document.getElementById('modalTargetAudience').value;
            const expiresAt = document.getElementById('modalExpiresAt').value;

            if (!title || !content) {
                alert('Please fill in the title and content fields.');
                return;
            }

            const priorityEmoji = {
                'normal': '📢',
                'important': '⚠️',
                'urgent': '🚨'
            };

            const categoryEmoji = {
                'general': '📋',
                'training': '🎯',
                'event': '📅',
                'academic': '🎓',
                'administrative': '📊'
            };

            const audienceEmoji = {
                'all': '👥',
                'cadets': '🎓',
                'officers': '⭐',
                'instructors': '👨‍🏫'
            };

            const previewHTML = `
                <div class="modern-announcement-card">
                    <div class="card-header">
                        <div class="card-title-section">
                            <h4 class="card-title">${title}</h4>
                            <div class="card-badges">
                                <span class="modern-badge priority-${priority}">
                                    ${priorityEmoji[priority]} ${priority.charAt(0).toUpperCase() + priority.slice(1)}
                                </span>
                                <span class="modern-badge category-${category}">
                                    ${categoryEmoji[category]} ${category.charAt(0).toUpperCase() + category.slice(1)}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="announcement-text">
                            ${content.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="author-info">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                            </div>
                            <div class="author-details">
                                <span class="author-name">You</span>
                                <span class="publish-date">${new Date().toLocaleDateString()}</span>
                            </div>
                        </div>
                        <div class="audience-info">
                            ${audienceEmoji[targetAudience]} ${targetAudience.charAt(0).toUpperCase() + targetAudience.slice(1)}
                        </div>
                    </div>
                    ${expiresAt ? `<div class="expiry-info"><i class="fas fa-clock"></i> Expires: ${new Date(expiresAt).toLocaleString()}</div>` : ''}
                </div>
            `;

            document.getElementById('previewContent').innerHTML = previewHTML;
            document.getElementById('previewModal').style.display = 'flex';
        }

        function submitAnnouncement() {
            document.getElementById('createAnnouncementForm').submit();
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Character count listener
            const contentTextarea = document.getElementById('modalContent');
            if (contentTextarea) {
                contentTextarea.addEventListener('input', updateCharCount);
                updateCharCount(); // Initialize count
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const createModal = document.getElementById('createAnnouncementModal');
                const previewModal = document.getElementById('previewModal');
                
                if (event.target === createModal) {
                    closeCreateModal();
                }
                if (event.target === previewModal) {
                    closePreviewModal();
                }
            });

            // Form submission handler
            const form = document.getElementById('createAnnouncementForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const title = document.getElementById('modalTitle').value.trim();
                    const content = document.getElementById('modalContent').value.trim();
                    
                    if (!title || !content) {
                        alert('Please fill in all required fields.');
                        return;
                    }
                    
                    // Submit the form
                    this.submit();
                });
            }
        });
    </script>

    <style>
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .announcement-card {
            background: var(--card-bg);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 1.5rem;
            transition: all var(--transition-fast);
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }

        .announcement-header {
            margin-bottom: 1rem;
        }

        .announcement-title {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .announcement-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .announcement-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .announcement-content {
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-line;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-primary);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all var(--transition-fast);
        }

        .modal-close:hover {
            background: var(--danger);
            color: white;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid var(--border-primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modern-input, .modern-select, .modern-textarea {
            padding: 0.75rem;
            border: 1px solid var(--border-primary);
            border-radius: 6px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }

        .modern-input:focus, .modern-select:focus, .modern-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }

        .modern-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .form-help {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .announcement-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
            }

            .modal-footer {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
