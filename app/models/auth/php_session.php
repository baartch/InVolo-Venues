<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../core/defaults.php';

/**
 * Start the native PHP session with app-level lifetime and cookie settings.
 */
function ensurePhpSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $lifetime = defined('SESSION_MAX_LIFETIME') ? (int) SESSION_MAX_LIFETIME : 0;
    $lifetime = max(0, $lifetime);
    $isSecure = isSecureConnection();

    // Keep the PHP session cookie separate from the auth token cookie.
    $cookieName = $isSecure ? '__Host-booking_php_session' : 'booking_php_session';
    session_name($cookieName);

    ini_set('session.gc_maxlifetime', (string) $lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}
