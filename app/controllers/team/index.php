<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/htmx_class.php';
require_once __DIR__ . '/../../views/core/layout.php';
require_once __DIR__ . '/../../models/communication/mailbox_helpers.php';
require_once __DIR__ . '/../../models/communication/email_templates_helpers.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/team/tasks_helpers.php';
require_once __DIR__ . '/../../models/team/shows.php';
require_once __DIR__ . '/../../models/core/link_helpers.php';

$isTeamAdmin = (bool) ($currentUser['is_team_admin'] ?? false);
$activeTab = $_GET['tab'] ?? 'tasks';
$validTabs = $isTeamAdmin
    ? ['tasks', 'shows', 'members', 'mailboxes', 'templates']
    : ['tasks', 'shows', 'members'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'tasks';
}

$errors = [
    'tasks' => [],
    'shows' => [],
    'mailboxes' => [],
    'templates' => []
];
$notice = [
    'tasks' => '',
    'shows' => '',
    'mailboxes' => '',
    'templates' => ''
];

$tasks = [];
$shows = [];
$showLinks = [];
$mailboxes = [];
$templates = [];
$teams = [];
$teamIds = [];

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'task_created') {
    $notice['tasks'] = 'Task created successfully.';
} elseif ($noticeKey === 'task_updated') {
    $notice['tasks'] = 'Task updated successfully.';
} elseif ($noticeKey === 'task_deleted') {
    $notice['tasks'] = 'Task deleted successfully.';
} elseif ($noticeKey === 'task_error') {
    $notice['tasks'] = 'Failed to save task.';
} elseif ($noticeKey === 'show_created') {
    $notice['shows'] = 'Show created successfully.';
} elseif ($noticeKey === 'show_updated') {
    $notice['shows'] = 'Show updated successfully.';
} elseif ($noticeKey === 'show_deleted') {
    $notice['shows'] = 'Show deleted successfully.';
} elseif ($noticeKey === 'show_error') {
    $errorDetail = trim((string) ($_GET['error'] ?? ''));
    $notice['shows'] = $errorDetail !== '' ? ('Failed to save show: ' . $errorDetail) : 'Failed to save show.';
} elseif ($noticeKey === 'mailbox_created') {
    $notice['mailboxes'] = 'Mailbox created successfully.';
} elseif ($noticeKey === 'mailbox_updated') {
    $notice['mailboxes'] = 'Mailbox updated successfully.';
} elseif ($noticeKey === 'template_created') {
    $notice['templates'] = 'Template created successfully.';
} elseif ($noticeKey === 'template_updated') {
    $notice['templates'] = 'Template updated successfully.';
}

$pdo = null;
try {
    $pdo = getDatabaseConnection();
    if ($isTeamAdmin) {
        [$teams, $teamIds] = loadTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
    }
} catch (Throwable $error) {
    $errors['tasks'][] = 'Failed to load teams.';
    $errors['mailboxes'][] = 'Failed to load teams.';
    $errors['templates'][] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_team_load_error', $error->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    if (in_array($action, ['delete_mailbox'], true)) {
        $activeTab = 'mailboxes';
    } elseif (in_array($action, ['delete_template'], true)) {
        $activeTab = 'templates';
    }

    if (!$pdo) {
        $errors[$activeTab][] = 'Database connection unavailable.';
    } elseif ($action === 'delete_mailbox') {
        $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
        if ($mailboxId <= 0) {
            $errors['mailboxes'][] = 'Select a mailbox to delete.';
        } else {
            try {
                $existingMailbox = fetchTeamMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors['mailboxes'][] = 'Mailbox not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM mailboxes WHERE id = :id');
                    $stmt->execute([':id' => $existingMailbox['id']]);
                    $notice['mailboxes'] = 'Mailbox deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_deleted', sprintf('Deleted mailbox %d', $existingMailbox['id']));
                }
            } catch (Throwable $error) {
                $errors['mailboxes'][] = 'Failed to delete mailbox.';
                logAction($currentUser['user_id'] ?? null, 'team_mailbox_delete_error', $error->getMessage());
            }
        }
    } elseif (in_array($action, ['create_mailbox', 'update_mailbox', 'test_imap', 'test_smtp'], true)) {
        $activeTab = 'mailboxes';
        $allowedEncryptions = ['ssl', 'tls', 'none'];
        $defaultImapPort = 993;
        $defaultSmtpPort = 587;

        $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
        $isCreate = $action === 'create_mailbox';

        if ($action === 'test_imap' || $action === 'test_smtp') {
            if ($mailboxId <= 0) {
                $errors['mailboxes'][] = 'Save mailbox first before testing.';
            } else {
                try {
                    $existingMailbox = fetchTeamMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
                    if (!$existingMailbox) {
                        $errors['mailboxes'][] = 'Mailbox not found for test.';
                    } elseif ($action === 'test_imap') {
                        $imapPasswordOverride = trim((string) ($_POST['imap_password'] ?? ''));
                        $imapResult = testImapConnection($existingMailbox, $imapPasswordOverride !== '' ? $imapPasswordOverride : null);
                        if (!empty($imapResult['ok'])) {
                            $notice['mailboxes'] = (string) ($imapResult['message'] ?? 'IMAP connection successful.');
                        } else {
                            $errors['mailboxes'][] = (string) ($imapResult['message'] ?? 'IMAP connection failed.');
                        }
                    } else {
                        $smtpPasswordOverride = trim((string) ($_POST['smtp_password'] ?? ''));
                        $smtpRecipient = trim((string) ($_POST['smtp_test_recipient'] ?? ''));
                        $smtpResult = sendSmtpTestEmail($pdo, $existingMailbox, $smtpRecipient, $smtpPasswordOverride !== '' ? $smtpPasswordOverride : null);
                        if (!empty($smtpResult['ok'])) {
                            $notice['mailboxes'] = (string) ($smtpResult['message'] ?? 'SMTP test email sent successfully.');
                        } else {
                            $errors['mailboxes'][] = (string) ($smtpResult['message'] ?? 'SMTP test email failed.');
                        }
                    }
                } catch (Throwable $error) {
                    $errors['mailboxes'][] = 'Mailbox test failed.';
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_test_error', $error->getMessage());
                }
            }
        }

        if ($action === 'create_mailbox' || $action === 'update_mailbox') {
            $formResult = buildMailboxFormInput($_POST, [
                'allowed_encryptions' => $allowedEncryptions,
                'default_imap_port' => $defaultImapPort,
                'default_smtp_port' => $defaultSmtpPort,
                'require_team' => true,
                'team_ids' => $teamIds,
                'default_team_id' => count($teamIds) === 1 ? (int) $teamIds[0] : 0,
                'is_create' => $isCreate,
            ]);

            if (!empty($formResult['errors'])) {
                $errors['mailboxes'] = array_merge($errors['mailboxes'], $formResult['errors']);
            } else {
                try {
                    if ($isCreate) {
                        persistMailbox($pdo, $formResult['values'], $formResult['imap_password'], $formResult['smtp_password']);
                        $notice['mailboxes'] = 'Mailbox created successfully.';
                    } else {
                        $existingMailbox = fetchTeamMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
                        if (!$existingMailbox) {
                            $errors['mailboxes'][] = 'Mailbox not found.';
                        } else {
                            $formValues = $formResult['values'];
                            $formValues['team_id'] = (int) ($existingMailbox['team_id'] ?? 0);
                            persistMailbox($pdo, $formValues, $formResult['imap_password'], $formResult['smtp_password'], $existingMailbox);
                            $notice['mailboxes'] = 'Mailbox updated successfully.';
                        }
                    }
                } catch (Throwable $error) {
                    $errors['mailboxes'][] = 'Failed to save mailbox.';
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_save_error', $error->getMessage());
                }
            }
        }
    } elseif ($action === 'delete_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            $errors['templates'][] = 'Select a template to delete.';
        } else {
            try {
                $existingTemplate = fetchTeamTemplate($pdo, $templateId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingTemplate) {
                    $errors['templates'][] = 'Template not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM email_templates WHERE id = :id');
                    $stmt->execute([':id' => $existingTemplate['id']]);
                    $notice['templates'] = 'Template deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'team_template_deleted', sprintf('Deleted template %d', $existingTemplate['id']));
                }
            } catch (Throwable $error) {
                $errors['templates'][] = 'Failed to delete template.';
                logAction($currentUser['user_id'] ?? null, 'team_template_delete_error', $error->getMessage());
            }
        }
    }
}

$activeTeamId = 0;
$userTeams = [];
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$selectedTaskId = (int) ($_GET['task_id'] ?? 0);
$selectedShowId = (int) ($_GET['show_id'] ?? 0);
$taskLinks = [];
$showLinks = [];

if ($pdo) {
    try {
        $userId = (int) ($currentUser['user_id'] ?? 0);
        $requestedTeamId = (int) ($_GET['team_id'] ?? 0);
        $activeTeamId = resolveActiveTeamId($pdo, $userId, $requestedTeamId);
        $userTeams = fetchUserTeams($pdo, $userId);

        if ($activeTeamId > 0) {
            $tasks = fetchTeamTasks($pdo, $activeTeamId, $searchQuery);
            $shows = fetchTeamShows($pdo, $activeTeamId, $searchQuery);
        } else {
            $errors['tasks'][] = 'No team access available.';
            $errors['shows'][] = 'No team access available.';
        }

        if ($activeTeamId > 0 && $selectedTaskId > 0) {
            $taskLinks = fetchLinkedObjects($pdo, 'task', $selectedTaskId, $activeTeamId, null);
        }

        if ($activeTeamId > 0 && $selectedShowId > 0) {
            $showLinks = fetchLinkedObjects($pdo, 'show', $selectedShowId, $activeTeamId, null);
        }
    } catch (Throwable $error) {
        $errors['tasks'][] = 'Failed to load tasks.';
        $errors['shows'][] = 'Failed to load shows.';
        logAction($currentUser['user_id'] ?? null, 'team_task_list_error', $error->getMessage());
    }
}

if ($pdo && $isTeamAdmin && $teamIds) {
    try {
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT m.*, t.name AS team_name
             FROM mailboxes m
             JOIN teams t ON t.id = m.team_id
             WHERE m.team_id IN (' . $placeholders . ')
             ORDER BY t.name, m.name'
        );
        $stmt->execute($teamIds);
        $mailboxes = $stmt->fetchAll();
    } catch (Throwable $error) {
        $errors['mailboxes'][] = 'Failed to load mailboxes.';
        logAction($currentUser['user_id'] ?? null, 'team_mailbox_list_error', $error->getMessage());
    }
}

if ($pdo && $isTeamAdmin) {
    try {
        $templates = loadTeamTemplates($pdo, (int) ($currentUser['user_id'] ?? 0));
    } catch (Throwable $error) {
        $errors['templates'][] = 'Failed to load templates.';
        logAction($currentUser['user_id'] ?? null, 'team_template_list_error', $error->getMessage());
    }
}

if (HTMX::isRequest() && $activeTab === 'tasks') {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/team/tasks/tasks.php';
    return;
}

if (HTMX::isRequest() && $activeTab === 'shows') {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/team/shows/shows.php';
    return;
}

if (HTMX::isRequest() && $activeTab === 'mailboxes') {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/team/mailboxes/mailboxes.php';
    return;
}

if (HTMX::isRequest() && $activeTab === 'templates') {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/team/templates.php';
    return;
}

require __DIR__ . '/../../views/team/index.php';
