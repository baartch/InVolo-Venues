<?php
require_once __DIR__ . '/../../models/auth/team_admin_check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../views/core/layout.php';
require_once __DIR__ . '/../../models/communication/email_templates_helpers.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';

$errors = [];
$notice = '';
$editTemplate = null;
$editTemplateId = isset($_GET['edit_template_id']) ? (int) $_GET['edit_template_id'] : 0;
if ($editTemplateId <= 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $editTemplateId = (int) ($_POST['template_id'] ?? 0);
}

$formValues = [
    'team_id' => 0,
    'name' => '',
    'subject' => '',
    'body' => ''
];

$teams = [];
$teamIds = [];
$pdo = null;

try {
    $pdo = getDatabaseConnection();
    $teams = fetchTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
    $teamIds = array_map('intval', array_column($teams, 'id'));
} catch (Throwable $error) {
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_template_team_load_error', $error->getMessage());
}

if ($editTemplateId > 0 && $pdo) {
    try {
        $editTemplate = fetchTeamTemplate($pdo, $editTemplateId, (int) ($currentUser['user_id'] ?? 0));
        if (!$editTemplate) {
            $errors[] = 'Template not found.';
            $editTemplateId = 0;
        } else {
            $formValues = [
                'team_id' => (int) ($editTemplate['team_id'] ?? 0),
                'name' => (string) ($editTemplate['name'] ?? ''),
                'subject' => (string) ($editTemplate['subject'] ?? ''),
                'body' => (string) ($editTemplate['body'] ?? '')
            ];
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load template.';
        logAction($currentUser['user_id'] ?? null, 'team_template_load_error', $error->getMessage());
        $editTemplateId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (!$pdo) {
        $errors[] = 'Database connection unavailable.';
    } else {
        $action = $_POST['action'] ?? '';
        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId <= 0 && count($teamIds) === 1) {
            $teamId = $teamIds[0];
        }

        $templateName = trim((string) ($_POST['name'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        $formValues = [
            'team_id' => $teamId,
            'name' => $templateName,
            'subject' => $subject,
            'body' => $body
        ];

        if ($teamId <= 0 || !in_array($teamId, $teamIds, true)) {
            $errors[] = 'Select a valid team.';
        }

        if ($templateName === '') {
            $errors[] = 'Template name is required.';
        }

        if (!$errors) {
            try {
                if ($action === 'create_template') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO email_templates (team_id, name, subject, body, created_by)
                         VALUES (:team_id, :name, :subject, :body, :created_by)'
                    );
                    $stmt->execute([
                        ':team_id' => $teamId,
                        ':name' => $templateName,
                        ':subject' => $subject !== '' ? $subject : null,
                        ':body' => $body !== '' ? $body : null,
                        ':created_by' => $currentUser['user_id'] ?? null
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'team_template_created', sprintf('Created template %s', $templateName));
                    header('Location: ' . BASE_PATH . '/app/controllers/team/index.php?tab=templates&notice=template_created');
                    exit;
                }

                $existingTemplate = fetchTeamTemplate($pdo, $editTemplateId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingTemplate) {
                    $errors[] = 'Template not found.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE email_templates
                         SET name = :name, subject = :subject, body = :body
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':name' => $templateName,
                        ':subject' => $subject !== '' ? $subject : null,
                        ':body' => $body !== '' ? $body : null,
                        ':id' => $existingTemplate['id']
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'team_template_updated', sprintf('Updated template %d', $existingTemplate['id']));
                    header('Location: ' . BASE_PATH . '/app/controllers/team/index.php?tab=templates&notice=template_updated');
                    exit;
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save template.';
                logAction($currentUser['user_id'] ?? null, 'team_template_save_error', $error->getMessage());
            }
        }
    }
}

require __DIR__ . '/../../views/team/template_form.php';
