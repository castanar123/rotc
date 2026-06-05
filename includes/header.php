<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ROTC Portal'; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Military-Grade CSS -->
    <link rel="stylesheet" href="/generate%20qr/css/military-grade.css">
    
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="/generate%20qr/css/mobile-responsive.css">
    
    <?php
    // Conditionally load dashboard CSS
    $dashboard_pages = [
        'admin_dashboard.php', 'user_management.php', 'profile_management.php',
        'instructor_dashboard.php', 'cadet_dashboard.php', 'view_profile.php', 'edit_user.php'
    ];
    $current_page = basename($_SERVER['PHP_SELF']);
    if (in_array($current_page, $dashboard_pages)) {
        echo '<link rel="stylesheet" href="/generate%20qr/css/dashboard.css">';
    }
    ?>
    <style>
        /* --- Collapsible Sidebar --- */
        .dashboard-sidebar {
            transition: width 0.2s ease-in-out;
            overflow-x: hidden;
            width: 250px; /* Default width */
            flex-shrink: 0; /* Prevent sidebar from shrinking */
        }
        .dashboard-container {
            display: flex;
        }
        .dashboard-content {
            flex-grow: 1; /* Allow content to grow */
            transition: margin-left 0.2s ease-in-out;
        }
        body.sidebar-collapsed .dashboard-sidebar {
            width: 0;
        }
        /* --- End Collapsible Sidebar --- */

        /* Additional styles for navbar consistency */
        .navbar {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        .nav-link {
            font-weight: 500;
        }
        body > .container {
             padding-top: 2rem;
             padding-bottom: 2rem;
        }
    </style>
</head>
<body class="<?php echo in_array($current_page, $dashboard_pages) ? 'dashboard-body' : ''; ?>">

<?php
// Always include the navbar at the very top
include 'navbar.php';

// For dashboard pages, create the main flex container that sits *below* the navbar
if (in_array($current_page, $dashboard_pages)) {
    echo '<div class="dashboard-container">'; // New flex container
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        include 'sidebar.php';
    }
    echo '<main class="dashboard-content">';
} else {
    // Original container for non-dashboard pages
    echo '<div class="container">';
}
