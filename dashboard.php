<?php
session_start();
ob_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $conn->prepare("SELECT * FROM cars WHERE availability = 1");
$stmt->execute();
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book'])) {
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
            <a href="#fleet">Fleet</a>
            <a href="#contact">Contact</a>
           
            <a href="profile.php">Profile</a>

            <span class="user-welcome">Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?> <a href="logout.php">Logout</a></span>
        </nav>
        <div class="search-section">
    <div class="search-container">
       
        <div class="search-bar">
            <input type="text" id="carSearch" placeholder="Search by car model, type, or price range...">
            <button type="button" id="clearSearch">Clear</button>
        </div>
        <div class="search-filters">
            <select id="typeFilter">
                <option value="">All Types</option>
                <option value="Sedan">Sedan</option>
                <option value="SUV">SUV</option>
                <option value="Sports">Sports</option>
                <option value="Luxury">Luxury</option>
            </select>
            <select id="priceFilter">
                <option value="">All Prices</option>
                <option value="0-100">$0 - $100</option>
                <option value="101-200">$101 - $200</option>
                <option value="201-500">$201 - $500</option>
                <option value="500+">$500+</option>
            </select>
        </div>
    </div>
</div>
    </header>
    <section class="hero" id="home">
        <div class="hero-content">
            <h1 class="animated-heading">
  <span class="text-rotation">
    <span class="text">Redefining Luxury Rentals</span>
    <span class="text">Experience Premium Travel</span>
    <span class="text">Drive Your Dreams Today</span>
    <span class="text">Luxury at Your Fingertips</span>
  </span>
</h1>

            <p>Elevate your journey with our handpicked collection of extraordinary vehicles.</p>
            <button class="cta-btn">Discover Now</button>
        </div>
    </section>
    <section class="fleet-section" id="fleet">
        <h2>Our Exquisite Fleet</h2>
        <div class="filter-bar">
            <select onchange="this.form.submit()" name="type">
                <option value="all">All Vehicles</option>
                <option value="sedan">Sedan</option>
                <option value="suv">SUV</option>
                <option value="electric">Electric</option>
            </select>
            <select onchange="this.form.submit()" name="sort">
                <option value="low">Low to High</option>
                <option value="high">High to Low</option>
            </select>
        </div>
        <form method="POST" style="display:none;">
            <input type="submit" name="filter">
        </form>
        <div class="car-grid">
            <?php
            $sql = "SELECT * FROM cars WHERE availability = 1";
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['filter'])) {
                $type = $_POST['type'] ?? 'all';
                $sort = $_POST['sort'] ?? 'low';
                if ($type != 'all') $sql .= " AND type = ?";
                if ($sort == 'high') $sql .= " ORDER BY price DESC";
                else $sql .= " ORDER BY price ASC";
                $stmt = $conn->prepare($sql);
                $stmt->execute($type != 'all' ? [$type] : []);
                $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($cars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo htmlspecialchars($car['image'] ?? 'default-car.jpg'); ?>" alt="<?php echo htmlspecialchars($car['model']); ?>">
                    <div class="car-card-content">
                        <h3><a href="car-detail.php?id=<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['model']); ?></a></h3>
                        <p>$<?php echo htmlspecialchars($car['price']); ?>/day</p>
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

<section class="routes-section">
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
      <span class="route-price">Starts from $<?php echo htmlspecialchars($route['price']); ?></span>
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
        
// Wait for the page to load completely
document.addEventListener('DOMContentLoaded', function() {
    
    // Get references to our search elements
    const searchInput = document.getElementById('carSearch');
    const clearButton = document.getElementById('clearSearch');
    const typeFilter = document.getElementById('typeFilter');
    const priceFilter = document.getElementById('priceFilter');
    const carCards = document.querySelectorAll('.car-card');
    
    // Function to filter cars based on search criteria
    function filterCars() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedType = typeFilter.value.toLowerCase();
        const selectedPriceRange = priceFilter.value;
        
        carCards.forEach(card => {
            // Get car details from the card
            const carModel = card.querySelector('h3 a').textContent.toLowerCase();
            const carType = card.querySelector('p').textContent.toLowerCase();
            const carPrice = parseFloat(card.querySelector('p:nth-child(2)').textContent.match(/\$(\d+)/)[1]);
            
            // Check if car matches search criteria
            let matchesSearch = true;
            let matchesType = true;
            let matchesPrice = true;
            
            // Search term matching
            if (searchTerm) {
                matchesSearch = carModel.includes(searchTerm) || carType.includes(searchTerm);
            }
            
            // Type filter matching
            if (selectedType) {
                matchesType = carType.includes(selectedType);
            }
            
            // Price filter matching
            if (selectedPriceRange) {
                if (selectedPriceRange === '0-100') {
                    matchesPrice = carPrice >= 0 && carPrice <= 100;
                } else if (selectedPriceRange === '101-200') {
                    matchesPrice = carPrice >= 101 && carPrice <= 200;
                } else if (selectedPriceRange === '201-500') {
                    matchesPrice = carPrice >= 201 && carPrice <= 500;
                } else if (selectedPriceRange === '500+') {
                    matchesPrice = carPrice > 500;
                }
            }
            
            // Show or hide the car card based on all criteria
            if (matchesSearch && matchesType && matchesPrice) {
                card.style.display = 'block';
                // Add a smooth fade-in effect
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.opacity = '1';
                }, 100);
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Add event listeners for real-time search
    searchInput.addEventListener('input', filterCars);
    typeFilter.addEventListener('change', filterCars);
    priceFilter.addEventListener('change', filterCars);
    
    // Clear search functionality
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        typeFilter.value = '';
        priceFilter.value = '';
        filterCars(); // Reset all filters
    });
});


        gsap.from('.hero-content h1', { opacity: 0, y: -50, duration: 1.5, ease: 'power4.out' });
        gsap.from('.hero-content p', { opacity: 0, y: 30, duration: 1.5, delay: 0.5, ease: 'power4.out' });
        gsap.from('.cta-btn', { opacity: 0, scale: 0.9, duration: 1, delay: 1, ease: 'back.out(1.7)' });
        gsap.from('.car-card', { opacity: 0, y: 50, duration: 1, stagger: 0.2, scrollTrigger: { trigger: '.fleet-section', start: 'top 80%' } });
        
        // Modal code stays the same
        const modal = document.getElementById('bookingModal');
        const closeModal = document.querySelector('.close-modal');
        const carIdInput = document.getElementById('carIdInput');

        document.querySelectorAll('.reserve-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const carId = btn.getAttribute('data-car-id');
                carIdInput.value = carId;
                modal.style.display = 'flex';
                gsap.from('.modal-content', { opacity: 0, y: -50, duration: 0.5, ease: 'power3.out' });
            });
        });

        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    </script>
</body>
</html>