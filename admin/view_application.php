<?php
// ============================================================
// admin/view_application.php — View & Manage One Application
// ============================================================
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

$appId = (int)($_GET['id'] ?? 0);
if (!$appId) {
    header('Location: applications.php');
    exit;
}

// Fetch application + student
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.dob, u.gender,
           u.nationality, u.omang_number, u.address
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
    echo "<p>Application not found.</p>";
    exit;
}

$quals       = getQualifications($pdo, $appId);
$docs        = getDocuments($pdo, $appId);
$totalPoints = getTotalPoints($pdo, $appId);

$message = '';
$msgType = 'success';

// Handle POST actions (status update OR admin notes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = "Invalid form submission.";
        $msgType = 'error';
    } else {
        // Status update
        if (isset($_POST['new_status'])) {
            $newStatus = $_POST['new_status'];
            $allowed   = ['Pending', 'Under Review', 'Accepted', 'Rejected'];
            if (in_array($newStatus, $allowed)) {
                $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?")->execute([$newStatus, $appId]);
                $app['status'] = $newStatus;
                $message = "Application status updated to <strong>{$newStatus}</strong>.";
            }
        }
        // Admin notes
        if (isset($_POST['admin_notes'])) {
            $notes = trim($_POST['admin_notes']);
            $pdo->prepare("UPDATE applications SET admin_notes = ? WHERE id = ?")->execute([$notes ?: null, $appId]);
            $app['admin_notes'] = $notes;
            $message = "Admin notes saved successfully.";
        }
    }
}

$csrf = generateCsrfToken();

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
    <title>View Application — UB Admin</title>
    <link rel="stylesheet" href="../css/style.css">
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

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msgType; ?>">
            <span class="alert-icon"><?php echo $msgType === 'success' ? '✔' : '✖'; ?></span>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="flex-between" style="margin-bottom:6px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title">Application UB-<?php echo str_pad($appId, 5, '0', STR_PAD_LEFT); ?></h1>
            <p class="page-subtitle"><?php echo e($app['first_name'] . ' ' . $app['last_name']); ?> — <?php echo e($app['program']); ?></p>
        </div>
        <div class="flex-gap">
            <span class="badge <?php echo $statusBadge[$app['status']] ?? 'badge-pending'; ?>" style="padding:10px 18px;font-size:0.92rem;">
                <?php echo e($app['status']); ?>
            </span>
            <button onclick="window.print()" class="btn btn-sm btn-outline-red">🖨 Print</button>
            <a href="applications.php" class="btn btn-sm btn-outline-red">← Back</a>
        </div>
    </div>

    <!-- Update Status -->
    <?php if ($app['submitted']): ?>
    <div class="card">
        <div class="card-title">🔄 Update Application Status</div>
        <form method="POST" action="view_application.php?id=<?php echo $appId; ?>" class="flex-gap" style="align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <div class="form-group" style="margin-bottom:0;flex:1;max-width:250px;">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">New Status</label>
                <select name="new_status" class="form-control">
                    <?php foreach (['Pending','Under Review','Accepted','Rejected'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($app['status'] === $s) ? 'selected' : ''; ?>>
                            <?php echo $s; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('Update this application\'s status?')">
                Update Status
            </button>
        </form>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            This application is still a <strong>draft</strong> and has not been submitted by the student.
        </div>
    <?php endif; ?>

    <!-- Personal Details -->
    <div class="card">
        <div class="card-title">👤 Personal Details</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;font-size:0.9rem;">
            <div><span class="text-muted">Full Name:</span><br><strong><?php echo e($app['first_name'] . ' ' . $app['last_name']); ?></strong></div>
            <div><span class="text-muted">Email:</span><br><strong><?php echo e($app['email']); ?></strong></div>
            <div><span class="text-muted">Phone:</span><br><strong><?php echo $app['phone'] ? e($app['phone']) : '—'; ?></strong></div>
            <div><span class="text-muted">Date of Birth:</span><br><strong><?php echo $app['dob'] ? date('d M Y', strtotime($app['dob'])) : '—'; ?></strong></div>
            <div><span class="text-muted">Gender:</span><br><strong><?php echo e($app['gender'] ?? '—'); ?></strong></div>
            <div><span class="text-muted">Nationality:</span><br><strong><?php echo e($app['nationality'] ?? '—'); ?></strong></div>
            <div><span class="text-muted">Omang / ID:</span><br><strong><?php echo $app['omang_number'] ? e($app['omang_number']) : '—'; ?></strong></div>
            <div><span class="text-muted">Address:</span><br><strong><?php echo $app['address'] ? e($app['address']) : '—'; ?></strong></div>
        </div>
    </div>

    <!-- Programme -->
    <div class="card">
        <div class="card-title">🎓 Programme Applied For</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;font-size:0.9rem;">
            <div><span class="text-muted">Programme:</span><br><strong style="font-size:1.05rem;"><?php echo e($app['program']); ?></strong></div>
            <div>
                <span class="text-muted">Qualification Type:</span><br>
                <strong><?php echo qualTypeLabel($app['qual_type'] ?? 'BGCSE', $app['qual_type_other'] ?? ''); ?></strong>
            </div>
            <div><span class="text-muted">Submission Date:</span><br><strong><?php echo $app['submitted_at'] ? date('d M Y, H:i', strtotime($app['submitted_at'])) : 'Not submitted'; ?></strong></div>
            <div><span class="text-muted">Application Date:</span><br><strong><?php echo date('d M Y', strtotime($app['created_at'])); ?></strong></div>
            <div>
                <span class="text-muted">Admission Score (top 6):</span><br>
                <span class="points-pill" style="font-size:1rem;"><?php echo $totalPoints; ?></span>
                <small class="text-muted"> / 48</small>
            </div>
        </div>
    </div>

    <!-- Academic Results -->
    <div class="card">
        <div class="card-title">
            📚 Academic Results
            <small class="text-muted" style="font-weight:400;font-size:0.82rem;margin-left:8px;">
                (<?php echo qualTypeLabel($app['qual_type'] ?? 'BGCSE', $app['qual_type_other'] ?? ''); ?>)
                — Admission score = top 6 subjects, max 48 pts
            </small>
        </div>
        <?php if (empty($quals)): ?>
            <p class="text-muted">No qualifications recorded.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>#</th><th>Subject</th><th>Grade</th><th>Points</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quals as $i => $q): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo e($q['subject']); ?></td>
                            <td><strong><?php echo e($q['grade']); ?></strong></td>
                            <td><span class="points-pill"><?php echo $q['points']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--red-pale);font-weight:700;">
                            <td colspan="3" style="text-align:right;padding:12px 16px;">
                                Admission Score (top 6 subjects):
                            </td>
                            <td>
                                <span class="points-pill"><?php echo $totalPoints; ?></span>
                                <small style="font-weight:400;color:var(--grey-mid);"> / 48</small>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card">
        <div class="card-title">📎 Uploaded Documents</div>
        <?php if (empty($docs)): ?>
            <p class="text-muted">No documents uploaded.</p>
        <?php else: ?>
            <ul class="doc-list">
                <?php foreach ($docs as $doc): ?>
                    <li>
                        <span class="doc-icon">📄</span>
                        <div style="flex:1;">
                            <strong><?php echo e($doc['doc_type']); ?></strong>
                            — <?php echo e($doc['original_name']); ?>
                            <small class="text-muted" style="display:block;">
                                Uploaded: <?php echo date('d M Y, H:i', strtotime($doc['uploaded_at'])); ?>
                            </small>
                        </div>
                        <a href="<?php echo SITE_URL . '/' . e($doc['file_path']); ?>"
                           target="_blank" class="btn btn-sm btn-outline-red">View</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Admin Notes -->
    <div class="card" style="border-top-color:#2980b9;">
        <div class="card-title" style="color:#2980b9;">📝 Admin Notes
            <small class="text-muted" style="font-weight:400;font-size:0.82rem;margin-left:8px;">
                Visible to the student on their application view
            </small>
        </div>
        <form method="POST" action="view_application.php?id=<?php echo $appId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
            <div class="form-group">
                <textarea name="admin_notes" class="form-control" rows="4"
                          placeholder="Add notes or feedback for the student (optional). Leave blank to clear."
                          style="resize:vertical;"><?php echo e($app['admin_notes'] ?? ''); ?></textarea>
                <span class="form-hint">These notes are shown to the student in their portal.</span>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">💾 Save Notes</button>
        </form>
    </div>

    <div class="flex-gap">
        <a href="applications.php" class="btn btn-outline-red">← All Applications</a>
        <a href="ranking.php" class="btn btn-primary btn-sm">📊 BGCSE Ranking</a>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Admin Portal</p>
</footer>

<script src="../js/validate.js"></script>
</body>
</html>
