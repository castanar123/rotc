/* =================================================================
   UNIFIED DASHBOARD JAVASCRIPT - ROLE-BASED FUNCTIONALITY
   ================================================================= */

/**
 * ROTC Dashboard Unified JavaScript
 * Version: 2.0
 * Description: Complete dashboard functionality and navigation system
 */

// ===== DASHBOARD CONFIGURATION =====
const DASHBOARD_CONFIG = {
    roles: {
        admin: {
            title: 'Admin Command Center',
            dashboard: 'admin_dashboard.php',
            features: ['user_management', 'reports', 'settings', 'qr_scanner']
        },
        officer: {
            title: 'Officer Command',
            dashboard: 'officer_dashboard.php',
            features: ['platoon_management', 'reports', 'qr_scanner']
        },
        instructor: {
            title: 'Instructor Command',
            dashboard: 'officer_dashboard.php',
            features: ['platoon_management', 'reports', 'qr_scanner']
        },
        cadet: {
            title: 'Cadet Portal',
            dashboard: 'cadet_dashboard.php',
            features: ['profile', 'grades', 'attendance']
        }
    },
    
    // Use relative base so the dashboard works under any mount path (e.g., /generate%20qr/rotc-system/)
    baseUrl: '',
    
    routes: {
        // Admin routes
        admin_dashboard: 'admin_dashboard.php',
        user_management: 'user_management.php',
        profile_management: 'profile_management.php',
        settings: 'settings.php',
        
        // Officer routes
        officer_dashboard: 'officer_dashboard.php',
        my_platoons: 'my_platoons.php',
        training_schedule: 'training_schedule.php',
        
        // Cadet routes
        cadet_dashboard: 'cadet_dashboard.php',
        my_profile: 'my_profile.php',
        schedule: 'schedule.php',
        
        // Shared routes
        attendance_dashboard: 'attendance/dashboard.php',
        attendance_scan: 'attendance/scan.php',
        attendance_manual: 'attendance/manual_attendance.php',
        reports: 'reports/view_report.php',
        generate_report: 'reports/generate_report.php',
        grades: 'grades/view_grades.php',
        manage_grades: 'grades/manage_grades.php',
        announcements: 'announcements.php',
        logout: 'logout.php'
    }
};

// ===== NAVIGATION MENU CONFIGURATION =====
const NAVIGATION_MENUS = {
    admin: {
        title: 'Admin Command Center',
        icon: 'fas fa-shield-alt',
        sections: [
            {
                title: 'COMMAND CENTER',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: 'admin_dashboard.php', active: true },
                    { icon: 'fas fa-users-cog', text: 'User Management', href: 'user_management.php' },
                    { icon: 'fas fa-cog', text: 'System Settings', href: 'settings.php' }
                ]
            },
            {
                title: 'OPERATIONS',
                items: [
                    { icon: 'fas fa-qrcode', text: 'QR Attendance', href: 'attendance/dashboard.php' },
                    { icon: 'fas fa-graduation-cap', text: 'Grade Management', href: 'grades/manage_grades.php' },
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements.php' },
                    { icon: 'fas fa-chart-bar', text: 'Reports', href: 'reports/view_report.php' }
                ]
            }
        ]
    },
    officer: {
        title: 'Officer Command',
        icon: 'fas fa-star',
        sections: [
            {
                title: 'COMMAND',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: 'officer_dashboard.php', active: true },
                    { icon: 'fas fa-users', text: 'My Platoon', href: 'my_platoons.php' },
                    { icon: 'fas fa-dumbbell', text: 'Training', href: 'training_schedule.php' }
                ]
            },
            {
                title: 'OPERATIONS',
                items: [
                    { icon: 'fas fa-qrcode', text: 'QR Attendance', href: 'attendance/dashboard.php' },
                    { icon: 'fas fa-graduation-cap', text: 'Grades', href: 'grades/view_grades.php' },
                    { icon: 'fas fa-chart-line', text: 'Reports', href: 'reports/view_report.php' }
                ]
            }
        ]
    },
    instructor: {
        title: 'Instructor Command',
        icon: 'fas fa-chalkboard-teacher',
        sections: [
            {
                title: 'TEACHING',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: 'officer_dashboard.php', active: true },
                    { icon: 'fas fa-users', text: 'My Classes', href: 'my_platoons.php' },
                    { icon: 'fas fa-book', text: 'Curriculum', href: 'training_schedule.php' }
                ]
            },
            {
                title: 'ASSESSMENT',
                items: [
                    { icon: 'fas fa-qrcode', text: 'Attendance', href: 'attendance/dashboard.php' },
                    { icon: 'fas fa-graduation-cap', text: 'Grades', href: 'grades/manage_grades.php' },
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements.php' }
                ]
            }
        ]
    },
    cadet: {
        title: 'Cadet Portal',
        icon: 'fas fa-user-graduate',
        sections: [
            {
                title: 'PERSONAL',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: 'cadet_dashboard.php', active: true },
                    { icon: 'fas fa-user', text: 'My Profile', href: 'my_profile.php' },
                    { icon: 'fas fa-id-card', text: 'My QR Code', href: 'display_qr.php' },
                    { icon: 'fas fa-id-card-alt', text: 'Missing ID', href: '../file_missing_id.php' }
                ]
            },
            {
                title: 'ACADEMIC',
                items: [
                    { icon: 'fas fa-calendar-check', text: 'My Attendance', href: 'attendance/scan.php' },
                    { icon: 'fas fa-graduation-cap', text: 'My Grades', href: 'grades/view_grades.php' },
                    { icon: 'fas fa-calendar', text: 'Schedule', href: 'schedule.php' }
                ]
            },
            {
                title: 'INFORMATION',
                items: [
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements.php' }
                ]
            }
        ]
    }
};

// ===== DASHBOARD CLASS =====
class ROTCDashboard {
    constructor() {
        this.currentUser = null;
        this.sidebarCollapsed = false;
        this.qrScanner = null;
        this.charts = {};
        
        this.init();
    }

    // Initialize dashboard
    init() {
        this.loadUserSession();
        this.generateSidebar();
        this.setupEventListeners();
        this.initializeComponents();
        this.setupResponsiveHandlers();
        
        // Auto-refresh data every 30 seconds
        setInterval(() => this.refreshDashboardData(), 30000);
    }
    
    // Load user session data
    loadUserSession() {
        // This would typically come from PHP session or API
        const userElement = document.querySelector('.user-info');
        if (userElement) {
            this.currentUser = {
                name: userElement.querySelector('.user-name')?.textContent || 'User',
                role: userElement.querySelector('.user-role')?.textContent?.toLowerCase() || 'cadet',
                avatar: userElement.querySelector('.user-avatar')?.textContent || 'U'
            };
        }
    }

    // Generate sidebar navigation
    generateSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;
        
        // Get user role
        const userRole = this.currentUser?.role || 'cadet';
        
        // Get navigation config
        const navConfig = NAVIGATION_MENUS[userRole] || NAVIGATION_MENUS.cadet;
        
        // Add role class
        sidebar.classList.add(userRole);
        
        // Update logo if elements exist
        const logoIcon = sidebar.querySelector('.logo-icon i');
        const logoText = sidebar.querySelector('.logo span');
        
        if (logoIcon) logoIcon.className = navConfig.icon;
        if (logoText) logoText.textContent = navConfig.title;
        
        // Generate navigation menu
        this.generateNavMenu(navConfig);
    }
    
    // Generate navigation menu
    generateNavMenu(navConfig) {
        const navMenu = document.querySelector('.nav-menu');
        if (!navMenu) return;
        
        // Clear existing menu
        navMenu.innerHTML = '';
        
        // Get current page path
        const currentPath = window.location.pathname;
        const baseUrl = DASHBOARD_CONFIG.baseUrl;
        
        // Generate menu sections
        navConfig.sections.forEach(section => {
            // Create section
            const sectionDiv = document.createElement('div');
            sectionDiv.className = 'nav-section';
            
            // Section title
            const sectionTitle = document.createElement('div');
            sectionTitle.className = 'nav-section-title';
            sectionTitle.textContent = section.title;
            sectionDiv.appendChild(sectionTitle);
            
            // Section items
            section.items.forEach(item => {
                const navItem = document.createElement('div');
                navItem.className = 'nav-item';
                
                // Determine if this link is active
                const isActive = item.active || currentPath.includes(item.href);
                
                const navLink = document.createElement('a');
                navLink.className = `nav-link ${isActive ? 'active' : ''}`;
                navLink.href = baseUrl + item.href;
                
                navLink.innerHTML = `
                    <div class="nav-icon"><i class="${item.icon}"></i></div>
                    <span>${item.text}</span>
                `;

                navItem.appendChild(navLink);
                sectionDiv.appendChild(navItem);
            });

            navMenu.appendChild(sectionDiv);
        });

        // Add logout at the bottom
        this.addLogoutButton(navMenu);
    }

    addLogoutButton(navMenu) {
        const logoutSection = document.createElement('div');
        logoutSection.className = 'nav-section';
        logoutSection.style.marginTop = 'auto';
        logoutSection.style.borderTop = '1px solid var(--border-primary)';
        logoutSection.style.paddingTop = 'var(--spacing-lg)';

        const logoutItem = document.createElement('div');
        logoutItem.className = 'nav-item';

        const logoutLink = document.createElement('a');
        logoutLink.className = 'nav-link';
        logoutLink.href = 'logout.php';
        logoutLink.innerHTML = `
            <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
            <span>Logout</span>
        `;

        logoutItem.appendChild(logoutLink);
        logoutSection.appendChild(logoutItem);
        navMenu.appendChild(logoutSection);
    }

    initializeComponents() {
        // Initialize charts if present
        this.initializeCharts();
        
        // Initialize data tables
        this.initializeDataTables();
        
        // Initialize tooltips
        this.initializeTooltips();
        
        // Initialize QR scanner if present
        this.initializeQRScanner();
        
        // Update page title
        this.updatePageTitle();
    }

    initializeCharts() {
        // Initialize Chart.js charts if present
        const chartElements = document.querySelectorAll('canvas[data-chart]');
        chartElements.forEach(canvas => {
            const chartType = canvas.dataset.chart;
            const chartData = canvas.dataset.chartData ? JSON.parse(canvas.dataset.chartData) : {};
            
            if (window.Chart) {
                new Chart(canvas, {
                    type: chartType,
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: {
                                    color: 'var(--text-primary)'
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: {
                                    color: 'var(--text-secondary)'
                                },
                                grid: {
                                    color: 'var(--border-primary)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'var(--text-secondary)'
                                },
                                grid: {
                                    color: 'var(--border-primary)'
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    initializeDataTables() {
        // Initialize DataTables if present
        const tables = document.querySelectorAll('table[data-table]');
        tables.forEach(table => {
            if (window.$ && $.fn.DataTable) {
                $(table).DataTable({
                    responsive: true,
                    pageLength: 25,
                    language: {
                        search: "Search records:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            }
        });
    }

    initializeTooltips() {
        // Initialize tooltips
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    initializeQRScanner() {
        // Initialize QR scanner if on scanner page
        const qrScannerElement = document.getElementById('qr-scanner');
        if (qrScannerElement && window.Html5QrcodeScanner) {
            const scanner = new Html5QrcodeScanner(
                "qr-scanner",
                { fps: 10, qrbox: 250 },
                false
            );
            
            scanner.render(
                (decodedText, decodedResult) => {
                    this.handleQRScanSuccess(decodedText, decodedResult);
                },
                (error) => {
                    console.warn('QR scan error:', error);
                }
            );
        }
    }

    handleQRScanSuccess(decodedText, decodedResult) {
        // Handle successful QR scan
        console.log('QR Code scanned:', decodedText);
        
        // Send to attendance processing
        fetch('attendance/process_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                qr_data: decodedText,
                timestamp: new Date().toISOString()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Attendance recorded successfully!', 'success');
            } else {
                this.showNotification(data.message || 'Failed to record attendance', 'error');
            }
        })
        .catch(error => {
            console.error('Error processing QR:', error);
            this.showNotification('Error processing QR code', 'error');
        });
    }

    showTooltip(element, text) {
        // Remove existing tooltip
        this.hideTooltip();
        
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.id = 'active-tooltip';
        
        document.body.appendChild(tooltip);
        
        // Position tooltip
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        // Show tooltip
        setTimeout(() => tooltip.classList.add('show'), 10);
    }

    hideTooltip() {
        const tooltip = document.getElementById('active-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    setupEventListeners() {
        // Sidebar toggle
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                
                // Save state
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });

            // Restore sidebar state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        }

        // Mobile sidebar toggle
        if (window.innerWidth <= 1024) {
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }

            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            });
        }

        // Navigation link active state
        document.addEventListener('click', (e) => {
            const navLink = e.target.closest('.nav-link');
            if (navLink && navLink.href !== '#') {
                // Remove active from all links
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                
                // Add active to clicked link
                navLink.classList.add('active');
            }
        });
    }

    setupSearch() {
        const searchInput = document.querySelector('.search-input');
        if (!searchInput) return;

        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.performSearch(e.target.value);
            }, 300);
        });
    }

    performSearch(query) {
        if (!query.trim()) return;
        
        // Implement search functionality based on role
        console.log(`Searching for: ${query} as ${this.userRole}`);
        
        // This would typically make an AJAX request to search endpoint
        // For now, just log the search
    }

    setupAnimations() {
        // Animate cards on load
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('fade-in');
        });

        // Animate sidebar on load
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.add('slide-in');
        }
    }

    updatePageTitle() {
        const pageTitle = document.querySelector('.page-title');
        if (pageTitle) {
            pageTitle.textContent = this.config.title;
        }

        // Update document title
        document.title = `${this.config.title} - ROTC Management System`;
    }

    // Utility methods
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '12px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '500',
            zIndex: '10000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease'
        });

        // Set background color based on type
        const colors = {
            success: '#00ff7f',
            error: '#ff4444',
            warning: '#28a745',
            info: '#00bfff'
        };
        notification.style.backgroundColor = colors[type] || colors.info;

        // Add to page
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after delay
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    updateStatCard(cardId, value, change = null) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const valueElement = card.querySelector('.stat-value');
        const changeElement = card.querySelector('.stat-change');

        if (valueElement) {
            // Animate number change
            const currentValue = parseInt(valueElement.textContent) || 0;
            const targetValue = parseInt(value) || 0;
            this.animateNumber(valueElement, currentValue, targetValue);
        }

        if (changeElement && change !== null) {
            changeElement.textContent = change;
            changeElement.className = `stat-change ${change.startsWith('+') ? 'positive' : 'negative'}`;
        }
    }

    animateNumber(element, start, end, duration = 1000) {
        const startTime = performance.now();
        const difference = end - start;

        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = Math.floor(start + (difference * progress));
            element.textContent = current.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    }

    setupResponsiveHandlers() {
        // Handle responsive behavior
        const handleResize = () => {
            const sidebar = document.querySelector('.sidebar');
            const isMobile = window.innerWidth <= 768;
            
            if (sidebar) {
                if (isMobile) {
                    sidebar.classList.add('mobile');
                } else {
                    sidebar.classList.remove('mobile');
                }
            }
            
            // Update charts on resize
            if (window.Chart) {
                Chart.helpers.each(Chart.instances, (instance) => {
                    instance.resize();
                });
            }
        };
        
        window.addEventListener('resize', debounce(handleResize, 250));
        handleResize(); // Initial call
    }

    refreshDashboardData() {
        // Refresh dashboard data periodically
        const currentPage = window.location.pathname.split('/').pop();
        
        // Only refresh on dashboard pages
        if (!currentPage.includes('dashboard')) return;
        
        fetch(`api/dashboard_data.php?page=${currentPage}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateDashboardStats(data.stats);
            }
        })
        .catch(error => {
            console.warn('Failed to refresh dashboard data:', error);
        });
    }

    updateDashboardStats(stats) {
        // Update statistics cards with new data
        Object.entries(stats).forEach(([key, value]) => {
            const statCard = document.querySelector(`[data-stat="${key}"]`);
            if (statCard) {
                const valueElement = statCard.querySelector('.stat-value');
                if (valueElement) {
                    const currentValue = parseInt(valueElement.textContent) || 0;
                    const newValue = parseInt(value) || 0;
                    this.animateNumber(valueElement, currentValue, newValue);
                }
            }
        });
    }
}

// ===== UTILITY FUNCTIONS =====

// Format date
function formatDate(date, format = 'short') {
    const options = {
        short: { month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        time: { hour: '2-digit', minute: '2-digit' }
    };
    
    return new Intl.DateTimeFormat('en-US', options[format]).format(new Date(date));
}

// Format number
function formatNumber(number, decimals = 0) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ===== INITIALIZATION =====

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.rotcDashboard = new ROTCDashboard();
});

// Export for global access
window.DASHBOARD_CONFIG = DASHBOARD_CONFIG;
window.ROTCDashboard = ROTCDashboard;

// ===== ADDITIONAL STYLES FOR NOTIFICATIONS =====
const notificationStyles = `
<style>
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification {
    background: var(--bg-card);
    border: 1px solid var(--border-secondary);
    border-radius: var(--radius-md);
    padding: var(--spacing-md);
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    box-shadow: 0 4px 12px var(--shadow-primary);
    animation: slideInRight 0.3s ease-out;
}

.notification-success {
    border-left: 4px solid var(--status-present);
}

.notification-error {
    border-left: 4px solid var(--status-absent);
}

.notification-warning {
    border-left: 4px solid var(--status-late);
}

.notification-info {
    border-left: 4px solid var(--status-excused);
}

.notification-content {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    color: var(--text-primary);
}

.notification-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: var(--spacing-xs);
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.notification-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.tooltip {
    position: absolute;
    background: var(--bg-secondary);
    color: var(--text-primary);
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    z-index: 10001;
    pointer-events: none;
    box-shadow: 0 2px 8px var(--shadow-primary);
}
</style>
`;

// Inject notification styles
document.head.insertAdjacentHTML('beforeend', notificationStyles);