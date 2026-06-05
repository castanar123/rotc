// Modern Dashboard JavaScript Framework
class DashboardManager {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.startRealTimeUpdates();
    }

    init() {
        this.sidebar = document.querySelector('.sidebar');
        this.mainContent = document.querySelector('.main-content');
        this.sidebarToggle = document.querySelector('.sidebar-toggle');
        this.searchInput = document.querySelector('.search-input');
        this.userMenu = document.querySelector('.user-menu');
        
        // Initialize animations
        this.animateElements();
        
        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            this.initializeCharts();
        }
        
        // Initialize QR scanner if available
        this.initializeScanner();
    }

    setupEventListeners() {
        // Sidebar toggle
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }

        // Search functionality
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        }

        // Mobile menu handling
        this.setupMobileMenu();

        // Form submissions
        this.setupFormHandlers();

        // Table interactions
        this.setupTableHandlers();

        // Real-time notifications
        this.setupNotifications();
    }

    toggleSidebar() {
        if (this.sidebar && this.mainContent) {
            this.sidebar.classList.toggle('collapsed');
            this.mainContent.classList.toggle('expanded');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', this.sidebar.classList.contains('collapsed'));
        }
    }

    setupMobileMenu() {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                this.sidebar.classList.toggle('mobile-open');
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && 
                !this.sidebar.contains(e.target) && 
                !e.target.closest('.mobile-menu-toggle')) {
                this.sidebar.classList.remove('mobile-open');
            }
        });
    }

    handleSearch(query) {
        if (query.length < 2) return;
        
        // Debounce search
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }

    async performSearch(query) {
        try {
            const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
            const results = await response.json();
            this.displaySearchResults(results);
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    displaySearchResults(results) {
        // Implementation for displaying search results
        console.log('Search results:', results);
    }

    setupFormHandlers() {
        const forms = document.querySelectorAll('form[data-ajax]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.handleAjaxForm(e));
        });
    }

    async handleAjaxForm(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
        }

        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Success!', result.message, 'success');
                if (result.redirect) {
                    setTimeout(() => window.location.href = result.redirect, 1500);
                }
            } else {
                this.showNotification('Error', result.message, 'error');
            }
        } catch (error) {
            this.showNotification('Error', 'An unexpected error occurred', 'error');
            console.error('Form submission error:', error);
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
        }
    }

    setupTableHandlers() {
        // Sortable tables
        const sortableHeaders = document.querySelectorAll('th[data-sort]');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', () => this.sortTable(header));
            header.style.cursor = 'pointer';
        });

        // Row selection
        const checkboxes = document.querySelectorAll('input[type="checkbox"][data-row]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => this.handleRowSelection());
        });
    }

    sortTable(header) {
        const table = header.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const column = header.dataset.sort;
        const currentOrder = header.dataset.order || 'asc';
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        
        rows.sort((a, b) => {
            const aValue = a.querySelector(`td[data-${column}]`)?.textContent || '';
            const bValue = b.querySelector(`td[data-${column}]`)?.textContent || '';
            
            if (newOrder === 'asc') {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });
        
        // Clear existing rows and append sorted rows
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
        
        // Update header indicators
        header.dataset.order = newOrder;
        
        // Update sort indicators
        sortableHeaders.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
        header.classList.add(`sort-${newOrder}`);
    }

    handleRowSelection() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"][data-row]:checked');
        const bulkActions = document.querySelector('.bulk-actions');
        
        if (bulkActions) {
            bulkActions.style.display = checkboxes.length > 0 ? 'block' : 'none';
        }
    }

    setupNotifications() {
        // Create notification container if it doesn't exist
        if (!document.querySelector('.notification-container')) {
            const container = document.createElement('div');
            container.className = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
    }

    showNotification(title, message, type = 'info', duration = 5000) {
        const container = document.querySelector('.notification-container');
        const notification = document.createElement('div');
        
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#28a745',
            info: '#3b82f6'
        };
        
        notification.className = 'notification fade-in';
        notification.style.cssText = `
            background: white;
            border-left: 4px solid ${colors[type]};
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            padding: 16px;
            position: relative;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">${title}</div>
                    <div style="color: #6b7280; font-size: 14px;">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="
                    background: none;
                    border: none;
                    color: #9ca3af;
                    cursor: pointer;
                    font-size: 18px;
                    padding: 0;
                    margin-left: 12px;
                ">×</button>
            </div>
        `;
        
        container.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    animateElements() {
        // Animate cards on load
        const cards = document.querySelectorAll('.card, .stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    initializeCharts() {
        // Initialize various chart types
        this.initAttendanceChart();
        this.initPerformanceChart();
        this.initActivityChart();
    }

    initAttendanceChart() {
        const ctx = document.getElementById('attendanceChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Attendance Rate',
                    data: [95, 87, 92, 89, 94, 88, 91],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    initPerformanceChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Average', 'Needs Improvement'],
                datasets: [{
                    data: [35, 40, 20, 5],
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#28a745',
                        '#ef4444'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    initActivityChart() {
        const ctx = document.getElementById('activityChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Activities',
                    data: [12, 19, 15, 25, 22, 18],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    initializeScanner() {
        const scannerContainer = document.getElementById('qr-scanner');
        if (!scannerContainer) return;

        // Initialize QR scanner
        if (typeof Html5QrcodeScanner !== 'undefined') {
            const scanner = new Html5QrcodeScanner(
                'qr-scanner',
                { fps: 10, qrbox: 250 },
                false
            );

            scanner.render(
                (decodedText, decodedResult) => {
                    this.handleScanResult(decodedText, decodedResult);
                },
                (error) => {
                    console.warn('QR scan error:', error);
                }
            );
        }
    }

    async handleScanResult(decodedText, decodedResult) {
        try {
            // Process attendance scan
            const response = await fetch('attendance/process_scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    qr_data: decodedText,
                    timestamp: new Date().toISOString()
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Scan Successful', result.message, 'success');
                this.updateAttendanceDisplay(result.data);
            } else {
                this.showNotification('Scan Error', result.message, 'error');
            }
        } catch (error) {
            this.showNotification('Error', 'Failed to process scan', 'error');
            console.error('Scan processing error:', error);
        }
    }

    updateAttendanceDisplay(data) {
        // Update attendance statistics in real-time
        const attendanceCount = document.getElementById('attendance-count');
        const attendanceRate = document.getElementById('attendance-rate');
        
        if (attendanceCount) {
            attendanceCount.textContent = data.total_present;
        }
        
        if (attendanceRate) {
            attendanceRate.textContent = `${data.attendance_rate}%`;
        }
    }

    startRealTimeUpdates() {
        // Update dashboard data every 30 seconds
        setInterval(() => {
            this.updateDashboardData();
        }, 30000);
    }

    async updateDashboardData() {
        try {
            const response = await fetch('api/dashboard_data.php');
            const data = await response.json();
            
            // Update various dashboard elements
            this.updateStatCards(data.stats);
            this.updateRecentActivity(data.recent_activity);
            this.updateNotifications(data.notifications);
        } catch (error) {
            console.error('Failed to update dashboard data:', error);
        }
    }

    updateStatCards(stats) {
        Object.keys(stats).forEach(key => {
            const element = document.getElementById(`stat-${key}`);
            if (element) {
                element.textContent = stats[key];
            }
        });
    }

    updateRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!container) return;

        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="${activity.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            </div>
        `).join('');
    }

    updateNotifications(notifications) {
        notifications.forEach(notification => {
            if (!notification.shown) {
                this.showNotification(
                    notification.title,
                    notification.message,
                    notification.type
                );
            }
        });
    }

    // Utility methods
    formatDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(new Date(date));
    }

    formatTime(date) {
        return new Intl.DateTimeFormat('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    debounce(func, wait) {
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
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new DashboardManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardManager;
}