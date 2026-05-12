<?php
// ============================================================
// admin/login.php — Admin Login
// ============================================================
require_once '../config.php';
require_once '../includes/auth.php';

if (isAdminLoggedIn()) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];
                session_regenerate_id(true);
                header('Location: ' . SITE_URL . '/admin/index.php');
                exit;
            } else {
                $error = "Invalid username or password.";
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
    <title>Admin Login — UB Admission System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-wrapper">

<nav class="navbar">
    <a href="../index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
</nav>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <div class="auth-logo" style="background:var(--red-dark);">🔑</div>
            <h2>Admin Login</h2>
            <p>University of Botswana — Admissions Staff Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span class="alert-icon">✖</span> <?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" class="form-control"
                       placeholder="admin username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="admin password" required autocomplete="current-password">
            </div>

            <div class="mt-24">
                <button type="submit" class="btn btn-primary btn-full">Login to Admin Panel</button>
            </div>

            <p class="text-center mt-16" style="font-size:0.85rem;">
                <a href="../login.php">← Back to Student Portal</a>
            </p>
        </form>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Restricted Access</p>
</footer>

<script src="../js/validate.js"></script>
</body>
</html>
