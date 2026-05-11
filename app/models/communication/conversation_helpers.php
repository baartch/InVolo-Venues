<?php
require_once __DIR__ . '/../core/database.php';

function fetchConversationSubjectById(int $conversationId): string
{
    if ($conversationId <= 0) {
        return '';
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('SELECT subject FROM email_conversations WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $conversationId]);
    $row = $stmt->fetch();

    return trim((string) ($row['subject'] ?? ''));
}

function fetchConversationsForUser(int $userId): array
{
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'SELECT c.*, COUNT(em.id) AS message_count,
                (SELECT em2.folder
                 FROM email_messages em2
                 WHERE em2.conversation_id = c.id
                 ORDER BY COALESCE(em2.received_at, em2.sent_at, em2.created_at) DESC, em2.id DESC
                 LIMIT 1) AS last_message_folder
         FROM email_conversations c
         LEFT JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = :viewer_user_id_join
         LEFT JOIN email_messages em ON em.conversation_id = c.id
         WHERE (c.team_id IS NOT NULL AND tm.user_id = :viewer_user_id_team)
            OR (c.user_id IS NOT NULL AND c.user_id = :viewer_user_id_owner)
         GROUP BY c.id
         ORDER BY c.last_activity_at DESC, c.id DESC'
    );
    $stmt->execute([
        ':viewer_user_id_join' => $userId,
        ':viewer_user_id_team' => $userId,
        ':viewer_user_id_owner' => $userId
    ]);

    return $stmt->fetchAll();
}

function fetchConversationMessagesForUser(int $conversationId, int $userId): ?array
{
    if ($conversationId <= 0) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $conversationScope = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversationScope) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT em.id, em.mailbox_id, em.subject, em.body, em.body_html, em.from_name, em.from_email, em.to_emails, em.folder,
                em.is_read, em.received_at, em.sent_at, em.created_at,
                em.team_id, em.user_id, u.username AS user_name
         FROM email_messages em
         LEFT JOIN users u ON u.id = em.user_id
         WHERE em.conversation_id = :conversation_id
         ORDER BY COALESCE(em.received_at, em.sent_at, em.created_at) DESC'
    );
    $stmt->execute([
        ':conversation_id' => $conversationId
    ]);

    return $stmt->fetchAll();
}

function ensureConversationAccess(PDO $pdo, int $conversationId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, t.name AS team_name, u.username AS user_name
         FROM email_conversations c
         LEFT JOIN teams t ON t.id = c.team_id
         LEFT JOIN users u ON u.id = c.user_id
         LEFT JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = :member_user_id_join
         WHERE c.id = :conversation_id
           AND ((c.team_id IS NOT NULL AND tm.user_id = :member_user_id_where)
             OR (c.user_id IS NOT NULL AND c.user_id = :owner_user_id))
         LIMIT 1'
    );
    $stmt->execute([
        ':conversation_id' => $conversationId,
        ':member_user_id_join' => $userId,
        ':member_user_id_where' => $userId,
        ':owner_user_id' => $userId
    ]);
    $conversation = $stmt->fetch();
    return $conversation ?: null;
}

function ensureConversationScopeAccess(PDO $pdo, int $conversationId, array $mailbox, int $userId): ?array
{
    $scopeTeamId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null;
    $scopeUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null;

    $stmt = $pdo->prepare(
        'SELECT c.*, t.name AS team_name, u.username AS user_name
         FROM email_conversations c
         LEFT JOIN teams t ON t.id = c.team_id
         LEFT JOIN users u ON u.id = c.user_id
         LEFT JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = :member_user_id_join
         WHERE c.id = :conversation_id
           AND ((c.team_id IS NOT NULL AND tm.user_id = :member_user_id_where)
             OR (c.user_id IS NOT NULL AND c.user_id = :owner_user_id))
           AND (c.team_id = :scope_team_id OR c.user_id = :scope_user_id)
         LIMIT 1'
    );
    $stmt->execute([
        ':conversation_id' => $conversationId,
        ':member_user_id_join' => $userId,
        ':member_user_id_where' => $userId,
        ':owner_user_id' => $userId,
        ':scope_team_id' => $scopeTeamId,
        ':scope_user_id' => $scopeUserId
    ]);
    $conversation = $stmt->fetch();
    return $conversation ?: null;
}

function touchConversationActivity(PDO $pdo, int $conversationId, ?string $activityAt = null): void
{
    if ($conversationId <= 0) {
        return;
    }

    $activityAt = $activityAt ?: date('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'UPDATE email_conversations
         SET last_activity_at = :last_activity_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':last_activity_at' => $activityAt,
        ':id' => $conversationId,
    ]);
}

function normalizeConversationSubject(?string $subject): string
{
    $subject = trim((string) $subject);
    if ($subject === '') {
        return 'no-subject';
    }

    $subject = preg_replace('/^\s*((re|fw|fwd|aw|sv|wg|rv):\s*)+/i', '', $subject);
    $subject = trim((string) $subject);

    if (function_exists('mb_convert_encoding')) {
        $subject = mb_convert_encoding($subject, 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
    }

    if (class_exists('Normalizer')) {
        $subject = Normalizer::normalize($subject, Normalizer::FORM_C);
    }

    if (function_exists('mb_strtolower')) {
        $subject = mb_strtolower($subject, 'UTF-8');
    } else {
        $subject = strtolower($subject);
    }

    return $subject === '' ? 'no-subject' : $subject;
}

function formatConversationSubject(?string $subject): string
{
    $subject = trim((string) $subject);
    if ($subject === '') {
        return '(No subject)';
    }

    $subject = preg_replace('/^\s*((re|fw|fwd|aw|sv|wg|rv):\s*)+/i', '', $subject);
    $subject = trim((string) $subject);

    return $subject !== '' ? $subject : '(No subject)';
}

function splitConversationEmailList(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[,;]+/', $value) ?: [];
    $emails = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $part, $matches)) {
            foreach ($matches[0] as $match) {
                $emails[] = strtolower($match);
            }
            continue;
        }

        $candidate = filter_var($part, FILTER_SANITIZE_EMAIL);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower($candidate);
        }
    }

    return array_values(array_unique($emails));
}

function getConversationMailboxPrimaryEmail(array $mailbox): string
{
    $email = strtolower(trim((string) ($mailbox['smtp_username'] ?? '')));
    if ($email === '') {
        $email = strtolower(trim((string) ($mailbox['imap_username'] ?? '')));
    }

    return $email;
}

function buildConversationParticipantKey(string $mailboxEmail, string $fromEmail, string $toEmails): string
{
    $mailboxEmail = strtolower(trim($mailboxEmail));
    $fromEmail = strtolower(trim($fromEmail));
    $recipientList = splitConversationEmailList($toEmails);

    $mailboxIdentity = '';
    if ($mailboxEmail !== '' && filter_var($mailboxEmail, FILTER_VALIDATE_EMAIL)) {
        $mailboxIdentity = $mailboxEmail;
    } elseif ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $mailboxIdentity = $fromEmail;
    } else {
        foreach ($recipientList as $recipient) {
            if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $mailboxIdentity = $recipient;
                break;
            }
        }
    }

    $partnerEmail = '';
    if ($mailboxIdentity !== '' && $fromEmail === $mailboxIdentity) {
        foreach ($recipientList as $recipient) {
            if ($recipient !== '' && $recipient !== $mailboxIdentity) {
                $partnerEmail = $recipient;
                break;
            }
        }
    } else {
        $partnerEmail = $fromEmail !== '' ? $fromEmail : '';
    }

    if ($partnerEmail === '' && $recipientList) {
        foreach ($recipientList as $recipient) {
            if ($recipient !== '' && $recipient !== $mailboxIdentity) {
                $partnerEmail = $recipient;
                break;
            }
        }
    }

    $participants = array_filter([$mailboxIdentity, $partnerEmail], static fn($value) => $value !== '');
    $participants = array_unique($participants);
    sort($participants, SORT_STRING);

    if (!$participants) {
        return 'unknown';
    }

    return implode('|', $participants);
}

function findConversationForEmail(
    PDO $pdo,
    array $mailbox,
    string $fromEmail,
    string $toEmails,
    ?string $subject,
    ?string $activityAt,
    ?int $scopeTeamId = null,
    ?int $scopeUserId = null
): ?int {
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $teamId = $scopeTeamId !== null
        ? (int) $scopeTeamId
        : (!empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null);
    $userId = $scopeUserId !== null
        ? (int) $scopeUserId
        : (!empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null);
    if ($mailboxId <= 0 || (!$teamId && !$userId)) {
        return null;
    }

    $normalizedSubject = normalizeConversationSubject($subject);
    $participantKey = buildConversationParticipantKey(getConversationMailboxPrimaryEmail($mailbox), $fromEmail, $toEmails);
    $activityAt = $activityAt ?: date('Y-m-d H:i:s');

    $openConditions = [];
    $openParams = [
        ':subject_normalized' => $normalizedSubject,
        ':participant_key' => $participantKey
    ];

    if ($teamId) {
        $openConditions[] = '(team_id = :team_id AND user_id IS NULL)';
        $openParams[':team_id'] = $teamId;
    }
    if ($userId) {
        $openConditions[] = '(user_id = :user_id AND team_id IS NULL)';
        $openParams[':user_id'] = $userId;
    }

    if (!$openConditions) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM email_conversations
         WHERE (' . implode(' OR ', $openConditions) . ')
           AND subject_normalized = :subject_normalized
           AND participant_key = :participant_key
           AND is_closed = 0
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute($openParams);
    $conversationId = (int) $stmt->fetchColumn();
    if ($conversationId > 0) {
        touchConversationActivity($pdo, $conversationId, $activityAt);
        return $conversationId;
    }

    $closedStmt = $pdo->prepare(
        'SELECT id FROM email_conversations
         WHERE (' . implode(' OR ', $openConditions) . ')
           AND subject_normalized = :subject_normalized
           AND participant_key = :participant_key
           AND is_closed = 1
         ORDER BY id DESC
         LIMIT 1'
    );
    $closedStmt->execute($openParams);
    $closedConversationId = (int) $closedStmt->fetchColumn();
    if ($closedConversationId > 0) {
        $reopenStmt = $pdo->prepare(
            'UPDATE email_conversations
             SET is_closed = 0,
                 closed_at = NULL,
                 last_activity_at = :last_activity_at
             WHERE id = :id'
        );
        $reopenStmt->execute([
            ':last_activity_at' => $activityAt,
            ':id' => $closedConversationId
        ]);
        return $closedConversationId;
    }

    return null;
}

function ensureConversationForEmail(
    PDO $pdo,
    array $mailbox,
    string $fromEmail,
    string $toEmails,
    ?string $subject,
    bool $forceNew,
    ?string $activityAt,
    ?int $scopeTeamId = null,
    ?int $scopeUserId = null
): ?int {
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $teamId = $scopeTeamId !== null
        ? (int) $scopeTeamId
        : (!empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null);
    $userId = $scopeUserId !== null
        ? (int) $scopeUserId
        : (!empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null);
    if ($mailboxId <= 0 || (!$teamId && !$userId)) {
        return null;
    }

    $normalizedSubject = normalizeConversationSubject($subject);
    $displaySubject = formatConversationSubject($subject);
    $participantKey = buildConversationParticipantKey(getConversationMailboxPrimaryEmail($mailbox), $fromEmail, $toEmails);
    $activityAt = $activityAt ?: date('Y-m-d H:i:s');

    if (!$forceNew) {
        $conversationId = findConversationForEmail(
            $pdo,
            $mailbox,
            $fromEmail,
            $toEmails,
            $subject,
            $activityAt,
            $teamId,
            $userId
        );
        if ($conversationId !== null) {
            return $conversationId;
        }

        return null;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO email_conversations
         (mailbox_id, team_id, user_id, subject, subject_normalized, participant_key, last_activity_at)
         VALUES
         (:mailbox_id, :team_id, :user_id, :subject, :subject_normalized, :participant_key, :last_activity_at)'
    );
    $insertStmt->execute([
        ':mailbox_id' => $mailboxId,
        ':team_id' => $teamId,
        ':user_id' => $userId,
        ':subject' => $displaySubject,
        ':subject_normalized' => $normalizedSubject,
        ':participant_key' => $participantKey,
        ':last_activity_at' => $activityAt
    ]);

    return (int) $pdo->lastInsertId();
}

function closeConversationForUser(int $conversationId, int $userId): bool
{
    if ($conversationId <= 0 || $userId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversation) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE email_conversations
         SET is_closed = 1,
             closed_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([':id' => $conversationId]);

    return true;
}

function reopenConversationForUser(int $conversationId, int $userId): bool
{
    if ($conversationId <= 0 || $userId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversation) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE email_conversations
         SET is_closed = 0,
             closed_at = NULL
         WHERE id = :id AND is_closed = 1'
    );
    $stmt->execute([':id' => $conversationId]);

    return true;
}

function deleteClosedConversationForUser(int $conversationId, int $userId): bool
{
    if ($conversationId <= 0 || $userId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversation || empty($conversation['is_closed'])) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare(
            'UPDATE email_messages
             SET conversation_id = NULL
             WHERE conversation_id = :conversation_id'
        );
        $updateStmt->execute([':conversation_id' => $conversationId]);

        $deleteStmt = $pdo->prepare('DELETE FROM email_conversations WHERE id = :id');
        $deleteStmt->execute([':id' => $conversationId]);

        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function removeMessageFromConversationForUser(int $messageId, int $conversationId, int $userId): bool
{
    if ($messageId <= 0 || $conversationId <= 0 || $userId <= 0) {
        return false;
    }

    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversation) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE email_messages
         SET conversation_id = NULL
         WHERE id = :id AND conversation_id = :conversation_id'
    );
    $stmt->execute([
        ':id' => $messageId,
        ':conversation_id' => $conversationId
    ]);

    return true;
}

function deleteAllClosedConversationsForUser(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $pdo = getDatabaseConnection();

    $selectStmt = $pdo->prepare(
        'SELECT c.id
         FROM email_conversations c
         LEFT JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = :viewer_user_id_join
         WHERE c.is_closed = 1
           AND (
             (c.team_id IS NOT NULL AND tm.user_id = :viewer_user_id_team)
             OR (c.user_id IS NOT NULL AND c.user_id = :viewer_user_id_owner)
           )'
    );
    $selectStmt->execute([
        ':viewer_user_id_join' => $userId,
        ':viewer_user_id_team' => $userId,
        ':viewer_user_id_owner' => $userId,
    ]);

    $conversationIds = array_map('intval', array_column($selectStmt->fetchAll(), 'id'));
    if (!$conversationIds) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));

    $pdo->beginTransaction();
    try {
        $unlinkStmt = $pdo->prepare(
            'UPDATE email_messages
             SET conversation_id = NULL
             WHERE conversation_id IN (' . $placeholders . ')'
        );
        $unlinkStmt->execute($conversationIds);

        $deleteStmt = $pdo->prepare(
            'DELETE FROM email_conversations
             WHERE id IN (' . $placeholders . ')'
        );
        $deleteStmt->execute($conversationIds);

        $pdo->commit();
        return count($conversationIds);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
