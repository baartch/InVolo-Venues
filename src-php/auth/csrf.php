<?php
/**
 * CSRF Protection Helpers
 * 
 * Provides CSRF token generation and validation for forms.
 */

/**
 * Generate a new CSRF token and store it in the session
 * 
 * @return string The generated token
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * Get the current CSRF token, generating one if it doesn't exist
 * 
 * @return string The CSRF token
 */
function getCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate new token if none exists or if token is older than 2 hours
    if (
        !isset($_SESSION['csrf_token']) ||
        !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > 7200
    ) {
        return generateCsrfToken();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST request
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check token age (max 2 hours)
    if (
        !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > 7200
    ) {
        return false;
    }
    
    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render a hidden CSRF token input field
 * 
 * @return void
 */
function renderCsrfField(): void
{
    $token = getCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST request and terminate with error if invalid
 * 
 * @return void
 */
function verifyCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCsrfToken($token)) {
        require_once __DIR__ . '/../core/database.php';
        
        $userId = null;
        if (isset($GLOBALS['currentUser']['user_id'])) {
            $userId = $GLOBALS['currentUser']['user_id'];
        }
        
        logAction($userId, 'csrf_validation_failed', sprintf(
            'CSRF token validation failed. Method=%s, URI=%s, Referer=%s',
            $_SERVER['REQUEST_METHOD'] ?? '',
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ));
        
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Error</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
</head>
<body class="has-background-light">
    <section class="section">
        <div class="container">
            <div class="columns is-centered">
                <div class="column is-half">
                    <div class="notification is-danger has-text-centered">
                        <h1 class="title is-4">🛡️ Security Error</h1>
                        <p><strong>CSRF token validation failed.</strong></p>
                        <p>This request appears to be invalid or has expired. This could happen if:</p>
                        <ul class="content">
                            <li>Your session expired (tokens are valid for 2 hours)</li>
                            <li>You submitted a form from an old page</li>
                            <li>Your browser blocked cookies</li>
                        </ul>
                        <p>
                            <a class="button is-light" href="javascript:history.back()">Go Back</a>
                            <a class="button is-link" href="' . (defined('BASE_PATH') ? BASE_PATH : '') . '/index.php">Go to Home</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>';
        exit;
    }
}
