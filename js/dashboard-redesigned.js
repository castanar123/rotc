/* =================================================================
   DASHBOARD REDESIGNED JAVASCRIPT - SIDEBAR TOGGLE & RESPONSIVE
   ================================================================= */

// Dashboard functionality
class DashboardRedesigned {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.mainContent = document.querySelector('.main-content');
        this.init();
    }

    init() {
        this.setupSidebarToggle();
        this.setupResponsiveDesign();
        this.setupAnimations();
        this.handleInitialState();
    }

    setupSidebarToggle() {
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!this.sidebar.contains(e.target) && !this.sidebarToggle.contains(e.target)) {
                    this.closeSidebar();
                }
            }
        });
    }

    toggleSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.toggle('collapsed');
            
            // Update toggle button icon
            const icon = this.sidebarToggle.querySelector('i');
            if (icon) {
                if (this.sidebar.classList.contains('collapsed')) {
                    icon.className = 'fas fa-bars';
                } else {
                    icon.className = 'fas fa-times';
                }
            }

            // Store state in localStorage
            localStorage.setItem('sidebarCollapsed', this.sidebar.classList.contains('collapsed'));
        }
    }

    closeSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.add('collapsed');
            const icon = this.sidebarToggle.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-bars';
            }
        }
    }

    openSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.remove('collapsed');
            const icon = this.sidebarToggle.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-times';
            }
        }
    }

    setupResponsiveDesign() {
        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResponsiveState();
        });

        // Initial responsive state
        this.handleResponsiveState();
    }

    handleResponsiveState() {
        if (window.innerWidth <= 768) {
            // Mobile: sidebar should be collapsed by default
            this.closeSidebar();
        } else {
            // Desktop: restore previous state or open by default
            const wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (wasCollapsed) {
                this.closeSidebar();
            } else {
                this.openSidebar();
            }
        }
    }

    handleInitialState() {
        // Set initial state based on screen size and stored preference
        if (window.innerWidth > 768) {
            const wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (wasCollapsed) {
                this.closeSidebar();
            } else {
                this.openSidebar();
            }
        } else {
            this.closeSidebar();
        }
    }

    setupAnimations() {
        // Add smooth transitions
        if (this.sidebar) {
            this.sidebar.style.transition = 'transform 0.3s ease-in-out';
        }

        // Animate cards on load
        const cards = document.querySelectorAll('.content-card, .stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new DashboardRedesigned();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardRedesigned;
}