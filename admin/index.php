<?php
// ============================================================
// admin/index.php — Admin Dashboard
// ============================================================
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Statistics
$totalApps     = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$submitted     = $pdo->query("SELECT COUNT(*) FROM applications WHERE submitted = 1")->fetchColumn();
$pending       = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Pending' AND submitted = 1")->fetchColumn();
$accepted      = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Accepted'")->fetchColumn();
$rejected      = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Rejected'")->fetchColumn();
$underReview   = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Under Review'")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Recent applications
$recent = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM applications a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

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
    <title>Admin Dashboard — UB Admission System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        Admin Panel
    </a>
    <ul class="navbar-nav">
        <li><a href="index.php" class="active">Dashboard</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="ranking.php">BGCSE Ranking</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">
    <div class="flex-between" style="margin-bottom:6px;">
        <div>
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="page-subtitle">Welcome, <?php echo e($_SESSION['admin_name']); ?> — University of Botswana Admissions</p>
        </div>
        <div class="flex-gap">
            <a href="applications.php" class="btn btn-outline-red btn-sm">📋 All Applications</a>
            <a href="ranking.php" class="btn btn-primary btn-sm">📊 BGCSE Ranking</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalStudents; ?></div>
            <div class="stat-label">Registered Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $submitted; ?></div>
            <div class="stat-label">Submitted Applications</div>
        </div>
        <div class="stat-card" style="border-top-color:var(--warning);">
            <div class="stat-number" style="color:var(--warning);"><?php echo $pending; ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card" style="border-top-color:var(--info);">
            <div class="stat-number" style="color:var(--info);"><?php echo $underReview; ?></div>
            <div class="stat-label">Under Review</div>
        </div>
        <div class="stat-card" style="border-top-color:var(--success);">
            <div class="stat-number" style="color:var(--success);"><?php echo $accepted; ?></div>
            <div class="stat-label">Accepted</div>
        </div>
        <div class="stat-card" style="border-top-color:var(--red-light);">
            <div class="stat-number" style="color:var(--red-light);"><?php echo $rejected; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-title">⚡ Quick Actions</div>
        <div class="flex-gap">
            <a href="applications.php" class="btn btn-outline-red">📋 Manage All Applications</a>
            <a href="applications.php?status=Pending" class="btn btn-warning">⏳ Review Pending (<?php echo $pending; ?>)</a>
            <a href="ranking.php" class="btn btn-primary">📊 Generate BSc General Ranking</a>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="card">
        <div class="card-title">🕐 Recent Applications</div>
        <?php if (empty($recent)): ?>
            <p class="text-muted">No applications yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Applicant</th>
                            <th>Programme</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                        <tr>
                            <td>UB-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <strong><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                <small class="text-muted" style="display:block;"><?php echo e($row['email']); ?></small>
                            </td>
                            <td><?php echo e($row['program']); ?></td>
                            <td><span class="badge <?php echo $statusBadge[$row['status']] ?? 'badge-pending'; ?>"><?php echo e($row['status']); ?></span></td>
                            <td><?php echo $row['submitted'] ? '<span class="badge badge-submitted">Yes</span>' : '<span class="badge badge-draft">Draft</span>'; ?></td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="view_application.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-sm btn-outline-red">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-16 text-right">
                <a href="applications.php" class="btn btn-sm btn-outline-red">View All Applications →</a>
            </div>
        <?php endif; ?>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Admin Portal</p>
</footer>

<script src="../js/validate.js"></script>
</body>
</html>
