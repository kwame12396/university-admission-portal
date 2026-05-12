<?php
// ============================================================
// reset_password.php — Set a new password via reset token
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';

if (isStudentLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$token   = trim($_GET['token'] ?? '');
$errors  = [];
$success = false;

// Validate token exists and is not expired
$tokenRow = null;
if ($token) {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch();
}

if (!$token || !$tokenRow) {
    $invalidToken = true;
} else {
    $invalidToken = false;
}

if (!$invalidToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission. Please try again.";
    } else {
        $newPass = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $pwErrors = validatePassword($newPass);
        $errors   = array_merge($errors, $pwErrors);

        if ($newPass !== $confirm) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);

            // Update password
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$hashed, $tokenRow['user_id']]);

            // Mark token as used
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                ->execute([$token]);

            $success = true;
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
    <title>Reset Password — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
</nav>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <div class="auth-logo">🔒</div>
            <h2>Reset Password</h2>
            <p>Enter and confirm your new password below.</p>
        </div>

        <?php if ($invalidToken): ?>
            <div class="alert alert-error">
                <span class="alert-icon">✖</span>
                This reset link is invalid or has expired.
                <a href="forgot_password.php">Request a new one →</a>
            </div>

        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✔</span>
                <strong>Password reset successfully!</strong><br>
                You can now log in with your new password.
            </div>
            <div class="mt-24 text-center">
                <a href="login.php" class="btn btn-primary">Login Now →</a>
            </div>

        <?php else: ?>

            <p class="text-muted" style="font-size:0.88rem;margin-bottom:16px;">
                Resetting password for: <strong><?php echo e($tokenRow['email']); ?></strong>
            </p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><span class="alert-icon">✖</span> <?php echo e($err); ?></div>
            <?php endforeach; ?>

            <form method="POST" action="reset_password.php?token=<?php echo urlencode($token); ?>" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-control"
                           placeholder="Create a strong password" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           placeholder="Repeat your new password" required autocomplete="new-password">
                </div>

                <div class="mt-24">
                    <button type="submit" class="btn btn-primary btn-full">Set New Password</button>
                </div>
            </form>

        <?php endif; ?>

        <p class="text-center mt-16" style="font-size:0.88rem;">
            <a href="login.php">← Back to Login</a>
        </p>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
<script>
    initPasswordStrength('new_password');
    initPasswordConfirm('new_password', 'confirm_password');
</script>
</body>
</html>
