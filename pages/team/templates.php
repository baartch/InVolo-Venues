<?php
/** @var string $activeTab */
require_once __DIR__ . '/../../src-php/email_templates_helpers.php';
require_once __DIR__ . '/../../src-php/email_helpers.php';

$errors = [];
$notice = '';
$templates = [];
$teams = [];
$pdo = null;

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'template_created') {
    $notice = 'Template created successfully.';
} elseif ($noticeKey === 'template_updated') {
    $notice = 'Template updated successfully.';
}

try {
    $pdo = getDatabaseConnection();
    $teams = fetchTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
} catch (Throwable $error) {
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_template_team_load_error', $error->getMessage());
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    $activeTab = 'templates';

    if (!$pdo) {
        $errors[] = 'Database connection unavailable.';
    } elseif ($action === 'delete_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            $errors[] = 'Select a template to delete.';
        } else {
            try {
                $existingTemplate = fetchTeamTemplate($pdo, $templateId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingTemplate) {
                    $errors[] = 'Template not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM email_templates WHERE id = :id');
                    $stmt->execute([':id' => $existingTemplate['id']]);
                    $notice = 'Template deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'team_template_deleted', sprintf('Deleted template %d', $existingTemplate['id']));
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete template.';
                logAction($currentUser['user_id'] ?? null, 'team_template_delete_error', $error->getMessage());
            }
        }
    }
}

if ($pdo) {
    try {
        $templates = loadTeamTemplates($pdo, (int) ($currentUser['user_id'] ?? 0));
    } catch (Throwable $error) {
        $errors[] = 'Failed to load templates.';
        logAction($currentUser['user_id'] ?? null, 'team_template_list_error', $error->getMessage());
    }
}
?>
<div class="tab-panel <?php echo $activeTab === 'templates' ? 'active' : ''; ?>" data-tab-panel="templates" role="tabpanel">
  <?php if ($notice): ?>
    <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
  <?php endif; ?>

  <?php foreach ($errors as $error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <div class="page-header mailbox-header">
    <h2>Templates</h2>
    <div class="page-header-actions">
      <a href="<?php echo BASE_PATH; ?>/pages/team/template_form.php" class="btn">Add Template</a>
    </div>
  </div>

  <div class="card card-section">
    <h2>Email Templates</h2>
    <?php if (!$templates): ?>
      <p class="text-muted">No templates configured yet.</p>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="table team-table">
          <thead>
            <tr>
              <th>Team</th>
              <th>Name</th>
              <th>Subject</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($templates as $template): ?>
              <tr>
                <td><?php echo htmlspecialchars($template['team_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($template['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($template['subject'] ?? ''); ?></td>
                <td>
                  <div class="venue-actions-buttons">
                    <a href="<?php echo BASE_PATH; ?>/pages/team/template_form.php?edit_template_id=<?php echo (int) $template['id']; ?>" class="icon-button secondary" aria-label="Edit template" title="Edit template">
                      <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-pen.svg" alt="Edit">
                    </a>
                    <form method="POST" action="<?php echo BASE_PATH; ?>/pages/team/index.php?tab=templates" onsubmit="return confirm('Delete this template?');">
                      <?php renderCsrfField(); ?>
                      <input type="hidden" name="action" value="delete_template">
                      <input type="hidden" name="template_id" value="<?php echo (int) $template['id']; ?>">
                      <button type="submit" class="icon-button" aria-label="Delete template" title="Delete template">
                        <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-basket.svg" alt="Delete">
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
