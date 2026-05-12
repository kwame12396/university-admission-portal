<?php
// ============================================================
// index.php — Landing Page
// University of Botswana Online Admission System
// ============================================================
require_once 'config.php';
require_once 'includes/auth.php';

// Redirect logged-in users to their dashboards
if (isStudentLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}
if (isAdminLoggedIn()) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University of Botswana — Online Admission System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="logo-circle">UB</div>
        UB Admission Portal
    </a>
    <ul class="navbar-nav">
        <li><a href="login.php">Login</a></li>
        <li><a href="signup.php" class="btn-nav">Apply Now</a></li>
    </ul>
</nav>

<!-- Hero Section -->
<section class="hero">
    <h1>University of Botswana<br>Online Admission System</h1>
    <p>Apply for admission to the University of Botswana from the comfort of your home.
       Submit your application, upload documents, and track your status online.</p>
    <div class="hero-buttons">
        <a href="signup.php" class="btn btn-outline">Start Application</a>
        <a href="login.php"  class="btn btn-primary" style="background:white;color:var(--red);border-color:white;">Already Applied? Login</a>
    </div>
</section>

<!-- Info Cards -->
<main class="main-content">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:8px;">

        <div class="card" style="border-top-color:#C0392B;">
            <div class="card-title">📋 How to Apply</div>
            <ol style="padding-left:18px;line-height:2;">
                <li>Create an account (Sign Up)</li>
                <li>Complete your personal details</li>
                <li>Enter your BGCSE results</li>
                <li>Upload your certificates &amp; Omang</li>
                <li>Submit your application</li>
            </ol>
        </div>

        <div class="card">
            <div class="card-title">📚 Available Programmes</div>
            <ul style="list-style:none;line-height:2;font-size:0.9rem;">
                <li>🎓 BSc General</li>
                <li>🎓 BSc Computer Science</li>
                <li>🎓 BSc Mathematics</li>
                <li>🎓 BA Social Sciences</li>
                <li>🎓 BEd Education</li>
                <li>🎓 BEng Engineering</li>
                <li style="color:var(--grey-mid)">…and many more</li>
            </ul>
        </div>

        <div class="card">
            <div class="card-title">⚠️ Important Notes</div>
            <ul style="list-style:none;line-height:2;font-size:0.9rem;">
                <li>✔ All fields marked <span class="text-red">*</span> are required</li>
                <li>✔ Documents must be PDF, JPG, or PNG (max 5MB each)</li>
                <li>✔ You may update personal info after submission</li>
                <li>✖ Academic qualifications <strong>cannot</strong> be changed after submission</li>
                <li>✔ Use a valid email — notifications will be sent there</li>
            </ul>
        </div>

    </div>

    <div class="text-center mt-24">
        <p class="text-muted" style="margin-bottom:14px;">Ready to begin your academic journey?</p>
        <a href="signup.php" class="btn btn-primary">Create Account &amp; Apply</a>
        &nbsp;&nbsp;
        <a href="login.php"  class="btn btn-outline-red">Login to Existing Account</a>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <span>University of Botswana</span> — Online Admission System.
       Department of Computer Science &nbsp;|&nbsp; All rights reserved.</p>
</footer>

</body>
</html>
