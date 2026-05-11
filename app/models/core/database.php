<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/defaults.php';

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
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

function isTeamAdmin(int $userId): bool
{
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare(
            "SELECT 1 FROM team_members WHERE user_id = :user_id AND role = 'admin' LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $error) {
        error_log('Team admin check failed: ' . $error->getMessage());
        return false;
    }
}

function updateUserTheme(int $userId, string $theme): void
{
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('UPDATE users SET ui_theme = :theme WHERE id = :user_id');
    $stmt->execute([
        ':theme' => $theme,
        ':user_id' => $userId
    ]);
}

function updateUserVenuePageSize(int $userId, int $pageSize): void
{
    $pageSize = max(25, min(500, $pageSize));
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('UPDATE users SET venues_page_size = :page_size WHERE id = :user_id');
    $stmt->execute([
        ':page_size' => $pageSize,
        ':user_id' => $userId
    ]);
}

