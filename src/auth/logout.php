<?php
// Sign out of the current account.
require __DIR__ . '/../../includes/auth-check.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

session_start();
set_flash('success', 'You have been signed out.');
header('Location: login.php');
exit;
