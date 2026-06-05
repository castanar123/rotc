/**
 * QR Dashboard JavaScript
 * Handles QR attendance dashboard specific functionality
 * Complements the main dashboard.js functionality
 */

// QR Dashboard specific functionality
class QRDashboard {
    constructor() {
        this.apiBaseUrl = '../QR/session.php';
        this.isInitialized = false;
        this.init();
    }

    init() {
        if (this.isInitialized) return;
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
        } else {
            this.initializeComponents();
        }
        
        this.isInitialized = true;
    }

    initializeComponents() {
        // Initialize QR-specific components
        this.setupEventListeners();
        this.loadInitialData();
    }

    setupEventListeners() {
        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const sidebar = document.querySelector('.sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        if (mobileMenuBtn && sidebar && mobileOverlay) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                mobileOverlay.classList.toggle('active');
            });

            mobileOverlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            });
        }

        // Chart period buttons
        const chartBtns = document.querySelectorAll('.chart-btn');
        chartBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                chartBtns.forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.updateCharts(e.target.dataset.period);
            });
        });

        // Export functionality
        window.exportAttendance = () => {
            this.exportAttendanceData();
        };
    }

    loadInitialData() {
        // Load any QR-specific initial data
        console.log('QR Dashboard initialized');
    }

    updateCharts(period) {
        // Update charts based on selected period
        console.log('Updating charts for period:', period);
        // Implementation for chart updates would go here
    }

    exportAttendanceData() {
        // Get current filters
        const td = document.getElementById('td-selector')?.value;
        const semester = document.getElementById('semesterFilter')?.value;
        const date = document.getElementById('dateFilter')?.value;

        if (!td || !semester || !date) {
            alert('Please select TD, semester, and date before exporting.');
            return;
        }

        // Create export URL
        const exportUrl = `${this.apiBaseUrl}?action=export_attendance&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}&date=${encodeURIComponent(date)}`;
        
        // Open in new window for download
        window.open(exportUrl, '_blank');
    }

    // Utility method to show notifications
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);

        // Manual close
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        });
    }

    // Method to handle real-time updates
    startRealTimeUpdates() {
        // Set up periodic updates every 30 seconds
        setInterval(() => {
            const refreshBtn = document.getElementById('refresh-btn');
            if (refreshBtn && !refreshBtn.disabled) {
                // Trigger refresh if dashboard is visible
                if (document.visibilityState === 'visible') {
                    refreshBtn.click();
                }
            }
        }, 30000);
    }

    // Method to handle offline/online status
    handleConnectionStatus() {
        window.addEventListener('online', () => {
            this.showNotification('Connection restored', 'success');
        });

        window.addEventListener('offline', () => {
            this.showNotification('Connection lost. Some features may not work.', 'warning');
        });
    }
}

// Initialize QR Dashboard when script loads
const qrDashboard = new QRDashboard();

// Start real-time updates and connection monitoring
qrDashboard.startRealTimeUpdates();
qrDashboard.handleConnectionStatus();

// Add some CSS for notifications if not already present
if (!document.querySelector('#qr-dashboard-styles')) {
    const style = document.createElement('style');
    style.id = 'qr-dashboard-styles';
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-secondary, #1a1a1a);
            border: 1px solid var(--border-primary, #333);
            border-radius: 8px;
            padding: 15px;
            max-width: 300px;
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        }
        
        .notification-info {
            border-left: 4px solid #3498db;
        }
        
        .notification-success {
            border-left: 4px solid #27ae60;
        }
        
        .notification-warning {
            border-left: 4px solid #f39c12;
        }
        
        .notification-error {
            border-left: 4px solid #e74c3c;
        }
        
        .notification-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-primary, #fff);
        }
        
        .notification-close {
            background: none;
            border: none;
            color: var(--text-secondary, #ccc);
            cursor: pointer;
            font-size: 18px;
            margin-left: 10px;
        }
        
        .notification-close:hover {
            color: var(--text-primary, #fff);
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
}

// Export for global access
window.QRDashboard = QRDashboard;
window.qrDashboard = qrDashboard;