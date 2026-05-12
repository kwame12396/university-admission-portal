<?php
// ============================================================
// admin/export.php — Export applications as CSV
// ============================================================
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Filters (same as applications.php)
$filterStatus  = $_GET['status']  ?? '';
$filterProgram = $_GET['program'] ?? '';
$search        = trim($_GET['search'] ?? '');

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

// Simple fetch — no correlated subqueries (MariaDB 10.4 compatibility)
$stmt = $pdo->prepare("
    SELECT
        a.id, u.first_name, u.last_name, u.email, u.phone,
        u.dob, u.gender, u.nationality, u.omang_number,
        a.program, a.qual_type, a.qual_type_other,
        a.status, a.submitted, a.submitted_at, a.created_at
    FROM applications a
    JOIN users u ON a.user_id = u.id
    $whereSQL
    ORDER BY a.submitted DESC, a.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Enrich each row with computed values via PHP
foreach ($rows as &$row) {
    $appId = $row['id'];
    $row['total_points']  = getTotalPoints($pdo, $appId);
    $row['subject_count'] = count(getQualifications($pdo, $appId));
    $row['doc_count']     = count(getDocuments($pdo, $appId));
}
unset($row);

// Generate filename
$filename = 'ub_admission_export_' . date('Ymd_His') . '.csv';

// Output CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'Ref #', 'First Name', 'Last Name', 'Email', 'Phone',
    'Date of Birth', 'Gender', 'Nationality', 'Omang / ID',
    'Programme', 'Qual Type', 'Qual Type (Other)',
    'Status', 'Submitted', 'Submitted At', 'Applied On',
    'Admission Score (top 6)', 'Subjects Entered', 'Docs Uploaded',
]);

// Data rows
foreach ($rows as $row) {
    fputcsv($out, [
        'UB-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone'] ?? '',
        $row['dob']   ?? '',
        $row['gender'] ?? '',
        $row['nationality'] ?? '',
        $row['omang_number'] ?? '',
        $row['program'],
        $row['qual_type'] ?? '',
        $row['qual_type_other'] ?? '',
        $row['status'],
        $row['submitted'] ? 'Yes' : 'No',
        $row['submitted_at'] ?? '',
        $row['created_at'],
        $row['total_points'],
        $row['subject_count'],
        $row['doc_count'],
    ]);
}

fclose($out);
exit;
