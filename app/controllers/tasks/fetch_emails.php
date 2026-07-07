<?php
require_once __DIR__ . '/../../models/communication/mail_fetch_helpers.php';
require_once __DIR__ . '/../../models/communication/conversation_helpers.php';
require_once __DIR__ . '/../../models/core/link_scope.php';

function normalizeFetchedMailboxFolder(string $mailboxName): string
{
    $normalized = strtolower(trim($mailboxName));
    if ($normalized === '') {
        return 'inbox';
    }

    $normalized = str_replace(' ', '', $normalized);
    $segments = preg_split('/[\.\/\\\\]+/', $normalized) ?: [$normalized];
    $leaf = is_array($segments) ? (string) (end($segments) ?: $normalized) : $normalized;
    $candidates = array_values(array_unique(array_filter([$leaf, $normalized], static fn($value): bool => $value !== '')));

    foreach ($candidates as $candidate) {
        if ($candidate === 'inbox') {
            return 'inbox';
        }
        if (in_array($candidate, ['junk', 'spam', 'bulkmail'], true)) {
            return 'junk';
        }
        if (in_array($candidate, ['sent', 'sentitems', 'sentmail'], true)) {
            return 'sent';
        }
        if (in_array($candidate, ['draft', 'drafts'], true)) {
            return 'drafts';
        }
        if (in_array($candidate, ['trash', 'deleteditems', 'deletedmessages', 'bin'], true)) {
            return 'trash';
        }
    }

    return 'inbox';
}

function runFetchEmailsTask(PDO $pdo): void
{
    $attachmentRoot = defined('MAIL_ATTACHMENTS_PATH') ? MAIL_ATTACHMENTS_PATH : '';
    if ($attachmentRoot === '') {
        echo "MAIL_ATTACHMENTS_PATH not configured.\n";
        return;
    }

    $attachmentBase = rtrim($attachmentRoot, '/\\');

    $stmt = $pdo->query('SELECT * FROM mailboxes ORDER BY id');
    $mailboxes = $stmt->fetchAll();

    if (!$mailboxes) {
        echo "No mailboxes configured.\n";
        return;
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
            $fallbackString = sprintf('{%s:%d%s/novalidate-cert}INBOX', $mailbox['imap_host'], (int) $mailbox['imap_port'], $imapFlags);
            $imap = @imap_open($fallbackString, $mailbox['imap_username'], $imapPassword);
        }

        if (!$imap) {
            $lastError = imap_last_error();
            $allErrors = imap_errors();
            $errorDetails = trim(implode(' | ', array_filter([
                $lastError,
                $allErrors ? implode(', ', $allErrors) : ''
            ])));
            $logMessage = sprintf(
                'IMAP connect failed mailbox=%d host=%s port=%d encryption=%s user=%s error=%s',
                $mailboxId,
                $mailbox['imap_host'] ?? '',
                (int) $mailbox['imap_port'],
                $encryption,
                $mailbox['imap_username'] ?? '',
                $errorDetails
            );
            logAction(null, 'email_imap_connect_error', $logMessage);
            echo "  Failed to connect to IMAP: " . ($errorDetails !== '' ? $errorDetails : 'Unknown error') . "\n";
            continue;
        }

        $foldersToFetch = [
            'INBOX' => 'inbox',
            'Junk' => 'junk',
            'Spam' => 'junk'
        ];

        $listedMailboxes = @imap_getmailboxes($imap, sprintf('{%s:%d%s}', $mailbox['imap_host'], (int) $mailbox['imap_port'], $imapFlags), '*');
        if (is_array($listedMailboxes)) {
            $connectionPrefix = sprintf('{%s:%d%s}', $mailbox['imap_host'], (int) $mailbox['imap_port'], $imapFlags);
            foreach ($listedMailboxes as $listedMailbox) {
                $fullName = (string) ($listedMailbox->name ?? '');
                if ($fullName === '') {
                    continue;
                }
                $folderName = str_starts_with($fullName, $connectionPrefix)
                    ? substr($fullName, strlen($connectionPrefix))
                    : $fullName;
                if ($folderName === '') {
                    continue;
                }
                $normalizedFolder = normalizeFetchedMailboxFolder($folderName);
                if (in_array($normalizedFolder, ['inbox', 'junk'], true)) {
                    $foldersToFetch[$folderName] = $normalizedFolder;
                }
            }
        }

        $mailboxDir = $attachmentBase . DIRECTORY_SEPARATOR . $mailboxId;
        if (!is_dir($mailboxDir)) {
            if (!mkdir($mailboxDir, 0770, true) && !is_dir($mailboxDir)) {
                $logMessage = sprintf(
                    'Attachment directory create failed mailbox=%d path=%s',
                    $mailboxId,
                    $mailboxDir
                );
                logAction(null, 'email_attachment_dir_error', $logMessage);
                echo "  Failed to create attachment directory: {$mailboxDir}\n";
                imap_close($imap);
                continue;
            }
        }

        $lastProcessedUid = (int) ($mailbox['last_uid'] ?? 0);

        foreach ($foldersToFetch as $remoteFolder => $normalizedFolder) {
            $targetMailboxString = sprintf('{%s:%d%s}%s', $mailbox['imap_host'], (int) $mailbox['imap_port'], $imapFlags, $remoteFolder);
            if (!@imap_reopen($imap, $targetMailboxString)) {
                $targetMailboxString = sprintf('{%s:%d%s/novalidate-cert}%s', $mailbox['imap_host'], (int) $mailbox['imap_port'], $imapFlags, $remoteFolder);
                if (!@imap_reopen($imap, $targetMailboxString)) {
                    continue;
                }
            }

            $lastUid = $normalizedFolder === 'inbox' ? (int) ($mailbox['last_uid'] ?? 0) : 0;
            $uidSearch = 'ALL';
            $uids = imap_search($imap, $uidSearch, SE_UID) ?: [];
            if ($lastUid > 0) {
                $uids = array_values(array_filter($uids, static fn($uid) => (int) $uid > $lastUid));
            }
            if (!$uids) {
                continue;
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

            $bodyHtml = $htmlBody !== '' ? $htmlBody : '';
            $bodyText = $plainBody !== '' ? $plainBody : ($bodyHtml !== '' ? htmlToText($bodyHtml) : '');
            if ($bodyText === '') {
                $fallback = (string) imap_body($imap, (string) $uid, FT_UID);
                $bodyText = trim($fallback);
            }

            try {
                $pdo = getDatabaseConnection();
                $pdo->beginTransaction();

                $toEmails = $toRaw !== '' ? parseEmailAddressList($toRaw) : '';
                $teamScopeId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null;
                $userScopeId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null;
                $conversationId = null;
                if ($teamScopeId || $userScopeId) {
                    $conversationId = findConversationForEmail(
                        $pdo,
                        $mailbox,
                        (string) $fromEmail,
                        $toEmails,
                        $subject,
                        $date !== '' ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s'),
                        $teamScopeId,
                        $userScopeId
                    );
                    if ($conversationId === null && !empty($mailbox['auto_start_conversation_inbound'])) {
                        $conversationId = ensureConversationForEmail(
                            $pdo,
                            $mailbox,
                            (string) $fromEmail,
                            $toEmails,
                            $subject,
                            true,
                            $date !== '' ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s'),
                            $teamScopeId,
                            $userScopeId
                        );
                    }
                }

                $fromCandidate = $fromEmail !== '' ? strtolower(trim((string) $fromEmail)) : '';
                $mailboxIdentityEmails = [];
                $smtpIdentity = strtolower(trim((string) ($mailbox['smtp_username'] ?? '')));
                $imapIdentity = strtolower(trim((string) ($mailbox['imap_username'] ?? '')));
                if ($smtpIdentity !== '' && filter_var($smtpIdentity, FILTER_VALIDATE_EMAIL)) {
                    $mailboxIdentityEmails[] = $smtpIdentity;
                }
                if ($imapIdentity !== '' && filter_var($imapIdentity, FILTER_VALIDATE_EMAIL)) {
                    $mailboxIdentityEmails[] = $imapIdentity;
                }
                $mailboxIdentityEmails = array_values(array_unique($mailboxIdentityEmails));

                $isSentMessage = $fromCandidate !== '' && in_array($fromCandidate, $mailboxIdentityEmails, true);
                $messageFolder = $isSentMessage ? 'sent' : $normalizedFolder;
                $messageTimestamp = $date !== '' ? date('Y-m-d H:i:s', strtotime($date)) : null;

                $stmt = $pdo->prepare(
                    'INSERT INTO email_messages
                     (mailbox_id, team_id, user_id, folder, subject, body, body_html, from_name, from_email, to_emails, cc_emails, message_id, is_read, received_at, sent_at, conversation_id)
                     VALUES
                     (:mailbox_id, :team_id, :user_id, :folder, :subject, :body, :body_html, :from_name, :from_email, :to_emails, :cc_emails, :message_id, :is_read, :received_at, :sent_at, :conversation_id)'
                );
                $stmt->execute([
                    ':mailbox_id' => $mailboxId,
                    ':team_id' => $mailbox['team_id'] ?? null,
                    ':user_id' => $mailbox['user_id'] ?? null,
                    ':folder' => $messageFolder,
                    ':subject' => $subject !== '' ? $subject : null,
                    ':body' => $bodyText !== '' ? $bodyText : null,
                    ':body_html' => $bodyHtml !== '' ? $bodyHtml : null,
                    ':from_name' => $fromName !== '' ? $fromName : null,
                    ':from_email' => $fromEmail !== '' ? $fromEmail : null,
                    ':to_emails' => $toEmails !== '' ? $toEmails : null,
                    ':cc_emails' => $ccRaw !== '' ? parseEmailAddressList($ccRaw) : null,
                    ':message_id' => $messageId !== '' ? $messageId : null,
                    ':is_read' => $isSentMessage ? 1 : 0,
                    ':received_at' => $isSentMessage ? null : $messageTimestamp,
                    ':sent_at' => $isSentMessage ? $messageTimestamp : null,
                    ':conversation_id' => $conversationId
                ]);
                $emailId = (int) $pdo->lastInsertId();

                if ($fromCandidate !== '') {
                    $linkTeamId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null;
                    $linkUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null;
                    if ($linkTeamId !== null || $linkUserId !== null) {
                        $contactIds = findContactIdsByEmail($pdo, $fromCandidate, $linkTeamId);
                        $venueIds = findVenueIdsByEmail($pdo, $fromCandidate);

                        $normalizedAutoLinks = normalizeLinkItems(array_merge(
                            array_map(static fn(int $id): array => ['type' => 'contact', 'id' => $id], $contactIds),
                            array_map(static fn(int $id): array => ['type' => 'venue', 'id' => $id], $venueIds)
                        ));

                        foreach ($normalizedAutoLinks as $autoLink) {
                            createObjectLink(
                                $pdo,
                                'email',
                                $emailId,
                                (string) ($autoLink['type'] ?? ''),
                                (int) ($autoLink['id'] ?? 0),
                                $linkTeamId,
                                $linkUserId
                            );
                        }
                    }
                }

                $messageDir = $mailboxDir . DIRECTORY_SEPARATOR . $emailId;
                if (!is_dir($messageDir)) {
                    if (!mkdir($messageDir, 0770, true) && !is_dir($messageDir)) {
                        $logMessage = sprintf(
                            'Attachment message directory create failed mailbox=%d email=%d path=%s',
                            $mailboxId,
                            $emailId,
                            $messageDir
                        );
                        logAction(null, 'email_attachment_dir_error', $logMessage);
                        $messageDir = $mailboxDir;
                    }
                }

                foreach ($attachments as $attachment) {
                    $fileSize = (int) ($attachment['size'] ?? 0);
                    $quotaLimit = (int) ($mailbox['attachment_quota_bytes'] ?? EMAIL_ATTACHMENT_QUOTA_DEFAULT);
                    $currentUsage = fetchMailboxQuotaUsage($pdo, $mailboxId);
                    if ($currentUsage + $fileSize > $quotaLimit) {
                        echo "  Attachment quota exceeded for mailbox {$mailboxId}, skipping attachment {$attachment['filename']}.\n";
                        continue;
                    }

                    $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $attachment['filename']);
                    $filePath = $messageDir . DIRECTORY_SEPARATOR . uniqid('att_', true) . '_' . $safeName;
                    if (file_put_contents($filePath, $attachment['data']) === false) {
                        $logMessage = sprintf(
                            'Attachment write failed mailbox=%d path=%s',
                            $mailboxId,
                            $filePath
                        );
                        logAction(null, 'email_attachment_write_error', $logMessage);
                    }

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

                if ($normalizedFolder === 'inbox') {
                    $stmt = $pdo->prepare('UPDATE mailboxes SET last_uid = :last_uid WHERE id = :id');
                    $stmt->execute([':last_uid' => $uid, ':id' => $mailboxId]);
                }
                $lastProcessedUid = max($lastProcessedUid, (int) $uid);
                $pdo->commit();

                if (!empty($mailbox['delete_after_retrieve'])) {
                    if (!imap_delete($imap, (string) $uid, FT_UID)) {
                        $deleteError = imap_last_error();
                        logAction(
                            null,
                            'email_imap_delete_error',
                            sprintf('Failed deleting UID %d for mailbox %d: %s', $uid, $mailboxId, $deleteError !== false ? $deleteError : 'Unknown error')
                        );
                    }
                }

                logAction(null, 'email_fetch', sprintf('Fetched email %s for mailbox %d', $messageId, $mailboxId));
            } catch (Throwable $error) {
                $pdo->rollBack();
                echo "  Failed to save email: " . $error->getMessage() . "\n";
            }
        }
        }

        if ($lastProcessedUid <= 0) {
            echo "  No new messages.\n";
        }

        if (!empty($mailbox['delete_after_retrieve'])) {
            imap_expunge($imap);
        }

        imap_close($imap);
    }
}
