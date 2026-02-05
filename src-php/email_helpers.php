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

    $parts = preg_split('/[\s,;]+/', $value) ?: [];
    $clean = [];
    foreach ($parts as $part) {
        $candidate = filter_var($part, FILTER_SANITIZE_EMAIL);
        if ($candidate !== '') {
            $clean[] = $candidate;
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
        'sent' => 'Sent'
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
