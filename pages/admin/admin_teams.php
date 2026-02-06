<?php
require_once __DIR__ . '/../../src-php/admin_check.php';

if (!isset($teams, $users, $memberIdsByTeam, $adminIdsByTeam)):
?>
  <?php return; ?>
<?php endif; ?>

<div class="box mb-5 has-background-dark has-text-light">
  <h2 class="title is-5 has-text-light">Create Team</h2>
  <form method="POST" action="" class="columns is-multiline">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="create_team">
    <input type="hidden" name="tab" value="teams">
    <div class="column is-4">
      <div class="field">
        <label for="team_name" class="label has-text-light">Team name</label>
        <div class="control">
          <input type="text" id="team_name" name="team_name" class="input has-background-grey-darker has-text-light" required>
        </div>
      </div>
    </div>
    <div class="column is-5">
      <div class="field">
        <label for="team_description" class="label has-text-light">Description</label>
        <div class="control">
          <input type="text" id="team_description" name="team_description" class="input has-background-grey-darker has-text-light">
        </div>
      </div>
    </div>
    <div class="column is-3 is-flex is-align-items-flex-end">
      <button type="submit" class="button is-link is-fullwidth">Create Team</button>
    </div>
    <div class="column is-12">
      <p class="help has-text-grey-light">Assign team members and admins after creation.</p>
    </div>
  </form>
</div>

<div class="box has-background-dark has-text-light">
  <h2 class="title is-5 has-text-light">Teams</h2>
  <div class="table-container">
    <table class="table is-fullwidth is-striped is-hoverable is-dark">
      <thead>
        <tr>
          <th class="has-text-light">Team</th>
          <th class="has-text-light">Description</th>
          <th class="has-text-light">Members</th>
          <th class="has-text-light">Admins</th>
          <th class="has-text-light">Created</th>
          <th class="has-text-light">Actions</th>
        </tr>
      </thead>
      <tbody class="has-text-light">
        <?php foreach ($teams as $team): ?>
          <?php $teamId = (int) $team['id']; ?>
          <?php $memberIds = $memberIdsByTeam[$teamId] ?? []; ?>
          <?php $adminIds = $adminIdsByTeam[$teamId] ?? []; ?>
          <?php $updateFormId = 'team_update_' . $teamId; ?>
          <tr>
            <td>
              <input type="text" name="team_name" class="input has-background-grey-darker has-text-light" value="<?php echo htmlspecialchars($team['name']); ?>" required form="<?php echo htmlspecialchars($updateFormId); ?>">
            </td>
            <td>
              <input type="text" name="team_description" class="input has-background-grey-darker has-text-light" value="<?php echo htmlspecialchars((string) ($team['description'] ?? '')); ?>" form="<?php echo htmlspecialchars($updateFormId); ?>">
            </td>
            <td>
              <div class="select is-multiple is-fullwidth">
                <select name="team_member_ids[]" multiple size="4" form="<?php echo htmlspecialchars($updateFormId); ?>" aria-label="Team members" class="has-background-grey-darker has-text-light">
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
                <select name="team_admin_ids[]" multiple size="4" form="<?php echo htmlspecialchars($updateFormId); ?>" aria-label="Team admins" class="has-background-grey-darker has-text-light">
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
                  <button type="submit" class="button is-link">Save</button>
                </form>
                <form method="POST" action="" onsubmit="return confirm('Delete this team?');">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="delete_team">
                  <input type="hidden" name="tab" value="teams">
                  <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                  <button type="submit" class="button is-danger is-light" aria-label="Delete team" title="Delete team">
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
