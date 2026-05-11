<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../models/core/defaults.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/auth/session.php';
require_once __DIR__ . '/../../models/auth/php_session.php';
require_once __DIR__ . '/../../models/auth/cookie_helpers.php';
require_once __DIR__ . '/../../views/core/layout.php';

ensurePhpSessionStarted();

require_once __DIR__ . '/../../models/auth/csrf.php';

$token = getSessionToken();
$existingSession = $token !== '' ? fetchSessionUser($token) : null;
if ($existingSession) {
    $expiresAt = refreshSession($token, $existingSession);
    if ($expiresAt) {
        migrateSessionCookie($token, $expiresAt);
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    }
}

if ($token !== '' && !$existingSession) {
    clearSessionCookie();
}

$error = '';
$step = $_GET['step'] ?? 'email';
$email = strtolower(trim((string) ($_GET['email'] ?? '')));

$errorKey = $_GET['error'] ?? '';
if ($errorKey === 'invalid') {
    $error = 'Please enter a valid email address.';
} elseif ($errorKey === 'unknown') {
    $error = 'unknown email';
} elseif ($errorKey === 'expired') {
    $error = 'Your code has expired. Please request a new one.';
} elseif ($errorKey === 'locked') {
    $error = 'Too many invalid attempts. Please request a new code.';
} elseif ($errorKey === 'send') {
    $error = 'Unable to send the code. Please try again later.';
} elseif ($errorKey !== '') {
    $error = (string) $errorKey;
}

$notice = '';
$noticeKey = $_GET['notice'] ?? '';
if ($noticeKey === 'resent') {
    $notice = 'A new code has been sent.';
}

require __DIR__ . '/../../views/auth/login.php';
