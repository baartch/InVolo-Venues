<?php
// Configuration file for venue crawler frontend
// Change these credentials for production!

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'involo_venues');
define('DB_USER', 'dbuservenues');
define('DB_PASSWORD', 'CGa?655Amcz#kzgl');

// Encryption key for settings values (32+ chars recommended)
define('ENCRYPTION_KEY', 'change-this-encryption-key');

// Base path - auto-detect or set manually
// For example: '' for root, '/venues' for subdirectory
// Auto-detect from current script location
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
// Remove app subdirectories from path if present
$pathParts = explode('/', trim($scriptPath, '/'));
$baseParts = $pathParts;
$rootMarkers = ['pages', 'routes'];

foreach ($rootMarkers as $marker) {
    $markerIndex = array_search($marker, $pathParts, true);
    if ($markerIndex !== false) {
        $baseParts = array_slice($pathParts, 0, $markerIndex);
        break;
    }
}

$basePath = implode('/', $baseParts);
define('BASE_PATH', $basePath === '' ? '' : '/' . $basePath);
unset($scriptPath, $pathParts, $baseParts, $rootMarkers, $markerIndex, $basePath);

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
