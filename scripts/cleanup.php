<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

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

    logAction(null, 'cleanup', sprintf('Deleted %d logs and %d sessions older than %s', $logsDeleted, $sessionsDeleted, $cutoff));

    echo sprintf("Cleanup complete. Logs deleted: %d, sessions deleted: %d (days=%d)\n", $logsDeleted, $sessionsDeleted, $daysToKeep);
} catch (Throwable $error) {
    logAction(null, 'cleanup_error', $error->getMessage());
    http_response_code(500);
    echo 'Cleanup failed: ' . $error->getMessage() . "\n";
}
