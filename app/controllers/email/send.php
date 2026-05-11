<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/communication/conversation_helpers.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/communication/mail_delivery.php';
require_once __DIR__ . '/../../models/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'send_email');
$draftId = (int) ($_POST['draft_id'] ?? 0);
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$toEmails = normalizeEmailList((string) ($_POST['to_emails'] ?? ''));
$ccEmails = normalizeEmailList((string) ($_POST['cc_emails'] ?? ''));
$bccEmails = normalizeEmailList((string) ($_POST['bcc_emails'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));
$startNewConversation = !empty($_POST['start_new_conversation']);
$scheduleDate = trim((string) ($_POST['schedule_date'] ?? ''));
$scheduleTime = trim((string) ($_POST['schedule_time'] ?? ''));
$linkItems = $_POST['link_items'] ?? [];
$linkItems = is_array($linkItems) ? $linkItems : [];
$hasAnyRecipient = $toEmails !== '' || $ccEmails !== '' || $bccEmails !== '';
$conversationRecipientEmails = normalizeEmailList(implode(',', array_filter([$toEmails, $ccEmails, $bccEmails], static fn ($value) => $value !== '')));

$parseScheduledAt = static function (string $date, string $time): ?string {
    if ($date === '' || $time === '') {
        return null;
    }

    $timestamp = strtotime($date . ' ' . $time);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
};

$redirectParams = [
    'tab' => 'email',
    'mailbox_id' => $mailboxId,
    'folder' => (string) ($_POST['folder'] ?? 'inbox'),
    'sort' => (string) ($_POST['sort'] ?? 'received_desc'),
    'filter' => (string) ($_POST['filter'] ?? ''),
    'page' => (int) ($_POST['page'] ?? 1)
];

if ($mailboxId <= 0) {
    $redirectParams['notice'] = 'send_failed';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $linkTeamId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null;
    $linkUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null;
    $fromEmail = (string) ($mailbox['smtp_username'] ?? '');
    $fromName = (string) ($mailbox['display_name'] ?? '');

    if ($conversationId > 0) {
        $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
        if (!$conversation) {
            $redirectParams['notice'] = 'send_failed';
            header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }
        $redirectParams['tab'] = 'conversations';
        $redirectParams['conversation_id'] = $conversationId;
        $redirectParams['mailbox_id'] = $conversation['mailbox_id'] ?? $mailboxId;
    }

    $scheduledAt = $parseScheduledAt($scheduleDate, $scheduleTime);
    $isScheduleAction = $action === 'schedule_send';

    if ($action === 'save_draft' || $isScheduleAction) {
        if ($isScheduleAction && !$scheduledAt) {
            $redirectParams['notice'] = 'send_failed';
            header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }

        if ($isScheduleAction && !$hasAnyRecipient) {
            $redirectParams['notice'] = 'recipient_required';
            header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }

        $draftResult = persistDraftEmailPayload(
            $pdo,
            [
                'mailbox' => $mailbox,
                'link_team_id' => $linkTeamId,
                'link_user_id' => $linkUserId,
                'user_id' => $userId,
            ],
            [
                'draft_id' => $draftId,
                'conversation_id' => $conversationId,
                'subject' => $subject,
                'body' => $body,
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'to_emails' => $toEmails,
                'cc_emails' => $ccEmails,
                'bcc_emails' => $bccEmails,
                'scheduled_at' => $scheduledAt,
                'start_new_conversation' => $startNewConversation,
                'is_schedule_action' => $isScheduleAction,
                'link_items' => $linkItems,
            ]
        );

        $draftId = (int) ($draftResult['draft_id'] ?? 0);
        $isUpdate = !empty($draftResult['is_update']);

        $logActionKey = $isScheduleAction
            ? ($isUpdate ? 'email_scheduled_updated' : 'email_scheduled_saved')
            : ($isUpdate ? 'email_draft_updated' : 'email_draft_saved');
        $logActionLabel = $isScheduleAction
            ? ($isUpdate ? 'Scheduled email updated' : 'Scheduled email saved')
            : ($isUpdate ? 'Updated draft' : 'Saved draft');

        if ($isUpdate) {
            logAction($userId, $logActionKey, sprintf('%s %d in mailbox %d', $logActionLabel, $draftId, $mailboxId));
        } else {
            logAction($userId, $logActionKey, sprintf('%s in mailbox %d', $logActionLabel, $mailboxId));
        }

        $redirectParams['notice'] = $isScheduleAction ? 'scheduled' : 'draft_saved';
        $redirectParams['folder'] = 'drafts';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    if (!$hasAnyRecipient) {
        $redirectParams['notice'] = 'recipient_required';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $sent = sendEmailViaMailbox($pdo, $mailbox, [
        'to_emails' => $toEmails,
        'cc_emails' => $ccEmails !== '' ? $ccEmails : null,
        'bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
        'subject' => $subject !== '' ? $subject : null,
        'body' => $body !== '' ? $body : null,
        'from_email' => $fromEmail,
        'from_name' => $fromName
    ]);

    if (!$sent) {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $conversationContext = resolveSendConversationContext(
        $pdo,
        [
            'mailbox' => $mailbox,
            'user_id' => $userId,
        ],
        [
            'conversation_id' => $conversationId,
            'to_emails' => $conversationRecipientEmails,
            'subject' => $subject,
            'start_new_conversation' => $startNewConversation,
        ]
    );
    $conversationId = (int) ($conversationContext['conversation_id'] ?? 0);
    $messageTeamId = $conversationContext['message_team_id'] ?? null;
    $messageUserId = $conversationContext['message_user_id'] ?? null;

    persistSentEmailPayload(
        $pdo,
        [
            'mailbox' => $mailbox,
            'link_team_id' => $linkTeamId,
            'link_user_id' => $linkUserId,
            'user_id' => $userId,
        ],
        [
            'conversation_id' => $conversationId,
            'message_team_id' => $messageTeamId,
            'message_user_id' => $messageUserId,
            'subject' => $subject,
            'body' => $body,
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails,
            'bcc_emails' => $bccEmails,
            'link_items' => $linkItems,
        ]
    );

    $redirectParams['folder'] = 'sent';
    logAction($userId, 'email_sent', sprintf('Sent email via mailbox %d', $mailboxId));
    $redirectParams['notice'] = 'sent';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_send_error', $error->getMessage());
    $redirectParams['notice'] = 'send_failed';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
