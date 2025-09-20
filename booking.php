<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch user info from users table
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user['full_name'] ?? '';
$user_email = $user['email'] ?? '';
$user_phone = $user['contact_info'] ?? '';

// --- Helper: Fetch locations from routes table ---
$locations = [];
$stmt = $conn->query("SELECT DISTINCT route_from FROM routes WHERE is_active=1");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $locations[] = $row['route_from'];
}
$stmt2 = $conn->query("SELECT DISTINCT route_to FROM routes WHERE is_active=1");
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    if (!in_array($row['route_to'], $locations)) $locations[] = $row['route_to'];
}

// --- Helper: Fetch car info ---
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
$car = null;
if ($car_id) {
    $car_stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $car_stmt->execute([$car_id]);
    $car = $car_stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Handle Booking Submission ---
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Validate Inputs ---
    $car_id = intval($_POST['car_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $pickup = $_POST['pickup_location'] ?? '';
    $dropoff = $_POST['dropoff_location'] ?? '';
    $full_name = $user_name;
    $email = $user_email;
    $phone = trim($_POST['phone'] ?? $user_phone);
    $driver_name = trim($_POST['driver_name'] ?? '');
    $driver_dob = $_POST['driver_dob'] ?? '';
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expiry = $_POST['license_expiry'] ?? '';
    $addons = isset($_POST['addons']) ? $_POST['addons'] : [];
    $details = [
        'driver_name' => $driver_name,
        'driver_dob' => $driver_dob,
        'license_number' => $license_number,
        'license_expiry' => $license_expiry,
        'addons' => $addons
    ];

    // --- Validate Required Fields ---
    if (!$car_id) $errors[] = "Please select a car.";
    if (!$start_date || !$start_time) $errors[] = "Please select a valid start date and time.";
    if (!$end_date || !$end_time) $errors[] = "Please select a valid end date and time.";
    if (!$pickup) $errors[] = "Please select a pickup location.";
    if (!$dropoff) $errors[] = "Please select a drop-off location.";
    if (!$full_name) $errors[] = "Full name is required.";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (!$phone) $errors[] = "Phone number is required.";

    // --- Validate Dates ---
    $start_dt = strtotime("$start_date $start_time");
    $end_dt = strtotime("$end_date $end_time");
    if ($start_dt < strtotime(date('Y-m-d H:i'))) $errors[] = "Start date/time cannot be in the past.";
    if ($end_dt <= $start_dt) $errors[] = "End date/time must be after start date/time.";

    // --- Check Car Availability (no overlapping bookings) ---
    if (!$errors) {
        $overlap_stmt = $conn->prepare(
            "SELECT COUNT(*) FROM bookings 
             WHERE car_id = ? AND status IN ('pending','confirmed')
             AND (
                 (start_date <= ? AND end_date >= ?) OR
                 (start_date <= ? AND end_date >= ?) OR
                 (start_date >= ? AND end_date <= ?)
             )"
        );
        $overlap_stmt->execute([
            $car_id,
            "$end_date $end_time", "$end_date $end_time",
            "$start_date $start_time", "$start_date $start_time",
            "$start_date $start_time", "$end_date $end_time"
        ]);
        $overlaps = $overlap_stmt->fetchColumn();
        if ($overlaps > 0) $errors[] = "This car is not available for the selected dates.";
    }

    // --- Calculate Price ---
    $price = 0;
    if ($car) {
        $hours = ($end_dt - $start_dt) / 3600;
        $days = ceil($hours / 24);
        $base_price = $car['price'] ?? 0;
        $price = $days * $base_price;
        // Add-ons (example: +100 per addon)
        $price += count($addons) * 100;
    }

    // --- Save Booking ---
    if (!$errors) {
        $details_json = json_encode($details);
        $insert_stmt = $conn->prepare(
            "INSERT INTO bookings 
            (user_id, car_id, start_date, end_date, pickup_location, dropoff_location, full_name, email, phone, details, price, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $insert_stmt->execute([
            $user_id,
            $car_id,
            "$start_date $start_time",
            "$end_date $end_time",
            $pickup,
            $dropoff,
            $full_name,
            $email,
            $phone,
            $details_json,
            $price
        ]);
        $success = "Booking submitted! Your reservation is pending confirmation.";
        // (Optional) Send confirmation email here
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book a Car | Modern Car Rental</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Use your main style.css for consistent aesthetics -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/car_rental/user/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Booking Page Specific Styles */
        .booking-wrapper {
            max-width: 700px;
            margin: 48px auto;
            background: linear-gradient(120deg, rgba(212,175,55,0.08) 0%, var(--muted-bg, #181818) 100%);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(212,175,55,0.13);
            padding: 40px 32px;
            animation: fadeInUp 0.8s;
        }
        .modern-booking-form {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }
        .form-section {
            margin-bottom: 24px;
        }
        .form-section h2 {
            font-family: 'Playfair Display', serif;
            color: var(--gold-accent, #d4af37);
            font-size: 1.5em;
            margin-bottom: 18px;
            letter-spacing: 0.03em;
            text-shadow: 0 2px 12px rgba(212,175,55,0.13);
        }
        .flex-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .flex-row > div {
            flex: 1;
            min-width: 180px;
        }
        label {
            color: var(--text-light, #eee);
            font-size: 1em;
            margin-bottom: 6px;
            font-weight: 500;
            display: block;
        }
        input, select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid rgba(212,175,55,0.18);
            background: var(--dark-bg, #111);
            color: var(--text-light, #eee);
            font-size: 1em;
            margin-bottom: 12px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus {
            border-color: var(--gold-accent, #d4af37);
            box-shadow: 0 0 8px rgba(212,175,55,0.13);
            outline: none;
        }
        input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            accent-color: var(--gold-accent, #d4af37);
        }
        .addons-row {
            display: flex;
            gap: 24px;
            margin-bottom: 12px;
        }
        .addons-row label {
            font-weight: 400;
            font-size: 1em;
            color: var(--text-muted, #aaa);
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cta-btn, .modern-booking-form button[type="submit"] {
            background: var(--gold-accent, #d4af37);
            color: var(--dark-bg, #181818);
            border: none;
            border-radius: 10px;
            padding: 14px 36px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.13s;
            margin-top: 10px;
            box-shadow: 0 2px 15px rgba(212,175,55,0.12);
        }
        .cta-btn:hover, .modern-booking-form button[type="submit"]:hover {
            background: #b8962e;
            transform: scale(1.04) translateY(-2px);
            box-shadow: 0 4px 22px var(--gold-accent, #d4af37);
        }
        .summary-block, #price-summary {
            background: var(--dark-bg, #111);
            border-radius: 12px;
            padding: 18px;
            margin-top: 18px;
            color: var(--gold-accent, #d4af37);
            font-size: 1.1em;
            text-align: center;
            box-shadow: 0 2px 10px rgba(212,175,55,0.07);
        }
        .modern-card {
            background: rgba(212,175,55,0.07);
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 10px;
            color: var(--text-light, #eee);
        }
        #form-messages {
            margin-top: 12px;
            color: #e74c3c;
            font-weight: 600;
            text-align: center;
        }
        .success {
            color: #4caf50;
            background: rgba(76,175,80,0.08);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        .error {
            color: #ff4444;
            background: rgba(255,68,68,0.08);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        input[type="file"] {
            background: var(--muted-bg, #181818);
            color: var(--text-light, #eee);
            border: none;
            padding: 8px;
            border-radius: 8px;
        }
        @media (max-width: 600px) {
            .booking-wrapper {
                padding: 16px 4px;
            }
            .flex-row {
                flex-direction: column;
                gap: 10px;
            }
            .form-section h2 {
                font-size: 1.15em;
            }
            .cta-btn, .modern-booking-form button[type="submit"] {
                padding: 12px 18px;
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <div class="booking-wrapper">
        <form class="modern-booking-form" method="POST" autocomplete="off">
            <div class="form-section">
                <h2><i class="fas fa-car"></i> Choose Your Car</h2>
                <div class="flex-row">
                    <div>
                        <label for="car_id">Car</label>
                        <select name="car_id" id="car_id" required>
                            <option value="">Select a car</option>
                            <?php
                            $car_stmt = $conn->query("SELECT * FROM cars WHERE availability=1");
                            while ($row = $car_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($car_id && $car_id == $row['id']) ? 'selected' : '';
                                echo "<option value=\"{$row['id']}\" $selected>{$row['brand']} {$row['model']} ({$row['year']}) - ৳{$row['price']}/day</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="start_date">Start Date & Time</label>
                        <input type="date" name="start_date" id="start_date" required min="<?= date('Y-m-d') ?>">
                        <input type="time" name="start_time" id="start_time" required>
                    </div>
                    <div>
                        <label for="end_date">End Date & Time</label>
                        <input type="date" name="end_date" id="end_date" required min="<?= date('Y-m-d') ?>">
                        <input type="time" name="end_time" id="end_time" required>
                    </div>
                </div>
                <div class="flex-row">
                    <div>
                        <label for="pickup_location">Pickup Location</label>
                        <select name="pickup_location" id="pickup_location" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="dropoff_location">Drop-off Location</label>
                        <select name="dropoff_location" id="dropoff_location" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h2><i class="fas fa-user"></i> Contact Information</h2>
                <div class="flex-row">
                    <div>
                        <label>Full Name</label>
                        <div class="modern-card"><?= htmlspecialchars($user_name) ?></div>
                    </div>
                    <div>
                        <label>Email</label>
                        <div class="modern-card"><?= htmlspecialchars($user_email) ?></div>
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" required value="<?= htmlspecialchars($user_phone) ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-plus-circle"></i> Add-ons</h2>
                <div class="addons-row">
                    <label><input type="checkbox" name="addons[]" value="GPS"> GPS</label>
                    <label><input type="checkbox" name="addons[]" value="Child Seat"> Child Seat</label>
                    <label><input type="checkbox" name="addons[]" value="Insurance"> Insurance</label>
                </div>
            </div>
            <div class="form-section summary-block">
                <h2><i class="fas fa-receipt"></i> Booking Summary</h2>
                <div id="price-summary">
                    <?php if ($car): ?>
                        <strong>Car:</strong> <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?><br>
                        <strong>Price per day:</strong> ৳<?= htmlspecialchars($car['price']) ?><br>
                    <?php endif; ?>
                    <span id="summary-details"></span>
                </div>
                <div id="form-messages">
                    <?php foreach ($errors as $err): ?>
                        <div class="error"><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                    <?php if ($success): ?>
                        <div class="success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="cta-btn">Confirm Booking</button>
            </div>
        </form>
    </div>
    <script src="https://kit.fontawesome.com/2c36e9b7b1.js" crossorigin="anonymous"></script>
    <script>
    // Live price calculation and summary
    document.addEventListener('DOMContentLoaded', function() {
        function updateSummary() {
            const carSelect = document.getElementById('car_id');
            const startDate = document.getElementById('start_date').value;
            const startTime = document.getElementById('start_time').value;
            const endDate = document.getElementById('end_date').value;
            const endTime = document.getElementById('end_time').value;
            const addons = Array.from(document.querySelectorAll('input[name="addons[]"]:checked')).map(i => i.value);

            let summary = '';
            let price = 0;
            let days = 0;

            if (carSelect.value && startDate && startTime && endDate && endTime) {
                const carOption = carSelect.options[carSelect.selectedIndex].text;
                summary += `<strong>Selected Car:</strong> ${carOption}<br>`;
                const start = new Date(startDate + 'T' + startTime);
                const end = new Date(endDate + 'T' + endTime);
                const ms = end - start;
                days = Math.ceil(ms / (1000 * 60 * 60 * 24));
                if (days > 0) {
                    summary += `<strong>Duration:</strong> ${days} day(s)<br>`;
                    // Extract price from option text
                    const priceMatch = carOption.match(/৳(\d+)/);
                    if (priceMatch) {
                        price = parseInt(priceMatch[1]) * days;
                        summary += `<strong>Base Price:</strong> ৳${price}<br>`;
                    }
                    if (addons.length) {
                        summary += `<strong>Add-ons:</strong> ${addons.join(', ')}<br>`;
                        price += addons.length * 100;
                        summary += `<strong>Add-on Fee:</strong> ৳${addons.length * 100}<br>`;
                    }
                    summary += `<strong>Total:</strong> <span style="color:#d4af37;">৳${price}</span>`;
                }
            } else {
                summary = "Select car and dates for price estimate.";
            }
            document.getElementById('summary-details').innerHTML = summary;
        }

        document.querySelectorAll('#car_id, #start_date, #start_time, #end_date, #end_time, input[name="addons[]"]').forEach(el => {
            el.addEventListener('change', updateSummary);
        });
        updateSummary();

        // Auto-select car if car_id is in URL
        const urlParams = new URLSearchParams(window.location.search);
        const carId = urlParams.get('car_id');
        if (carId) {
            document.getElementById('car_id').value = carId;
            updateSummary();
        }
    });
    </script>
</body>
</html>