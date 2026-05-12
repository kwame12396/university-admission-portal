<?php
// ============================================================
// test.php — System Diagnostics (restrict to localhost only)
// ============================================================
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Access denied.');
}
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Diagnostics — UB Admission</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 30px; }
        h2 { color: #c0392b; }
        table { border-collapse: collapse; width: 100%; max-width: 700px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        th, td { padding: 12px 18px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #c0392b; color: #fff; }
        .ok  { color: #27ae60; font-weight: bold; }
        .fail { color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>
<h2>🔧 UB Admission — System Diagnostics</h2>
<table>
    <tr><th>Check</th><th>Result</th></tr>
    <tr>
        <td>PHP Version</td>
        <td class="<?php echo version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'fail'; ?>">
            <?php echo PHP_VERSION; ?> <?php echo version_compare(PHP_VERSION, '8.0', '>=') ? '✔ OK' : '✖ PHP 8.0+ required'; ?>
        </td>
    </tr>
    <tr>
        <td>Database Connection</td>
        <td>
            <?php
            try {
                $test = $pdo->query("SELECT 1");
                echo '<span class="ok">✔ Connected to ' . DB_NAME . ' @ ' . DB_HOST . '</span>';
            } catch (Exception $e) {
                echo '<span class="fail">✖ ' . htmlspecialchars($e->getMessage()) . '</span>';
            }
            ?>
        </td>
    </tr>
    <tr>
        <td>Tables Exist</td>
        <td>
            <?php
            $tables = ['users','admins','applications','academic_qualifications','documents'];
            $missing = [];
            foreach ($tables as $t) {
                $r = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
                if (!$r) $missing[] = $t;
            }
            if (empty($missing)) {
                echo '<span class="ok">✔ All tables present</span>';
            } else {
                echo '<span class="fail">✖ Missing: ' . implode(', ', $missing) . ' — run db/ub_admission.sql</span>';
            }
            ?>
        </td>
    </tr>
    <tr>
        <td>Uploads Directory</td>
        <td>
            <?php
            if (is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR)) {
                echo '<span class="ok">✔ Writable</span>';
            } else {
                echo '<span class="fail">✖ Not writable — chmod 755 uploads/</span>';
            }
            ?>
        </td>
    </tr>
    <tr>
        <td>Session</td>
        <td class="ok">✔ Running (ID: <?php echo substr(session_id(), 0, 12); ?>…)</td>
    </tr>
    <tr>
        <td>Admin Account</td>
        <td>
            <?php
            $a = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            echo $a > 0
                ? '<span class="ok">✔ ' . $a . ' admin(s) registered</span>'
                : '<span class="fail">✖ No admins — run the SQL seed</span>';
            ?>
        </td>
    </tr>
</table>
<p style="margin-top:20px;color:#888;font-size:.85rem;">
    ⚠ This page is only accessible from localhost. Remove or restrict it before deploying to production.
</p>
</body>
</html>
