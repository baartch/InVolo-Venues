<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
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
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$redirectParams = buildConversationsTabQuery($conversationId);

if ($conversationId <= 0) {
    header('Location: ' . buildCommunicationUrl($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if ($conversation) {
        $stmt = $pdo->prepare(
            'UPDATE email_conversations
             SET is_closed = 1,
                 closed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $conversationId
        ]);

        logAction($userId, 'conversation_closed', sprintf('Closed conversation %d', $conversationId));
    }
} catch (Throwable $error) {
    logThrowable($userId, 'conversation_close_error', $error);
}

header('Location: ' . buildCommunicationUrl($redirectParams));
exit;
