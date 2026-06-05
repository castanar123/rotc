// Mobile Navigation and Sidebar Toggle for ROTC QR System
// Universal implementation for all pages

(function() {
    'use strict';
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeMobileNavigation();
        setupSidebarToggle();
        setupMobileOverlay();
        setupResponsiveHandling();
    });
    
    function initializeMobileNavigation() {
        // Create mobile overlay if it doesn't exist
        if (!document.getElementById('mobileOverlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'mobileOverlay';
            overlay.className = 'mobile-overlay';
            document.body.appendChild(overlay);
        }
        
        // Add fixed sidebar toggle button if it doesn't exist
        if (!document.getElementById('sidebarToggle') && !document.querySelector('.sidebar-toggle-fixed')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.id = 'sidebarToggle';
            toggleBtn.className = 'sidebar-toggle-fixed';
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(toggleBtn);
        }
    }
    
    function setupSidebarToggle() {
        const sidebarToggle = document.getElementById('sidebarToggle') || document.querySelector('.sidebar-toggle-fixed');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Check if mobile
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('show');
                    document.body.classList.toggle('sidebar-open', sidebar.classList.contains('show'));
                    
                    // Show/hide overlay
                    const overlay = document.getElementById('mobileOverlay');
                    if (overlay) {
                        overlay.classList.toggle('active', sidebar.classList.contains('show'));
                    }
                } else {
                    // Toggle between collapsed (default) and expanded states
                    if (sidebar.classList.contains('collapsed')) {
                        sidebar.classList.remove('collapsed');
                        document.body.classList.remove('sidebar-collapsed');
                        localStorage.setItem('sidebarCollapsed', 'false');
                    } else {
                        sidebar.classList.add('collapsed');
                        document.body.classList.add('sidebar-collapsed');
                        localStorage.setItem('sidebarCollapsed', 'true');
                    }
                }
            });
        }
    }
    
    function setupMobileOverlay() {
        const overlay = document.getElementById('mobileOverlay');
        const sidebar = document.getElementById('sidebar');
        
        if (overlay && sidebar) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                overlay.classList.remove('active');
            });
        }
    }
    
    function setupResponsiveHandling() {
        const sidebar = document.getElementById('sidebar');
        
        // Set default collapsed state and restore state if saved for desktop
        if (window.innerWidth > 768 && sidebar) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            } else {
                // Default expanded state
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
            }
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (sidebar) {
                if (window.innerWidth > 768) {
                    // Desktop mode
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                    
                    const overlay = document.getElementById('mobileOverlay');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                    
                    // Restore collapsed state or set default expanded
                    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        document.body.classList.add('sidebar-collapsed');
                    } else {
                        // Default expanded state
                        sidebar.classList.remove('collapsed');
                        document.body.classList.remove('sidebar-collapsed');
                    }
                } else {
                    // Mobile mode
                    sidebar.classList.remove('collapsed');
                    document.body.classList.remove('sidebar-collapsed');
                }
            }
        });
        
        // Close mobile sidebar when clicking on nav links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768 && sidebar) {
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                    
                    const overlay = document.getElementById('mobileOverlay');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                }
            });
        });
    }
    
    // Utility function to adjust main content margin
    function adjustMainContentMargin() {
        const mainContent = document.querySelector('.main-content, .qr-attendance-container, .content');
        const sidebar = document.getElementById('sidebar');
        
        if (mainContent && sidebar) {
            if (window.innerWidth <= 768) {
                mainContent.style.marginLeft = '0';
            } else if (sidebar.classList.contains('collapsed')) {
                mainContent.style.marginLeft = '70px';
            } else {
                mainContent.style.marginLeft = '280px'; // Default expanded state
            }
        }
    }
    
    // Export for external use if needed
    window.MobileNavigation = {
        adjustMainContentMargin: adjustMainContentMargin
    };
})();