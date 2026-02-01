<?php
/**
 * Cookie Security Helpers
 * Ensures cookies are set with proper security flags
 */

/**
 * Set a secure cookie with all security flags
 * Wrapper around setcookie() that enforces security best practices
 * 
 * @param string $name Cookie name
 * @param string $value Cookie value
 * @param array $options Cookie options (expires, path, domain, secure, httponly, samesite)
 * @return bool Success status
 */
function setSecureCookie(string $name, string $value, array $options = []): bool
{
    // Ensure we have security defaults
    $defaults = [
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isSecureConnection(),
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    
    $options = array_merge($defaults, $options);
    
    // Validate required security flags
    if (!$options['httponly']) {
        error_log('WARNING: Attempting to set cookie without httponly flag: ' . $name);
    }
    
    if (!$options['secure'] && isSecureConnection()) {
        error_log('WARNING: Attempting to set insecure cookie on HTTPS connection: ' . $name);
    }
    
    // Use array syntax for PHP 7.3+
    return setcookie($name, $value, $options);
}

/**
 * Set the session cookie with proper security flags and prefix
 * 
 * @param string $token Session token value
 * @param int $expiresAt Expiration timestamp
 * @return bool Success status
 */
function setSessionCookie(string $token, int $expiresAt): bool
{
    $cookieName = getSessionCookieName();
    $options = buildSessionCookieOptions($expiresAt);
    
    return setSecureCookie($cookieName, $token, $options);
}

/**
 * Clear the session cookie
 * 
 * @return bool Success status
 */
function clearSessionCookie(): bool
{
    $cookieName = getSessionCookieName();
    $options = buildSessionCookieOptions(time() - 3600);
    
    return setSecureCookie($cookieName, '', $options);
}

/**
 * Get session token from cookie
 * Handles both prefixed and non-prefixed cookie names for migration
 * 
 * @return string Session token or empty string
 */
function getSessionToken(): string
{
    $cookieName = getSessionCookieName();
    
    // Check for current cookie name
    if (isset($_COOKIE[$cookieName])) {
        return (string) $_COOKIE[$cookieName];
    }
    
    // Migration: Check for old cookie name (without prefix)
    // This allows existing sessions to continue working after upgrade
    $legacyCookieNames = ['venue_crawler_session', 'venue_session'];
    foreach ($legacyCookieNames as $legacyName) {
        if (isset($_COOKIE[$legacyName])) {
            // Found old cookie - it will be replaced with new one on next session refresh
            return (string) $_COOKIE[$legacyName];
        }
    }
    
    return '';
}

/**
 * Migrate old session cookies to new secure format
 * Call this during authentication check
 * 
 * @param string $token Current valid session token
 * @param int $expiresAt Session expiration timestamp
 */
function migrateSessionCookie(string $token, int $expiresAt): void
{
    $currentName = getSessionCookieName();
    
    // Clear old cookie names if they exist
    $legacyCookieNames = ['venue_crawler_session', 'venue_session'];
    foreach ($legacyCookieNames as $legacyName) {
        if ($legacyName !== $currentName && isset($_COOKIE[$legacyName])) {
            // Clear old cookie
            setcookie($legacyName, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isSecureConnection(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
    
    // Set new cookie with secure prefix
    setSessionCookie($token, $expiresAt);
}
