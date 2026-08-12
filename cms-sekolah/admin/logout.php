<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Hapus semua data session
$_SESSION = [];

// Hapus session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect ke halaman login
header('Location: /cms-sekolah/admin/login.php');
exit;
