<?php
require_once __DIR__ . '/config.php';

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    return $pdo;
}

function encryptSettingValue(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $key = hash('sha256', (string) ENCRYPTION_KEY, true);
    $ivLength = openssl_cipher_iv_length('aes-256-gcm');
    if ($ivLength === false) {
        return null;
    }

    $iv = random_bytes($ivLength);
    $tag = '';
    $cipherText = openssl_encrypt(
        $value,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($cipherText === false) {
        return null;
    }

    return base64_encode($iv . $tag . $cipherText);
}

function decryptSettingValue(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        return '';
    }

    $key = hash('sha256', (string) ENCRYPTION_KEY, true);
    $ivLength = openssl_cipher_iv_length('aes-256-gcm');
    if ($ivLength === false) {
        return '';
    }

    $tagLength = 16;
    if (strlen($decoded) < ($ivLength + $tagLength)) {
        return '';
    }

    $iv = substr($decoded, 0, $ivLength);
    $tag = substr($decoded, $ivLength, $tagLength);
    $cipherText = substr($decoded, $ivLength + $tagLength);

    $plainText = openssl_decrypt(
        $cipherText,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return $plainText === false ? '' : $plainText;
}

function logAction(?int $userId, string $action, string $details = ''): void
{
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO logs (user_id, action, details) VALUES (:user_id, :action, :details)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':details' => $details
        ]);
    } catch (Throwable $error) {
        error_log('Logging failed: ' . $error->getMessage());
    }
}

function createSession(int $userId): array
{
    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + SESSION_LIFETIME;

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO sessions (user_id, session_token, expires_at) VALUES (:user_id, :token, :expires_at)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token' => $token,
        ':expires_at' => date('Y-m-d H:i:s', $expiresAt)
    ]);

    return [
        'token' => $token,
        'expiresAt' => $expiresAt
    ];
}

function fetchSessionUser(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'SELECT sessions.id AS session_id, sessions.user_id, sessions.expires_at, users.username, users.role
         FROM sessions
         JOIN users ON users.id = sessions.user_id
         WHERE sessions.session_token = :token
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $session = $stmt->fetch();

    if (!$session) {
        deleteSession($token);
        return null;
    }

    $expiresAt = strtotime((string) $session['expires_at']);
    if ($expiresAt !== false && $expiresAt < time()) {
        deleteSession($token);
        return null;
    }

    return $session;
}

function refreshSession(string $token): ?int
{
    if ($token === '') {
        return null;
    }

    $expiresAt = time() + SESSION_LIFETIME;
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'UPDATE sessions SET expires_at = :expires_at WHERE session_token = :token'
    );
    $stmt->execute([
        ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ':token' => $token
    ]);

    return $expiresAt;
}

function deleteSession(string $token): void
{
    if ($token === '') {
        return;
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('DELETE FROM sessions WHERE session_token = :token');
    $stmt->execute([':token' => $token]);
}

function clearSessionCookie(): void
{
    setcookie(SESSION_NAME, '', buildSessionCookieOptions(time() - 3600));
}
