<?php
// Include this file at the top of any protected page
$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    $examplePath = __DIR__ . '/../../config/config.example.php';
    if (file_exists($examplePath)) {
        require_once $examplePath;
    } else {
        http_response_code(500);
        echo 'Configuration file missing. Please create config/config.php from config/config.example.php.';
        exit;
    }
} else {
    require_once $configPath;
}
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/csrf.php';
require_once __DIR__ . '/../../src-php/cookie_helpers.php';
unset($configPath, $examplePath);

// Start session for CSRF token storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = getSessionToken();
$session = fetchSessionUser($token);
if (!$session) {
    $details = sprintf(
        'Missing session. token=%s cookies=%s host=%s https=%s forwarded_proto=%s',
        $token,
        json_encode($_COOKIE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $_SERVER['HTTP_HOST'] ?? '',
        $_SERVER['HTTPS'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
    );
    logAction(null, 'auth_check_failed', $details);
    clearSessionCookie();
    header('Location: ' . BASE_PATH . '/pages/auth/login.php');
    exit;
}

$expiresAt = refreshSession($token);
if (!$expiresAt) {
    $details = sprintf(
        'Failed to refresh session. token=%s host=%s https=%s forwarded_proto=%s',
        $token,
        $_SERVER['HTTP_HOST'] ?? '',
        $_SERVER['HTTPS'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
    );
    logAction($session ? (int) $session['user_id'] : null, 'auth_check_refresh_failed', $details);
    clearSessionCookie();
    header('Location: ' . BASE_PATH . '/pages/auth/login.php');
    exit;
}

// Set/migrate session cookie with secure flags and prefix
migrateSessionCookie($token, $expiresAt);

$currentUser = [
    'user_id' => (int) $session['user_id'],
    'username' => $session['username'],
    'role' => $session['role'],
    'ui_theme' => $session['ui_theme'] ?? null,
    'venues_page_size' => $session['venues_page_size'] ?? null
];
