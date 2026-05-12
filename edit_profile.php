<?php
// ============================================================
// edit_profile.php — Update Personal Information Only
// (NOT academic qualifications after submission)
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireStudentLogin();

$userId  = $_SESSION['user_id'];
$errors  = [];
$success = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission. Please try again.";
    } else {
        $action = $_POST['form_action'] ?? 'profile';

        if ($action === 'password') {
            // Change password
            $current  = $_POST['current_password'] ?? '';
            $newPass  = $_POST['new_password']      ?? '';
            $confirm  = $_POST['confirm_new']        ?? '';

            if (!password_verify($current, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            }

            $pwErrors = validatePassword($newPass);
            $errors   = array_merge($errors, $pwErrors);

            if ($newPass !== $confirm) {
                $errors[] = "New passwords do not match.";
            }

            if (empty($errors)) {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $userId]);
                $success = "Password updated successfully.";
            }

        } else {
            // Update personal info
            $firstName   = trim($_POST['first_name']   ?? '');
            $lastName    = trim($_POST['last_name']    ?? '');
            $phone       = trim($_POST['phone']        ?? '');
            $dob         = trim($_POST['dob']          ?? '');
            $gender      = trim($_POST['gender']       ?? '');
            $nationality = trim($_POST['nationality']  ?? '');
            $omang       = trim($_POST['omang']        ?? '');
            $address     = trim($_POST['address']      ?? '');
            $email       = trim($_POST['email']        ?? '');

            if (empty($firstName))  $errors[] = "First name is required.";
            if (empty($lastName))   $errors[] = "Last name is required.";
            if (empty($email))      $errors[] = "Email is required.";
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";

            // Check email uniqueness (excluding current user)
            if (empty($errors)) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->execute([$email, $userId]);
                if ($chk->fetch()) $errors[] = "This email is already used by another account.";
            }

            if (empty($errors)) {
                $pdo->prepare("
                    UPDATE users SET
                        first_name = ?, last_name = ?, email = ?, phone = ?,
                        dob = ?, gender = ?, nationality = ?, omang_number = ?, address = ?
                    WHERE id = ?
                ")->execute([
                    $firstName, $lastName, $email,
                    $phone ?: null, $dob ?: null, $gender,
                    $nationality, $omang ?: null, $address ?: null,
                    $userId,
                ]);

                // Update session name
                $_SESSION['user_name']  = $firstName;
                $_SESSION['user_email'] = $email;

                $success = "Profile updated successfully.";

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch() ?: [];
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
    <title>Edit Profile — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="application.php">My Application</a></li>
        <li><a href="edit_profile.php" class="active">My Profile</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">
    <h1 class="page-title">Edit Profile</h1>
    <p class="page-subtitle">Update your personal information. Academic qualifications are managed in your application.</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><span class="alert-icon">✖</span> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><span class="alert-icon">✔</span> <?php echo e($success); ?></div>
    <?php endif; ?>

    <!-- Personal Info Form -->
    <div class="card">
        <div class="card-title">👤 Personal Information</div>
        <form method="POST" action="edit_profile.php" id="profileForm">
            <input type="hidden" name="csrf_token"   value="<?php echo e($csrf); ?>">
            <input type="hidden" name="form_action" value="profile">

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="<?php echo e($user['first_name'] ?? ''); ?>" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="<?php echo e($user['last_name'] ?? ''); ?>" required maxlength="100">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo e($user['email'] ?? ''); ?>" required maxlength="150">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo e($user['phone'] ?? ''); ?>" maxlength="20">
                </div>
                <div class="form-group">
                    <label for="omang">Omang / ID Number</label>
                    <input type="text" id="omang" name="omang" class="form-control"
                           value="<?php echo e($user['omang_number'] ?? ''); ?>" maxlength="20">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" class="form-control"
                           value="<?php echo e($user['dob'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">— Select —</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo ($user['gender'] === $g) ? 'selected' : ''; ?>>
                                <?php echo $g; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control"
                       value="<?php echo e($user['nationality'] ?? ''); ?>" maxlength="100">
            </div>

            <div class="form-group">
                <label for="address">Residential Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"
                          maxlength="500"><?php echo e($user['address'] ?? ''); ?></textarea>
            </div>

            <div class="flex-gap mt-16">
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                <a href="dashboard.php" class="btn btn-outline-red">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-title">🔒 Change Password</div>
        <form method="POST" action="edit_profile.php" id="passwordForm">
            <input type="hidden" name="csrf_token"   value="<?php echo e($csrf); ?>">
            <input type="hidden" name="form_action" value="password">

            <div class="form-group">
                <label for="current_password">Current Password <span class="required">*</span></label>
                <input type="password" id="current_password" name="current_password"
                       class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label for="new_password">New Password <span class="required">*</span></label>
                <input type="password" id="new_password" name="new_password"
                       class="form-control" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_new">Confirm New Password <span class="required">*</span></label>
                <input type="password" id="confirm_new" name="confirm_new"
                       class="form-control" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">🔒 Update Password</button>
        </form>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
<script>
    initPasswordStrength('new_password');
    initPasswordConfirm('new_password', 'confirm_new');
</script>
</body>
</html>
