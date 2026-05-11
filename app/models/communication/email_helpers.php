<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/link_helpers.php';
require_once __DIR__ . '/../core/object_links.php';
require_once __DIR__ . '/../core/link_scope.php';
require_once __DIR__ . '/team_helpers.php';

const EMAIL_PAGE_SIZE_DEFAULT = 25;
const EMAIL_ATTACHMENT_QUOTA_DEFAULT = 104857600;

function fetchTeamMailboxes(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.name, m.display_name, m.team_id, t.name AS team_name, m.attachment_quota_bytes
         FROM mailboxes m
         JOIN team_members tm ON tm.team_id = m.team_id
         JOIN teams t ON t.id = m.team_id
         WHERE tm.user_id = :user_id
         ORDER BY t.name, m.name'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetchAccessibleMailboxes(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.name, m.display_name, m.team_id, m.user_id,
                m.imap_username, m.smtp_username,
                t.name AS team_name, m.attachment_quota_bytes
         FROM mailboxes m
         LEFT JOIN team_members tm ON tm.team_id = m.team_id AND tm.user_id = :team_user_id
         LEFT JOIN teams t ON t.id = m.team_id
         WHERE m.user_id = :owner_user_id
            OR tm.user_id = :member_user_id
         ORDER BY
           CASE WHEN m.user_id = :order_user_id THEN 0 ELSE 1 END,
           t.name,
           m.name'
    );
    $stmt->execute([
        ':team_user_id' => $userId,
        ':owner_user_id' => $userId,
        ':member_user_id' => $userId,
        ':order_user_id' => $userId
    ]);
    return $stmt->fetchAll();
}

function fetchTeamAdminTeams(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT t.id, t.name
         FROM teams t
         JOIN team_members tm ON tm.team_id = t.id
         WHERE tm.user_id = :user_id AND tm.role = "admin"
         ORDER BY t.name'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetchTeamTemplates(PDO $pdo, int $userId, ?int $teamId = null): array
{
    $params = [':user_id' => $userId];
    $teamFilter = '';
    if ($teamId) {
        $teamFilter = 'AND t.id = :team_id';
        $params[':team_id'] = $teamId;
    }

    $stmt = $pdo->prepare(
        'SELECT et.*
         FROM email_templates et
         JOIN teams t ON t.id = et.team_id
         JOIN team_members tm ON tm.team_id = t.id
         WHERE tm.user_id = :user_id ' . $teamFilter . '
         ORDER BY et.name'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchMailboxQuotaUsage(PDO $pdo, int $mailboxId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(file_size), 0) FROM email_attachments WHERE mailbox_id = :mailbox_id');
    $stmt->execute([':mailbox_id' => $mailboxId]);
    return (int) $stmt->fetchColumn();
}

function fetchAttachmentForUser(int $attachmentId, int $userId): ?array
{
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'SELECT ea.*, em.mailbox_id
         FROM email_attachments ea
         JOIN email_messages em ON em.id = ea.email_id
         LEFT JOIN team_members tm ON tm.team_id = em.team_id AND tm.user_id = :team_user_id
         WHERE ea.id = :id
           AND (tm.user_id = :member_user_id OR em.user_id = :owner_user_id)
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $attachmentId,
        ':team_user_id' => $userId,
        ':member_user_id' => $userId,
        ':owner_user_id' => $userId
    ]);

    $attachment = $stmt->fetch();
    return $attachment ?: null;
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

function ensureMailboxAccess(PDO $pdo, int $mailboxId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, t.name AS team_name
         FROM mailboxes m
         LEFT JOIN team_members tm ON tm.team_id = m.team_id AND tm.user_id = :member_user_id_join
         LEFT JOIN teams t ON t.id = m.team_id
         WHERE m.id = :mailbox_id
           AND (m.user_id = :owner_user_id OR tm.user_id = :member_user_id_where)
         LIMIT 1'
    );
    $stmt->execute([
        ':mailbox_id' => $mailboxId,
        ':member_user_id_join' => $userId,
        ':member_user_id_where' => $userId,
        ':owner_user_id' => $userId
    ]);
    $mailbox = $stmt->fetch();
    return $mailbox ?: null;
}

function ensureTemplateAccess(PDO $pdo, int $templateId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT et.*
         FROM email_templates et
         JOIN team_members tm ON tm.team_id = et.team_id
         WHERE et.id = :template_id AND tm.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':template_id' => $templateId,
        ':user_id' => $userId
    ]);
    $template = $stmt->fetch();
    return $template ?: null;
}

function normalizeEmailList(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $parts = preg_split('/[,;]+/', $value) ?: [];
    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $part, $matches)) {
            foreach ($matches[0] as $match) {
                $clean[] = strtolower($match);
            }
            continue;
        }

        $candidate = filter_var($part, FILTER_SANITIZE_EMAIL);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $clean[] = strtolower($candidate);
        }
    }

    return implode(', ', array_unique($clean));
}

function splitEmailList(string $value): array
{
    $normalized = normalizeEmailList($value);
    if ($normalized === '') {
        return [];
    }

    $parts = array_map('trim', explode(',', $normalized));
    return array_values(array_filter($parts, static fn($part) => $part !== ''));
}

/**
 * Persist draft/scheduled email payload and associated object links.
 *
 * @param array<string,mixed> $context
 * @param array<string,mixed> $payload
 * @return array{draft_id:int,is_update:bool,is_schedule_action:bool}
 */
function persistDraftEmailPayload(PDO $pdo, array $context, array $payload): array
{
    $mailbox = (array) ($context['mailbox'] ?? []);
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $linkTeamId = isset($context['link_team_id']) ? (int) $context['link_team_id'] : null;
    $linkUserId = isset($context['link_user_id']) ? (int) $context['link_user_id'] : null;
    $userId = (int) ($context['user_id'] ?? 0);

    $draftId = (int) ($payload['draft_id'] ?? 0);
    $conversationId = (int) ($payload['conversation_id'] ?? 0);
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $fromName = trim((string) ($payload['from_name'] ?? ''));
    $fromEmail = trim((string) ($payload['from_email'] ?? ''));
    $toEmails = trim((string) ($payload['to_emails'] ?? ''));
    $ccEmails = trim((string) ($payload['cc_emails'] ?? ''));
    $bccEmails = trim((string) ($payload['bcc_emails'] ?? ''));
    $scheduledAt = isset($payload['scheduled_at']) ? (string) $payload['scheduled_at'] : null;
    $startNewConversation = !empty($payload['start_new_conversation']);
    $isScheduleAction = !empty($payload['is_schedule_action']);
    $rawLinkItems = isset($payload['link_items']) && is_array($payload['link_items']) ? $payload['link_items'] : [];
    $linkItems = [];
    foreach ($rawLinkItems as $linkItem) {
        [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
        $linkItems[] = [
            'type' => $type,
            'id' => (int) $id,
        ];
    }
    $linkItems = normalizeLinkItems($linkItems);

    if ($mailboxId <= 0) {
        throw new InvalidArgumentException('Mailbox id is required for draft persistence.');
    }

    $pdo->beginTransaction();
    try {
        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE email_messages
                 SET subject = :subject,
                     body = :body,
                     body_html = :body_html,
                     from_name = :from_name,
                     from_email = :from_email,
                     to_emails = :to_emails,
                     cc_emails = :cc_emails,
                     bcc_emails = :bcc_emails,
                     conversation_id = :conversation_id,
                     scheduled_at = :scheduled_at,
                     start_new_conversation = :start_new_conversation,
                     updated_at = NOW()
                 WHERE id = :id
                   AND mailbox_id = :mailbox_id
                   AND folder = "drafts"'
            );
            $stmt->execute([
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? strip_tags($body) : null,
                ':body_html' => $body !== '' ? $body : null,
                ':from_name' => $fromName !== '' ? $fromName : null,
                ':from_email' => $fromEmail !== '' ? $fromEmail : null,
                ':to_emails' => $toEmails !== '' ? $toEmails : null,
                ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
                ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
                ':conversation_id' => $conversationId > 0 ? $conversationId : null,
                ':scheduled_at' => $isScheduleAction ? $scheduledAt : null,
                ':start_new_conversation' => $startNewConversation ? 1 : 0,
                ':id' => $draftId,
                ':mailbox_id' => $mailboxId
            ]);

            if (!empty($mailbox['user_id'])) {
                $ownershipStmt = $pdo->prepare(
                    'UPDATE email_messages
                     SET team_id = NULL,
                         user_id = :user_id
                     WHERE id = :id'
                );
                $ownershipStmt->execute([
                    ':user_id' => (int) $mailbox['user_id'],
                    ':id' => $draftId
                ]);
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO email_messages
                 (mailbox_id, team_id, user_id, conversation_id, folder, subject, body, body_html, from_name, from_email, to_emails, cc_emails, bcc_emails, created_by, created_at, scheduled_at, start_new_conversation)
                 VALUES
                 (:mailbox_id, :team_id, :user_id, :conversation_id, "drafts", :subject, :body, :body_html, :from_name, :from_email, :to_emails, :cc_emails, :bcc_emails, :created_by, NOW(), :scheduled_at, :start_new_conversation)'
            );
            $stmt->execute([
                ':mailbox_id' => $mailboxId,
                ':team_id' => $mailbox['team_id'] ?? null,
                ':user_id' => $mailbox['user_id'] ?? null,
                ':conversation_id' => $conversationId > 0 ? $conversationId : null,
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? strip_tags($body) : null,
                ':body_html' => $body !== '' ? $body : null,
                ':from_name' => $fromName !== '' ? $fromName : null,
                ':from_email' => $fromEmail !== '' ? $fromEmail : null,
                ':to_emails' => $toEmails !== '' ? $toEmails : null,
                ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
                ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
                ':created_by' => $userId > 0 ? $userId : null,
                ':scheduled_at' => $isScheduleAction ? $scheduledAt : null,
                ':start_new_conversation' => $startNewConversation ? 1 : 0
            ]);
            $draftId = (int) $pdo->lastInsertId();
        }

        clearObjectLinks($pdo, 'email', $draftId, $linkTeamId, $linkUserId);
        foreach ($linkItems as $link) {
            createObjectLink(
                $pdo,
                'email',
                $draftId,
                (string) ($link['type'] ?? ''),
                (int) ($link['id'] ?? 0),
                $linkTeamId,
                $linkUserId
            );
        }

        $pdo->commit();

        return [
            'draft_id' => $draftId,
            'is_update' => (int) ($payload['draft_id'] ?? 0) > 0,
            'is_schedule_action' => $isScheduleAction,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Persist sent email payload and associated object links.
 *
 * @param array<string,mixed> $context
 * @param array<string,mixed> $payload
 * @return array{sent_id:int}
 */
function persistSentEmailPayload(PDO $pdo, array $context, array $payload): array
{
    $mailbox = (array) ($context['mailbox'] ?? []);
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $linkTeamId = isset($context['link_team_id']) ? (int) $context['link_team_id'] : null;
    $linkUserId = isset($context['link_user_id']) ? (int) $context['link_user_id'] : null;
    $userId = (int) ($context['user_id'] ?? 0);

    $conversationId = (int) ($payload['conversation_id'] ?? 0);
    $messageTeamId = $payload['message_team_id'] ?? null;
    $messageUserId = $payload['message_user_id'] ?? null;
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $fromName = trim((string) ($payload['from_name'] ?? ''));
    $fromEmail = trim((string) ($payload['from_email'] ?? ''));
    $toEmails = trim((string) ($payload['to_emails'] ?? ''));
    $ccEmails = trim((string) ($payload['cc_emails'] ?? ''));
    $bccEmails = trim((string) ($payload['bcc_emails'] ?? ''));
    $rawLinkItems = isset($payload['link_items']) && is_array($payload['link_items']) ? $payload['link_items'] : [];
    $linkItems = [];
    foreach ($rawLinkItems as $linkItem) {
        [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
        $linkItems[] = [
            'type' => $type,
            'id' => (int) $id,
        ];
    }
    $linkItems = normalizeLinkItems($linkItems);

    if ($mailboxId <= 0) {
        throw new InvalidArgumentException('Mailbox id is required for sent email persistence.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO email_messages
             (mailbox_id, team_id, user_id, folder, subject, body, body_html, from_name, from_email, to_emails, cc_emails, bcc_emails, created_by, sent_at, created_at, conversation_id)
             VALUES
             (:mailbox_id, :team_id, :user_id, "sent", :subject, :body, :body_html, :from_name, :from_email, :to_emails, :cc_emails, :bcc_emails, :created_by, NOW(), NOW(), :conversation_id)'
        );
        $stmt->execute([
            ':mailbox_id' => $mailboxId,
            ':team_id' => $messageTeamId,
            ':user_id' => $messageUserId,
            ':subject' => $subject !== '' ? $subject : null,
            ':body' => $body !== '' ? strip_tags($body) : null,
            ':body_html' => $body !== '' ? $body : null,
            ':from_name' => $fromName !== '' ? $fromName : null,
            ':from_email' => $fromEmail !== '' ? $fromEmail : null,
            ':to_emails' => $toEmails,
            ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
            ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
            ':created_by' => $userId > 0 ? $userId : null,
            ':conversation_id' => $conversationId > 0 ? $conversationId : null,
        ]);

        $sentId = (int) $pdo->lastInsertId();

        foreach ($linkItems as $link) {
            createObjectLink(
                $pdo,
                'email',
                $sentId,
                (string) ($link['type'] ?? ''),
                (int) ($link['id'] ?? 0),
                $linkTeamId,
                $linkUserId
            );
        }

        if ($conversationId > 0) {
            touchConversationActivity($pdo, $conversationId, date('Y-m-d H:i:s'));
        }

        $pdo->commit();

        return [
            'sent_id' => $sentId,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Resolve target conversation id for outbound send.
 *
 * @param array<string,mixed> $context
 * @param array<string,mixed> $payload
 * @return array{conversation_id:int,message_team_id:int|null,message_user_id:int|null}
 */
function resolveSendConversationContext(PDO $pdo, array $context, array $payload): array
{
    $mailbox = (array) ($context['mailbox'] ?? []);
    $userId = (int) ($context['user_id'] ?? 0);

    $conversationId = (int) ($payload['conversation_id'] ?? 0);
    $toEmails = trim((string) ($payload['to_emails'] ?? ''));
    $subject = trim((string) ($payload['subject'] ?? ''));
    $startNewConversation = !empty($payload['start_new_conversation']);
    $existingMessageTeamId = array_key_exists('message_team_id', $payload) && $payload['message_team_id'] !== null
        ? (int) $payload['message_team_id']
        : null;
    $existingMessageUserId = array_key_exists('message_user_id', $payload) && $payload['message_user_id'] !== null
        ? (int) $payload['message_user_id']
        : null;

    if ($conversationId <= 0) {
        $teamScopeId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : $existingMessageTeamId;
        $fallbackUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : $existingMessageUserId;

        $conversationId = $teamScopeId
            ? findConversationForEmail(
                $pdo,
                $mailbox,
                getMailboxPrimaryEmail($mailbox),
                $toEmails,
                $subject,
                date('Y-m-d H:i:s'),
                $teamScopeId,
                null
            )
            : findConversationForEmail(
                $pdo,
                $mailbox,
                getMailboxPrimaryEmail($mailbox),
                $toEmails,
                $subject,
                date('Y-m-d H:i:s'),
                null,
                $fallbackUserId
            );

        if ($conversationId <= 0 && $startNewConversation) {
            $conversationId = ensureConversationForEmail(
                $pdo,
                $mailbox,
                getMailboxPrimaryEmail($mailbox),
                $toEmails,
                $subject,
                true,
                date('Y-m-d H:i:s'),
                $teamScopeId,
                $fallbackUserId
            );
        }
    }

    $messageTeamId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : $existingMessageTeamId;
    $messageUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : $existingMessageUserId;

    if ($conversationId > 0 && $messageTeamId !== null) {
        $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
        if ($conversation) {
            $messageTeamId = !empty($conversation['team_id']) ? (int) $conversation['team_id'] : $messageTeamId;
        }
    }

    return [
        'conversation_id' => (int) $conversationId,
        'message_team_id' => $messageTeamId,
        'message_user_id' => $messageUserId,
    ];
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

function findContactIdsByEmail(PDO $pdo, string $email, ?int $teamId = null): array
{
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
        return [];
    }

    $params = [':email' => $normalized];
    $teamFilter = '';
    if ($teamId !== null) {
        $teamFilter = ' AND team_id = :team_id';
        $params[':team_id'] = $teamId;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM contacts
         WHERE email IS NOT NULL AND LOWER(email) = :email' . $teamFilter
    );
    $stmt->execute($params);

    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function findVenueIdsByEmail(PDO $pdo, string $email): array
{
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM venues
         WHERE contact_email IS NOT NULL AND LOWER(contact_email) = :email'
    );
    $stmt->execute([':email' => $normalized]);

    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function formatPlainEmailBodyWithQuotes(string $body): string
{
    $lines = preg_split("/\r\n|\r|\n/", $body) ?: [];
    $html = '';
    $depth = 0;

    foreach ($lines as $line) {
        $lineDepth = 0;
        if (preg_match('/^\s*(>+)(\s?)/', $line, $matches)) {
            $lineDepth = strlen($matches[1]);
        }

        $content = preg_replace('/^\s*>+\s?/', '', $line);
        while ($depth < $lineDepth) {
            $html .= '<blockquote type="cite">';
            $depth++;
        }
        while ($depth > $lineDepth) {
            $html .= '</blockquote>';
            $depth--;
        }

        if ($content === '') {
            $html .= '<br>';
        } else {
            $html .= htmlspecialchars($content) . '<br>';
        }
    }

    while ($depth > 0) {
        $html .= '</blockquote>';
        $depth--;
    }

    return $html;
}

function getEmailFolderOptions(): array
{
    return [
        'inbox' => 'Inbox',
        'drafts' => 'Drafts',
        'sent' => 'Sent',
        'trash' => 'Trash bin'
    ];
}

function getEmailSortOptions(): array
{
    return [
        'received_desc' => ['label' => 'Newest', 'column' => 'received_at', 'direction' => 'DESC'],
        'received_asc' => ['label' => 'Oldest', 'column' => 'received_at', 'direction' => 'ASC'],
        'subject_asc' => ['label' => 'Subject A-Z', 'column' => 'subject', 'direction' => 'ASC'],
        'subject_desc' => ['label' => 'Subject Z-A', 'column' => 'subject', 'direction' => 'DESC']
    ];
}

function calculateQuotaPercent(int $used, int $quota): int
{
    if ($quota <= 0) {
        return 0;
    }

    $percent = (int) round(($used / $quota) * 100);
    return max(0, min(100, $percent));
}

function formatBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $index = (int) floor(log($bytes, 1024));
    $index = min($index, count($units) - 1);
    $value = $bytes / pow(1024, $index);
    return number_format($value, 1) . ' ' . $units[$index];
}

function getMailboxPrimaryEmail(array $mailbox): string
{
    $email = strtolower(trim((string) ($mailbox['smtp_username'] ?? '')));
    if ($email === '') {
        $email = strtolower(trim((string) ($mailbox['imap_username'] ?? '')));
    }

    return $email;
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

function buildConversationParticipantKey(string $mailboxEmail, string $fromEmail, string $toEmails): string
{
    $mailboxEmail = strtolower(trim($mailboxEmail));
    $fromEmail = strtolower(trim($fromEmail));
    $recipientList = array_map('strtolower', splitEmailList($toEmails));

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
    $participantKey = buildConversationParticipantKey(getMailboxPrimaryEmail($mailbox), $fromEmail, $toEmails);
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
    $participantKey = buildConversationParticipantKey(getMailboxPrimaryEmail($mailbox), $fromEmail, $toEmails);
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
