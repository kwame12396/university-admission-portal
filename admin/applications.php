<?php
// ============================================================
// admin/applications.php — Manage All Applications
// ============================================================
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Filters
$filterStatus  = $_GET['status']   ?? '';
$filterProgram = $_GET['program']  ?? '';
$search        = trim($_GET['search'] ?? '');
$perPage       = 20;
$page          = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE clause
$where  = [];
$params = [];

if ($filterStatus) {
    $where[]  = "a.status = ?";
    $params[] = $filterStatus;
}
if ($filterProgram) {
    $where[]  = "a.program = ?";
    $params[] = $filterProgram;
}
if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total (for pagination)
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications a
    JOIN users u ON a.user_id = u.id
    $whereSQL
");
$countStmt->execute($params);
$totalApps  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalApps / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Paginated results (total_points computed in PHP — MariaDB 10.4 doesn't support
// correlated references inside derived-table subqueries)
$pageParams = array_merge($params, [$perPage, $offset]);
$applications = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM applications a
    JOIN users u ON a.user_id = u.id
    $whereSQL
    ORDER BY a.submitted DESC, a.created_at DESC
    LIMIT ? OFFSET ?
");
$applications->execute($pageParams);
$apps = $applications->fetchAll();

// Enrich with admission score (max 20 extra queries — one per page row)
foreach ($apps as &$app) {
    $app['total_points'] = getTotalPoints($pdo, $app['id']);
}
unset($app);

// Programs list for filter dropdown
$programs = array_column(
    $pdo->query("SELECT DISTINCT program FROM applications ORDER BY program")->fetchAll(),
    'program'
);

$statusBadge = [
    'Pending'      => 'badge-pending',
    'Under Review' => 'badge-review',
    'Accepted'     => 'badge-accepted',
    'Rejected'     => 'badge-rejected',
];

// Build query string for export/pagination links
function buildQS(array $overrides = []): string {
    global $filterStatus, $filterProgram, $search, $page;
    $base = array_filter([
        'status'  => $filterStatus,
        'program' => $filterProgram,
        'search'  => $search,
        'page'    => $page,
    ]);
    return http_build_query(array_merge($base, $overrides));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications — UB Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .pagination { display:flex; gap:6px; flex-wrap:wrap; justify-content:center; margin-top:16px; padding:0 24px 16px; }
        .pagination a, .pagination span {
            padding:6px 12px; border-radius:6px; font-size:.88rem; text-decoration:none;
            border:1px solid var(--grey-light); background:#fff; color:var(--red);
        }
        .pagination .current { background:var(--red); color:#fff; border-color:var(--red); font-weight:700; }
        .pagination a:hover  { background:var(--red-pale); }
        .pagination .disabled { color:var(--grey-mid); pointer-events:none; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        Admin Panel
    </a>
    <ul class="navbar-nav">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="applications.php" class="active">Applications</a></li>
        <li><a href="ranking.php">BGCSE Ranking</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">
    <div class="flex-between" style="margin-bottom:6px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title">Manage Applications</h1>
            <p class="page-subtitle">View, filter and manage all prospective student applications</p>
        </div>
        <a href="export.php?<?php echo buildQS(['page' => null]); ?>"
           class="btn btn-sm btn-outline-red" title="Download all matching applications as CSV">
            📥 Export CSV
        </a>
    </div>

    <!-- Filters -->
    <div class="card" style="padding:18px 24px;">
        <form method="GET" action="applications.php" class="flex-gap" style="flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">Search</label>
                <input type="text" name="search" class="form-control" style="height:40px;"
                       placeholder="Name or email..." value="<?php echo e($search); ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:150px;">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">Status</label>
                <select name="status" class="form-control" style="height:40px;">
                    <option value="">All Statuses</option>
                    <?php foreach (['Pending','Under Review','Accepted','Rejected'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($filterStatus === $s) ? 'selected' : ''; ?>>
                            <?php echo $s; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:160px;">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">Programme</label>
                <select name="program" class="form-control" style="height:40px;">
                    <option value="">All Programmes</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?php echo e($p); ?>" <?php echo ($filterProgram === $p) ? 'selected' : ''; ?>>
                            <?php echo e($p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary btn-sm" style="height:40px;">🔍 Filter</button>
                <a href="applications.php" class="btn btn-outline-red btn-sm" style="height:40px;line-height:26px;">Clear</a>
            </div>
        </form>
    </div>

    <!-- Applications Table -->
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:16px 24px;border-bottom:1px solid var(--grey-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <span>
                Showing <strong><?php echo count($apps); ?></strong> of
                <strong><?php echo $totalApps; ?></strong> application(s)
                <?php if ($totalPages > 1): ?>
                    — Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                <?php endif; ?>
            </span>
            <?php if ($totalApps > 0): ?>
                <a href="export.php?<?php echo buildQS(['page' => null]); ?>"
                   class="btn btn-sm btn-outline-red" style="font-size:.82rem;">
                    📥 Download All <?php echo $totalApps; ?> as CSV
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($apps)): ?>
            <div style="padding:40px;text-align:center;color:var(--grey-mid);">
                <div style="font-size:2rem;margin-bottom:12px;">📋</div>
                No applications found matching your filters.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Applicant</th>
                            <th>Programme</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Applied On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apps as $row): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--red);">
                                UB-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>
                            </td>
                            <td>
                                <strong><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                <small class="text-muted" style="display:block;"><?php echo e($row['email']); ?></small>
                            </td>
                            <td><?php echo e($row['program']); ?></td>
                            <td>
                                <span class="points-pill" title="Admission score (top 6 subjects)">
                                    <?php echo (int)$row['total_points']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $statusBadge[$row['status']] ?? 'badge-pending'; ?>">
                                    <?php echo e($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $row['submitted']
                                    ? '<span class="badge badge-submitted">Yes</span>'
                                    : '<span class="badge badge-draft">Draft</span>'; ?>
                            </td>
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

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="applications.php?<?php echo buildQS(['page' => 1]); ?>">«</a>
                    <a href="applications.php?<?php echo buildQS(['page' => $page - 1]); ?>">‹ Prev</a>
                <?php else: ?>
                    <span class="disabled">«</span>
                    <span class="disabled">‹ Prev</span>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <?php if ($p === $page): ?>
                        <span class="current"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="applications.php?<?php echo buildQS(['page' => $p]); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="applications.php?<?php echo buildQS(['page' => $page + 1]); ?>">Next ›</a>
                    <a href="applications.php?<?php echo buildQS(['page' => $totalPages]); ?>">»</a>
                <?php else: ?>
                    <span class="disabled">Next ›</span>
                    <span class="disabled">»</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Admin Portal</p>
</footer>

<script src="../js/validate.js"></script>
</body>
</html>
