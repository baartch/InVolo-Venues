<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../models/core/defaults.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/auth/session.php';
require_once __DIR__ . '/../../models/auth/csrf.php';
require_once __DIR__ . '/../../models/auth/rate_limit.php';
require_once __DIR__ . '/../../models/auth/cookie_helpers.php';
require_once __DIR__ . '/../../models/auth/otp_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$code = trim((string) ($_POST['otp_code'] ?? ''));
$clientIp = getClientIdentifier();

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=invalid');
    exit;
}

$rateKey = 'otp_verify:' . $email . ':' . $clientIp;
$rateLimit = checkRateLimit($rateKey, 'otp_verify', 5, 600);
if (!$rateLimit['allowed']) {
    $error = sprintf(
        'Too many verification attempts. Please try again in %s.',
        formatRateLimitReset($rateLimit['reset_at'])
    );
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=otp&email=' . urlencode($email) . '&error=' . urlencode($error));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $record = fetchOtpRecord($pdo, $email);
    if (!$record) {
        recordRateLimitAttempt($rateKey, 'otp_verify', false);
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=unknown');
        exit;
    }

    $user = findUserByEmail($pdo, $email);
    if (!$user) {
        deleteOtpRecord($pdo, $email);
        recordRateLimitAttempt($rateKey, 'otp_verify', false);
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=unknown');
        exit;
    }

    $expiresAt = strtotime((string) ($record['expires_at'] ?? ''));
    if (!$expiresAt || $expiresAt < time()) {
        deleteOtpRecord($pdo, $email);
        recordRateLimitAttempt($rateKey, 'otp_verify', false);
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=expired');
        exit;
    }

    $attempts = (int) ($record['attempts'] ?? 0);
    if ($attempts >= 3) {
        recordRateLimitAttempt($rateKey, 'otp_verify', false);
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=locked');
        exit;
    }

    if (!preg_match('/^\d{8}$/', $code) || !password_verify($code, (string) ($record['code_hash'] ?? ''))) {
        $attempts = incrementOtpAttempts($pdo, $email);
        recordRateLimitAttempt($rateKey, 'otp_verify', false);
        if ($attempts >= 3) {
            header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=locked');
            exit;
        }
        $remaining = max(0, 3 - $attempts);
        $error = sprintf('Invalid code. %d attempt(s) remaining.', $remaining);
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=otp&email=' . urlencode($email) . '&error=' . urlencode($error));
        exit;
    }

    deleteOtpRecord($pdo, $email);
    recordRateLimitAttempt($rateKey, 'otp_verify', true);

    $sessionData = createSession((int) $user['id']);
    setSessionCookie($sessionData['token'], $sessionData['expiresAt']);
    logAction((int) $user['id'], 'login', 'User logged in with OTP');

    header('Location: ' . BASE_PATH . '/index.php');
    exit;
} catch (Throwable $error) {
    logAction(null, 'otp_verify_error', $error->getMessage());
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=send');
    exit;
}
