<?php
// ============================================================
// admin/logout.php — Destroy admin session
// ============================================================
require_once '../config.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: ' . SITE_URL . '/admin/login.php');
exit;
