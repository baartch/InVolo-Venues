<?php
/**
 * Security Headers Helper
 * Sets HTTP security headers to protect against common web vulnerabilities
 */

function setSecurityHeaders(): void
{
    // Prevent clickjacking attacks
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS filtering in older browsers
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy - only send referrer to same origin
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy - restrict browser features
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()');
        
        // Content Security Policy
        // Allows: self, Leaflet CDN, Bulma CDN, OpenStreetMap tiles (wildcard covers all subdomains), Mapbox API, Font Awesome
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: https://*.openstreetmap.org https://api.mapbox.com",
            "font-src 'self' data: https://cdnjs.cloudflare.com",
            "connect-src 'self' https://unpkg.com https://api.mapbox.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        header('Content-Security-Policy: ' . $csp);
        
        // HSTS - Force HTTPS
        // Note: HSTS is set in .htaccess
        // Removed from PHP to avoid duplicate header
    }
}

/**
 * Set security headers for API responses (JSON, XML, etc.)
 * More restrictive CSP for API endpoints
 */
function setApiSecurityHeaders(): void
{
    if (!headers_sent()) {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer');
        
        // Strict CSP for API responses
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        
        // Note: HSTS is set in .htaccess to avoid duplicate headers
    }
}

/**
 * Set cache control headers for sensitive pages (login, admin, etc.)
 */
function setNoCacheHeaders(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

/**
 * Set cache control headers for public static assets
 */
function setPublicCacheHeaders(int $maxAge = 3600): void
{
    if (!headers_sent()) {
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    }
}
