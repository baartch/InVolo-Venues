<?php
require_once __DIR__ . '/../../src-php/admin_check.php';

if (!isset($users, $teamsByUser)):
?>
  <?php return; ?>
<?php endif; ?>

<?php $isEditing = $editUser !== null; ?>
<?php $editUserId = $isEditing ? (int) $editUser['id'] : 0; ?>
<?php $editTeams = $isEditing ? ($teamsByUser[$editUserId] ?? []) : []; ?>

<div class="box mb-5 has-background-dark has-text-light">
  <h2 class="title is-5 has-text-light"><?php echo $isEditing ? 'Edit User' : 'Create User'; ?></h2>
  <form method="POST" action="" class="columns is-multiline">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEditing ? 'update_user' : 'create'; ?>">
    <input type="hidden" name="tab" value="users">
    <?php if ($isEditing): ?>
      <input type="hidden" name="user_id" value="<?php echo $editUserId; ?>">
    <?php endif; ?>
    <div class="column is-3">
      <div class="field">
        <label for="username" class="label has-text-light">Username</label>
        <div class="control">
          <input type="text" id="username" name="username" class="input has-background-grey-darker has-text-light" required value="<?php echo $isEditing ? htmlspecialchars($editUser['username']) : ''; ?>">
        </div>
      </div>
    </div>
    <div class="column is-3">
      <div class="field">
        <label for="password" class="label has-text-light">Password<?php echo $isEditing ? ' (leave blank to keep)' : ''; ?></label>
        <div class="control">
          <input type="password" id="password" name="password" class="input has-background-grey-darker has-text-light" <?php echo $isEditing ? '' : 'required'; ?>>
        </div>
      </div>
    </div>
    <div class="column is-2">
      <div class="field">
        <label for="role" class="label has-text-light">Role</label>
        <div class="control">
          <div class="select is-fullwidth">
            <select id="role" name="role" class="has-background-grey-darker has-text-light" <?php echo $isEditing && ($currentUser['user_id'] ?? 0) === $editUserId ? 'disabled' : ''; ?>>
              <option value="agent" <?php echo $isEditing && $editUser['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
              <option value="admin" <?php echo $isEditing && $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
          </div>
          <?php if ($isEditing && ($currentUser['user_id'] ?? 0) === $editUserId): ?>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($editUser['role']); ?>">
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="column is-3">
      <div class="field">
        <label class="label has-text-light">Teams</label>
        <div class="control">
          <input type="text" class="input has-background-grey-darker has-text-light" value="<?php echo empty($editTeams) ? 'No teams' : htmlspecialchars(implode(', ', $editTeams)); ?>" readonly>
        </div>
      </div>
    </div>
    <div class="column is-1 is-flex is-align-items-flex-end">
      <button type="submit" class="button is-link is-fullwidth"><?php echo $isEditing ? 'Save' : 'Create'; ?></button>
    </div>
    <?php if ($isEditing): ?>
      <div class="column is-12">
        <a href="<?php echo BASE_PATH; ?>/pages/admin/user_management.php?tab=users" class="button is-light">Cancel</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<div class="box has-background-dark has-text-light">
  <h2 class="title is-5 has-text-light">Current Users</h2>
  <div class="table-container">
    <table class="table is-fullwidth is-striped is-hoverable is-dark" data-users-table>
      <thead>
        <tr>
          <th class="has-text-light">Username</th>
          <th class="has-text-light">Role</th>
          <th class="has-text-light">Teams</th>
          <th class="has-text-light">Created</th>
          <th class="has-text-light">Actions</th>
        </tr>
      </thead>
      <tbody class="has-text-light">
        <?php foreach ($users as $user): ?>
          <?php $userId = (int) $user['id']; ?>
          <?php $assignedTeams = $teamsByUser[$userId] ?? []; ?>
          <tr>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
            <td>
              <?php if (empty($assignedTeams)): ?>
                <span class="has-text-grey-light">No teams</span>
              <?php else: ?>
                <span class="has-text-light"><?php echo htmlspecialchars(implode(', ', $assignedTeams)); ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
            <td>
              <div class="buttons are-small">
                <a class="button is-light" href="<?php echo BASE_PATH; ?>/pages/admin/user_management.php?tab=users&edit_user_id=<?php echo $userId; ?>" aria-label="Edit user" title="Edit user">
                  <span class="icon"><i class="fa-solid fa-pen"></i></span>
                </a>
                <form method="POST" action="" onsubmit="return confirm('Reset password for this user?');">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="tab" value="users">
                  <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                  <button type="submit" class="button is-info is-light" aria-label="Reset password" title="Reset password">
                    <span class="icon"><i class="fa-solid fa-rotate"></i></span>
                  </button>
                </form>
                <form method="POST" action="" onsubmit="return confirm('Delete this user?');">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="tab" value="users">
                  <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                  <button type="submit" class="button is-danger is-light" aria-label="Delete user" title="Delete user">
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
  <p class="help has-text-grey-light">Your own role is locked.</p>
</div>
