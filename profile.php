<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Connect to database
$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get user’s current email
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set up message variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $new_password = $_POST['password'];

    // Check if email is valid
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $update_email = false;
        $update_password = false;

        // Check if email changed and is already taken
        if ($new_email != $user['email']) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$new_email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already in use.";
            } else {
                $update_email = true;
            }
        }

        // Check if new password is provided
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password = true;
        }

        // Update database if no errors
        if (!$error) {
            if ($update_email && $update_password) {
                $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                $stmt->execute([$new_email, $hashed_password, $user_id]);
            } elseif ($update_email) {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$new_email, $user_id]);
            } elseif ($update_password) {
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
            if ($update_email || $update_password) {
                $success = "Profile updated successfully.";
                if ($update_email) {
                    $_SESSION['email'] = $new_email;
                }
            } else {
                $error = "No changes were made.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Profile</title>
   
        
</head>
<body>
    
<div class="profile-wrapper">
    <div class="profile-container">
        <h2>Profile Settings</h2>
        <p class="subtitle">Update your account information</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="profile-form">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            
            <label for="password">New Password (leave blank to keep current)</label>
            <input type="password" id="password" name="password" placeholder="Enter new password">
            
            <button type="submit">Update Profile</button>
        </form>
        
        <div class="profile-navigation">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="my_bookings.php">My Bookings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

</body>
</html>