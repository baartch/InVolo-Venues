<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo BASE_PATH; ?>/">
    <title>Venue Crawler - Login</title>
    <link rel="stylesheet" href="public/styles.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            padding: 40px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
        }

        h1 {
            color: var(--color-primary-dark);
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: var(--color-muted);
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--color-text);
            font-weight: 500;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            color: var(--color-muted);
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container card">
        <h1>🎵 Venue Crawler</h1>
        <p class="subtitle">Please login to access the venue map</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="input" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="input" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="footer">
            Booking Agency for Singer-Songwriters
        </div>
    </div>
</body>
</html>
