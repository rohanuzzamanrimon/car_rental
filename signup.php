<script type="text/javascript">
        var gk_isXlsx = false;
        var gk_xlsxFileLookup = {};
        var gk_fileData = {};
        function filledCell(cell) {
          return cell !== '' && cell != null;
        }
        function loadFileData(filename) {
        if (gk_isXlsx && gk_xlsxFileLookup[filename]) {
            try {
                var workbook = XLSX.read(gk_fileData[filename], { type: 'base64' });
                var firstSheetName = workbook.SheetNames[0];
                var worksheet = workbook.Sheets[firstSheetName];

                // Convert sheet to JSON to filter blank rows
                var jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: false, defval: '' });
                // Filter out blank rows (rows where all cells are empty, null, or undefined)
                var filteredData = jsonData.filter(row => row.some(filledCell));

                // Heuristic to find the header row by ignoring rows with fewer filled cells than the next row
                var headerRowIndex = filteredData.findIndex((row, index) =>
                  row.filter(filledCell).length >= filteredData[index + 1]?.filter(filledCell).length
                );
                // Fallback
                if (headerRowIndex === -1 || headerRowIndex > 25) {
                  headerRowIndex = 0;
                }

                // Convert filtered JSON back to CSV
                var csv = XLSX.utils.aoa_to_sheet(filteredData.slice(headerRowIndex)); // Create a new sheet from filtered array of arrays
                csv = XLSX.utils.sheet_to_csv(csv, { header: 1 });
                return csv;
            } catch (e) {
                console.error(e);
                return "";
            }
        }
        return gk_fileData[filename] || "";
        }
        </script><?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            $conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            $stmt->execute([$email, $hashed_password]);
            header("Location: login.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Email and password are required.";
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxe Drive - Sign Up</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
    <style>
        :root { --dark-bg: #1a1a1a; --muted-bg: #252525; --gold-accent: #d4af37; --text-light: #f0f0f0; --text-muted: #b0b0b0; --shadow: 0 10px 30px rgba(0,0,0,0.5); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Lora', serif; background: var(--dark-bg); color: var(--text-light); line-height: 1.8; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .wrapper { display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; }
        .signup-container { background: var(--muted-bg); padding: 40px; border-radius: 15px; box-shadow: var(--shadow); max-width: 400px; width: 100%; text-align: center; margin: 0 auto; }
        .signup-container h2 { font-family: 'Playfair Display', serif; font-size: 2.5em; color: var(--gold-accent); margin-bottom: 20px; }
        .signup-container p { color: var(--text-muted); margin-bottom: 30px; }
        .signup-form input { width: 100%; padding: 12px; margin-bottom: 20px; background: #333; border: none; border-radius: 5px; color: var(--text-light); }
        .signup-form button { padding: 12px 40px; background: var(--gold-accent); color: var(--dark-bg); border: none; border-radius: 5px; font-size: 1.1em; cursor: pointer; }
        .error { color: #ff4444; margin-top: 10px; display: none; }
        @media (max-width: 768px) { .signup-container { margin: 20px; padding: 30px; } .signup-container h2 { font-size: 2em; } }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="signup-container">
            <h2>Create Account</h2>
            <p>Join to unlock our luxury fleet.</p>
            <form class="signup-form" method="POST" action="">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <div class="error" id="errorMsg"><?php echo $error; ?></div>
                <button type="submit">Sign Up</button>
            </form>
            <div class="signup-link">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </div>
    </div>
    <script>
        gsap.from('.signup-container', { opacity: 0, y: 50, duration: 1.5, ease: 'power4.out' });
        document.addEventListener('DOMContentLoaded', () => {
            const errorMsg = document.getElementById('errorMsg');
            if (errorMsg.textContent.trim()) {
                errorMsg.style.display = 'block';
                gsap.from(errorMsg, { opacity: 0, y: -10, duration: 0.5, ease: 'power2.out' });
            }
        });
    </script>
</body>
</html>