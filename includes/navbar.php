<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
        <button class="btn btn-dark me-2" id="sidebarToggleBtn" type="button" title="Toggle sidebar"><i class="fas fa-bars"></i></button>
        <?php endif; ?>
        <a class="navbar-brand" href="/generate%20qr/index.php">🎖️ ROTC Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/generate%20qr/<?php echo $_SESSION['role']; ?>_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/generate%20qr/logout.php">Logout</a>
                    </li>
                    
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/generate%20qr/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/generate%20qr/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/generate%20qr/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
