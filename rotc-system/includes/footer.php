<?php
// Conditionally close the main content wrapper
$dashboard_pages = [
    'admin_dashboard.php', 'user_management.php', 'profile_management.php',
    'instructor_dashboard.php', 'cadet_dashboard.php', 'view_profile.php', 'edit_user.php',
    'my_platoons.php', 'announcements.php', 'my_profile.php', 'schedule.php'
];
$current_page = basename($_SERVER['PHP_SELF']);

if (in_array($current_page, $dashboard_pages)) {
    echo '</main>'; // Closes .dashboard-content
    echo '</div>'; // Closes .dashboard-container
} else {
    echo '</div>'; // Closes .container
}
?>

<footer class="bg-dark text-white text-center p-3 mt-4">
    <p>&copy; <?php echo date('Y'); ?> ROTC Management Portal. All Rights Reserved.</p>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Collapsible Sidebar Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const body = document.body;

    // Check for saved preference in localStorage
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
    }

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            body.classList.toggle('sidebar-collapsed');
            // Save the preference to localStorage
            localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
        });
    }
});
</script>
</body>
</html>
