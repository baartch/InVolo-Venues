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
require_once __DIR__ . '/../../config/database.php';
unset($configPath, $examplePath);

$token = $_COOKIE[SESSION_NAME] ?? '';
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
        'Failed to refresh session. token=%s cookies=%s host=%s https=%s forwarded_proto=%s',
        $token,
        json_encode($_COOKIE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $_SERVER['HTTP_HOST'] ?? '',
        $_SERVER['HTTPS'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
    );
    logAction($session ? (int) $session['user_id'] : null, 'auth_check_refresh_failed', $details);
    clearSessionCookie();
    header('Location: ' . BASE_PATH . '/pages/auth/login.php');
    exit;
}

setcookie(SESSION_NAME, $token, buildSessionCookieOptions($expiresAt));

$currentUser = [
    'user_id' => (int) $session['user_id'],
    'username' => $session['username'],
    'role' => $session['role'],
    'ui_theme' => $session['ui_theme'] ?? null,
    'venues_page_size' => $session['venues_page_size'] ?? null
];
