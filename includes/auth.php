<?php
// ============================================================
// includes/auth.php — Authentication helpers
// ============================================================

/**
 * Redirect to login if student is not authenticated.
 */
function requireStudentLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

/**
 * Redirect to admin login if admin is not authenticated.
 */
function requireAdminLogin(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Check if current session belongs to a logged-in student.
 */
function isStudentLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Check if current session belongs to a logged-in admin.
 */
function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

/**
 * Validate password against UB strict password policy:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one digit
 * - At least one special character (!@#$%^&*()_+-=[]{}|;':\",./<>?)
 *
 * Returns an array of error messages, empty if valid.
 */
function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A–Z).";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a–z).";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0–9).";
    }
    if (!preg_match('/[\!\@\#\$\%\^\&\*\(\)\_\+\-\=\[\]\{\}\|\;\'\:\"\,\.\/\<\>\?]/', $password)) {
        $errors[] = "Password must contain at least one special character (e.g. !@#\$%).";
    }
    return $errors;
}

/**
 * Generate and store a CSRF token in session.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate submitted CSRF token against session token.
 */
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize a string for safe HTML output.
 * Accepts null gracefully (treats as empty string).
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
