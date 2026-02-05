<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src-php/database.php';
require_once __DIR__ . '/../src-php/email_helpers.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$attachmentBase = defined('MAIL_ATTACHMENTS_PATH') ? MAIL_ATTACHMENTS_PATH : '';
if ($attachmentBase === '') {
    echo "MAIL_ATTACHMENTS_PATH not configured.\n";
    exit(1);
}

function decodeHeaderValue(?string $value): string
{
    return $value ? imap_utf8($value) : '';
}

function parseEmailAddressList(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $parsed = imap_rfc822_parse_adrlist($raw, '');
    if (!is_array($parsed)) {
        return '';
    }

    $addresses = [];
    foreach ($parsed as $address) {
        if (!isset($address->mailbox, $address->host)) {
            continue;
        }
        $email = strtolower(trim($address->mailbox . '@' . $address->host));
        if ($email !== 'invalid_address@' && $email !== '') {
            $addresses[] = $email;
        }
    }

    return implode(', ', array_unique($addresses));
}

function getPartParameter(object $part, string $name): string
{
    $name = strtolower($name);
    foreach (['parameters', 'dparameters'] as $property) {
        if (!isset($part->{$property}) || !is_array($part->{$property})) {
            continue;
        }
        foreach ($part->{$property} as $param) {
            if (strtolower($param->attribute ?? '') === $name) {
                return decodeHeaderValue((string) $param->value);
            }
        }
    }

    return '';
}

function decodePartBody(string $body, int $encoding): string
{
    if ($encoding === 3) {
        return (string) base64_decode($body);
    }
    if ($encoding === 4) {
        return (string) quoted_printable_decode($body);
    }

    return $body;
}

function convertToUtf8(string $body, string $charset): string
{
    $charset = trim($charset);
    if ($charset === '') {
        return $body;
    }

    $charset = strtoupper($charset);
    if ($charset === 'UTF-8' || $charset === 'UTF8') {
        return $body;
    }

    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($body, 'UTF-8', $charset);
    }

    return $body;
}

function fetchPartBody($imap, int $uid, string $partNumber): string
{
    if ($partNumber === '') {
        return (string) imap_body($imap, (string) $uid, FT_UID);
    }

    return (string) imap_fetchbody($imap, (string) $uid, $partNumber, FT_UID);
}

function partMimeType(object $part): string
{
    $typeMap = [
        0 => 'text',
        1 => 'multipart',
        2 => 'message',
        3 => 'application',
        4 => 'audio',
        5 => 'image',
        6 => 'video',
        7 => 'other'
    ];

    $type = $typeMap[$part->type ?? 0] ?? 'application';
    $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));

    return $type . '/' . $subtype;
}

function htmlToText(string $html): string
{
    $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\s*\/p\s*>/i', "\n", $html);
    $text = trim(strip_tags($html));
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function collectParts($imap, int $uid, object $structure, string $partNumber, string &$plainBody, string &$htmlBody, array &$attachments): void
{
    if ($structure->type === 1 && isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $nextNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
            collectParts($imap, $uid, $part, $nextNumber, $plainBody, $htmlBody, $attachments);
        }
        return;
    }

    $filename = getPartParameter($structure, 'filename');
    if ($filename === '') {
        $filename = getPartParameter($structure, 'name');
    }
    $disposition = strtolower((string) ($structure->disposition ?? ''));
    $isAttachment = $filename !== '' || $disposition === 'attachment' || $disposition === 'inline';

    $body = fetchPartBody($imap, $uid, $partNumber === '' ? '1' : $partNumber);
    $body = decodePartBody($body, (int) ($structure->encoding ?? 0));
    $charset = getPartParameter($structure, 'charset');
    $body = convertToUtf8($body, $charset);

    if ($isAttachment && $filename !== '') {
        $attachments[] = [
            'filename' => $filename,
            'data' => $body,
            'mime_type' => partMimeType($structure),
            'size' => strlen($body)
        ];
        return;
    }

    if ($structure->type === 0) {
        $subtype = strtolower((string) ($structure->subtype ?? 'plain'));
        if ($subtype === 'plain' && $plainBody === '') {
            $plainBody = trim($body);
        }
        if ($subtype === 'html' && $htmlBody === '') {
            $htmlBody = trim($body);
        }
    }
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query('SELECT * FROM mailboxes ORDER BY id');
    $mailboxes = $stmt->fetchAll();
} catch (Throwable $error) {
    echo "Failed to load mailboxes: " . $error->getMessage() . "\n";
    exit(1);
}

if (!$mailboxes) {
    echo "No mailboxes configured.\n";
    exit(0);
}

foreach ($mailboxes as $mailbox) {
    $mailboxId = (int) $mailbox['id'];
    echo "Fetching mailbox {$mailboxId} ({$mailbox['name']})...\n";

    $imapPassword = decryptSettingValue($mailbox['imap_password'] ?? '');
    if ($imapPassword === '') {
        echo "  Skipping: IMAP password not set.\n";
        continue;
    }

    $encryption = $mailbox['imap_encryption'] ?? 'ssl';
    $imapFlags = '/imap';
    if ($encryption === 'ssl') {
        $imapFlags .= '/ssl';
    } elseif ($encryption === 'tls') {
        $imapFlags .= '/tls';
    } else {
        $imapFlags .= '/notls';
    }

    $mailboxString = sprintf('{%s:%d%s}INBOX', $mailbox['imap_host'], (int) $mailbox['imap_port'], $imapFlags);

    $imap = @imap_open($mailboxString, $mailbox['imap_username'], $imapPassword);
    if (!$imap) {
        echo "  Failed to connect to IMAP: " . imap_last_error() . "\n";
        continue;
    }

    $lastUid = (int) ($mailbox['last_uid'] ?? 0);
    $uidSearch = $lastUid > 0 ? sprintf('%d:*', $lastUid + 1) : '1:*';
    $uids = imap_search($imap, $uidSearch, SE_UID) ?: [];
    if (!$uids) {
        echo "  No new messages.\n";
        imap_close($imap);
        continue;
    }

    $mailboxDir = rtrim($attachmentBase, '/\\') . DIRECTORY_SEPARATOR . 'mailbox_' . $mailboxId;
    if (!is_dir($mailboxDir)) {
        mkdir($mailboxDir, 0770, true);
    }

    foreach ($uids as $uid) {
        $overview = imap_fetch_overview($imap, (string) $uid, FT_UID);
        if (!$overview || !isset($overview[0])) {
            continue;
        }
        $overview = $overview[0];

        $subject = decodeHeaderValue($overview->subject ?? '');
        $fromRaw = decodeHeaderValue($overview->from ?? '');
        $toRaw = decodeHeaderValue($overview->to ?? '');
        $ccRaw = decodeHeaderValue($overview->cc ?? '');
        $date = isset($overview->date) ? (string) $overview->date : '';
        $messageId = isset($overview->message_id) ? (string) $overview->message_id : '';

        $fromEmail = '';
        $fromName = '';
        $addressList = imap_rfc822_parse_adrlist($fromRaw, '');
        if (is_array($addressList) && isset($addressList[0])) {
            $address = $addressList[0];
            $fromEmail = trim(($address->mailbox ?? '') . '@' . ($address->host ?? ''));
            $fromName = isset($address->personal) ? decodeHeaderValue((string) $address->personal) : '';
        }

        $plainBody = '';
        $htmlBody = '';
        $attachments = [];
        $structure = imap_fetchstructure($imap, (string) $uid, FT_UID);
        if ($structure) {
            collectParts($imap, $uid, $structure, '', $plainBody, $htmlBody, $attachments);
        }

        $body = $plainBody !== '' ? $plainBody : ($htmlBody !== '' ? htmlToText($htmlBody) : '');
        if ($body === '') {
            $fallback = (string) imap_body($imap, (string) $uid, FT_UID);
            $body = trim($fallback);
        }

        try {
            $pdo = getDatabaseConnection();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO email_messages
                 (mailbox_id, team_id, folder, subject, body, from_name, from_email, to_emails, cc_emails, message_id, received_at)
                 VALUES
                 (:mailbox_id, :team_id, "inbox", :subject, :body, :from_name, :from_email, :to_emails, :cc_emails, :message_id, :received_at)'
            );
            $stmt->execute([
                ':mailbox_id' => $mailboxId,
                ':team_id' => $mailbox['team_id'],
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? $body : null,
                ':from_name' => $fromName !== '' ? $fromName : null,
                ':from_email' => $fromEmail !== '' ? $fromEmail : null,
                ':to_emails' => $toRaw !== '' ? parseEmailAddressList($toRaw) : null,
                ':cc_emails' => $ccRaw !== '' ? parseEmailAddressList($ccRaw) : null,
                ':message_id' => $messageId !== '' ? $messageId : null,
                ':received_at' => $date !== '' ? date('Y-m-d H:i:s', strtotime($date)) : null
            ]);
            $emailId = (int) $pdo->lastInsertId();

            foreach ($attachments as $attachment) {
                $fileSize = (int) ($attachment['size'] ?? 0);
                $quotaLimit = (int) ($mailbox['attachment_quota_bytes'] ?? EMAIL_ATTACHMENT_QUOTA_DEFAULT);
                $currentUsage = fetchMailboxQuotaUsage($pdo, $mailboxId);
                if ($currentUsage + $fileSize > $quotaLimit) {
                    echo "  Attachment quota exceeded for mailbox {$mailboxId}, skipping attachment {$attachment['filename']}.\n";
                    continue;
                }

                $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $attachment['filename']);
                $filePath = $mailboxDir . DIRECTORY_SEPARATOR . uniqid('att_', true) . '_' . $safeName;
                file_put_contents($filePath, $attachment['data']);

                $stmt = $pdo->prepare(
                    'INSERT INTO email_attachments
                     (email_id, mailbox_id, filename, file_path, mime_type, file_size)
                     VALUES
                     (:email_id, :mailbox_id, :filename, :file_path, :mime_type, :file_size)'
                );
                $stmt->execute([
                    ':email_id' => $emailId,
                    ':mailbox_id' => $mailboxId,
                    ':filename' => $attachment['filename'],
                    ':file_path' => $filePath,
                    ':mime_type' => $attachment['mime_type'] ?? null,
                    ':file_size' => $fileSize
                ]);
            }

            $stmt = $pdo->prepare('UPDATE mailboxes SET last_uid = :last_uid WHERE id = :id');
            $stmt->execute([':last_uid' => $uid, ':id' => $mailboxId]);
            $pdo->commit();

            logAction(null, 'email_fetch', sprintf('Fetched email %s for mailbox %d', $messageId, $mailboxId));
        } catch (Throwable $error) {
            $pdo->rollBack();
            echo "  Failed to save email: " . $error->getMessage() . "\n";
        }
    }

    imap_close($imap);
}
