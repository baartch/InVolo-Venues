<?php
/** @var string $activeTab */
require_once __DIR__ . '/../../src-php/mailbox_helpers.php';

$errors = [];
$notice = '';
$mailboxes = [];
$pdo = null;

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'mailbox_created') {
    $notice = 'Mailbox created successfully.';
} elseif ($noticeKey === 'mailbox_updated') {
    $notice = 'Mailbox updated successfully.';
}

try {
    $pdo = getDatabaseConnection();
    [$teams, $teamIds] = loadTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
} catch (Throwable $error) {
    $teams = [];
    $teamIds = [];
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_mailbox_team_load_error', $error->getMessage());
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    $activeTab = 'mailboxes';

    if (!$pdo) {
        $errors[] = 'Database connection unavailable.';
    } elseif ($action === 'delete_mailbox') {
        $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
        if ($mailboxId <= 0) {
            $errors[] = 'Select a mailbox to delete.';
        } else {
            try {
                $existingMailbox = fetchTeamMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors[] = 'Mailbox not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM mailboxes WHERE id = :id');
                    $stmt->execute([':id' => $existingMailbox['id']]);
                    $notice = 'Mailbox deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_deleted', sprintf('Deleted mailbox %d', $existingMailbox['id']));
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete mailbox.';
                logAction($currentUser['user_id'] ?? null, 'team_mailbox_delete_error', $error->getMessage());
            }
        }
    }
}

if ($pdo && $teamIds) {
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
        $errors[] = 'Failed to load mailboxes.';
        logAction($currentUser['user_id'] ?? null, 'team_mailbox_list_error', $error->getMessage());
    }
}
?>
<div class="tab-panel <?php echo $activeTab === 'mailboxes' ? '' : 'is-hidden'; ?>" data-tab-panel="mailboxes" role="tabpanel">
  <?php if ($notice): ?>
    <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
  <?php endif; ?>

  <?php foreach ($errors as $error): ?>
    <div class="notification"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <div class="level mb-4">
    <div class="level-left">
      <h2 class="title is-4">Mailboxes</h2>
    </div>
    <div class="level-right">
      <a href="<?php echo BASE_PATH; ?>/pages/team/mailbox_form.php" class="button">Add Mailbox</a>
    </div>
  </div>

  <div class="box">
    <h3 class="title is-5">Configured Mailboxes</h3>
    <?php if (!$mailboxes): ?>
      <p>No mailboxes configured yet.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table is-fullwidth is-striped is-hoverable" data-mailboxes-table>
          <thead>
            <tr>
              <th>Team</th>
              <th>Name</th>
              <th>IMAP Host</th>
              <th>IMAP Port</th>
              <th>IMAP User</th>
              <th>IMAP Encryption</th>
              <th>SMTP Host</th>
              <th>SMTP Port</th>
              <th>SMTP User</th>
              <th>SMTP Encryption</th>
              <th>Delete After Retrieve</th>
              <th>Store Sent on Server</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mailboxes as $mailbox): ?>
              <tr>
                <td><?php echo htmlspecialchars($mailbox['team_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['imap_host'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['imap_port'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['imap_username'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($mailbox['imap_encryption'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars($mailbox['smtp_host'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['smtp_port'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['smtp_username'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($mailbox['smtp_encryption'] ?? '')); ?></td>
                <td><?php echo !empty($mailbox['delete_after_retrieve']) ? 'Yes' : 'No'; ?></td>
                <td><?php echo !empty($mailbox['store_sent_on_server']) ? 'Yes' : 'No'; ?></td>
                <td>
                  <div class="buttons are-small">
                    <a href="<?php echo BASE_PATH; ?>/pages/team/mailbox_form.php?edit_mailbox_id=<?php echo (int) $mailbox['id']; ?>" class="button" aria-label="Edit mailbox" title="Edit mailbox">
                      <span class="icon"><i class="fa-solid fa-pen"></i></span>
                    </a>
                    <form method="POST" action="<?php echo BASE_PATH; ?>/pages/team/index.php?tab=mailboxes" onsubmit="return confirm('Delete this mailbox?');">
                      <?php renderCsrfField(); ?>
                      <input type="hidden" name="action" value="delete_mailbox">
                      <input type="hidden" name="mailbox_id" value="<?php echo (int) $mailbox['id']; ?>">
                      <button type="submit" class="button" aria-label="Delete mailbox" title="Delete mailbox">
                        <span class="icon"><i class="fa-solid fa-trash"></i></span>
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
