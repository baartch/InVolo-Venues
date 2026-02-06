<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src-php/defaults.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/rate_limit.php';
require_once __DIR__ . '/../../src-php/cookie_helpers.php';
require_once __DIR__ . '/../../src-php/layout.php';

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
<?php renderPageStart('Login', ['includeSidebar' => false, 'bodyClass' => 'has-background-grey-dark has-text-light is-flex is-flex-direction-column is-fullheight']); ?>
  <section class="section is-flex is-flex-grow-1 is-align-items-center">
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-4">
          <div class="box has-background-dark has-text-light">
            <h1 class="title is-3 has-text-light">BooKing</h1>
            <p class="subtitle is-6 has-text-grey-light">Please login to access the app.</p>

            <?php if ($error): ?>
              <div class="notification is-danger is-light"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
              <div class="field">
                <label for="username" class="label has-text-light">Username</label>
                <div class="control">
                  <input type="text" id="username" name="username" class="input has-background-grey-darker has-text-light" required autofocus>
                </div>
              </div>

              <div class="field">
                <label for="password" class="label has-text-light">Password</label>
                <div class="control">
                  <input type="password" id="password" name="password" class="input has-background-grey-darker has-text-light" required>
                </div>
              </div>

              <div class="field">
                <div class="control">
                  <button type="submit" class="button is-link is-fullwidth">Login</button>
                </div>
              </div>
            </form>

            <p class="has-text-grey-light">Keep your Booking organized.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
<?php renderPageEnd(['includeSidebar' => false]); ?>
