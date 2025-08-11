<?php
session_start();
ob_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php") ;
    exit();
}

$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$car_id = $_GET['id'] ?? null;
$car = null;
if ($car_id) {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedy Cars - Car Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark-bg: #1a1a1a;
            --gold-accent: #d4af37;
            --text-light: #f0f0f0;
            --text-muted: #b0b0b0;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        body { font-family: 'Lora', serif; background: var(--dark-bg); color: var(--text-light); padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; text-align: center; }
        h1 { font-family: 'Playfair Display', serif; color: var(--gold-accent); font-size: 2.5em; margin-bottom: 20px; }
        img { width: 100%; height: 400px; object-fit: cover; border-radius: 10px; margin-bottom: 20px; }
        p { color: var(--text-muted); line-height: 1.6; margin-bottom: 15px; }
        .back-btn { padding: 10px 30px; background: var(--gold-accent); color: var(--dark-bg); border: none; border-radius: 5px; font-size: 1em; cursor: pointer; transition: all 0.3s ease; }
        .back-btn:hover { background: #b8962e; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($car): ?>
            <h1><?php echo htmlspecialchars($car['model']); ?></h1>
            <img src="<?php echo htmlspecialchars($car['image'] ?? 'default-car.jpg'); ?>" alt="<?php echo htmlspecialchars($car['model']); ?>">
            <p>Price: $<?php echo htmlspecialchars($car['price']); ?>/day</p>
            <p>Type: <?php echo htmlspecialchars($car['type']); ?></p>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        <?php else: ?>
            <p>Car not found.</p>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>