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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
    
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
    </header>
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Redefining Luxury Rentals</h1>
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
    <footer id="contact">
        <p>© 2025 Speedy Cars. All Rights Reserved.</p>
        <p>Contact us: <a href="rohanuzzamanrimon@gmail.com">info@speedycars.com</a> | +1 234 567 890</p>
        <p><a href="#">Facebook</a> | <a href="#">Instagram</a> | <a href="#">Twitter</a></p>
    </footer>
    <script>
        gsap.from('.hero-content h1', { opacity: 0, y: -50, duration: 1.5, ease: 'power4.out' });
        gsap.from('.hero-content p', { opacity: 0, y: 30, duration: 1.5, delay: 0.5, ease: 'power4.out' });
        gsap.from('.cta-btn', { opacity: 0, scale: 0.9, duration: 1, delay: 1, ease: 'back.out(1.7)' });
        gsap.from('.car-card', { opacity: 0, y: 50, duration: 1, stagger: 0.2, scrollTrigger: { trigger: '.fleet-section', start: 'top 80%' } });

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