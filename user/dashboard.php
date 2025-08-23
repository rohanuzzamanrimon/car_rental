<?php
session_start();
ob_start();

/*if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}*/

$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Build dynamic SQL based on search/filter GET params
$sql = "SELECT * FROM cars WHERE availability = 1";
$params = [];

// Search query (q)
if (!empty($_GET['q'])) {
    $q = '%' . trim($_GET['q']) . '%';
    $sql .= " AND (model LIKE ? OR type LIKE ?)";
    $params[] = $q;
    $params[] = $q;
}

// Type filter (type)
if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
    $sql .= " AND LOWER(type) = LOWER(?)";
    $params[] = $_GET['type'];
}

// Price sort (sort)
$sort = $_GET['sort'] ?? '';
if ($sort === 'high') {
    $sql .= " ORDER BY price DESC";
} else {
    $sql .= " ORDER BY price ASC";
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book'])) {
    if (!isset($_SESSION['user_id'])) {
        // Save intended action and redirect to login
        $_SESSION['redirect_after_login'] = 'dashboard.php';
        header("Location: login.php");
        exit();
    }

    $car_id = $_POST['car_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if (strtotime($end_date) > strtotime($start_date)) {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, car_id, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $car_id, $start_date, $end_date]);
            $stmt = $conn->prepare("UPDATE cars SET booked = 1 WHERE id = ?");
            $stmt->execute([$car_id]);
            $conn->commit();
            $_SESSION['booking_success'] = "Booking confirmed! Car reserved from $start_date to $end_date.";
            header("Location: dashboard.php"); // or replace with your current filename
            exit();

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Booking failed: " . $e->getMessage();
        }
    } else {
        $error = "End date must be after start date.";
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedy Cars - Post-Login Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    
</head>

<body>
    <header>
        <div class="logo"> 
            <img src="logo.png" alt="Speedy Cars Logo">
</div>
        <nav>
            <a href="#home">Home</a>
            <a href="#routes">Routes</a>
            <a href="#contact">Contact</a>
           
            <a href="profile.php">Profile</a>

            <span class="user-welcome">
    <?php if (isset($_SESSION['email'])): ?>
        Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?> <a href="logout.php">Logout</a>
    <?php else: ?>
        <a href="login.php">Login</a>
    <?php endif; ?>
</span>
        </nav>
        <section class="search-section">
    <div class="search-container">
        <!-- Compact Search Icon (Initial State) -->
        <div class="compact-search-wrapper" id="searchWrapper">
            <button class="search-trigger-btn" id="searchTrigger" type="button" aria-label="Open search">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>

            <!-- Expandable Search Container now contains a GET form -->
            <div class="expandable-search-container" id="expandableSearch">
                <form id="searchForm" method="GET" action="dashboard.php" class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="q"
                        class="search-input" 
                        placeholder="Search cars, brands, models..."
                        id="searchInput"
                        value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                    >
                    <button class="search-submit-btn" id="searchSubmit" type="submit" aria-label="Search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </button>

                    <button class="filter-toggle-btn" id="filterToggle" type="button" aria-label="Toggle filters">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"></polygon>
                        </svg>
                    </button>

                    <!-- Hidden inputs for type/sort can be placed here or in filters panel -->
                    <div class="filters-panel" id="filtersPanel">
                        <div class="filter-row">
                            <select name="type" class="filter-select">
                                <option value="">All Types</option>
                                <option value="luxury" <?php if(!empty($_GET['type']) && $_GET['type']=='luxury') echo 'selected'; ?>>Luxury</option>
                                <option value="suv" <?php if(!empty($_GET['type']) && $_GET['type']=='suv') echo 'selected'; ?>>SUV</option>
                                <option value="sedan" <?php if(!empty($_GET['type']) && $_GET['type']=='sedan') echo 'selected'; ?>>Sedan</option>
                                <option value="sports" <?php if(!empty($_GET['type']) && $_GET['type']=='sports') echo 'selected'; ?>>Sports</option>
                                <option value="electric" <?php if(!empty($_GET['type']) && $_GET['type']=='electric') echo 'selected'; ?>>Electric</option>
                            </select>

                            <select name="sort" class="filter-select">
                                <option value="low" <?php if(!empty($_GET['sort']) && $_GET['sort']=='low') echo 'selected'; ?>>Low to High</option>
                                <option value="high" <?php if(!empty($_GET['sort']) && $_GET['sort']=='high') echo 'selected'; ?>>High to Low</option>
                            </select>

                            <button type="button" class="clear-filters-btn" id="clearFiltersBtn">Clear</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

       
       
    </header>
    <section class="hero" id="home">
        <div class="hero-content">
            <h1 class="animated-heading">
  <span class="text-rotation">
    <span class="text">Redefining Luxury Rentals</span>
    <span class="text">Experience Premium Travel</span>
    <span class="text">Drive 4Your Dreams Today</span>
    <span class="text">Luxury at Your Fingertips</span>
  </span>
</h1>

            <p>Elevate your journey with our handpicked collection of extraordinary vehicles.</p>
            <button class="cta-btn">Discover Now</button>
        </div>
    </section>
    <section class="fleet-section" id="fleet">
        <h2>Our Exquisite Fleet</h2>
        <form class="filter-bar" method="GET" action="dashboard.php" style="display:flex;gap:12px;margin-bottom:24px;">
    <select onchange="this.form.submit()" name="type">
        <option value="all" <?php if(empty($_GET['type']) || $_GET['type']=='all') echo 'selected'; ?>>All Vehicles</option>
        <option value="sedan" <?php if(!empty($_GET['type']) && $_GET['type']=='sedan') echo 'selected'; ?>>Sedan</option>
        <option value="suv" <?php if(!empty($_GET['type']) && $_GET['type']=='suv') echo 'selected'; ?>>SUV</option>
        <option value="electric" <?php if(!empty($_GET['type']) && $_GET['type']=='electric') echo 'selected'; ?>>Electric</option>
    </select>
    <select onchange="this.form.submit()" name="sort">
        <option value="low" <?php if(empty($_GET['sort']) || $_GET['sort']=='low') echo 'selected'; ?>>Low to High</option>
        <option value="high" <?php if(!empty($_GET['sort']) && $_GET['sort']=='high') echo 'selected'; ?>>High to Low</option>
    </select>
</form>
        <div class="car-grid">
            <?php
            foreach ($cars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo htmlspecialchars($car['image'] ?? 'default-car.jpg'); ?>" alt="<?php echo htmlspecialchars($car['model']); ?>">
                    <div class="car-card-content">
                        <h3><a href="car-detail.php?id=<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['model']); ?></a></h3>
                        <p>৳<?php echo htmlspecialchars($car['price']); ?>/day</p>
                        <button class="reserve-btn" data-car-id="<?php echo $car['id']; ?>">Reserve Now</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <!-- Add this after the closing </div> of hero section -->


    <!-- Booking Modal -->
    <div class="modal" id="bookingModal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h3>Book Your Car</h3>
            <form method="POST" action="">
                <input type="hidden" name="car_id" id="carIdInput">
                <label for="start_date" style="display:block; text-align:left; color: var(--text-light); margin-bottom: 5px;">Start Date</label>
<input type="date" id="start_date" name="start_date" required>

<label for="end_date" style="display:block; text-align:left; color: var(--text-light); margin: 15px 0 5px;">End Date</label>
<input type="date" id="end_date" name="end_date" required>

                <button type="submit" name="book">Confirm Booking</button>
                <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
            </form>
        </div>
    </div>
    <?php
// Fetch all routes from your database (make sure $conn is your PDO connection)
$stmt = $conn->query("SELECT * FROM routes ORDER BY id DESC");
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="routes-section" id="routes">
  <div class="routes-header">
    <h2>Popular Routes</h2>
    <p>Plan your trip with ease. See cost estimates and book instantly for stress-free travel!</p>
  </div>
  <div class="routes-list">
    <?php foreach ($routes as $route): ?>
    <div class="route-card">
      <span class="route-icon">🚗</span>
      <span class="route-from"><?php echo htmlspecialchars($route['route_from']); ?></span>
      <span class="route-to">→ <?php echo htmlspecialchars($route['route_to']); ?></span>
      <span class="route-price">Starts from ৳<?php echo htmlspecialchars($route['price']); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>
    <footer id="contact">
        <p>© 2025 Speedy Cars. All Rights Reserved.</p>
        <p>Contact us: <a href="rohanuzzamanrimon@gmail.com">info@speedycars.com</a> | +1 234 567 890</p>
        <p><a href="#">Facebook</a> | <a href="#">Instagram</a> | <a href="#">Twitter</a></p>
    </footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchTrigger = document.getElementById('searchTrigger');
    const expandableSearch = document.getElementById('expandableSearch');
    const searchInput = document.getElementById('searchInput');
    const filterToggle = document.getElementById('filterToggle');
    const filtersPanel = document.getElementById('filtersPanel');
    const searchForm = document.getElementById('searchForm');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');

    function openSearch() {
        if (!expandableSearch) return;
        expandableSearch.classList.add('active');
        searchTrigger && searchTrigger.setAttribute('aria-expanded', 'true');
        // small timeout to wait for any CSS transition then focus
        setTimeout(() => searchInput && searchInput.focus(), 150);
    }

    function closeSearch() {
        if (!expandableSearch) return;
        expandableSearch.classList.remove('active');
        filtersPanel && filtersPanel.classList.remove('active');
        searchTrigger && searchTrigger.setAttribute('aria-expanded', 'false');
    }

    // Toggle expandable search
    if (searchTrigger) {
        searchTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (expandableSearch.classList.contains('active')) {
                closeSearch();
            } else {
                openSearch();
            }
        });
    }

    // Toggle filters panel inside search
    if (filterToggle) {
        filterToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            filtersPanel && filtersPanel.classList.toggle('active');
        });
    }

    // Click outside closes panels
    document.addEventListener('click', function (e) {
        if (!expandableSearch) return;
        if (!expandableSearch.contains(e.target) && !searchTrigger.contains(e.target)) {
            closeSearch();
        }
    });

    // Esc closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSearch();
    });

    // Clear filters button -> reset and submit to show all
    if (clearFiltersBtn && searchForm) {
        clearFiltersBtn.addEventListener('click', function (e) {
            e.preventDefault();
            searchForm.querySelectorAll('input[type="text"], select').forEach(i => i.value = '');
            searchForm.submit();
        });
    }

    // Let the form submit normally (GET) so PHP handles the search.

    // Modal logic for Reserve Now
    const reserveBtns = document.querySelectorAll('.reserve-btn');
    const bookingModal = document.getElementById('bookingModal');
    const closeModalBtn = document.querySelector('.close-modal');
    const carIdInput = document.getElementById('carIdInput');

    reserveBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const carId = btn.getAttribute('data-car-id');
            if (carIdInput) carIdInput.value = carId;
            if (bookingModal) bookingModal.style.display = 'block';
        });
    });

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function () {
            if (bookingModal) bookingModal.style.display = 'none';
        });
    }

    // Optional: Close modal when clicking outside modal content
    window.onclick = function(event) {
        if (event.target === bookingModal) {
            bookingModal.style.display = "none";
        }
    };
});
</script>