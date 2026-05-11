<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../models/core/defaults.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/auth/session.php';
require_once __DIR__ . '/../../models/auth/cookie_helpers.php';

$token = getSessionToken();
$session = $token !== '' ? fetchSessionUser($token) : null;
$userId = $session ? (int) $session['user_id'] : null;

if ($token !== '') {
    deleteSession($token);
}

clearSessionCookie();
logAction($userId, 'logout', 'User logged out');

header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php');
exit;
