<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../views/core/layout.php';
require_once __DIR__ . '/../../models/communication/mailbox_helpers.php';

$errors = [];
$notice = '';
$editMailbox = null;
$editMailboxId = isset($_GET['edit_mailbox_id']) ? (int) $_GET['edit_mailbox_id'] : 0;
if ($editMailboxId <= 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $editMailboxId = (int) ($_POST['mailbox_id'] ?? 0);
}
$allowedEncryptions = ['ssl', 'tls', 'none'];
$defaultImapPort = 993;
$defaultSmtpPort = 587;

$formValues = mailboxFormDefaults($defaultImapPort, $defaultSmtpPort);
$formValues['user_id'] = (int) ($currentUser['user_id'] ?? 0);

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailbox.';
    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_load_error', $error->getMessage());
    $pdo = null;
}

if ($editMailboxId > 0 && $pdo) {
    try {
        $editMailbox = fetchUserMailbox($pdo, $editMailboxId, (int) ($currentUser['user_id'] ?? 0));
        if (!$editMailbox) {
            $errors[] = 'Mailbox not found.';
            $editMailboxId = 0;
        } else {
            $formValues = mailboxFormValuesFromRow($editMailbox, $defaultImapPort, $defaultSmtpPort);
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load mailbox.';
        logAction($currentUser['user_id'] ?? null, 'profile_mailbox_load_error', $error->getMessage());
        $editMailboxId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (!$pdo) {
        $errors[] = 'Database connection unavailable.';
    } else {
        $action = $_POST['action'] ?? '';
        $isCreate = $action === 'create_mailbox';
        $formResult = buildMailboxFormInput($_POST, [
            'allowed_encryptions' => $allowedEncryptions,
            'default_imap_port' => $defaultImapPort,
            'default_smtp_port' => $defaultSmtpPort,
            'require_team' => false,
            'is_create' => $isCreate
        ]);

        $formValues = $formResult['values'];
        $formValues['user_id'] = (int) ($currentUser['user_id'] ?? 0);
        $imapPassword = $formResult['imap_password'];
        $smtpPassword = $formResult['smtp_password'];
        $errors = array_merge($errors, $formResult['errors']);

        if (!$errors) {
            try {
                if ($isCreate) {
                    $mailboxId = persistMailbox($pdo, $formValues, $imapPassword, $smtpPassword);
                    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_created', sprintf('Created mailbox %s', $formValues['name']));
                    header('Location: ' . BASE_PATH . '/app/controllers/profile/index.php?tab=mailboxes&notice=mailbox_created');
                    exit;
                }

                $existingMailbox = fetchUserMailbox($pdo, $editMailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors[] = 'Mailbox not found.';
                } else {
                    $formValues['user_id'] = (int) ($existingMailbox['user_id'] ?? 0);
                    persistMailbox($pdo, $formValues, $imapPassword, $smtpPassword, $existingMailbox);
                    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_updated', sprintf('Updated mailbox %d', $existingMailbox['id']));
                    header('Location: ' . BASE_PATH . '/app/controllers/profile/index.php?tab=mailboxes&notice=mailbox_updated');
                    exit;
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save mailbox.';
                logAction($currentUser['user_id'] ?? null, 'profile_mailbox_save_error', $error->getMessage());
            }
        }
    }
}

if (!in_array($formValues['imap_encryption'], $allowedEncryptions, true)) {
    $formValues['imap_encryption'] = 'ssl';
}

if (!in_array($formValues['smtp_encryption'], $allowedEncryptions, true)) {
    $formValues['smtp_encryption'] = 'tls';
}

require __DIR__ . '/../../views/profile/mailbox_form.php';
