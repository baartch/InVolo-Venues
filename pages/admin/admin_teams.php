<?php
require_once __DIR__ . '/../../src-php/admin_check.php';

if (!isset($teams, $users, $memberIdsByTeam, $adminIdsByTeam)):
?>
  <?php return; ?>
<?php endif; ?>

<div class="grid">
  <div class="card card-section">
    <h2>Create Team</h2>
    <form method="POST" action="" class="create-user-form">
      <?php renderCsrfField(); ?>
      <input type="hidden" name="action" value="create_team">
      <input type="hidden" name="tab" value="teams">
      <div class="create-user-row">
        <div class="form-group">
          <label for="team_name">Team name</label>
          <input type="text" id="team_name" name="team_name" class="input" required>
        </div>
        <div class="form-group">
          <label for="team_description">Description</label>
          <input type="text" id="team_description" name="team_description" class="input">
        </div>
        <div class="form-group">
          <button type="submit" class="btn">Create Team</button>
        </div>
      </div>
      <p class="text-muted">Assign team members and admins after creation.</p>
    </form>
  </div>
</div>

<div class="card card-section users-card">
  <h2>Teams</h2>
  <table class="table team-table">
    <thead>
      <tr>
        <th>Team</th>
        <th>Description</th>
        <th>Members</th>
        <th>Admins</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($teams as $team): ?>
        <?php $teamId = (int) $team['id']; ?>
        <?php $memberIds = $memberIdsByTeam[$teamId] ?? []; ?>
        <?php $adminIds = $adminIdsByTeam[$teamId] ?? []; ?>
        <?php $updateFormId = 'team_update_' . $teamId; ?>
        <tr>
          <td>
            <input type="text" name="team_name" class="input" value="<?php echo htmlspecialchars($team['name']); ?>" required form="<?php echo htmlspecialchars($updateFormId); ?>">
          </td>
          <td>
            <input type="text" name="team_description" class="input" value="<?php echo htmlspecialchars((string) ($team['description'] ?? '')); ?>" form="<?php echo htmlspecialchars($updateFormId); ?>">
          </td>
          <td>
            <select name="team_member_ids[]" class="input" multiple form="<?php echo htmlspecialchars($updateFormId); ?>" style="min-width: 180px; max-width: 220px;">
              <?php foreach ($users as $user): ?>
                <?php $userId = (int) $user['id']; ?>
                <option value="<?php echo $userId; ?>" <?php echo in_array($userId, $memberIds, true) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($user['username']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select name="team_admin_ids[]" class="input" multiple form="<?php echo htmlspecialchars($updateFormId); ?>" style="min-width: 180px; max-width: 220px;">
              <?php foreach ($users as $user): ?>
                <?php $userId = (int) $user['id']; ?>
                <option value="<?php echo $userId; ?>" <?php echo in_array($userId, $adminIds, true) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($user['username']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><?php echo htmlspecialchars($team['created_at']); ?></td>
          <td>
            <div class="table-actions">
              <form method="POST" action="" id="<?php echo htmlspecialchars($updateFormId); ?>" onsubmit="return confirm('Update this team?');">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="action" value="update_team">
                <input type="hidden" name="tab" value="teams">
                <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                <button type="submit" class="btn">Save</button>
              </form>
              <form method="POST" action="" onsubmit="return confirm('Delete this team?');">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="action" value="delete_team">
                <input type="hidden" name="tab" value="teams">
                <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                <button type="submit" class="icon-button" aria-label="Delete team" title="Delete team">
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
