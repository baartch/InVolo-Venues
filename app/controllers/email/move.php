<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/auth/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

verifyCsrfToken();

header('Content-Type: application/json');

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$emailId = (int) ($_POST['email_id'] ?? 0);
$targetFolder = trim((string) ($_POST['target_folder'] ?? ''));
$folderOptions = getEmailFolderOptions();

if ($mailboxId <= 0 || $emailId <= 0 || !array_key_exists($targetFolder, $folderOptions)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $result = moveEmailMessageForUser($userId, $mailboxId, $emailId, $targetFolder);
    $status = (int) ($result['status'] ?? 500);

    if (empty($result['ok'])) {
        http_response_code($status);
        echo json_encode([
            'ok' => false,
            'error' => (string) ($result['error'] ?? 'Failed to move email'),
        ]);
        exit;
    }

    if (!empty($result['moved'])) {
        logAction(
            $userId,
            'email_moved',
            sprintf(
                'Moved email %d from %s to %s',
                $emailId,
                (string) ($result['current_folder'] ?? ''),
                (string) ($result['target_folder'] ?? $targetFolder)
            )
        );
    }

    http_response_code($status);
    echo json_encode([
        'ok' => true,
        'moved' => (bool) ($result['moved'] ?? false),
        'target_folder' => (string) ($result['target_folder'] ?? $targetFolder),
    ]);
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_move_error', $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to move email']);
    exit;
}
