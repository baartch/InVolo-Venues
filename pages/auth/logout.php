<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src-php/database.php';

$token = $_COOKIE[SESSION_NAME] ?? '';
$session = $token !== '' ? fetchSessionUser($token) : null;
$userId = $session ? (int) $session['user_id'] : null;

if ($token !== '') {
    deleteSession($token);
}

clearSessionCookie();
logAction($userId, 'logout', 'User logged out');

header('Location: ' . BASE_PATH . '/pages/auth/login.php');
exit;
