<?php
require_once __DIR__ . '/database.php';

const EMAIL_PAGE_SIZE_DEFAULT = 25;
const EMAIL_ATTACHMENT_QUOTA_DEFAULT = 104857600;

function fetchUserTeams(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT t.id, t.name
         FROM teams t
         JOIN team_members tm ON tm.team_id = t.id
         WHERE tm.user_id = :user_id
         ORDER BY t.name'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetchTeamMailboxes(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.name, m.team_id, t.name AS team_name, m.attachment_quota_bytes
         FROM mailboxes m
         JOIN team_members tm ON tm.team_id = m.team_id
         JOIN teams t ON t.id = m.team_id
         WHERE tm.user_id = :user_id
         ORDER BY t.name, m.name'
    );
    $stmt->execute([':user_id' => $userId]);
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

function ensureMailboxAccess(PDO $pdo, int $mailboxId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, t.name AS team_name
         FROM mailboxes m
         JOIN team_members tm ON tm.team_id = m.team_id
         JOIN teams t ON t.id = m.team_id
         WHERE m.id = :mailbox_id AND tm.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':mailbox_id' => $mailboxId,
        ':user_id' => $userId
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

function ensureConversationForEmail(
    PDO $pdo,
    array $mailbox,
    string $fromEmail,
    string $toEmails,
    ?string $subject,
    bool $forceNew,
    ?string $activityAt
): ?int {
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $teamId = (int) ($mailbox['team_id'] ?? 0);
    if ($mailboxId <= 0 || $teamId <= 0) {
        return null;
    }

    $normalizedSubject = normalizeConversationSubject($subject);
    $displaySubject = formatConversationSubject($subject);
    $participantKey = buildConversationParticipantKey(getMailboxPrimaryEmail($mailbox), $fromEmail, $toEmails);
    $activityAt = $activityAt ?: date('Y-m-d H:i:s');

    if (!$forceNew) {
        $stmt = $pdo->prepare(
            'SELECT id FROM email_conversations
             WHERE mailbox_id = :mailbox_id
               AND subject_normalized = :subject_normalized
               AND participant_key = :participant_key
               AND is_closed = 0
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':mailbox_id' => $mailboxId,
            ':subject_normalized' => $normalizedSubject,
            ':participant_key' => $participantKey
        ]);
        $conversationId = (int) $stmt->fetchColumn();
        if ($conversationId > 0) {
            $updateStmt = $pdo->prepare(
                'UPDATE email_conversations
                 SET last_activity_at = :last_activity_at
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':last_activity_at' => $activityAt,
                ':id' => $conversationId
            ]);
            return $conversationId;
        }
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO email_conversations
         (mailbox_id, team_id, subject, subject_normalized, participant_key, last_activity_at)
         VALUES
         (:mailbox_id, :team_id, :subject, :subject_normalized, :participant_key, :last_activity_at)'
    );
    $insertStmt->execute([
        ':mailbox_id' => $mailboxId,
        ':team_id' => $teamId,
        ':subject' => $displaySubject,
        ':subject_normalized' => $normalizedSubject,
        ':participant_key' => $participantKey,
        ':last_activity_at' => $activityAt
    ]);

    return (int) $pdo->lastInsertId();
}
