// Dashboard JavaScript for ROTC Management System

// Global variables
let attendanceChart = null;
let sidebarCollapsed = false;
let mobileMenuOpen = false;

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
    setupMobileResponsiveness();
    setupToastNotifications();
});

// Main dashboard initialization
function initializeDashboard() {
    // Initialize sidebar state
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarCollapsed');
    
    if (savedState === 'true') {
        toggleSidebar(true);
    }
    
    // Initialize search functionality
    setupSearch();
    
    // Initialize user menu
    setupUserMenu();
    
    // Initialize notifications
    setupNotifications();
    
    // Add loading animations
    addLoadingAnimations();
}

// Setup event listeners
function setupEventListeners() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => toggleSidebar());
    }
    
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', () => toggleMobileSidebar());
    }
    
    // Navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active class from all links
            navLinks.forEach(l => l.parentElement.classList.remove('active'));
            // Add active class to clicked link
            this.parentElement.classList.add('active');
        });
    });
    
    // Action cards hover effects
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
        });
    });
    
    // Progress bars animation
    animateProgressBars();
}

// Toggle sidebar collapse
function toggleSidebar(force = null) {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (force !== null) {
        sidebarCollapsed = force;
    } else {
        sidebarCollapsed = !sidebarCollapsed;
    }
    
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
    } else {
        sidebar.classList.remove('collapsed');
    }
    
    // Save state to localStorage
    localStorage.setItem('sidebarCollapsed', sidebarCollapsed.toString());
    
    // Trigger chart resize if needed
    setTimeout(() => {
        if (attendanceChart) {
            attendanceChart.resize();
        }
    }, 300);
}

// Toggle mobile sidebar
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    mobileMenuOpen = !mobileMenuOpen;
    
    if (mobileMenuOpen) {
        sidebar.classList.add('mobile-open');
        // Add overlay
        createMobileOverlay();
    } else {
        sidebar.classList.remove('mobile-open');
        removeMobileOverlay();
    }
}

// Create mobile overlay
function createMobileOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'mobile-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        backdrop-filter: blur(2px);
    `;
    
    overlay.addEventListener('click', () => {
        toggleMobileSidebar();
    });
    
    document.body.appendChild(overlay);
}

// Remove mobile overlay
function removeMobileOverlay() {
    const overlay = document.querySelector('.mobile-overlay');
    if (overlay) {
        overlay.remove();
    }
}

// Setup mobile responsiveness
function setupMobileResponsiveness() {
    // Handle window resize
    window.addEventListener('resize', function() {
        const width = window.innerWidth;
        
        if (width > 768) {
            // Desktop view
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('mobile-open');
            removeMobileOverlay();
            mobileMenuOpen = false;
        }
        
        // Resize charts
        if (attendanceChart) {
            attendanceChart.resize();
        }
    });
}

// Setup search functionality
function setupSearch() {
    const searchInput = document.querySelector('.search-input');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            // Implement search logic here
            console.log('Searching for:', query);
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Perform search
                performSearch(this.value);
            }
        });
    }
}

// Perform search
function performSearch(query) {
    if (query.trim() === '') return;
    
    showToast('Searching for: ' + query, 'info');
    // Implement actual search logic here
}

// Setup user menu
function setupUserMenu() {
    const userMenuBtn = document.querySelector('.user-menu-btn');
    
    if (userMenuBtn) {
        userMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Toggle user menu dropdown
            toggleUserMenu();
        });
    }
}

// Toggle user menu dropdown
function toggleUserMenu() {
    // Create dropdown if it doesn't exist
    let dropdown = document.querySelector('.user-dropdown');
    
    if (!dropdown) {
        dropdown = createUserDropdown();
        document.querySelector('.user-menu').appendChild(dropdown);
    }
    
    dropdown.classList.toggle('show');
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function closeDropdown(e) {
        if (!e.target.closest('.user-menu')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

// Create user dropdown menu
function createUserDropdown() {
    const dropdown = document.createElement('div');
    dropdown.className = 'user-dropdown';
    dropdown.innerHTML = `
        <a href="profile.php" class="dropdown-item">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
        <a href="settings.php" class="dropdown-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="../../logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    `;
    
    // Add styles
    dropdown.style.cssText = `
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 200px;
        z-index: 1000;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        pointer-events: none;
    `;
    
    // Style dropdown items
    const style = document.createElement('style');
    style.textContent = `
        .user-dropdown.show {
            opacity: 1 !important;
            transform: translateY(0) !important;
            pointer-events: auto !important;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }
    `;
    
    document.head.appendChild(style);
    
    return dropdown;
}

// Setup notifications
function setupNotifications() {
    const notificationBtn = document.querySelector('.notification-btn');
    
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showNotifications();
        });
    }
}

// Show notifications panel
function showNotifications() {
    showToast('Notifications panel opened', 'info');
    // Implement notifications panel logic here
}

// Initialize attendance chart
function initializeAttendanceChart(data) {
    const canvas = document.getElementById('attendanceChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    if (attendanceChart) {
        attendanceChart.destroy();
    }
    
    attendanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [data.present, data.late, data.absent],
                backgroundColor: [
                    'var(--success)',
                    'var(--warning)',
                    'var(--danger)'
                ],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'var(--bg-secondary)',
                    titleColor: 'var(--text-primary)',
                    bodyColor: 'var(--text-secondary)',
                    borderColor: 'var(--border-color)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateRotate: true,
                duration: 1000
            }
        }
    });
}

// Animate progress bars
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target;
                const width = progressBar.style.width;
                
                // Reset width and animate
                progressBar.style.width = '0%';
                progressBar.style.transition = 'width 1s ease-out';
                
                setTimeout(() => {
                    progressBar.style.width = width;
                }, 100);
                
                observer.unobserve(progressBar);
            }
        });
    }, { threshold: 0.5 });
    
    progressBars.forEach(bar => observer.observe(bar));
}

// Add loading animations
function addLoadingAnimations() {
    const cards = document.querySelectorAll('.dashboard-card, .action-card');
    
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Toast notification system
function setupToastNotifications() {
    // Create toast container if it doesn't exist
    if (!document.getElementById('toastContainer')) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
}

// Show toast notification
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = getToastIcon(type);
    
    toast.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

// Get toast icon based on type
function getToastIcon(type) {
    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    return icons[type] || icons.info;
}

// Utility functions
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(date) {
    return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function calculatePercentage(value, total) {
    return total > 0 ? Math.round((value / total) * 100) : 0;
}

// Export functions for global use
window.dashboardUtils = {
    showToast,
    initializeAttendanceChart,
    toggleSidebar,
    toggleMobileSidebar,
    formatDate,
    formatTime,
    calculatePercentage
};

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Refresh data when page becomes visible
        console.log('Page is now visible, refreshing data...');
        // Implement data refresh logic here
    }
});

// Handle online/offline status
window.addEventListener('online', function() {
    showToast('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showToast('Connection lost. Some features may not work.', 'warning', 5000);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to close mobile menu
    if (e.key === 'Escape' && mobileMenuOpen) {
        toggleMobileSidebar();
    }
});

// Performance monitoring
if ('performance' in window) {
    window.addEventListener('load', function() {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
            
            if (loadTime > 3000) {
                console.warn('Dashboard loaded slowly:', loadTime + 'ms');
            }
        }, 0);
    });
}