<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../src-php/core/defaults.php';
require_once __DIR__ . '/../src-php/core/database.php';

$defaultDays = 180;
$daysToKeep = $defaultDays;

if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $argDays = (int) $argv[1];
    if ($argDays > 0) {
        $daysToKeep = $argDays;
    }
}

$cutoff = date('Y-m-d H:i:s', strtotime('-' . $daysToKeep . ' days'));

try {
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare('DELETE FROM logs WHERE created_at < :cutoff');
    $stmt->execute([':cutoff' => $cutoff]);
    $logsDeleted = $stmt->rowCount();

    $stmt = $pdo->prepare('DELETE FROM sessions WHERE expires_at < :cutoff');
    $stmt->execute([':cutoff' => $cutoff]);
    $sessionsDeleted = $stmt->rowCount();

    // Clean rate limits older than 30 days (they're only needed for recent tracking)
    $rateLimitCutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE attempted_at < :cutoff');
    $stmt->execute([':cutoff' => $rateLimitCutoff]);
    $rateLimitsDeleted = $stmt->rowCount();

    logAction(null, 'cleanup', sprintf('Deleted %d logs, %d sessions, %d rate limits older than %s', $logsDeleted, $sessionsDeleted, $rateLimitsDeleted, $cutoff));

    echo sprintf("Cleanup complete. Logs deleted: %d, sessions deleted: %d, rate limits deleted: %d (days=%d)\n", $logsDeleted, $sessionsDeleted, $rateLimitsDeleted, $daysToKeep);
} catch (Throwable $error) {
    logAction(null, 'cleanup_error', $error->getMessage());
    http_response_code(500);
    echo 'Cleanup failed: ' . $error->getMessage() . "\n";
}
