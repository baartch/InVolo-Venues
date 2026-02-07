<?php
require_once __DIR__ . '/../core/database.php';

/**
 * Rate limiting functionality for preventing brute force attacks
 */

/**
 * Check if rate limit exceeded for a given identifier
 * 
 * @param string $identifier User identifier (IP, username, etc.)
 * @param string $action Action being rate limited (e.g., 'login')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
 */
function checkRateLimit(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 900): array
{
    $pdo = getDatabaseConnection();
    
    // Clean old attempts outside the window
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    $stmt = $pdo->prepare(
        'DELETE FROM rate_limits WHERE identifier = :identifier AND action = :action AND attempted_at < :cutoff'
    );
    $stmt->execute([
        ':identifier' => $identifier,
        ':action' => $action,
        ':cutoff' => $cutoff
    ]);
    
    // Count recent attempts
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as attempt_count, MAX(attempted_at) as last_attempt 
         FROM rate_limits 
         WHERE identifier = :identifier AND action = :action'
    );
    $stmt->execute([
        ':identifier' => $identifier,
        ':action' => $action
    ]);
    $result = $stmt->fetch();
    
    $attemptCount = (int) ($result['attempt_count'] ?? 0);
    $lastAttempt = $result['last_attempt'] ?? null;
    
    $remaining = max(0, $maxAttempts - $attemptCount);
    $allowed = $attemptCount < $maxAttempts;
    
    // Calculate reset time
    $resetAt = $lastAttempt 
        ? strtotime($lastAttempt) + $windowSeconds 
        : time() + $windowSeconds;
    
    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'reset_at' => $resetAt,
        'attempt_count' => $attemptCount
    ];
}

/**
 * Record a rate limit attempt
 * 
 * @param string $identifier User identifier (IP, username, etc.)
 * @param string $action Action being rate limited
 * @param bool $success Whether the attempt was successful
 */
function recordRateLimitAttempt(string $identifier, string $action, bool $success = false): void
{
    $pdo = getDatabaseConnection();
    
    // If successful, clear all attempts for this identifier/action
    if ($success) {
        $stmt = $pdo->prepare(
            'DELETE FROM rate_limits WHERE identifier = :identifier AND action = :action'
        );
        $stmt->execute([
            ':identifier' => $identifier,
            ':action' => $action
        ]);
        return;
    }
    
    // Record failed attempt
    $stmt = $pdo->prepare(
        'INSERT INTO rate_limits (identifier, action, attempted_at) VALUES (:identifier, :action, NOW())'
    );
    $stmt->execute([
        ':identifier' => $identifier,
        ':action' => $action
    ]);
}

/**
 * Get client identifier for rate limiting (IP address)
 * 
 * @return string Client IP address
 */
function getClientIdentifier(): string
{
    // Check for proxy headers
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Validate IP format
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = 'invalid';
    }
    
    return $ip;
}

/**
 * Format time remaining until rate limit reset
 * 
 * @param int $resetAt Unix timestamp when limit resets
 * @return string Human-readable time remaining
 */
function formatRateLimitReset(int $resetAt): string
{
    $seconds = max(0, $resetAt - time());
    
    if ($seconds < 60) {
        return sprintf('%d seconds', $seconds);
    }
    
    $minutes = ceil($seconds / 60);
    return sprintf('%d minutes', $minutes);
}
