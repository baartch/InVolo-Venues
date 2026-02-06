<?php
require_once __DIR__ . '/../../src-php/admin_check.php';

if (!isset($teams, $users, $memberIdsByTeam, $adminIdsByTeam)):
?>
  <?php return; ?>
<?php endif; ?>

<div class="box mb-5">
  <h2 class="title is-5">Create Team</h2>
  <form method="POST" action="" class="columns is-multiline">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="create_team">
    <input type="hidden" name="tab" value="teams">
    <div class="column is-4">
      <div class="field">
        <label for="team_name" class="label">Team name</label>
        <div class="control">
          <input type="text" id="team_name" name="team_name" class="input" required>
        </div>
      </div>
    </div>
    <div class="column is-5">
      <div class="field">
        <label for="team_description" class="label">Description</label>
        <div class="control">
          <input type="text" id="team_description" name="team_description" class="input">
        </div>
      </div>
    </div>
    <div class="column is-3 is-flex is-align-items-flex-end">
      <button type="submit" class="button is-fullwidth">Create Team</button>
    </div>
    <div class="column is-12">
      <p class="help">Assign team members and admins after creation.</p>
    </div>
  </form>
</div>

<div class="box">
  <h2 class="title is-5">Teams</h2>
  <div class="table-container">
    <table class="table is-fullwidth is-striped is-hoverable">
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
              <div class="select is-multiple is-fullwidth">
                <select name="team_member_ids[]" multiple size="4" form="<?php echo htmlspecialchars($updateFormId); ?>" aria-label="Team members">
                  <?php foreach ($users as $user): ?>
                    <?php $userId = (int) $user['id']; ?>
                    <option value="<?php echo $userId; ?>" <?php echo in_array($userId, $memberIds, true) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </td>
            <td>
              <div class="select is-multiple is-fullwidth">
                <select name="team_admin_ids[]" multiple size="4" form="<?php echo htmlspecialchars($updateFormId); ?>" aria-label="Team admins">
                  <?php foreach ($users as $user): ?>
                    <?php $userId = (int) $user['id']; ?>
                    <option value="<?php echo $userId; ?>" <?php echo in_array($userId, $adminIds, true) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </td>
            <td><?php echo htmlspecialchars($team['created_at']); ?></td>
            <td>
              <div class="buttons are-small">
                <form method="POST" action="" id="<?php echo htmlspecialchars($updateFormId); ?>" onsubmit="return confirm('Update this team?');">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="update_team">
                  <input type="hidden" name="tab" value="teams">
                  <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                  <button type="submit" class="button">Save</button>
                </form>
                <form method="POST" action="" onsubmit="return confirm('Delete this team?');">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="delete_team">
                  <input type="hidden" name="tab" value="teams">
                  <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                  <button type="submit" class="button" aria-label="Delete team" title="Delete team">
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
</div>
