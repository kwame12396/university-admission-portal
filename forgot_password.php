<?php
// ============================================================
// forgot_password.php — Request a password reset link
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';

if (isStudentLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$message = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = "Invalid form submission. Please try again.";
        $msgType = 'error';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $msgType = 'error';
        } else {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Delete any previous tokens for this user
                $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

                // Generate a secure token
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $pdo->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at)
                    VALUES (?, ?, ?)
                ")->execute([$user['id'], $token, $expires]);

                $resetLink = SITE_URL . '/reset_password.php?token=' . $token;

                // In production you would email this link.
                // For local development we display it directly:
                $message = "A reset link has been generated. Click below to reset your password:<br><br>
                    <a href='" . htmlspecialchars($resetLink) . "' class='btn btn-primary btn-sm'>
                        Reset My Password →
                    </a>
                    <br><small class='text-muted' style='display:block;margin-top:10px;'>
                        This link expires in 1 hour.
                    </small>";
                $msgType = 'success';
            } else {
                // Don't reveal whether the email exists (security best practice)
                $message = "If that email address is registered, a reset link has been generated.";
                $msgType = 'success';
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
    <title>Forgot Password — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="login.php">Login</a></li>
        <li><a href="signup.php" class="btn-nav">Sign Up</a></li>
    </ul>
</nav>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <div class="auth-logo">🔑</div>
            <h2>Forgot Password</h2>
            <p>Enter your registered email address to receive a password reset link.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <span class="alert-icon"><?php echo $msgType === 'success' ? '✔' : '✖'; ?></span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($msgType !== 'success'): ?>
        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="your@email.com" required autofocus autocomplete="email">
                <span class="form-hint">Enter the email you used when signing up.</span>
            </div>

            <div class="mt-24">
                <button type="submit" class="btn btn-primary btn-full">Send Reset Link</button>
            </div>
        </form>
        <?php endif; ?>

        <p class="text-center mt-16" style="font-size:0.88rem;">
            Remembered your password? <a href="login.php">Login here</a>
        </p>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
</body>
</html>
