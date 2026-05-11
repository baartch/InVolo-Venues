<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/core/security_headers.php';

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
         LEFT JOIN team_members tm ON tm.team_id = em.team_id AND tm.user_id = :team_user_id
         WHERE ea.id = :id
           AND (tm.user_id = :member_user_id OR em.user_id = :owner_user_id)
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $attachmentId,
        ':team_user_id' => $userId,
        ':member_user_id' => $userId,
        ':owner_user_id' => $userId
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
    header('Content-Disposition: inline; filename="' . basename($filename) . '"');
    readfile($filePath);
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_attachment_error', $error->getMessage());
    http_response_code(500);
    exit;
}
