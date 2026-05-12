<?php
// ============================================================
// application.php — Student Application Form
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireStudentLogin();

$userId = $_SESSION['user_id'];
$app    = getApplicationByUser($pdo, $userId);
$errors  = [];
$success = '';

// If already submitted, redirect to read-only view
if ($app && $app['submitted']) {
    header('Location: ' . SITE_URL . '/view_application.php');
    exit;
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission. Please try again.";
    } else {
        $action       = $_POST['action']          ?? 'save';
        $program      = trim($_POST['program']     ?? '');
        $qualType     = trim($_POST['qual_type']   ?? 'BGCSE');
        $qualOther    = trim($_POST['qual_other']  ?? '');

        if (empty($program))  $errors[] = "Please select a programme.";
        if (empty($qualType)) $errors[] = "Please select a qualification type.";
        if ($qualType === 'Other' && empty($qualOther)) {
            $errors[] = "Please specify your qualification type in the 'Other' field.";
        }

        // Qualifications
        $qualifications = $_POST['qualifications'] ?? [];
        $validQuals = [];
        foreach ($qualifications as $q) {
            $subj  = trim($q['subject'] ?? '');
            $grade = trim($q['grade']   ?? '');
            if ($subj && $grade) {
                $validQuals[] = [
                    'subject' => $subj,
                    'grade'   => $grade,
                    'points'  => gradeToPoints($grade),
                ];
            }
        }

        if (count($validQuals) < 5) {
            $errors[] = "Please enter at least 5 subject results.";
        }

        if (empty($errors)) {
            // Create or update application record
            if (!$app) {
                $stmt = $pdo->prepare("
                    INSERT INTO applications (user_id, program, qual_type, qual_type_other)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $program, $qualType,
                    $qualType === 'Other' ? $qualOther : null]);
                $appId = (int)$pdo->lastInsertId();
            } else {
                $appId = $app['id'];
                $pdo->prepare("
                    UPDATE applications SET program = ?, qual_type = ?, qual_type_other = ?
                    WHERE id = ?
                ")->execute([$program, $qualType,
                    $qualType === 'Other' ? $qualOther : null,
                    $appId]);
                // Delete old quals to re-insert fresh
                $pdo->prepare("DELETE FROM academic_qualifications WHERE application_id = ?")->execute([$appId]);
            }

            // Insert qualifications
            $stmtQ = $pdo->prepare("
                INSERT INTO academic_qualifications (application_id, subject, grade, points)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($validQuals as $q) {
                $stmtQ->execute([$appId, $q['subject'], $q['grade'], $q['points']]);
            }

            // Handle document uploads
            $uploadErrors = [];
            if (!empty($_FILES['documents']['name'][0])) {
                foreach ($_FILES['documents']['name'] as $i => $name) {
                    if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    $file = [
                        'name'     => $name,
                        'type'     => $_FILES['documents']['type'][$i],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                        'error'    => $_FILES['documents']['error'][$i],
                        'size'     => $_FILES['documents']['size'][$i],
                    ];
                    $docType = trim($_POST['doc_types'][$i] ?? 'Other');
                    $result  = handleFileUpload($file, $appId, $docType);
                    if (is_string($result)) {
                        $uploadErrors[] = $result;
                    } else {
                        $pdo->prepare("
                            INSERT INTO documents (application_id, doc_type, original_name, file_path)
                            VALUES (?, ?, ?, ?)
                        ")->execute([$appId, $docType, $result['original'], $result['path']]);
                    }
                }
            }
            if (!empty($uploadErrors)) {
                $errors = array_merge($errors, $uploadErrors);
            }

            // Submit
            if ($action === 'submit' && empty($errors)) {
                $pdo->prepare("UPDATE applications SET submitted = 1, submitted_at = NOW() WHERE id = ?")->execute([$appId]);
                header('Location: ' . SITE_URL . '/view_application.php?submitted=1');
                exit;
            }

            if (empty($errors)) {
                $success = "Application saved as draft. Complete all sections and submit when ready.";
                $app = getApplicationByUser($pdo, $userId);
            }
        }
    }
}

// Load existing data
$existingQuals = $app ? getQualifications($pdo, $app['id']) : [];
$existingDocs  = $app ? getDocuments($pdo, $app['id']) : [];
$programs      = getPrograms();

$currentQualType  = $app['qual_type']       ?? 'BGCSE';
$currentQualOther = $app['qual_type_other'] ?? '';

// All subject lists sent to JS
$subjectMap = [
    'BGCSE'  => getSubjectsByQualType('BGCSE'),
    'IGCSE'  => getSubjectsByQualType('IGCSE'),
    'IB'     => getSubjectsByQualType('IB'),
    'Matric' => getSubjectsByQualType('Matric'),
    'Other'  => getAllSubjects(),
];

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Application — UB Admission Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .points-scoreboard {
            background: var(--red);
            color: #fff;
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
            transition: background 0.4s;
        }
        .points-scoreboard .score-label { font-size: 0.9rem; opacity: 0.9; }
        .points-scoreboard .score-value { font-size: 2rem; font-weight: 900; line-height: 1; }
        .points-scoreboard .score-note  { font-size: 0.8rem; opacity: 0.8; margin-top: 2px; }

        #otherQualBox { display: none; margin-top: 10px; }
        #otherQualBox.visible { display: block; }

        .qual-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
            padding: 12px;
            background: var(--off-white);
            border-radius: var(--radius);
            border: 1px solid var(--grey-light);
            transition: border-color 0.2s, background 0.2s;
        }
        .qual-row.top6 {
            border-color: var(--red);
            background: #fff5f5;
        }
        .qual-row .btn-remove {
            background: none; border: none;
            color: var(--red); cursor: pointer;
            font-size: 1.2rem; padding: 8px 10px;
            border-radius: 4px;
            transition: background var(--transition);
            align-self: flex-end;
            margin-bottom: 2px;
        }
        .qual-row .btn-remove:hover { background: var(--red-pale); }
        .top6-tag {
            font-size: 0.7rem;
            background: var(--red);
            color: #fff;
            border-radius: 10px;
            padding: 1px 7px;
            margin-left: 6px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="application.php" class="active">My Application</a></li>
        <li><a href="edit_profile.php">My Profile</a></li>
        <li><a href="logout.php" onclick="return confirm('Logout?')">Logout</a></li>
    </ul>
</nav>

<main class="main-content">
    <h1 class="page-title">My Application</h1>
    <p class="page-subtitle">Complete all sections and submit before the deadline</p>

    <!-- Progress Steps -->
    <div class="steps">
        <div class="step done">
            <div class="step-circle">✔</div>
            <div class="step-label">Account Created</div>
        </div>
        <div class="step <?php echo $app ? 'active' : ''; ?>">
            <div class="step-circle">2</div>
            <div class="step-label">Programme &amp; Qualifications</div>
        </div>
        <div class="step <?php echo ($app && count($existingDocs) > 0) ? 'active' : ''; ?>">
            <div class="step-circle">3</div>
            <div class="step-label">Upload Documents</div>
        </div>
        <div class="step">
            <div class="step-circle">4</div>
            <div class="step-label">Submit</div>
        </div>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><span class="alert-icon">✖</span> <?php echo e($err); ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><span class="alert-icon">✔</span> <?php echo e($success); ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <span class="alert-icon">ℹ</span>
        <strong>Important:</strong> Once submitted, your <strong>academic qualifications cannot be changed</strong>.
        Personal information can be updated at any time from your profile.
    </div>

    <form method="POST" action="application.php" enctype="multipart/form-data" id="appForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
        <input type="hidden" name="action"     id="formAction" value="save">

        <!-- ================================================
             SECTION 1: Programme
             ================================================ -->
        <div class="card">
            <div class="card-title">🎓 Programme Selection</div>
            <div class="form-group">
                <label for="program">Choose Programme <span class="required">*</span></label>
                <select id="program" name="program" class="form-control" required>
                    <option value="">— Select a Programme —</option>
                    <?php
                    $currentProgram = $app['program'] ?? '';
                    // Group programmes into faculties by prefix
                    $facMap = [
                        'Faculty of Science'                  => [],
                        'Faculty of Engineering & Technology' => [],
                        'Faculty of Business'                 => [],
                        'Faculty of Humanities'               => [],
                        'Faculty of Education'                => [],
                        'Faculty of Health Sciences'          => [],
                        'Faculty of Law'                      => [],
                        'Faculty of Social Sciences'          => [],
                        'Faculty of Architecture & Planning'  => [],
                        'Postgraduate'                        => [],
                        'Other'                               => [],
                    ];
                    $prefixFac = [
                        'BSc'      => 'Faculty of Science',
                        'BEng'     => 'Faculty of Engineering & Technology',
                        'BTech'    => 'Faculty of Engineering & Technology',
                        'BBA'      => 'Faculty of Business',
                        'BCom'     => 'Faculty of Business',
                        'BA '      => 'Faculty of Humanities',
                        'BEd'      => 'Faculty of Education',
                        'MBBS'     => 'Faculty of Health Sciences',
                        'BNS'      => 'Faculty of Health Sciences',
                        'BPharm'   => 'Faculty of Health Sciences',
                        'BDS'      => 'Faculty of Health Sciences',
                        'LLB'      => 'Faculty of Law',
                        'BSocSci'  => 'Faculty of Social Sciences',
                        'BArch'    => 'Faculty of Architecture & Planning',
                        'Postgrad' => 'Postgraduate',
                        'MSc'      => 'Postgraduate',
                        'MBA'      => 'Postgraduate',
                    ];
                    foreach ($programs as $p) {
                        $placed = false;
                        foreach ($prefixFac as $prefix => $fac) {
                            if (str_starts_with($p, $prefix)) {
                                $facMap[$fac][] = $p;
                                $placed = true; break;
                            }
                        }
                        if (!$placed) $facMap['Other'][] = $p;
                    }
                    foreach ($facMap as $fac => $progs):
                        if (empty($progs)) continue;
                    ?>
                    <optgroup label="<?php echo e($fac); ?>">
                        <?php foreach ($progs as $p): ?>
                            <option value="<?php echo e($p); ?>"
                                <?php echo ($currentProgram === $p) ? 'selected' : ''; ?>>
                                <?php echo e($p); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ================================================
             SECTION 2: Qualification Type
             ================================================ -->
        <div class="card">
            <div class="card-title">📜 Type of Qualification</div>
            <p class="text-muted" style="font-size:0.88rem;margin-bottom:16px;">
                Select the qualification you are applying with.
                This updates the subject list and document types below.
            </p>

            <div class="form-group">
                <label for="qual_type">Qualification Type <span class="required">*</span></label>
                <select id="qual_type" name="qual_type" class="form-control" required>
                    <option value="IGCSE"  <?php echo $currentQualType === 'IGCSE'  ? 'selected' : ''; ?>>
                        IGCSE — International General Certificate of Secondary Education
                    </option>
                    <option value="BGCSE"  <?php echo $currentQualType === 'BGCSE'  ? 'selected' : ''; ?>>
                        BGCSE — Botswana General Certificate of Secondary Education
                    </option>
                    <option value="IB"     <?php echo $currentQualType === 'IB'     ? 'selected' : ''; ?>>
                        IB — International Baccalaureate
                    </option>
                    <option value="Matric" <?php echo $currentQualType === 'Matric' ? 'selected' : ''; ?>>
                        Matric — South African National Senior Certificate
                    </option>
                    <option value="Other"  <?php echo $currentQualType === 'Other'  ? 'selected' : ''; ?>>
                        Other — Please specify below
                    </option>
                </select>
            </div>

            <div id="otherQualBox" class="<?php echo $currentQualType === 'Other' ? 'visible' : ''; ?>">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="qual_other">Specify Your Qualification <span class="required">*</span></label>
                    <input type="text" id="qual_other" name="qual_other" class="form-control"
                           placeholder="e.g. Cambridge A-Levels, French Baccalauréat, GED…"
                           value="<?php echo e($currentQualOther); ?>" maxlength="150">
                    <span class="form-hint">Type the full name of your qualification.</span>
                </div>
            </div>
        </div>

        <!-- ================================================
             SECTION 3: Academic Results
             ================================================ -->
        <div class="card">
            <div class="card-title">📚 Academic Results</div>

            <!-- Live scoreboard -->
            <div class="points-scoreboard" id="scoreboard">
                <div>
                    <div class="score-label">Your Admission Score</div>
                    <div class="score-note" id="scoreNote">Top 6 subjects · Maximum 48 pts</div>
                </div>
                <div style="text-align:right;">
                    <div class="score-value">
                        <span id="liveScore">0</span>
                        <span style="font-size:1rem;opacity:0.7;"> / 48</span>
                    </div>
                </div>
            </div>

            <p class="text-muted" style="font-size:0.88rem;margin-bottom:16px;">
                Enter all your subjects. Your score is calculated from your
                <strong>6 highest-scoring subjects</strong> (maximum <strong>48 points</strong>).
                Highlighted rows <span style="background:var(--red);color:#fff;border-radius:4px;padding:1px 6px;font-size:0.8rem;">●</span>
                are your top 6.
                Points: A*&nbsp;=&nbsp;8, A&nbsp;=&nbsp;7, B&nbsp;=&nbsp;6, C&nbsp;=&nbsp;5,
                D&nbsp;=&nbsp;4, E&nbsp;=&nbsp;3, U&nbsp;=&nbsp;0.
            </p>

            <div id="qualContainer">
                <?php
                $rowsToShow = !empty($existingQuals)
                    ? $existingQuals
                    : array_fill(0, 5, ['subject' => '', 'grade' => '']);
                foreach ($rowsToShow as $i => $q):
                    $subjList = getSubjectsByQualType($currentQualType);
                ?>
                <div class="qual-row" data-index="<?php echo $i; ?>">
                    <div>
                        <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">
                            Subject
                        </label>
                        <select name="qualifications[<?php echo $i; ?>][subject]"
                                class="form-control subject-select" required>
                            <option value="">— Select Subject —</option>
                            <?php foreach ($subjList as $s): ?>
                                <option value="<?php echo e($s); ?>"
                                    <?php echo (($q['subject'] ?? '') === $s) ? 'selected' : ''; ?>>
                                    <?php echo e($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px;">
                            Grade
                        </label>
                        <select name="qualifications[<?php echo $i; ?>][grade]"
                                class="form-control grade-select" required>
                            <option value="">— Grade —</option>
                            <?php foreach (['A*'=>8,'A'=>7,'B'=>6,'C'=>5,'D'=>4,'E'=>3,'U'=>0] as $g => $pts): ?>
                                <option value="<?php echo $g; ?>"
                                    <?php echo (($q['grade'] ?? '') === $g) ? 'selected' : ''; ?>>
                                    <?php echo "$g ($pts pts)"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn-remove" title="Remove subject">✖</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-16">
                <button type="button" id="addSubjectBtn" class="btn btn-outline-red btn-sm">
                    ＋ Add Subject
                </button>
                <span class="text-muted" style="font-size:0.8rem;margin-left:10px;">
                    Up to 20 subjects; only the top 6 count toward your score.
                </span>
            </div>
        </div>

        <!-- ================================================
             SECTION 4: Documents
             ================================================ -->
        <div class="card">
            <div class="card-title">📎 Upload Supporting Documents</div>
            <p class="text-muted" style="font-size:0.88rem;margin-bottom:16px;">
                Upload your ID and qualification certificates.
                Accepted: <strong>PDF, JPG, PNG</strong> — max 5 MB each.
            </p>

            <?php if (!empty($existingDocs)): ?>
                <div style="margin-bottom:20px;">
                    <div style="font-weight:600;font-size:0.9rem;margin-bottom:8px;">Already Uploaded:</div>
                    <ul class="doc-list">
                        <?php foreach ($existingDocs as $doc): ?>
                            <li>
                                <span class="doc-icon">📄</span>
                                <div>
                                    <strong><?php echo e($doc['doc_type']); ?></strong>
                                    — <?php echo e($doc['original_name']); ?>
                                    <small class="text-muted">
                                        (<?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?>)
                                    </small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php for ($d = 0; $d < 3; $d++): ?>
            <div style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:8px;">
                    <label>Document Type (Upload <?php echo $d + 1; ?>)</label>
                    <select name="doc_types[<?php echo $d; ?>]"
                            class="form-control doc-type-select">
                        <?php foreach (getDocTypes($currentQualType) as $dt): ?>
                            <option value="<?php echo e($dt); ?>"><?php echo e($dt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="file-upload-area" id="uploadArea<?php echo $d; ?>">
                    <input type="file" name="documents[<?php echo $d; ?>]"
                           accept=".pdf,.jpg,.jpeg,.png">
                    <div class="file-upload-icon">📂</div>
                    <div class="file-upload-text">
                        <strong>Click to browse</strong> or drag &amp; drop<br>
                        <small>PDF, JPG, PNG — max 5 MB</small>
                    </div>
                    <ul class="file-list"></ul>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- ================================================
             SECTION 5: Actions
             ================================================ -->
        <div class="card">
            <div class="flex-between" style="flex-wrap:wrap;gap:12px;">
                <a href="dashboard.php" class="btn btn-outline-red">← Back to Dashboard</a>
                <div class="flex-gap">
                    <button type="submit" class="btn btn-outline-red"
                            onclick="document.getElementById('formAction').value='save'">
                        💾 Save Draft
                    </button>
                    <button type="submit" class="btn btn-primary"
                            onclick="if(!confirm('Submit your application?\n\nAcademic qualifications CANNOT be changed after submission.')) return false; document.getElementById('formAction').value='submit'">
                        ✔ Submit Application
                    </button>
                </div>
            </div>
        </div>

    </form>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System</p>
</footer>

<script src="js/validate.js"></script>
<script>
// ---- Data from PHP ----
const SUBJECT_MAP  = <?php echo json_encode($subjectMap, JSON_UNESCAPED_UNICODE); ?>;
const GRADE_POINTS = { 'A*': 8, 'A': 7, 'B': 6, 'C': 5, 'D': 4, 'E': 3, 'U': 0 };
const DOC_TYPES_MAP = <?php echo json_encode([
    'IGCSE'  => getDocTypes('IGCSE'),
    'BGCSE'  => getDocTypes('BGCSE'),
    'IB'     => getDocTypes('IB'),
    'Matric' => getDocTypes('Matric'),
    'Other'  => getDocTypes('Other'),
], JSON_UNESCAPED_UNICODE); ?>;

// ---- DOM refs ----
const qualTypeSelect = document.getElementById('qual_type');
const otherQualBox   = document.getElementById('otherQualBox');
const qualOtherInput = document.getElementById('qual_other');
const container      = document.getElementById('qualContainer');
const addBtn         = document.getElementById('addSubjectBtn');
const liveScore      = document.getElementById('liveScore');
const scoreNote      = document.getElementById('scoreNote');
const scoreboard     = document.getElementById('scoreboard');

// ============================================================
// Qualification type change handler
// ============================================================
qualTypeSelect.addEventListener('change', function () {
    const type = this.value;

    // Toggle "Other" text box
    if (type === 'Other') {
        otherQualBox.classList.add('visible');
        qualOtherInput.required = true;
    } else {
        otherQualBox.classList.remove('visible');
        qualOtherInput.required = false;
        qualOtherInput.value = '';
    }

    // Rebuild all subject selects for the new qual type
    const subjects = SUBJECT_MAP[type] || SUBJECT_MAP['Other'];
    container.querySelectorAll('.qual-row').forEach(row => {
        const sel = row.querySelector('.subject-select');
        const cur = sel.value;
        sel.innerHTML = '<option value="">— Select Subject —</option>';
        subjects.forEach(s => {
            const o = new Option(s, s, false, s === cur);
            sel.appendChild(o);
        });
    });

    // Rebuild document type selects
    const docTypes = DOC_TYPES_MAP[type] || DOC_TYPES_MAP['Other'];
    document.querySelectorAll('.doc-type-select').forEach(sel => {
        const cur = sel.value;
        sel.innerHTML = '';
        docTypes.forEach(dt => {
            const o = new Option(dt, dt, false, dt === cur);
            sel.appendChild(o);
        });
    });

    recalcScore();
});

// ============================================================
// Live score: sum of top-6 subject points, max 48
// ============================================================
function recalcScore() {
    const rows = Array.from(container.querySelectorAll('.qual-row'));

    // Gather points + row index pairs
    const rowData = rows.map((row, idx) => {
        const g = row.querySelector('.grade-select')?.value || '';
        return { idx, pts: g ? (GRADE_POINTS[g] ?? 0) : -1 };
    });

    // Rows that have a grade selected
    const graded = rowData.filter(r => r.pts >= 0);

    // Sort descending by points
    const sorted = [...graded].sort((a, b) => b.pts - a.pts);
    const top6Set = new Set(sorted.slice(0, 6).map(r => r.idx));
    const total   = sorted.slice(0, 6).reduce((sum, r) => sum + r.pts, 0);

    // Update scoreboard
    liveScore.textContent = total;

    if (total >= 40)      scoreboard.style.background = '#27AE60';
    else if (total >= 28) scoreboard.style.background = '#E67E22';
    else                  scoreboard.style.background = 'var(--red)';

    // Highlight top-6 rows
    rows.forEach((row, idx) => {
        if (top6Set.has(idx)) {
            row.classList.add('top6');
        } else {
            row.classList.remove('top6');
        }
    });

    // Note text
    if (graded.length === 0) {
        scoreNote.textContent = 'Enter grades to see your score — Top 6 · Max 48 pts';
    } else if (graded.length < 6) {
        const need = 6 - graded.length;
        scoreNote.textContent = `Add ${need} more subject${need > 1 ? 's' : ''} to fill your top 6`;
    } else if (graded.length === 6) {
        scoreNote.textContent = 'All 6 subjects count toward your score';
    } else {
        scoreNote.textContent = `${graded.length} subjects entered — top 6 highlighted in red`;
    }
}

container.addEventListener('change', e => {
    if (e.target.classList.contains('grade-select')) recalcScore();
});

// ============================================================
// Add / remove subject rows
// ============================================================
let rowCount = container.querySelectorAll('.qual-row').length;

addBtn.addEventListener('click', function () {
    if (rowCount >= 20) { alert('Maximum 20 subjects allowed.'); return; }
    const type     = qualTypeSelect.value;
    const subjects = SUBJECT_MAP[type] || SUBJECT_MAP['Other'];
    container.appendChild(buildQualRow(rowCount, subjects));
    rowCount++;
    recalcScore();
});

container.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-remove');
    if (!btn) return;
    if (container.querySelectorAll('.qual-row').length <= 1) {
        alert('You must have at least one subject row.');
        return;
    }
    btn.closest('.qual-row').remove();
    rowCount--;
    renumberRows();
    recalcScore();
});

function buildQualRow(index, subjects) {
    const row = document.createElement('div');
    row.className = 'qual-row';
    row.dataset.index = index;

    // Subject
    const sd = document.createElement('div');
    const sl = document.createElement('label');
    sl.textContent = 'Subject';
    sl.style.cssText = 'font-size:.85rem;font-weight:600;display:block;margin-bottom:4px;';
    const ss = document.createElement('select');
    ss.className = 'form-control subject-select';
    ss.name = `qualifications[${index}][subject]`;
    ss.required = true;
    ss.appendChild(new Option('— Select Subject —', ''));
    subjects.forEach(s => ss.appendChild(new Option(s, s)));
    sd.appendChild(sl); sd.appendChild(ss);

    // Grade
    const gd = document.createElement('div');
    const gl = document.createElement('label');
    gl.textContent = 'Grade';
    gl.style.cssText = 'font-size:.85rem;font-weight:600;display:block;margin-bottom:4px;';
    const gs = document.createElement('select');
    gs.className = 'form-control grade-select';
    gs.name = `qualifications[${index}][grade]`;
    gs.required = true;
    gs.appendChild(new Option('— Grade —', ''));
    Object.entries(GRADE_POINTS).forEach(([g, p]) => gs.appendChild(new Option(`${g} (${p} pts)`, g)));
    gd.appendChild(gl); gd.appendChild(gs);

    // Remove btn
    const rb = document.createElement('button');
    rb.type = 'button';
    rb.className = 'btn-remove';
    rb.title = 'Remove subject';
    rb.textContent = '✖';
    rb.style.cssText = 'align-self:flex-end;margin-bottom:2px;';

    row.appendChild(sd); row.appendChild(gd); row.appendChild(rb);
    return row;
}

function renumberRows() {
    container.querySelectorAll('.qual-row').forEach((row, i) => {
        row.dataset.index = i;
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
        });
    });
}

// ============================================================
// File upload drag-and-drop
// ============================================================
<?php for ($d = 0; $d < 3; $d++): ?>
initFileUpload('uploadArea<?php echo $d; ?>');
<?php endfor; ?>

// ---- Init ----
recalcScore();
</script>
</body>
</html>
