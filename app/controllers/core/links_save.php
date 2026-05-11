<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/core/link_scope.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/communication/conversation_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = (int) ($currentUser['user_id'] ?? 0);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

// Accept CSRF token from header, POST body, or JSON body
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? $_POST['csrf_token']
    ?? trim((string) ($input['csrf_token'] ?? ''));

if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

$sourceTypeInput = (string) ($input['source_type'] ?? '');
$sourceId = (int) ($input['source_id'] ?? 0);
$mailboxId = (int) ($input['mailbox_id'] ?? 0);
$conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : null;
$detachConversation = !empty($input['detach_conversation']);
$links = is_array($input['links'] ?? null) ? (array) $input['links'] : [];

try {
    $pdo = getDatabaseConnection();

    $sourceType = assertAllowedLinkType($sourceTypeInput, 'source_type');
    $normalizedLinks = normalizeLinkItems($links);

    $scope = resolveLinkSourceScopeOrThrow(
        $pdo,
        $sourceType,
        $sourceId,
        $userId,
        [
            'mailbox_id' => $mailboxId,
            'team_id' => isset($input['team_id']) ? (int) $input['team_id'] : null,
            'user_id' => isset($input['user_id']) ? (int) $input['user_id'] : null,
            'route' => 'core/links_save',
        ]
    );

    $teamId = isset($scope['team_id']) ? (int) $scope['team_id'] : null;
    $scopeUserId = isset($scope['user_id']) ? (int) $scope['user_id'] : null;
    if ($teamId !== null && $teamId <= 0) {
        $teamId = null;
    }
    if ($scopeUserId !== null && $scopeUserId <= 0) {
        $scopeUserId = null;
    }

    if ($teamId === null && $scopeUserId === null) {
        throwLinkScopeException(
            $userId > 0 ? $userId : null,
            'scope_unresolved',
            'Cannot determine link scope.',
            [
                'action' => 'links_save',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'request_user_id' => $userId,
                'resolved_team_id' => $teamId,
                'resolved_user_id' => $scopeUserId,
                'route' => 'core/links_save',
            ],
            422
        );
    }

    $pdo->beginTransaction();

    // User scope takes precedence when both scopes are present.
    if ($scopeUserId !== null) {
        clearObjectLinks($pdo, $sourceType, $sourceId, $teamId, $scopeUserId);
    } else {
        clearObjectLinks($pdo, $sourceType, $sourceId, $teamId, null);
    }

    foreach ($normalizedLinks as $link) {
        $targetType = assertAllowedLinkType((string) ($link['type'] ?? ''), 'target_type');
        $targetId = (int) ($link['id'] ?? 0);
        if ($targetId <= 0) {
            continue;
        }

        createObjectLink(
            $pdo,
            $sourceType,
            $sourceId,
            $targetType,
            $targetId,
            $teamId,
            $scopeUserId
        );
    }

    // Handle conversation assignment / detach for emails
    if ($sourceType === 'email') {
        if ($detachConversation) {
            $stmt = $pdo->prepare('UPDATE email_messages SET conversation_id = NULL WHERE id = :id');
            $stmt->execute([':id' => $sourceId]);
            logAction($userId, 'email_conversation_detached', sprintf('Detached email %d from conversation', $sourceId));
        } elseif ($conversationId !== null && $conversationId > 0) {
            $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
            if ($conversation) {
                $stmt = $pdo->prepare('UPDATE email_messages SET conversation_id = :cid WHERE id = :id');
                $stmt->execute([':cid' => $conversationId, ':id' => $sourceId]);
                logAction($userId, 'email_conversation_assigned', sprintf('Assigned email %d to conversation %d', $sourceId, $conversationId));
            } else {
                $errorPayload = sprintf('Conversation access denied. conversation_id=%d user_id=%d', $conversationId, $userId);
                logAction($userId, 'email_conversation_assign_denied', $errorPayload);
            }
        }
    }

    $pdo->commit();

    logAction(
        $userId,
        'links_saved',
        json_encode([
            'action' => 'links_saved',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'request_user_id' => $userId,
            'resolved_team_id' => $teamId,
            'resolved_user_id' => $scopeUserId,
            'route' => 'core/links_save',
            'link_count' => count($normalizedLinks),
        ]) ?: sprintf('Saved links for %s:%d', $sourceType, $sourceId)
    );

    echo json_encode(['ok' => true]);
} catch (LinkScopeException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $payload = array_merge([
        'action' => 'links_save_failed',
        'source_type' => $sourceTypeInput,
        'source_id' => $sourceId,
        'request_user_id' => $userId,
        'route' => 'core/links_save',
        'error_code' => $error->getErrorCode(),
        'message' => $error->getMessage(),
    ], $error->getContext());

    logAction($userId, 'links_save_scope_error', json_encode($payload) ?: $error->getMessage());

    http_response_code($error->getHttpStatus());
    echo json_encode([
        'error' => $error->getMessage(),
        'error_code' => $error->getErrorCode(),
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $payload = [
        'action' => 'links_save_failed',
        'source_type' => $sourceTypeInput,
        'source_id' => $sourceId,
        'request_user_id' => $userId,
        'route' => 'core/links_save',
        'error_code' => 'internal_error',
        'message' => $error->getMessage(),
    ];

    logAction($userId, 'links_save_error', json_encode($payload) ?: $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save links']);
}
