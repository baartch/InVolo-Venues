<?php
require_once __DIR__ . '/../../models/auth/team_admin_check.php';
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

$teams = [];
$teamIds = [];
$pdo = null;

try {
    $pdo = getDatabaseConnection();
    [$teams, $teamIds] = loadTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
} catch (Throwable $error) {
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_mailbox_team_load_error', $error->getMessage());
}

if ($editMailboxId > 0 && $pdo) {
    try {
        $editMailbox = fetchTeamMailbox($pdo, $editMailboxId, (int) ($currentUser['user_id'] ?? 0));
        if (!$editMailbox) {
            $errors[] = 'Mailbox not found.';
            $editMailboxId = 0;
        } else {
            $formValues = mailboxFormValuesFromRow($editMailbox, $defaultImapPort, $defaultSmtpPort);
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load mailbox.';
        logAction($currentUser['user_id'] ?? null, 'team_mailbox_load_error', $error->getMessage());
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
            'require_team' => true,
            'team_ids' => $teamIds,
            'default_team_id' => count($teamIds) === 1 ? (int) $teamIds[0] : 0,
            'is_create' => $isCreate
        ]);

        $formValues = $formResult['values'];
        $imapPassword = $formResult['imap_password'];
        $smtpPassword = $formResult['smtp_password'];
        $errors = array_merge($errors, $formResult['errors']);

        if (!$errors) {
            try {
                if ($isCreate) {
                    $mailboxId = persistMailbox($pdo, $formValues, $imapPassword, $smtpPassword);
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_created', sprintf('Created mailbox %s', $formValues['name']));
                    header('Location: ' . BASE_PATH . '/app/controllers/team/index.php?tab=mailboxes&notice=mailbox_created');
                    exit;
                }

                $existingMailbox = fetchTeamMailbox($pdo, $editMailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors[] = 'Mailbox not found.';
                } else {
                    $formValues['team_id'] = (int) ($existingMailbox['team_id'] ?? 0);
                    persistMailbox($pdo, $formValues, $imapPassword, $smtpPassword, $existingMailbox);
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_updated', sprintf('Updated mailbox %d', $existingMailbox['id']));
                    header('Location: ' . BASE_PATH . '/app/controllers/team/index.php?tab=mailboxes&notice=mailbox_updated');
                    exit;
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save mailbox.';
                logAction($currentUser['user_id'] ?? null, 'team_mailbox_save_error', $error->getMessage());
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

require __DIR__ . '/../../views/team/mailbox_form.php';
