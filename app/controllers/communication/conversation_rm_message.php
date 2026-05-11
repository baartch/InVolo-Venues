<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/conversation_helpers.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$messageId = (int) ($_POST['message_id'] ?? 0);
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$redirectParams = [
    'tab' => 'conversations',
    'conversation_id' => $conversationId
];

if ($messageId <= 0) {
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if ($conversation) {
        $stmt = $pdo->prepare(
            'UPDATE email_messages
             SET conversation_id = NULL
             WHERE id = :id AND conversation_id = :conversation_id'
        );
        $stmt->execute([
            ':id' => $messageId,
            ':conversation_id' => $conversationId
        ]);

        logAction($userId, 'conversation_email_removed', sprintf('Removed email %d from conversation %d', $messageId, $conversationId));
    }
} catch (Throwable $error) {
    logAction($userId, 'conversation_email_remove_error', $error->getMessage());
}

header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
exit;
