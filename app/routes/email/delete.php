<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';
require_once __DIR__ . '/../../src-php/core/form_helpers.php';

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
    header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        $redirectParams['notice'] = 'deleted';
        header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $stmt = $pdo->prepare(
        'UPDATE email_messages
         SET folder = "trash"
         WHERE id = :id AND mailbox_id = :mailbox_id'
    );
    $stmt->execute([
        ':id' => $emailId,
        ':mailbox_id' => $mailboxId
    ]);

    logAction($userId, 'email_deleted', sprintf('Moved email %d to trash', $emailId));
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_delete_error', $error->getMessage());
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
