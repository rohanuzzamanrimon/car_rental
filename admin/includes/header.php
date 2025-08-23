<header class="admin-header">
    <div class="admin-nav-container">
        <div class="admin-logo">
            <h2>Car Rental Admin</h2>
        </div>
        
        <nav class="admin-nav">
            <a href="dashboard.php" class="nav-link">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="cars/index.php" class="nav-link">
                <span class="nav-icon">🚗</span> Cars
            </a>
            <a href="bookings/index.php" class="nav-link">
                <span class="nav-icon">📅</span> Bookings
            </a>
            <a href="routes/index.php" class="nav-link">
                <span class="nav-icon">🛣️</span> Routes
            </a>
            <a href="users/index.php" class="nav-link">
                <span class="nav-icon">👥</span> Users
            </a>
        </nav>
        
        <div class="admin-user-menu">
            <span class="admin-user-name"><?php echo htmlspecialchars(getAdminName()); ?></span>
            <a href="../user/dashboard.php" class="view-site-btn" target="_blank">View Site</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
