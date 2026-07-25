<?php
// Start session with secure settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

// =========================
// DATABASE CONNECTION
// =========================
$conn = new mysqli("localhost", "root", "", "infotag_bot");

if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("System temporarily unavailable. Please try again later.");
}
$conn->set_charset("utf8mb4");

// =========================
// REDIRECT IF ALREADY LOGIN
// =========================
if (isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true) {
    header("Location: dashboard.php");
    exit();
}

// =========================
// VARIABLES & RATE LIMITING
// =========================
$error = "";
$success = "";

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// =========================
// LOGIN SYSTEM
// =========================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_time = time();
    $time_diff = $current_time - $_SESSION['last_attempt_time'];

    if ($_SESSION['login_attempts'] >= 5 && $time_diff < 300) {
        $remaining = ceil((300 - $time_diff) / 60);
        $error = "Too many login attempts. Try again after {$remaining} minute(s).";
    } else {
        if ($time_diff >= 300) {
            $_SESSION['login_attempts'] = 0;
        }

        $admin_id = trim($_POST['admin_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($admin_id) || empty($password)) {
            $error = "Please enter Admin ID and Password";
        } else {
            $sql = "SELECT admin_id, password FROM admins WHERE admin_id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                error_log($conn->error);
                $error = "System error";
            } else {
                $stmt->bind_param("s", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $admin = $result->fetch_assoc();
                    if (password_verify($password, $admin['password'])) {
                        $_SESSION['login_attempts'] = 0;
                        session_regenerate_id(true);
                        $_SESSION['admin_login'] = true;
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_name'] = $admin['admin_id'];
                        $_SESSION['login_time'] = time();
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid credentials";
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                    }
                } else {
                    $error = "Invalid credentials";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>InfoTag · Secure Admin Access</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet" />
</head>
<body>

<div class="glass-master">

    <!-- ===== FORM SIDE ===== -->
    <div class="form-panel">
        <div class="brand-block">
            <div class="brand-icon"><i class="fas fa-robot"></i></div>
            <span class="brand-text">InfoTag</span>
            <span class="brand-sub">v2.4</span>
        </div>

        <div class="greeting">
            <h1>Welcome back</h1>
            <p>Secure access to the <span class="highlight">Bot Engine</span> · enter your credentials</p>
        </div>

        <!-- error alert (PHP) -->
        <?php if (!empty($error)): ?>
        <div class="alert-modern" id="errorAlert">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
            <button class="close-alert" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">
            <div class="field-group">
                <label class="field-label" for="admin_id">
                    <i class="fas fa-id-badge"></i> Admin ID
                </label>
                <div class="input-shell">
                    <i class="fas fa-user prefix"></i>
                    <input type="text" name="admin_id" id="admin_id" placeholder="admin_01" 
                           value="<?= isset($_POST['admin_id']) ? htmlspecialchars($_POST['admin_id']) : '' ?>" required autofocus>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label" for="password">
                    <i class="fas fa-key"></i> Password
                </label>
                <div class="input-shell">
                    <i class="fas fa-lock prefix"></i>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <button type="button" class="toggle-eye" onclick="togglePassword()" aria-label="toggle password visibility">
                        <i id="toggleIcon" class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="options-row">
                <label class="remember-wrap">
                    <input type="checkbox" name="remember" id="remember" />
                    <span>Remember me</span>
                </label>
                <a href="#" id="forgotBtn" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" id="loginBtn" class="submit-btn">
                <span><i class="fas fa-arrow-right-to-bracket"></i> Sign In</span>
            </button>
        </form>

        <div class="footer-links">
            <a href="#"><i class="fas fa-shield-halved"></i> Secure</a>
            <a href="#"><i class="fas fa-life-ring"></i> Help Desk</a>
            <a href="#"><i class="fas fa-lock"></i> Privacy</a>
        </div>
    </div>

    <!-- ===== VISUAL SIDE ===== -->
    <div class="visual-panel">
        <img class="bg-img" src="https://images.unsplash.com/photo-1551434678-e076c223a692?ixlib=rb-4.0.3&auto=format&fit=crop&w=2850&q=80" alt="workspace" />
        <div class="grad-overlay"></div>
        <div class="visual-content">
            <div class="badge-pulse">
                <i class="fas fa-circle"></i> System Online · v2.4
            </div>
            <blockquote>
                “Automating complex messaging workflows with zero manual overhead across micro-infrastructure.”
            </blockquote>
            <div class="quote-author">
                <div class="author-avatar"><i class="fas fa-microchip"></i></div>
                <div class="author-detail">
                    <div class="name">InfoTag Core</div>
                    <div class="role">Bot System Engine</div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/infologin.js"></script>

</body>
</html>
