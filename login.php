<?php
// ============================================================
// login.php — Student Login
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';

if (isStudentLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = "Please enter both your email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['first_name'];
                $_SESSION['user_email'] = $user['email'];
                session_regenerate_id(true);
                header('Location: ' . SITE_URL . '/dashboard.php');
                exit;
            } else {
                $error = "Incorrect email or password. Please try again.";
            }
        }
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="signup.php" class="btn-nav">Apply Now</a></li>
    </ul>
</nav>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <div class="auth-logo">UB</div>
            <h2>Welcome Back</h2>
            <p>Login to your UB Admission account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span class="alert-icon">✖</span> <?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($_GET['registered'])): ?>
            <div class="alert alert-success"><span class="alert-icon">✔</span> Account created successfully! Please login.</div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo e($email); ?>"
                       placeholder="your@email.com" required autofocus autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Your password" required autocomplete="current-password">
            </div>

            <div class="mt-24">
                <button type="submit" class="btn btn-primary btn-full">Login</button>
            </div>

            <p class="text-center mt-16" style="font-size:0.88rem;">
                Don't have an account? <a href="signup.php">Sign Up &amp; Apply</a>
            </p>
            <p class="text-center" style="font-size:0.85rem;margin-top:6px;">
                <a href="forgot_password.php">Forgot your password?</a>
            </p>

            <hr class="divider">
            <p class="text-center" style="font-size:0.82rem;color:var(--grey-mid);">
                Are you an administrator? <a href="admin/login.php">Admin Login →</a>
            </p>
        </form>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
</body>
</html>
