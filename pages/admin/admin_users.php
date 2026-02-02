<?php
require_once __DIR__ . '/../../src-php/admin_check.php';

if (!isset($users, $teams, $teamsByUser)):
?>
  <?php return; ?>
<?php endif; ?>

<?php $isEditing = $editUser !== null; ?>
<?php $editUserId = $isEditing ? (int) $editUser['id'] : 0; ?>
<?php $editTeams = $isEditing ? ($teamsByUser[$editUserId] ?? []) : []; ?>

<div class="grid">
  <div class="card card-section">
    <h2><?php echo $isEditing ? 'Edit User' : 'Create User'; ?></h2>
    <form method="POST" action="" class="create-user-form">
      <?php renderCsrfField(); ?>
      <input type="hidden" name="action" value="<?php echo $isEditing ? 'update_user' : 'create'; ?>">
      <input type="hidden" name="tab" value="users">
      <?php if ($isEditing): ?>
        <input type="hidden" name="user_id" value="<?php echo $editUserId; ?>">
      <?php endif; ?>
      <div class="create-user-row">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" class="input" required value="<?php echo $isEditing ? htmlspecialchars($editUser['username']) : ''; ?>">
        </div>
        <div class="form-group">
          <label for="password">Password<?php echo $isEditing ? ' (leave blank to keep)' : ''; ?></label>
          <input type="password" id="password" name="password" class="input" <?php echo $isEditing ? '' : 'required'; ?>>
        </div>
        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" class="input" <?php echo $isEditing && ($currentUser['user_id'] ?? 0) === $editUserId ? 'disabled' : ''; ?>>
            <option value="agent" <?php echo $isEditing && $editUser['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
            <option value="team_admin" <?php echo $isEditing && $editUser['role'] === 'team_admin' ? 'selected' : ''; ?>>Team Admin</option>
            <option value="admin" <?php echo $isEditing && $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
          </select>
          <?php if ($isEditing && ($currentUser['user_id'] ?? 0) === $editUserId): ?>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($editUser['role']); ?>">
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label for="team_ids">Teams</label>
          <select id="team_ids" name="team_ids[]" class="input" multiple <?php echo empty($teams) ? 'disabled' : ''; ?> style="min-width: 180px; max-width: 220px;">
            <?php if (empty($teams)): ?>
              <option value="">No teams available</option>
            <?php else: ?>
              <?php foreach ($teams as $team): ?>
                <?php $teamId = (int) $team['id']; ?>
                <option value="<?php echo $teamId; ?>" <?php echo $isEditing && in_array($teamId, $editTeams, true) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($team['name']); ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <button type="submit" class="btn"><?php echo $isEditing ? 'Save User' : 'Create User'; ?></button>
        </div>
      </div>
      <?php if ($isEditing): ?>
        <div class="actions">
          <a href="<?php echo BASE_PATH; ?>/pages/admin/user_management.php?tab=users" class="text-muted">Cancel</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card card-section users-card">
  <h2>Current Users</h2>
  <table class="table">
    <thead>
      <tr>
        <th>Username</th>
        <th>Role</th>
        <th>Teams</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <?php $userId = (int) $user['id']; ?>
        <?php $assignedTeams = $teamsByUser[$userId] ?? []; ?>
        <tr>
          <td><?php echo htmlspecialchars($user['username']); ?></td>
          <td>
            <?php
              $roleLabel = $user['role'] === 'team_admin' ? 'Team Admin' : ucfirst($user['role']);
              echo htmlspecialchars($roleLabel);
            ?>
          </td>
          <td>
            <?php if (empty($assignedTeams)): ?>
              <span class="text-muted">No teams</span>
            <?php else: ?>
              <?php
                $teamNames = [];
                foreach ($teams as $team) {
                    $teamId = (int) $team['id'];
                    if (in_array($teamId, $assignedTeams, true)) {
                        $teamNames[] = $team['name'];
                    }
                }
              ?>
              <?php echo htmlspecialchars(implode(', ', $teamNames)); ?>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($user['created_at']); ?></td>
          <td class="table-actions">
            <a class="icon-button" href="<?php echo BASE_PATH; ?>/pages/admin/user_management.php?tab=users&edit_user_id=<?php echo $userId; ?>" aria-label="Edit user teams" title="Edit user teams">
              <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-pen.svg" alt="Edit">
            </a>
            <form method="POST" action="" onsubmit="return confirm('Reset password for this user?');">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="tab" value="users">
              <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
              <button type="submit" class="icon-button secondary" aria-label="Reset password" title="Reset password">
                <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-reset.svg" alt="Reset password">
              </button>
            </form>
            <form method="POST" action="" onsubmit="return confirm('Delete this user?');">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tab" value="users">
              <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
              <button type="submit" class="icon-button" aria-label="Delete user" title="Delete user">
                <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-basket.svg" alt="Delete">
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="warning">Your own role is locked.</p>
</div>
