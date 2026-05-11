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
$action = (string) ($_POST['action'] ?? '');
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$messageId = (int) ($_POST['message_id'] ?? 0);

$redirectParams = buildConversationsTabQuery($conversationId > 0 ? $conversationId : null);

$redirect = static function (array $params): never {
    header('Location: ' . buildCommunicationUrl($params));
    exit;
};

try {
    switch ($action) {
        case 'close':
            if ($conversationId > 0 && closeConversationForUser($conversationId, $userId)) {
                logAction($userId, 'conversation_closed', sprintf('Closed conversation %d', $conversationId));
            }
            $redirect($redirectParams);

        case 'reopen':
            if ($conversationId > 0 && reopenConversationForUser($conversationId, $userId)) {
                logAction($userId, 'conversation_reopened', sprintf('Reopened conversation %d', $conversationId));
            }
            $redirect($redirectParams);

        case 'delete':
            if ($conversationId > 0 && deleteClosedConversationForUser($conversationId, $userId)) {
                logAction($userId, 'conversation_deleted', sprintf('Deleted conversation %d', $conversationId));
            }
            $redirect(buildConversationsTabQuery());

        case 'remove_message':
            if ($messageId > 0 && $conversationId > 0 && removeMessageFromConversationForUser($messageId, $conversationId, $userId)) {
                logAction($userId, 'conversation_email_removed', sprintf('Removed email %d from conversation %d', $messageId, $conversationId));
            }
            $redirect($redirectParams);

        case 'delete_all_closed':
            $deletedCount = deleteAllClosedConversationsForUser($userId);
            if ($deletedCount > 0) {
                logAction($userId, 'conversation_closed_bulk_deleted', sprintf('Deleted %d closed conversations', $deletedCount));
            }
            $redirect(buildConversationsTabQuery());

        default:
            $redirect(buildConversationsTabQuery());
    }
} catch (Throwable $error) {
    switch ($action) {
        case 'close':
            logThrowable($userId, 'conversation_close_error', $error);
            $redirect($redirectParams);

        case 'reopen':
            logThrowable($userId, 'conversation_reopen_error', $error);
            $redirect($redirectParams);

        case 'delete_all_closed':
            logThrowable($userId, 'conversation_closed_bulk_delete_error', $error);
            $redirect($redirectParams);

        case 'delete':
            logAction($userId, 'conversation_delete_error', $error->getMessage());
            $redirect(buildConversationsTabQuery());

        case 'remove_message':
            logAction($userId, 'conversation_email_remove_error', $error->getMessage());
            $redirect($redirectParams);

        default:
            logThrowable($userId, 'conversation_action_error', $error);
            $redirect($redirectParams);
    }
}
