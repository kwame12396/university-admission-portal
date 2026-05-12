<?php
// ============================================================
// setup_db.php — One-time database installer
// Run once at http://localhost/ub_admission/setup_db.php
// DELETE this file after setup is complete.
// ============================================================

// Connect without specifying a database first
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3307;charset=utf8mb4",
        "root", "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("<pre style='color:red'>Connection failed: " . $e->getMessage() . "</pre>");
}

$steps = [];
$errors = [];

function runSQL(PDO $pdo, string $label, string $sql, array &$steps, array &$errors): void {
    try {
        $pdo->exec($sql);
        $steps[] = "✔ $label";
    } catch (PDOException $e) {
        // Ignore "already exists" type errors
        if (strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'Duplicate') !== false) {
            $steps[] = "⚠ $label (already exists — skipped)";
        } else {
            $errors[] = "✖ $label: " . $e->getMessage();
        }
    }
}

// 1. Create database
runSQL($pdo, "Create database ub_admission",
    "CREATE DATABASE IF NOT EXISTS ub_admission CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
    $steps, $errors);

// 2. Select it
runSQL($pdo, "Select ub_admission", "USE ub_admission", $steps, $errors);

// 3. Create tables
runSQL($pdo, "Create table: users", "
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(100)    NOT NULL,
    last_name       VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password        VARCHAR(255)    NOT NULL,
    phone           VARCHAR(20),
    dob             DATE,
    gender          ENUM('Male','Female','Other'),
    nationality     VARCHAR(100)    DEFAULT 'Motswana',
    omang_number    VARCHAR(20),
    address         TEXT,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps, $errors);

runSQL($pdo, "Create table: admins", "
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    full_name   VARCHAR(200),
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps, $errors);

runSQL($pdo, "Create table: applications", "
CREATE TABLE IF NOT EXISTS applications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT             NOT NULL,
    program             VARCHAR(200)    NOT NULL DEFAULT 'BSc General',
    qual_type           ENUM('IGCSE','BGCSE','IB','Matric','Other') DEFAULT 'BGCSE',
    qual_type_other     VARCHAR(150)    DEFAULT NULL,
    status              ENUM('Pending','Under Review','Accepted','Rejected') DEFAULT 'Pending',
    submitted           TINYINT(1)      DEFAULT 0,
    submitted_at        TIMESTAMP       NULL,
    admin_notes         TEXT            DEFAULT NULL,
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps, $errors);

runSQL($pdo, "Create table: academic_qualifications", "
CREATE TABLE IF NOT EXISTS academic_qualifications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    application_id      INT         NOT NULL,
    subject             VARCHAR(150) NOT NULL,
    grade               ENUM('A*','A','B','C','D','E','U') NOT NULL,
    points              INT         NOT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps, $errors);

runSQL($pdo, "Create table: documents", "
CREATE TABLE IF NOT EXISTS documents (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    application_id      INT         NOT NULL,
    doc_type            ENUM('Omang/ID','BGCSE Certificate','IGCSE Certificate','IB Certificate','Matric Certificate','Transcript','Birth Certificate','Other') NOT NULL,
    original_name       VARCHAR(255) NOT NULL,
    file_path           VARCHAR(500) NOT NULL,
    uploaded_at         TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps, $errors);

runSQL($pdo, "Create table: password_resets", "
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT         NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    expires_at  DATETIME    NOT NULL,
    used        TINYINT(1)  DEFAULT 0,
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps, $errors);

runSQL($pdo, "Seed default admin account",
    "INSERT IGNORE INTO admins (username, password, full_name) VALUES ('admin', '\$2y\$12\$xM.rJm/VSqEJE6G.06f.Lu3jJgnccPkHDZN1BdstY0TbcUG5Q6tEW', 'System Administrator')",
    $steps, $errors);

$success = empty($errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Setup — UB Admission</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; display: flex; justify-content: center; padding: 40px 20px; }
        .box { background: #fff; border-radius: 10px; padding: 36px 44px; box-shadow: 0 4px 20px rgba(0,0,0,.10); max-width: 560px; width: 100%; }
        h2 { color: #c0392b; margin-top: 0; }
        ul { list-style: none; padding: 0; line-height: 2; }
        .ok   { color: #27ae60; }
        .warn { color: #e67e22; }
        .err  { color: #c0392b; }
        .btn  { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #c0392b; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 700; }
        .success-banner { background: #eafaf1; border: 1px solid #27ae60; border-radius: 6px; padding: 14px 18px; margin-bottom: 20px; color: #1e8449; font-weight: 600; }
        .error-banner   { background: #fdf2f2; border: 1px solid #c0392b; border-radius: 6px; padding: 14px 18px; margin-bottom: 20px; color: #c0392b; font-weight: 600; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔧 UB Admission — Database Setup</h2>

    <?php if ($success): ?>
        <div class="success-banner">✔ Database setup completed successfully!</div>
    <?php else: ?>
        <div class="error-banner">⚠ Setup completed with errors — see details below.</div>
    <?php endif; ?>

    <strong>Steps:</strong>
    <ul>
        <?php foreach ($steps as $s): ?>
            <li class="<?php echo str_starts_with($s,'✔') ? 'ok' : 'warn'; ?>"><?php echo htmlspecialchars($s); ?></li>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <li class="err"><?php echo htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($success): ?>
        <p style="color:#555;font-size:.9rem;">
            ✔ Admin login: <strong>admin</strong> / <strong>Admin@UB2024!</strong><br>
            ⚠ Please change the admin password after first login.<br>
            🗑 Delete <code>setup_db.php</code> after setup.
        </p>
        <a href="http://localhost/ub_admission/" class="btn">Go to UB Admission Portal →</a>
    <?php else: ?>
        <a href="setup_db.php" class="btn" style="background:#888;">Try Again</a>
    <?php endif; ?>
</div>
</body>
</html>
