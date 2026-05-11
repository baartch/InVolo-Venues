<?php
// Base path - auto-detect or set manually
// For example: '' for root, '/venues' for subdirectory
if (!defined('BASE_PATH')) {
    $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $pathParts = explode('/', trim($scriptPath, '/'));
    $baseParts = $pathParts;

    $markerIndex = array_search('app', $pathParts, true);
    if ($markerIndex !== false) {
        $baseParts = array_slice($pathParts, 0, $markerIndex);
    }

    $basePath = implode('/', $baseParts);
    define('BASE_PATH', $basePath === '' ? '' : '/' . $basePath);
    unset($scriptPath, $pathParts, $baseParts, $markerIndex, $basePath);
}

// Mail storage
// Base directory for stored email attachments (web root).
// Ensure the web server user can read/write this directory.
if (!defined('MAIL_ATTACHMENTS_PATH')) {
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($documentRoot === '') {
        $documentRoot = rtrim(dirname(__DIR__, 3), '/');
    }

    $basePath = rtrim(BASE_PATH, '/');
    $rootPrefix = $documentRoot;
    if ($basePath !== '' && !str_ends_with($documentRoot, $basePath)) {
        $rootPrefix .= $basePath;
    }
    define('MAIL_ATTACHMENTS_PATH', $rootPrefix . '/uploads/email_attachments');
    unset($documentRoot, $basePath, $rootPrefix);
}

/**
 * Determine if current connection is HTTPS
 */
if (!function_exists('isSecureConnection')) {
    function isSecureConnection(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}

/**
 * Get session cookie name with secure prefix if HTTPS
 * __Host- prefix provides additional security:
 * - Must be sent with secure flag
 * - Must be sent from same host (no domain attribute)
 * - Must have path=/
 */
if (!function_exists('getSessionCookieName')) {
    function getSessionCookieName(): string
    {
        // Use __Host- prefix on HTTPS for maximum security
        // Falls back to __Secure- or no prefix on HTTP for development
        if (isSecureConnection()) {
            return '__Host-venue_session';
        }
        return 'venue_session'; // Development/HTTP fallback
    }
}

// Dynamic session name based on connection security
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', getSessionCookieName());
}

if (!function_exists('buildSessionCookieOptions')) {
    function buildSessionCookieOptions(int $expiresAt): array
    {
        $isSecure = isSecureConnection();

        return [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => '', // Empty domain required for __Host- prefix
            'secure' => $isSecure, // Required for __Host- prefix
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Strict'
        ];
    }
}
