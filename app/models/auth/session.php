<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../core/database.php';

function createSession(int $userId): array
{
    $token = bin2hex(random_bytes(32));
    $createdAt = time();
    $maxExpiresAt = $createdAt + SESSION_MAX_LIFETIME;
    $idleExpiresAt = $createdAt + SESSION_IDLE_LIFETIME;
    $expiresAt = min($idleExpiresAt, $maxExpiresAt);

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO sessions (user_id, session_token, expires_at, last_refreshed_at)
         VALUES (:user_id, :token, :expires_at, :last_refreshed_at)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token' => $token,
        ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ':last_refreshed_at' => date('Y-m-d H:i:s', $createdAt)
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
        'SELECT sessions.id AS session_id, sessions.user_id, sessions.expires_at, sessions.last_refreshed_at, sessions.created_at, users.username, users.display_name, users.role, users.ui_theme,
                users.venues_page_size
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

    if (!empty($session['created_at'])) {
        $createdAt = strtotime((string) $session['created_at']);
        if ($createdAt !== false) {
            $maxExpiresAt = $createdAt + SESSION_MAX_LIFETIME;
            if ($maxExpiresAt < time()) {
                deleteSession($token);
                return null;
            }
        }
    }

    return $session;
}

function refreshSession(string $token, ?array $session = null): ?int
{
    if ($token === '') {
        return null;
    }

    $pdo = getDatabaseConnection();
    if (!$session) {
        $session = fetchSessionUser($token);
    }

    if (!$session) {
        return null;
    }

    $currentExpiresAt = strtotime((string) ($session['expires_at'] ?? ''));
    if ($currentExpiresAt === false) {
        return null;
    }

    $createdAt = strtotime((string) ($session['created_at'] ?? ''));
    if ($createdAt === false) {
        return null;
    }

    $now = time();
    $maxExpiresAt = $createdAt + SESSION_MAX_LIFETIME;
    if ($maxExpiresAt <= $now) {
        deleteSession($token);
        return null;
    }

    $lastRefreshedAt = strtotime((string) ($session['last_refreshed_at'] ?? ''));
    if ($lastRefreshedAt === false) {
        $lastRefreshedAt = $createdAt;
    }

    $interval = defined('SESSION_REFRESH_INTERVAL') ? (int) SESSION_REFRESH_INTERVAL : 21600;
    $interval = max(0, $interval);

    if (($now - $lastRefreshedAt) < $interval) {
        return min($currentExpiresAt, $maxExpiresAt);
    }

    $expiresAt = min($now + SESSION_IDLE_LIFETIME, $maxExpiresAt);
    $stmt = $pdo->prepare(
        'UPDATE sessions
         SET expires_at = :expires_at,
             last_refreshed_at = :last_refreshed_at
         WHERE session_token = :token'
    );
    $stmt->execute([
        ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ':last_refreshed_at' => date('Y-m-d H:i:s', $now),
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
