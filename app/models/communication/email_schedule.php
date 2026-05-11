<?php
require_once __DIR__ . '/mail_delivery.php';
require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/conversation_helpers.php';
require_once __DIR__ . '/../core/object_links.php';

function fetchScheduledEmails(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT em.*, m.smtp_host, m.smtp_port, m.smtp_username, m.smtp_password, m.smtp_encryption,
                m.display_name, m.team_id AS mailbox_team_id, m.user_id AS mailbox_user_id
         FROM email_messages em
         JOIN mailboxes m ON m.id = em.mailbox_id
         WHERE em.folder = "drafts"
           AND em.scheduled_at IS NOT NULL
           AND em.scheduled_at <= NOW()
         ORDER BY em.scheduled_at ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function markScheduledEmailAsSent(PDO $pdo, int $emailId, ?int $conversationId): void
{
    $stmt = $pdo->prepare(
        'UPDATE email_messages
         SET folder = "sent",
             scheduled_at = NULL,
             start_new_conversation = 0,
             sent_at = NOW(),
             conversation_id = :conversation_id,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $emailId,
        ':conversation_id' => $conversationId
    ]);
}

function runScheduledEmailTasks(PDO $pdo): void
{
    $scheduledEmails = fetchScheduledEmails($pdo);
    if (!$scheduledEmails) {
        return;
    }

    foreach ($scheduledEmails as $email) {
        $emailId = (int) $email['id'];
        $mailbox = [
            'id' => $email['mailbox_id'],
            'smtp_host' => $email['smtp_host'],
            'smtp_port' => $email['smtp_port'],
            'smtp_username' => $email['smtp_username'],
            'smtp_password' => $email['smtp_password'],
            'smtp_encryption' => $email['smtp_encryption'],
            'display_name' => $email['display_name'],
            'team_id' => $email['mailbox_team_id'],
            'user_id' => $email['mailbox_user_id']
        ];

        $payload = [
            'to_emails' => (string) ($email['to_emails'] ?? ''),
            'cc_emails' => (string) ($email['cc_emails'] ?? ''),
            'bcc_emails' => (string) ($email['bcc_emails'] ?? ''),
            'subject' => (string) ($email['subject'] ?? ''),
            'body' => (string) ($email['body_html'] ?? $email['body'] ?? ''),
            'from_email' => (string) ($email['from_email'] ?? $email['smtp_username'] ?? ''),
            'from_name' => (string) ($email['from_name'] ?? $email['display_name'] ?? '')
        ];

        $toEmails = normalizeEmailList($payload['to_emails']);
        $payload['to_emails'] = $toEmails;
        $payload['cc_emails'] = normalizeEmailList($payload['cc_emails'] ?? '');
        $payload['bcc_emails'] = normalizeEmailList($payload['bcc_emails'] ?? '');

        $hasAnyRecipient = ($payload['to_emails'] ?? '') !== ''
            || ($payload['cc_emails'] ?? '') !== ''
            || ($payload['bcc_emails'] ?? '') !== '';
        if (!$hasAnyRecipient) {
            logAction((int) ($email['created_by'] ?? 0), 'email_schedule_missing_recipient', sprintf('Scheduled email %d has no recipients', $emailId));
            continue;
        }

        $sent = sendEmailViaMailbox($pdo, $mailbox, $payload);
        if (!$sent) {
            logAction((int) ($email['created_by'] ?? 0), 'email_schedule_send_failed', sprintf('Scheduled email %d failed to send', $emailId));
            continue;
        }

        $conversationId = null;
        try {
            $hasConversationId = !empty($email['conversation_id']);
            $wantsNewConversation = !empty($email['start_new_conversation']);
            if ($hasConversationId && $wantsNewConversation) {
                logAction(
                    (int) ($email['created_by'] ?? 0),
                    'email_schedule_conversation_conflict',
                    sprintf('Scheduled email %d has both conversation_id and start_new_conversation; preserving conversation_id', $emailId)
                );
            }

            $conversationContext = resolveSendConversationContext(
                $pdo,
                [
                    'mailbox' => $mailbox,
                    'user_id' => (int) ($email['created_by'] ?? 0),
                ],
                [
                    'conversation_id' => (int) ($email['conversation_id'] ?? 0),
                    'to_emails' => normalizeEmailList(implode(',', array_filter([
                        (string) ($payload['to_emails'] ?? ''),
                        (string) ($payload['cc_emails'] ?? ''),
                        (string) ($payload['bcc_emails'] ?? '')
                    ], static fn ($value) => trim($value) !== ''))),
                    'subject' => (string) ($payload['subject'] ?? ''),
                    'start_new_conversation' => $wantsNewConversation,
                    'message_team_id' => isset($email['team_id']) ? (int) $email['team_id'] : null,
                    'message_user_id' => isset($email['user_id']) ? (int) $email['user_id'] : null,
                ]
            );
            $resolvedConversationId = (int) ($conversationContext['conversation_id'] ?? 0);
            $conversationId = $resolvedConversationId > 0 ? $resolvedConversationId : null;
        } catch (Throwable $error) {
            logAction(
                (int) ($email['created_by'] ?? 0),
                'email_schedule_conversation_resolve_error',
                sprintf('Scheduled email %d conversation resolve failed: %s', $emailId, $error->getMessage())
            );
        }

        markScheduledEmailAsSent($pdo, $emailId, $conversationId);
        if ($conversationId !== null && $conversationId > 0) {
            touchConversationActivity($pdo, (int) $conversationId, date('Y-m-d H:i:s'));
        }
        logAction((int) ($email['created_by'] ?? 0), 'email_schedule_sent', sprintf('Scheduled email %d sent', $emailId));
    }
}
