/* =================================================================
   UNIFIED DASHBOARD JAVASCRIPT - ROLE-BASED FUNCTIONALITY
   ================================================================= */

// Dashboard configuration for different roles
const DASHBOARD_CONFIG = {
    admin: {
        title: 'Admin Command Center',
        icon: 'fas fa-shield-alt',
        sections: [
            {
                title: 'System Management',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: '#', active: true },
                    { icon: 'fas fa-users', text: 'User Management', href: 'user_management.php' },
                    { icon: 'fas fa-cog', text: 'System Settings', href: 'profile_management.php' }
                ]
            },
            {
                title: 'Operations',
                items: [
                    { icon: 'fas fa-calendar-check', text: 'Attendance System', href: 'attendance/dashboard.php' },
                    { icon: 'fas fa-graduation-cap', text: 'Grade Management', href: 'grades/manage_grades.php' },
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements/create.php' },
                    { icon: 'fas fa-qrcode', text: 'QR Generator', href: 'batch_generate_qr.php' }
                ]
            },
            {
                title: 'Analytics',
                items: [
                    { icon: 'fas fa-chart-bar', text: 'Reports', href: 'reports/generate_report.php' },
                    { icon: 'fas fa-database', text: 'Data Export', href: '#' }
                ]
            }
        ]
    },
    officer: {
        title: 'Officer Command Panel',
        icon: 'fas fa-star',
        sections: [
            {
                title: 'Command',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: '#', active: true },
                    { icon: 'fas fa-users', text: 'My Cadets', href: 'my_platoons.php' },
                    { icon: 'fas fa-dumbbell', text: 'Training', href: 'training/schedule.php' }
                ]
            },
            {
                title: 'Operations',
                items: [
                    { icon: 'fas fa-calendar-check', text: 'Attendance', href: 'attendance/dashboard.php' },
                    { icon: 'fas fa-graduation-cap', text: 'Grades', href: 'grades/view_grades.php' },
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements/view.php' }
                ]
            },
            {
                title: 'Reports',
                items: [
                    { icon: 'fas fa-chart-line', text: 'Performance', href: 'reports/view_report.php' },
                    { icon: 'fas fa-clipboard-list', text: 'Evaluations', href: '#' }
                ]
            }
        ]
    },
    cadet: {
        title: 'Cadet Portal',
        icon: 'fas fa-user-graduate',
        sections: [
            {
                title: 'Personal',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: '#', active: true },
                    { icon: 'fas fa-user', text: 'My Profile', href: 'my_profile.php' },
                    { icon: 'fas fa-id-card', text: 'ID Card', href: 'display_qr.php' }
                ]
            },
            {
                title: 'Academic',
                items: [
                    { icon: 'fas fa-calendar-check', text: 'My Attendance', href: 'attendance/logs.php' },
                    { icon: 'fas fa-graduation-cap', text: 'My Grades', href: 'grades/view_grades.php' },
                    { icon: 'fas fa-dumbbell', text: 'Training Schedule', href: 'training/schedule.php' }
                ]
            },
            {
                title: 'Information',
                items: [
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements/view.php' },
                    { icon: 'fas fa-calendar', text: 'Events', href: 'schedule.php' }
                ]
            }
        ]
    },
    instructor: {
        title: 'Instructor Panel',
        icon: 'fas fa-chalkboard-teacher',
        sections: [
            {
                title: 'Teaching',
                items: [
                    { icon: 'fas fa-tachometer-alt', text: 'Dashboard', href: '#', active: true },
                    { icon: 'fas fa-users', text: 'My Classes', href: 'my_platoons.php' },
                    { icon: 'fas fa-book', text: 'Curriculum', href: '#' }
                ]
            },
            {
                title: 'Assessment',
                items: [
                    { icon: 'fas fa-calendar-check', text: 'Attendance', href: 'attendance/dashboard.php' },
                    { icon: 'fas fa-graduation-cap', text: 'Grades', href: 'grades/manage_grades.php' },
                    { icon: 'fas fa-clipboard-check', text: 'Evaluations', href: '#' }
                ]
            },
            {
                title: 'Communication',
                items: [
                    { icon: 'fas fa-bullhorn', text: 'Announcements', href: 'announcements/create.php' },
                    { icon: 'fas fa-comments', text: 'Messages', href: '#' }
                ]
            }
        ]
    }
};

// Dashboard class
class UnifiedDashboard {
    constructor() {
        this.userRole = this.getUserRole();
        this.config = DASHBOARD_CONFIG[this.userRole] || DASHBOARD_CONFIG.cadet;
        this.init();
    }

    getUserRole() {
        // Get role from body data attribute or session
        const bodyRole = document.body.getAttribute('data-role');
        if (bodyRole) return bodyRole;
        
        // Fallback: detect from URL or page title
        const url = window.location.pathname;
        if (url.includes('admin')) return 'admin';
        if (url.includes('officer')) return 'officer';
        if (url.includes('instructor')) return 'instructor';
        return 'cadet';
    }

    init() {
        this.setupSidebar();
        this.setupEventListeners();
        this.setupSearch();
        this.setupAnimations();
        this.updatePageTitle();
    }

    setupSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        // Add role class
        sidebar.classList.add(this.userRole);

        // Update logo
        const logoIcon = sidebar.querySelector('.logo-icon i');
        const logoText = sidebar.querySelector('.logo span');
        
        if (logoIcon) logoIcon.className = this.config.icon;
        if (logoText) logoText.textContent = this.config.title;

        // Generate navigation menu
        this.generateNavMenu();
    }

    generateNavMenu() {
        const navMenu = document.querySelector('.nav-menu');
        if (!navMenu) return;

        navMenu.innerHTML = '';

        this.config.sections.forEach(section => {
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

                const navLink = document.createElement('a');
                navLink.className = `nav-link ${item.active ? 'active' : ''}`;
                navLink.href = item.href;

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
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new UnifiedDashboard();
});

// Export for use in other scripts
window.UnifiedDashboard = UnifiedDashboard;