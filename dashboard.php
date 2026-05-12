<?php
// ============================================================
// dashboard.php — Student Dashboard
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireStudentLogin();

$userId = $_SESSION['user_id'];

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

// Fetch application
$app = getApplicationByUser($pdo, $userId);

// Application stats
$totalPoints = 0;
$qualCount   = 0;
$docCount    = 0;

if ($app) {
    $totalPoints = getTotalPoints($pdo, $app['id']);
    $qualCount   = count(getQualifications($pdo, $app['id']));
    $docCount    = count(getDocuments($pdo, $app['id']));
}

$statusBadge = [
    'Pending'      => 'badge-pending',
    'Under Review' => 'badge-review',
    'Accepted'     => 'badge-accepted',
    'Rejected'     => 'badge-rejected',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="dashboard.php" class="active">Dashboard</a></li>
        <li><a href="application.php">My Application</a></li>
        <li><a href="edit_profile.php">My Profile</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">

    <?php if (!empty($_GET['welcome'])): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✔</span>
            Welcome, <?php echo e($user['first_name'] ?? ''); ?>! Your account has been created.
            Start your application below.
        </div>
    <?php endif; ?>

    <div class="flex-between" style="margin-bottom:6px;">
        <div>
            <h1 class="page-title">My Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></p>
        </div>
        <?php if (!$app || !$app['submitted']): ?>
            <a href="application.php" class="btn btn-primary">
                <?php echo $app ? '✏ Continue Application' : '📋 Start Application'; ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $qualCount; ?></div>
            <div class="stat-label">BGCSE Subjects</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalPoints; ?></div>
            <div class="stat-label">Total BGCSE Points</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $docCount; ?></div>
            <div class="stat-label">Documents Uploaded</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $app ? ($app['submitted'] ? '✔' : '⋯') : '—'; ?></div>
            <div class="stat-label"><?php echo $app ? ($app['submitted'] ? 'Submitted' : 'Draft') : 'No Application'; ?></div>
        </div>
    </div>

    <!-- Application Status Card -->
    <div class="card">
        <div class="card-title">📄 Application Status</div>

        <?php if (!$app): ?>
            <div class="alert alert-info">
                <span class="alert-icon">ℹ</span>
                You have not started an application yet.
                <a href="application.php" style="margin-left:8px;" class="btn btn-sm btn-primary">Start Now</a>
            </div>
        <?php else: ?>
            <div class="flex-between" style="flex-wrap:wrap;gap:12px;margin-bottom:18px;">
                <div>
                    <div style="font-size:0.85rem;color:var(--grey-mid);margin-bottom:4px;">Programme Applied For</div>
                    <div style="font-weight:700;font-size:1.05rem;"><?php echo e($app['program']); ?></div>
                </div>
                <div>
                    <div style="font-size:0.85rem;color:var(--grey-mid);margin-bottom:4px;">Application Status</div>
                    <span class="badge <?php echo $statusBadge[$app['status']] ?? 'badge-pending'; ?>">
                        <?php echo e($app['status']); ?>
                    </span>
                </div>
                <div>
                    <div style="font-size:0.85rem;color:var(--grey-mid);margin-bottom:4px;">Submission</div>
                    <span class="badge <?php echo $app['submitted'] ? 'badge-submitted' : 'badge-draft'; ?>">
                        <?php echo $app['submitted'] ? 'Submitted ' . date('d M Y', strtotime($app['submitted_at'])) : 'Draft (Not Submitted)'; ?>
                    </span>
                </div>
            </div>

            <?php if (!$app['submitted']): ?>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠</span>
                    Your application is saved as a draft. Remember to submit it before the deadline.
                    <a href="application.php" class="btn btn-sm btn-primary" style="margin-left:10px;">Complete &amp; Submit</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <span class="alert-icon">ℹ</span>
                    Your application has been submitted. You cannot change academic qualifications,
                    but you may update your personal information.
                    <a href="view_application.php" class="btn btn-sm btn-outline-red" style="margin-left:10px;">View Application</a>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-title">⚡ Quick Actions</div>
        <div class="flex-gap">
            <?php if (!$app || !$app['submitted']): ?>
                <a href="application.php" class="btn btn-primary btn-sm">
                    <?php echo $app ? '✏ Edit Application' : '📋 Start Application'; ?>
                </a>
            <?php else: ?>
                <a href="view_application.php" class="btn btn-outline-red btn-sm">📄 View Application</a>
            <?php endif; ?>
            <a href="edit_profile.php" class="btn btn-outline-red btn-sm">👤 Update Profile</a>
        </div>
    </div>

    <!-- Profile Summary -->
    <div class="card">
        <div class="card-title">👤 Personal Details</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;font-size:0.9rem;">
            <div><span class="text-muted">Full Name:</span><br><strong><?php echo e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></strong></div>
            <div><span class="text-muted">Email:</span><br><strong><?php echo e($user['email'] ?? ''); ?></strong></div>
            <div><span class="text-muted">Phone:</span><br><strong><?php echo !empty($user['phone']) ? e($user['phone']) : '—'; ?></strong></div>
            <div><span class="text-muted">Date of Birth:</span><br><strong><?php echo !empty($user['dob']) ? date('d M Y', strtotime($user['dob'])) : '—'; ?></strong></div>
            <div><span class="text-muted">Gender:</span><br><strong><?php echo !empty($user['gender']) ? e($user['gender']) : '—'; ?></strong></div>
            <div><span class="text-muted">Nationality:</span><br><strong><?php echo e($user['nationality'] ?? '—'); ?></strong></div>
        </div>
        <div class="mt-16">
            <a href="edit_profile.php" class="btn btn-sm btn-outline-red">✏ Edit Profile</a>
        </div>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
</body>
</html>
