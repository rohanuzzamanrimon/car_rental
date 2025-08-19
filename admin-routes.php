<?php
require 'db_connect.php'; // This should set up your $conn variable.

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add a new route
    $from = $_POST['route_from'];
    $to = $_POST['route_to'];
    $price = $_POST['price'];
    $vehicle = $_POST['vehicle_type'];
    $stmt = $conn->prepare("INSERT INTO routes (route_from, route_to, price, vehicle_type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$from, $to, $price, $vehicle]);
}

// (Add logic for edit/delete here in future)

$routes = $conn->query("SELECT * FROM routes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Add New Route</h2>
<form method="post">
    <input name="route_from" placeholder="From" required>
    <input name="route_to" placeholder="To" required>
    <input name="price" type="number" placeholder="Price" required>
    <input name="vehicle_type" placeholder="Vehicle Type" required>
    <button type="submit">Add Route</button>
</form>

<h2>All Routes</h2>
<table>
    <tr>
        <th>From</th>
        <th>To</th>
        <th>Price</th>
        <th>Vehicle</th>
    </tr>
    <?php foreach ($routes as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['route_from']) ?></td>
        <td><?= htmlspecialchars($r['route_to']) ?></td>
        <td>$<?= htmlspecialchars($r['price']) ?></td>
        <td><?= htmlspecialchars($r['vehicle_type']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
