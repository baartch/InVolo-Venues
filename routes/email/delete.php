<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/email_helpers.php';
require_once __DIR__ . '/../../src-php/form_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$emailId = (int) ($_POST['email_id'] ?? 0);

$redirectParams = [
    'tab' => 'email',
    'mailbox_id' => $mailboxId,
    'folder' => (string) ($_POST['folder'] ?? 'inbox'),
    'sort' => (string) ($_POST['sort'] ?? 'received_desc'),
    'filter' => (string) ($_POST['filter'] ?? ''),
    'page' => (int) ($_POST['page'] ?? 1)
];

if ($mailboxId <= 0 || $emailId <= 0) {
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        $redirectParams['notice'] = 'deleted';
        header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $attachmentStmt = $pdo->prepare(
        'SELECT file_path FROM email_attachments WHERE email_id = :email_id AND mailbox_id = :mailbox_id'
    );
    $attachmentStmt->execute([
        ':email_id' => $emailId,
        ':mailbox_id' => $mailboxId
    ]);
    $attachmentPaths = $attachmentStmt->fetchAll();

    $stmt = $pdo->prepare(
        'DELETE FROM email_messages WHERE id = :id AND mailbox_id = :mailbox_id'
    );
    $stmt->execute([
        ':id' => $emailId,
        ':mailbox_id' => $mailboxId
    ]);

    foreach ($attachmentPaths as $attachment) {
        $filePath = $attachment['file_path'] ?? '';
        if ($filePath !== '' && file_exists($filePath)) {
            unlink($filePath);
        }
    }

    logAction($userId, 'email_deleted', sprintf('Deleted email %d', $emailId));
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_delete_error', $error->getMessage());
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
