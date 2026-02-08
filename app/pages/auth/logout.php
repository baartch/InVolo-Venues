<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../src-php/core/defaults.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/auth/cookie_helpers.php';

$token = getSessionToken();
$session = $token !== '' ? fetchSessionUser($token) : null;
$userId = $session ? (int) $session['user_id'] : null;

if ($token !== '') {
    deleteSession($token);
}

clearSessionCookie();
logAction($userId, 'logout', 'User logged out');

header('Location: ' . BASE_PATH . '/app/pages/auth/login.php');
exit;
