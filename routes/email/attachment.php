<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/email_helpers.php';
require_once __DIR__ . '/../../src-php/security_headers.php';

setApiSecurityHeaders();

$userId = (int) ($currentUser['user_id'] ?? 0);
$attachmentId = (int) ($_GET['id'] ?? 0);
if ($attachmentId <= 0) {
    http_response_code(404);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'SELECT ea.*, em.mailbox_id
         FROM email_attachments ea
         JOIN email_messages em ON em.id = ea.email_id
         JOIN team_members tm ON tm.team_id = em.team_id
         WHERE ea.id = :id AND tm.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $attachmentId,
        ':user_id' => $userId
    ]);
    $attachment = $stmt->fetch();

    if (!$attachment) {
        http_response_code(404);
        exit;
    }

    $filePath = $attachment['file_path'] ?? '';
    if ($filePath === '' || !file_exists($filePath)) {
        http_response_code(404);
        exit;
    }

    $filename = $attachment['filename'] ?? 'attachment';
    $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    readfile($filePath);
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_attachment_error', $error->getMessage());
    http_response_code(500);
    exit;
}
