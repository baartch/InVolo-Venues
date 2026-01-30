<?php
// Configuration file for venue crawler frontend
// Copy this file to config.php and update the credentials

define('LOGIN_USERNAME', 'admin');
define('LOGIN_PASSWORD', 'change-this-password'); // CHANGE THIS!

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
define('BASE_PATH', '/' . implode('/', $pathParts));
unset($scriptPath, $pathParts, $lastPart);

// Session configuration
define('SESSION_NAME', 'venue_crawler_session');
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// Start session with custom settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_name(SESSION_NAME);
session_start();

// Set session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();
