<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/rate_limit.php';
require_once __DIR__ . '/../../src-php/cookie_helpers.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$token = getSessionToken();
$existingSession = $token !== '' ? fetchSessionUser($token) : null;
if ($existingSession) {
    $expiresAt = refreshSession($token, $existingSession);
    if ($expiresAt) {
        migrateSessionCookie($token, $expiresAt);
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    }
}

if ($token !== '' && !$existingSession) {
    clearSessionCookie();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $clientIp = getClientIdentifier();
    
    // Check rate limit by IP
    $rateLimitByIp = checkRateLimit($clientIp, 'login', 5, 900); // 5 attempts per 15 minutes
    
    // Check rate limit by username (if provided)
    $rateLimitByUsername = $username !== '' 
        ? checkRateLimit('user:' . $username, 'login', 10, 1800) // 10 attempts per 30 minutes
        : ['allowed' => true];
    
    if (!$rateLimitByIp['allowed']) {
        $error = sprintf(
            'Too many login attempts from your IP address. Please try again in %s.',
            formatRateLimitReset($rateLimitByIp['reset_at'])
        );
        logAction(null, 'login_rate_limit_ip', sprintf('Rate limit exceeded for IP %s', $clientIp));
    } elseif (!$rateLimitByUsername['allowed']) {
        $error = sprintf(
            'Too many login attempts for this account. Please try again in %s.',
            formatRateLimitReset($rateLimitByUsername['reset_at'])
        );
        logAction(null, 'login_rate_limit_user', sprintf('Rate limit exceeded for username %s', $username));
    } else {
        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Clear rate limit attempts on successful login
                recordRateLimitAttempt($clientIp, 'login', true);
                recordRateLimitAttempt('user:' . $username, 'login', true);
                
                $sessionData = createSession((int) $user['id']);
                setSessionCookie($sessionData['token'], $sessionData['expiresAt']);
                logAction((int) $user['id'], 'login', 'User logged in');
                header('Location: ' . BASE_PATH . '/index.php');
                exit;
            }

            // Record failed login attempts
            recordRateLimitAttempt($clientIp, 'login', false);
            if ($username !== '') {
                recordRateLimitAttempt('user:' . $username, 'login', false);
            }

            $details = sprintf('Login failed (user=%s, ip=%s)', $username, $clientIp);
            logAction($user ? (int) $user['id'] : null, 'login_failed', $details);
            $error = 'Invalid username or password';
        } catch (Throwable $errorException) {
            $details = sprintf('Login error: %s', $errorException->getMessage());
            logAction(null, 'login_error', $details);
            $error = 'Login failed. Please try again later.';
        }
    }
}
?>
<?php renderPageStart('Venue Database - Login', ['includeSidebar' => false, 'bodyClass' => 'login-body', 'theme' => getCurrentTheme()]); ?>
    <div class="login-container card">
        <h1 class="login-title">🎵 Venue Database</h1>
        <p class="login-subtitle">Please login to access the venue map</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="login-form-group">
                <label for="username" class="login-label">Username</label>
                <input type="text" id="username" name="username" class="input" required autofocus>
            </div>

            <div class="login-form-group">
                <label for="password" class="login-label">Password</label>
                <input type="password" id="password" name="password" class="input" required>
            </div>

            <button type="submit" class="btn login-button">Login</button>
        </form>

        <div class="login-footer">
            Booking Agency for Singer-Songwriters
        </div>
    </div>
<?php renderPageEnd(['includeSidebar' => false]); ?>
