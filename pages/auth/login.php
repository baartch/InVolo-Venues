<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';
require_once __DIR__ . '/../../src-php/csrf.php';

// Start session for CSRF token storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = $_COOKIE[SESSION_NAME] ?? '';
$existingSession = $token !== '' ? fetchSessionUser($token) : null;
if ($existingSession) {
    $expiresAt = refreshSession($token);
    if ($expiresAt) {
        setcookie(SESSION_NAME, $token, buildSessionCookieOptions($expiresAt));
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
    verifyCsrfToken();
    
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $sessionData = createSession((int) $user['id']);
            setcookie(SESSION_NAME, $sessionData['token'], buildSessionCookieOptions($sessionData['expiresAt']));
            logAction((int) $user['id'], 'login', 'User logged in');
            header('Location: ' . BASE_PATH . '/index.php');
            exit;
        }

        $details = sprintf(
            'Login failed (user=%s)',
            $username
        );
        logAction($user ? (int) $user['id'] : null, 'login_failed', $details);
        $error = 'Invalid username or password';
    } catch (Throwable $errorException) {
        $details = sprintf(
            'Login error: %s',
            $errorException->getMessage()
        );
        logAction(null, 'login_error', $details);
        $error = 'Login failed. Please try again later.';
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
            <?php renderCsrfField(); ?>
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
