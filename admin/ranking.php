<?php
// ============================================================
// admin/ranking.php — Auto-generate BGCSE Ranking for BSc General
// Ranks submitted applicants by total BGCSE points (descending)
// ============================================================
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Allow selecting any programme (default: BSc General)
$programs = array_column(
    $pdo->query("SELECT DISTINCT program FROM applications WHERE submitted = 1 ORDER BY program")->fetchAll(),
    'program'
);

$selectedProgram = $_GET['program'] ?? 'BSc General';

// Fetch submitted applicants for this programme
// total_points computed in PHP via getTotalPoints() to avoid MariaDB correlated subquery limitation
$stmt = $pdo->prepare("
    SELECT
        a.id            AS app_id,
        u.id            AS user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.gender,
        u.nationality,
        u.omang_number,
        a.program,
        a.qual_type,
        a.qual_type_other,
        a.status,
        a.submitted_at,
        (SELECT COUNT(*) FROM academic_qualifications WHERE application_id = a.id) AS subject_count
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.submitted = 1
      AND a.program = ?
    ORDER BY a.submitted_at ASC
");
$stmt->execute([$selectedProgram]);
$rows = $stmt->fetchAll();

// Add total_points via PHP (sum of top 6 subjects) and sort descending
foreach ($rows as &$row) {
    $row['total_points'] = getTotalPoints($pdo, $row['app_id']);
}
unset($row);

usort($rows, fn($a, $b) =>
    $b['total_points'] <=> $a['total_points']
    ?: strtotime($a['submitted_at']) <=> strtotime($b['submitted_at'])
);
$ranked = $rows;

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
    <title>BGCSE Ranking — UB Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .main-content { padding: 10px 0; }
            .card { border: 1px solid #ddd; margin-bottom: 12px; }
        }
        .rank-gold   { background: #FFF9E6; }
        .rank-silver { background: #F4F4F4; }
        .rank-bronze { background: #FFF3EE; }
    </style>
</head>
<body>

<nav class="navbar no-print">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        Admin Panel
    </a>
    <ul class="navbar-nav">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="ranking.php" class="active">BGCSE Ranking</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">

    <div class="flex-between no-print" style="margin-bottom:6px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title">BGCSE Student Ranking</h1>
            <p class="page-subtitle">Auto-generated ranking of submitted applicants by total BGCSE points (descending)</p>
        </div>
        <div class="flex-gap">
            <button onclick="window.print()" class="btn btn-outline-red btn-sm">🖨 Print Report</button>
            <a href="applications.php" class="btn btn-sm btn-outline-red">← Applications</a>
        </div>
    </div>

    <!-- Programme Selector -->
    <div class="card no-print" style="padding:16px 24px;">
        <form method="GET" action="ranking.php" class="flex-gap" style="align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;flex:1;max-width:300px;">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">Select Programme</label>
                <select name="program" class="form-control">
                    <?php
                    // Always show BSc General even if no applications yet
                    $allProgs = array_unique(array_merge(['BSc General'], $programs));
                    foreach ($allProgs as $p):
                    ?>
                        <option value="<?php echo e($p); ?>" <?php echo ($selectedProgram === $p) ? 'selected' : ''; ?>>
                            <?php echo e($p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height:40px;">📊 Generate Ranking</button>
        </form>
    </div>

    <!-- Print Header (only visible when printing) -->
    <div style="display:none;" class="print-only">
        <h2 style="color:#C0392B;text-align:center;">University of Botswana</h2>
        <h3 style="text-align:center;">BGCSE Applicant Ranking — <?php echo e($selectedProgram); ?></h3>
        <p style="text-align:center;color:#888;">Generated: <?php echo date('d F Y, H:i'); ?></p>
        <hr>
    </div>

    <!-- Ranking Table -->
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:18px 24px;background:var(--red);color:white;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <strong style="font-size:1.05rem;">📊 Ranking: <?php echo e($selectedProgram); ?></strong>
                <small style="opacity:0.85;display:block;">
                    <?php echo count($ranked); ?> submitted applicant(s) |
                    Generated: <?php echo date('d M Y, H:i'); ?>
                </small>
            </div>
            <?php if (!empty($ranked)): ?>
                <div style="font-size:0.88rem;opacity:0.9;">
                    Highest: <?php echo $ranked[0]['total_points']; ?> pts |
                    Lowest: <?php echo end($ranked)['total_points']; ?> pts
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($ranked)): ?>
            <div style="padding:48px;text-align:center;color:var(--grey-mid);">
                <div style="font-size:2.5rem;margin-bottom:14px;">📭</div>
                <strong>No submitted applications found for "<?php echo e($selectedProgram); ?>".</strong><br>
                <small>Only submitted (not draft) applications appear in the ranking.</small>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;">Rank</th>
                            <th>Applicant</th>
                            <th>Omang / ID</th>
                            <th>Qual. Type</th>
                            <th>Gender</th>
                            <th>Nationality</th>
                            <th style="text-align:center;">Subjects</th>
                            <th style="text-align:center;">Score (top 6 · max 48)</th>
                            <th>Status</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranked as $rank => $row):
                            $rankNum  = $rank + 1;
                            $rowClass = '';
                            if ($rankNum === 1) $rowClass = 'rank-gold';
                            if ($rankNum === 2) $rowClass = 'rank-silver';
                            if ($rankNum === 3) $rowClass = 'rank-bronze';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td style="font-weight:800;font-size:1.1rem;color:var(--red);text-align:center;">
                                <?php
                                if      ($rankNum === 1) echo '🥇';
                                else if ($rankNum === 2) echo '🥈';
                                else if ($rankNum === 3) echo '🥉';
                                else echo "#$rankNum";
                                ?>
                            </td>
                            <td>
                                <strong><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                <small class="text-muted" style="display:block;"><?php echo e($row['email']); ?></small>
                                <small style="color:var(--grey-mid);font-size:0.75rem;">
                                    Submitted: <?php echo date('d M Y', strtotime($row['submitted_at'])); ?>
                                </small>
                            </td>
                            <td><?php echo $row['omang_number'] ? e($row['omang_number']) : '—'; ?></td>
                            <td>
                                <span style="font-weight:600;">
                                    <?php echo e($row['qual_type'] ?? 'BGCSE'); ?>
                                </span>
                                <?php if (($row['qual_type'] ?? '') === 'Other' && !empty($row['qual_type_other'])): ?>
                                    <small class="text-muted" style="display:block;">
                                        <?php echo e($row['qual_type_other']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($row['gender'] ?? '—'); ?></td>
                            <td><?php echo e($row['nationality'] ?? '—'); ?></td>
                            <td style="text-align:center;"><?php echo $row['subject_count']; ?></td>
                            <td style="text-align:center;">
                                <span class="points-pill" style="font-size:1rem;padding:6px 16px;">
                                    <?php echo $row['total_points']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $statusBadge[$row['status']] ?? 'badge-pending'; ?>">
                                    <?php echo e($row['status']); ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <a href="view_application.php?id=<?php echo $row['app_id']; ?>"
                                   class="btn btn-sm btn-outline-red">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div style="padding:16px 24px;border-top:1px solid var(--grey-light);background:var(--off-white);">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;font-size:0.88rem;">
                    <div><span class="text-muted">Total Applicants:</span><br><strong><?php echo count($ranked); ?></strong></div>
                    <div><span class="text-muted">Highest Points:</span><br><strong><?php echo $ranked[0]['total_points']; ?> pts</strong></div>
                    <div><span class="text-muted">Lowest Points:</span><br><strong><?php echo end($ranked)['total_points']; ?> pts</strong></div>
                    <div><span class="text-muted">Average Points:</span><br>
                        <strong><?php echo count($ranked) > 0 ? round(array_sum(array_column($ranked, 'total_points')) / count($ranked), 1) : 0; ?> pts</strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- How Points Work -->
    <div class="card no-print">
        <div class="card-title">ℹ️ Grading Key (applies to all qualification types)</div>
        <p class="text-muted" style="font-size:0.85rem;margin-bottom:12px;">
            <strong>Scoring rule:</strong> Each applicant's admission score is the sum of their
            <strong>6 highest-scoring subjects</strong>. Maximum possible score = <strong>48 points</strong>
            (6 subjects × 8 pts for A*).
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:0.88rem;">
            <?php foreach (['A*'=>8,'A'=>7,'B'=>6,'C'=>5,'D'=>4,'E'=>3,'U'=>0] as $g => $pts): ?>
                <div style="background:var(--off-white);border:1px solid var(--grey-light);border-radius:6px;padding:8px 14px;text-align:center;min-width:70px;">
                    <div style="font-weight:800;font-size:1.1rem;color:var(--red);"><?php echo $g; ?></div>
                    <div class="text-muted"><?php echo $pts; ?> pts</div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted mt-16" style="font-size:0.82rem;">
            Rankings are sorted by total BGCSE points in descending order.
            Ties are broken by earliest submission date. Only <em>submitted</em> applications appear in the ranking.
        </p>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Admin Portal</p>
</footer>

<script src="../js/validate.js"></script>
<style>
    @media print {
        .print-only { display: block !important; }
    }
</style>
</body>
</html>
