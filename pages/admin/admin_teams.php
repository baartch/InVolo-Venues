<?php if (!isset($teams, $membersByTeam)): ?>
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
    </form>
  </div>
</div>

<div class="card card-section users-card">
  <h2>Teams</h2>
  <table class="table">
    <thead>
      <tr>
        <th>Team</th>
        <th>Description</th>
        <th>Members</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($teams as $team): ?>
        <?php $teamId = (int) $team['id']; ?>
        <?php $members = $membersByTeam[$teamId] ?? []; ?>
        <?php $updateFormId = 'team_update_' . $teamId; ?>
        <tr>
          <td>
            <input type="text" name="team_name" class="input" value="<?php echo htmlspecialchars($team['name']); ?>" required form="<?php echo htmlspecialchars($updateFormId); ?>">
          </td>
          <td>
            <input type="text" name="team_description" class="input" value="<?php echo htmlspecialchars((string) ($team['description'] ?? '')); ?>" form="<?php echo htmlspecialchars($updateFormId); ?>">
          </td>
          <td>
            <?php if (empty($members)): ?>
              <span class="text-muted">No members</span>
            <?php else: ?>
              <?php echo htmlspecialchars(implode(', ', $members)); ?>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($team['created_at']); ?></td>
          <td class="table-actions">
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
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
