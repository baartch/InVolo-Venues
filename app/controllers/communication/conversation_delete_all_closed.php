<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/communication/conversation_helpers.php';
require_once __DIR__ . '/../../models/communication/navigation_helpers.php';
require_once __DIR__ . '/../../models/core/error_helpers.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$redirectParams = buildConversationsTabQuery();

try {
    $deletedCount = deleteAllClosedConversationsForUser($userId);
    if ($deletedCount > 0) {
        logAction($userId, 'conversation_closed_bulk_deleted', sprintf('Deleted %d closed conversations', $deletedCount));
    }
} catch (Throwable $error) {
    logThrowable($userId, 'conversation_closed_bulk_delete_error', $error);
}

header('Location: ' . buildCommunicationUrl($redirectParams));
exit;
