<?php
// Determine the current page to set the 'active' class
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

$role = $_SESSION['role'] ?? 'cadet';

$menu_items = [];

// Define menu items based on role
if ($role === 'admin') {
    $menu_items = [
        'admin_dashboard.php' => ['icon' => 'fa-tachometer-alt', 'text' => 'Dashboard'],
        'user_management.php' => ['icon' => 'fa-users-cog', 'text' => 'User Management'],
        'profile_management.php' => ['icon' => 'fa-id-card', 'text' => 'Cadet Profiles'],
        'attendance' => [
            'icon' => 'fa-qrcode',
            'text' => 'Attendance',
            'sub_items' => [
                'scan.php' => 'Scan QR',
                'logs.php' => 'View Logs'
            ]
        ],
        'announcements.php' => ['icon' => 'fa-bullhorn', 'text' => 'Announcements'],
        'display_qr.php' => ['icon' => 'fa-qrcode', 'text' => 'Scanner QR Code'],

    ];
} elseif ($role === 'instructor') {
    $menu_items = [
        'instructor_dashboard.php' => ['icon' => 'fa-tachometer-alt', 'text' => 'Dashboard'],
        'my_platoons.php' => ['icon' => 'fa-users', 'text' => 'My Platoons'],
        'attendance' => [
            'icon' => 'fa-qrcode',
            'text' => 'Attendance',
            'sub_items' => [
                'scan.php' => 'Scan QR',
                'logs.php' => 'View Logs'
            ]
        ],
        'announcements.php' => ['icon' => 'fa-bullhorn', 'text' => 'Announcements'],
        'display_qr.php' => ['icon' => 'fa-qrcode', 'text' => 'Scanner QR Code'],
    ];
} else { // Cadet
    $menu_items = [
        'cadet_dashboard.php' => ['icon' => 'fa-tachometer-alt', 'text' => 'My Dashboard'],
        'my_profile.php' => ['icon' => 'fa-user-circle', 'text' => 'My Profile'],
        'schedule.php' => ['icon' => 'fa-calendar-alt', 'text' => 'My Schedule'],
    ];
}

?>

<div class="dashboard-sidebar">
    <ul class="sidebar-menu">
        <?php foreach ($menu_items as $key => $item): ?>
            <?php if (isset($item['sub_items'])): ?>
                <?php $is_active = ($current_dir == $key); ?>
                <li class="nav-item-dropdown <?php echo $is_active ? 'open' : ''; ?>">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas <?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['text']; ?></span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="dropdown-menu" style="display: <?php echo $is_active ? 'block' : 'none'; ?>;">
                        <?php foreach ($item['sub_items'] as $sub_url => $sub_text): ?>
                            <li>
                                <a href="/rotc-system/<?php echo $key . '/' . $sub_url; ?>" class="<?php echo ($current_page == $sub_url) ? 'active-sub' : ''; ?>">
                                   <i class="fas fa-minus fa-xs"></i> <?php echo $sub_text; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php else: ?>
                 <li>
                    <a href="/rotc-system/<?php echo $key; ?>" class="<?php echo ($current_page == $key) ? 'active' : ''; ?>">
                        <i class="fas <?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['text']; ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropdowns = document.querySelectorAll('.nav-item-dropdown .dropdown-toggle');
    dropdowns.forEach(function(dropdown) {
        dropdown.addEventListener('click', function(event) {
            event.preventDefault();
            var parentLi = this.parentElement;
            parentLi.classList.toggle('open');
            var menu = parentLi.querySelector('.dropdown-menu');
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }
        });
    });
});
</script>
