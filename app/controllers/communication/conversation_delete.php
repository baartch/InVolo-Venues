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
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$redirectParams = [
    'tab' => 'conversations'
];

if ($conversationId <= 0) {
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);

    if ($conversation && !empty($conversation['is_closed'])) {
        $updateStmt = $pdo->prepare(
            'UPDATE email_messages
             SET conversation_id = NULL
             WHERE conversation_id = :conversation_id'
        );
        $updateStmt->execute([':conversation_id' => $conversationId]);

        $deleteStmt = $pdo->prepare(
            'DELETE FROM email_conversations WHERE id = :id'
        );
        $deleteStmt->execute([
            ':id' => $conversationId
        ]);

        logAction($userId, 'conversation_deleted', sprintf('Deleted conversation %d', $conversationId));
    }
} catch (Throwable $error) {
    logAction($userId, 'conversation_delete_error', $error->getMessage());
}

header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
exit;
