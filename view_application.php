<?php
// ============================================================
// view_application.php — View submitted application
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireStudentLogin();

$userId = $_SESSION['user_id'];
$app    = getApplicationByUser($pdo, $userId);

if (!$app) {
    header('Location: ' . SITE_URL . '/application.php');
    exit;
}

// Handle document delete (only allowed before submission)
if (!$app['submitted'] && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $docId = (int)$_POST['delete_doc'];
        // Verify doc belongs to this user's application
        $dStmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND application_id = ?");
        $dStmt->execute([$docId, $app['id']]);
        $doc = $dStmt->fetch();
        if ($doc) {
            // Delete file from disk
            $filePath = UPLOAD_DIR . basename($doc['file_path']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$docId]);
            header('Location: ' . SITE_URL . '/view_application.php?deleted=1');
            exit;
        }
    }
}

$quals       = getQualifications($pdo, $app['id']);
$docs        = getDocuments($pdo, $app['id']);
$totalPoints = getTotalPoints($pdo, $app['id']);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

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
    <title>View Application — UB Admission Portal</title>
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
        <li><a href="view_application.php" class="active">My Application</a></li>
        <li><a href="edit_profile.php">My Profile</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">

    <?php if (!empty($_GET['submitted'])): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✔</span>
            <strong>Application Submitted Successfully!</strong>
            Your application has been received and will be reviewed by the admissions team.
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['deleted'])): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✔</span>
            Document removed successfully.
        </div>
    <?php endif; ?>

    <div class="flex-between" style="margin-bottom:6px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="page-title">My Application</h1>
            <p class="page-subtitle">Reference #UB-<?php echo str_pad($app['id'], 5, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="flex-gap">
            <span class="badge <?php echo $statusBadge[$app['status']] ?? 'badge-pending'; ?>" style="padding:8px 16px;font-size:0.9rem;">
                <?php echo e($app['status']); ?>
            </span>
            <?php if (!$app['submitted']): ?>
                <a href="application.php" class="btn btn-primary btn-sm">✏ Continue Editing</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Programme & Status -->
    <div class="card">
        <div class="card-title">🎓 Programme &amp; Status</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;font-size:0.9rem;">
            <div><span class="text-muted">Programme:</span><br><strong><?php echo e($app['program']); ?></strong></div>
            <div>
                <span class="text-muted">Qualification Type:</span><br>
                <strong><?php echo qualTypeLabel($app['qual_type'] ?? 'BGCSE', $app['qual_type_other'] ?? ''); ?></strong>
            </div>
            <div><span class="text-muted">Submission Date:</span><br>
                <strong><?php echo $app['submitted_at'] ? date('d M Y, H:i', strtotime($app['submitted_at'])) : 'Not yet submitted'; ?></strong>
            </div>
            <div><span class="text-muted">Application Date:</span><br>
                <strong><?php echo date('d M Y', strtotime($app['created_at'])); ?></strong>
            </div>
            <div><span class="text-muted">Status:</span><br>
                <span class="badge <?php echo $statusBadge[$app['status']] ?? 'badge-pending'; ?>"><?php echo e($app['status']); ?></span>
            </div>
        </div>
    </div>

    <!-- Personal Details -->
    <div class="card">
        <div class="card-title">👤 Personal Details</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;font-size:0.9rem;">
            <div><span class="text-muted">Full Name:</span><br><strong><?php echo e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></strong></div>
            <div><span class="text-muted">Email:</span><br><strong><?php echo e($user['email'] ?? ''); ?></strong></div>
            <div><span class="text-muted">Phone:</span><br><strong><?php echo !empty($user['phone']) ? e($user['phone']) : '—'; ?></strong></div>
            <div><span class="text-muted">Date of Birth:</span><br><strong><?php echo !empty($user['dob']) ? date('d M Y', strtotime($user['dob'])) : '—'; ?></strong></div>
            <div><span class="text-muted">Gender:</span><br><strong><?php echo e($user['gender'] ?? '—'); ?></strong></div>
            <div><span class="text-muted">Nationality:</span><br><strong><?php echo e($user['nationality'] ?? '—'); ?></strong></div>
            <div><span class="text-muted">Omang / ID:</span><br><strong><?php echo !empty($user['omang_number']) ? e($user['omang_number']) : '—'; ?></strong></div>
        </div>
        <div class="mt-16">
            <a href="edit_profile.php" class="btn btn-sm btn-outline-red">✏ Update Personal Info</a>
        </div>
    </div>

    <!-- Academic Results -->
    <div class="card">
        <div class="card-title">📚 Academic Results
            <small class="text-muted" style="font-weight:400;font-size:0.82rem;margin-left:8px;">
                (<?php echo qualTypeLabel($app['qual_type'] ?? 'BGCSE', $app['qual_type_other'] ?? ''); ?>)
            </small>
        </div>
        <?php if (empty($quals)): ?>
            <p class="text-muted">No qualifications recorded.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject</th>
                            <th>Grade</th>
                            <th>Points</th>
                        </tr>
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
                                <small class="text-muted" style="display:block;font-weight:400;font-size:0.75rem;">
                                    max 48 pts
                                </small>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($app['submitted']): ?>
                <div class="alert alert-warning mt-16" style="margin-bottom:0;">
                    <span class="alert-icon">🔒</span>
                    Academic qualifications are locked after submission and cannot be changed.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card">
        <div class="card-title">📎 Uploaded Documents</div>
        <?php if (empty($docs)): ?>
            <p class="text-muted">No documents uploaded yet.</p>
            <?php if (!$app['submitted']): ?>
                <a href="application.php" class="btn btn-sm btn-outline-red mt-8">Upload Documents</a>
            <?php endif; ?>
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
                        <?php if (!$app['submitted']): ?>
                            <form method="POST" action="view_application.php" style="display:inline;"
                                  onsubmit="return confirm('Remove this document?')">
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="delete_doc" value="<?php echo $doc['id']; ?>">
                                <button type="submit" class="btn btn-sm" style="background:#c0392b;color:#fff;border:none;">🗑</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$app['submitted']): ?>
                <p class="text-muted" style="font-size:0.82rem;margin-top:10px;">
                    ⓘ You can remove documents before submitting your application.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Admin Notes (if any) -->
    <?php if (!empty($app['admin_notes'])): ?>
    <div class="card" style="border-top-color:#2980b9;">
        <div class="card-title" style="color:#2980b9;">📝 Message from Admissions Office</div>
        <p style="white-space:pre-wrap;margin:0;"><?php echo e($app['admin_notes']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Back -->
    <div class="flex-gap">
        <a href="dashboard.php" class="btn btn-outline-red">← Dashboard</a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-red">🖨 Print</button>
    </div>

</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
</body>
</html>
