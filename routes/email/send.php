<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';
require_once __DIR__ . '/../../src-php/core/form_helpers.php';
require_once __DIR__ . '/../../src-php/communication/mail_delivery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'send_email');
$draftId = (int) ($_POST['draft_id'] ?? 0);
$toEmails = normalizeEmailList((string) ($_POST['to_emails'] ?? ''));
$ccEmails = normalizeEmailList((string) ($_POST['cc_emails'] ?? ''));
$bccEmails = normalizeEmailList((string) ($_POST['bcc_emails'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));
$startNewConversation = !empty($_POST['start_new_conversation']);

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
    header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    if ($action === 'save_draft') {
        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE email_messages
                 SET subject = :subject,
                     body = :body,
                     to_emails = :to_emails,
                     cc_emails = :cc_emails,
                     bcc_emails = :bcc_emails,
                     updated_at = NOW()
                 WHERE id = :id
                   AND mailbox_id = :mailbox_id
                   AND folder = "drafts"'
            );
            $stmt->execute([
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? $body : null,
                ':to_emails' => $toEmails !== '' ? $toEmails : null,
                ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
                ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
                ':id' => $draftId,
                ':mailbox_id' => $mailbox['id']
            ]);
            logAction($userId, 'email_draft_updated', sprintf('Updated draft %d in mailbox %d', $draftId, $mailboxId));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO email_messages
                 (mailbox_id, team_id, folder, subject, body, to_emails, cc_emails, bcc_emails, created_by, created_at)
                 VALUES
                 (:mailbox_id, :team_id, "drafts", :subject, :body, :to_emails, :cc_emails, :bcc_emails, :created_by, NOW())'
            );
            $stmt->execute([
                ':mailbox_id' => $mailbox['id'],
                ':team_id' => $mailbox['team_id'],
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? $body : null,
                ':to_emails' => $toEmails !== '' ? $toEmails : null,
                ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
                ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
                ':created_by' => $userId
            ]);
            logAction($userId, 'email_draft_saved', sprintf('Saved draft in mailbox %d', $mailboxId));
        }
        $redirectParams['notice'] = 'draft_saved';
        $redirectParams['folder'] = 'drafts';
        header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    if ($toEmails === '') {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $sent = sendEmailViaMailbox($pdo, $mailbox, [
        'to_emails' => $toEmails,
        'cc_emails' => $ccEmails !== '' ? $ccEmails : null,
        'bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
        'subject' => $subject !== '' ? $subject : null,
        'body' => $body !== '' ? $body : null,
        'from_email' => $mailbox['smtp_username'] ?? ''
    ]);

    if (!$sent) {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    if ($startNewConversation) {
        $conversationId = ensureConversationForEmail(
            $pdo,
            $mailbox,
            getMailboxPrimaryEmail($mailbox),
            $toEmails,
            $subject,
            true,
            date('Y-m-d H:i:s')
        );
    } else {
        $conversationId = findConversationForEmail(
            $pdo,
            $mailbox,
            getMailboxPrimaryEmail($mailbox),
            $toEmails,
            $subject,
            date('Y-m-d H:i:s')
        );
    }

    $stmt = $pdo->prepare(
        'INSERT INTO email_messages
         (mailbox_id, team_id, folder, subject, body, to_emails, cc_emails, bcc_emails, created_by, sent_at, created_at, conversation_id)
         VALUES
         (:mailbox_id, :team_id, "sent", :subject, :body, :to_emails, :cc_emails, :bcc_emails, :created_by, NOW(), NOW(), :conversation_id)'
    );
    $stmt->execute([
        ':mailbox_id' => $mailbox['id'],
        ':team_id' => $mailbox['team_id'],
        ':subject' => $subject !== '' ? $subject : null,
        ':body' => $body !== '' ? $body : null,
        ':to_emails' => $toEmails,
        ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
        ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
        ':created_by' => $userId,
        ':conversation_id' => $conversationId
    ]);
    $redirectParams['folder'] = 'sent';
    logAction($userId, 'email_sent', sprintf('Sent email via mailbox %d', $mailboxId));
    $redirectParams['notice'] = 'sent';
    header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_send_error', $error->getMessage());
    $redirectParams['notice'] = 'send_failed';
    header('Location: ' . BASE_PATH . '/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
