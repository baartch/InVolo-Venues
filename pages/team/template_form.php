<?php
require_once __DIR__ . '/../../src-php/auth/team_admin_check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';
require_once __DIR__ . '/../../src-php/communication/email_templates_helpers.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';

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

try {
    $pdo = getDatabaseConnection();
    $teams = fetchTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
    $teamIds = array_map('intval', array_column($teams, 'id'));
} catch (Throwable $error) {
    $teams = [];
    $teamIds = [];
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_template_team_load_error', $error->getMessage());
    $pdo = null;
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
                    header('Location: ' . BASE_PATH . '/pages/team/index.php?tab=templates&notice=template_created');
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
                    header('Location: ' . BASE_PATH . '/pages/team/index.php?tab=templates&notice=template_updated');
                    exit;
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save template.';
                logAction($currentUser['user_id'] ?? null, 'team_template_save_error', $error->getMessage());
            }
        }
    }
}

logAction($currentUser['user_id'] ?? null, 'view_team_template_form', $editTemplate ? 'User opened edit template form' : 'User opened create template form');
?>
<?php renderPageStart('Template', ['bodyClass' => 'is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3"><?php echo $editTemplate ? 'Edit Template' : 'Add Template'; ?></h1>
            </div>
            <div class="level-right">
              <a href="<?php echo BASE_PATH; ?>/pages/team/index.php?tab=templates" class="button">Back to Templates</a>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <div class="box">
            <form method="POST" action="" class="columns is-multiline">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="<?php echo $editTemplate ? 'update_template' : 'create_template'; ?>">
              <?php if ($editTemplate): ?>
                <input type="hidden" name="template_id" value="<?php echo (int) $editTemplate['id']; ?>">
                <input type="hidden" name="team_id" value="<?php echo (int) $editTemplate['team_id']; ?>">
              <?php elseif (count($teams) === 1): ?>
                <input type="hidden" name="team_id" value="<?php echo (int) $teams[0]['id']; ?>">
              <?php endif; ?>

              <?php if (count($teams) > 1 && !$editTemplate): ?>
                <div class="column is-4">
                  <div class="field">
                    <label for="team_id" class="label">Team</label>
                    <div class="control">
                      <div class="select is-fullwidth">
                        <select id="team_id" name="team_id" required>
                          <option value="">Select a team</option>
                          <?php foreach ($teams as $team): ?>
                            <option value="<?php echo (int) $team['id']; ?>" <?php echo (int) $formValues['team_id'] === (int) $team['id'] ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="column is-6">
                <div class="field">
                  <label for="template_name" class="label">Template Name</label>
                  <div class="control">
                    <input type="text" id="template_name" name="name" class="input" value="<?php echo htmlspecialchars($formValues['name']); ?>" required>
                  </div>
                </div>
              </div>

              <div class="column is-6">
                <div class="field">
                  <label for="template_subject" class="label">Subject</label>
                  <div class="control">
                    <input type="text" id="template_subject" name="subject" class="input" value="<?php echo htmlspecialchars($formValues['subject']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="field">
                  <label for="template_body" class="label">Body</label>
                  <div class="control">
                    <textarea id="template_body" name="body" class="textarea" rows="8"><?php echo htmlspecialchars($formValues['body']); ?></textarea>
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="buttons">
                  <button type="submit" class="button is-primary"><?php echo $editTemplate ? 'Update Template' : 'Create Template'; ?></button>
                  <a href="<?php echo BASE_PATH; ?>/pages/team/index.php?tab=templates" class="button">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
