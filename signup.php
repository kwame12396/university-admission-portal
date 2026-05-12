<?php
// ============================================================
// signup.php — Student Registration
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';

if (isStudentLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$errors  = [];
$success = '';
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission. Please try again.";
    } else {
        // Collect & sanitize
        $old = [
            'first_name'  => trim($_POST['first_name']  ?? ''),
            'last_name'   => trim($_POST['last_name']   ?? ''),
            'email'       => trim($_POST['email']       ?? ''),
            'phone'       => trim($_POST['phone']       ?? ''),
            'dob'         => trim($_POST['dob']         ?? ''),
            'gender'      => trim($_POST['gender']      ?? ''),
            'nationality' => trim($_POST['nationality'] ?? 'Motswana'),
            'omang'       => trim($_POST['omang']       ?? ''),
            'address'     => trim($_POST['address']     ?? ''),
        ];
        $password        = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($old['first_name']))  $errors[] = "First name is required.";
        if (empty($old['last_name']))   $errors[] = "Last name is required.";

        if (empty($old['email'])) {
            $errors[] = "Email address is required.";
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($old['dob']))    $errors[] = "Date of birth is required.";
        if (empty($old['gender'])) $errors[] = "Gender is required.";

        // Password validation
        $pwErrors = validatePassword($password);
        $errors = array_merge($errors, $pwErrors);

        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }

        // Check email uniqueness
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = "An account with this email already exists. Please <a href='login.php'>login</a>.";
            }
        }

        // Insert user
        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, phone, dob, gender, nationality, omang_number, address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $old['first_name'], $old['last_name'], $old['email'],
                $hashed, $old['phone'] ?: null,
                $old['dob'] ?: null,  $old['gender'],
                $old['nationality'],  $old['omang'] ?: null,
                $old['address'] ?: null,
            ]);

            $userId = $pdo->lastInsertId();

            // Start session
            $_SESSION['user_id']    = $userId;
            $_SESSION['user_name']  = $old['first_name'];
            $_SESSION['user_email'] = $old['email'];
            session_regenerate_id(true);

            header('Location: ' . SITE_URL . '/dashboard.php?welcome=1');
            exit;
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
    <title>Sign Up — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-wrapper">

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="login.php">Already have an account? Login</a></li>
    </ul>
</nav>

<div class="auth-container" style="max-width:640px;margin:0 auto;padding:36px 20px;">
    <div class="auth-box" style="max-width:100%;">
        <div class="auth-header">
            <div class="auth-logo">UB</div>
            <h2>Create Your Account</h2>
            <p>Fill in your details to start your application to the University of Botswana</p>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><span class="alert-icon">✖</span> <?php echo $err; ?></div>
        <?php endforeach; ?>

        <form method="POST" action="signup.php" id="signupForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

            <!-- Personal Details -->
            <div class="form-section-title">👤 Personal Information</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="<?php echo e($old['first_name'] ?? ''); ?>"
                           placeholder="e.g. Thabo" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="<?php echo e($old['last_name'] ?? ''); ?>"
                           placeholder="e.g. Mokoena" required maxlength="100">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo e($old['email'] ?? ''); ?>"
                       placeholder="e.g. thabo@example.com" required maxlength="150">
                <span class="form-hint">This will be your login and contact email.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="dob">Date of Birth <span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" class="form-control"
                           value="<?php echo e($old['dob'] ?? ''); ?>"
                           max="<?php echo date('Y-m-d', strtotime('-14 years')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender <span class="required">*</span></label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo (($old['gender'] ?? '') === $g) ? 'selected' : ''; ?>>
                                <?php echo $g; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo e($old['phone'] ?? ''); ?>"
                           placeholder="+267 71 234 567" maxlength="20">
                </div>
                <div class="form-group">
                    <label for="omang">Omang / ID Number</label>
                    <input type="text" id="omang" name="omang" class="form-control"
                           value="<?php echo e($old['omang'] ?? ''); ?>"
                           placeholder="e.g. 123456789" maxlength="20">
                </div>
            </div>

            <div class="form-group">
                <label for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control"
                       value="<?php echo e($old['nationality'] ?? 'Motswana'); ?>"
                       placeholder="e.g. Motswana" maxlength="100">
            </div>

            <div class="form-group">
                <label for="address">Residential Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"
                          placeholder="Street, Village/City, District"
                          maxlength="500"><?php echo e($old['address'] ?? ''); ?></textarea>
            </div>

            <!-- Password -->
            <div class="form-section-title">🔒 Security</div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Create a strong password" required autocomplete="new-password">
                <!-- Strength bar and rules injected by JS -->
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                       placeholder="Re-enter your password" required autocomplete="new-password">
            </div>

            <div class="mt-24">
                <button type="submit" class="btn btn-primary btn-full">
                    Create Account &amp; Continue
                </button>
            </div>

            <p class="text-center mt-16" style="font-size:0.88rem;">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </form>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
<script>
    initPasswordStrength('password');
    initPasswordConfirm('password', 'confirm_password');
    validateSignupForm('signupForm');
</script>
</body>
</html>
