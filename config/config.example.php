<?php
// Configuration file for venue crawler frontend
// Copy this file to config.php and update the credentials

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'involo_venues');
define('DB_USER', 'dbuservenues');
define('DB_PASSWORD', 'change-this-password'); // CHANGE THIS!

// Base path - auto-detect or set manually
// For example: '' for root, '/venues' for subdirectory
// Auto-detect from current script location
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
// Remove auth, api, or config from path if present
$pathParts = explode('/', trim($scriptPath, '/'));
$lastPart = end($pathParts);
if (in_array($lastPart, ['auth', 'api', 'config'])) {
    array_pop($pathParts);
}
$basePath = implode('/', $pathParts);
define('BASE_PATH', $basePath === '' ? '' : '/' . $basePath);
unset($scriptPath, $pathParts, $lastPart, $basePath);

// Session configuration
// Managed in the database using the sessions table
// Cookie name is used to store the session token
define('SESSION_NAME', 'venue_crawler_session');
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

function buildSessionCookieOptions(int $expiresAt): array
{
    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}
