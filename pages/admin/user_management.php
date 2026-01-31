<?php
require_once __DIR__ . '/../../config/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = $_POST['role'] ?? 'agent';
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errors[] = 'Username and password are required.';
        }

        if (!in_array($role, ['admin', 'agent'], true)) {
            $errors[] = 'Invalid role selected.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
                $stmt->execute([':username' => $username]);
                if ($stmt->fetch()) {
                    $errors[] = 'Username already exists.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)'
                    );
                    $stmt->execute([
                        ':username' => $username,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':role' => $role
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'user_created', sprintf('Created user %s', $username));
                    $notice = 'User created successfully.';
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to create user.';
                logAction($currentUser['user_id'] ?? null, 'user_create_error', $error->getMessage());
            }
        }
    }

    if ($action === 'reset_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $errors[] = 'Please select a user to reset.';
        }

        if (!$errors) {
            try {
                $newPassword = bin2hex(random_bytes(4));
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                $stmt->execute([
                    ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    ':id' => $userId
                ]);
                logAction($currentUser['user_id'] ?? null, 'password_reset', sprintf('Reset password for user %d', $userId));
                $notice = 'Password reset successfully. New password: ' . $newPassword;
            } catch (Throwable $error) {
                $errors[] = 'Failed to reset password.';
                logAction($currentUser['user_id'] ?? null, 'password_reset_error', $error->getMessage());
            }
        }
    }

    if ($action === 'update_role') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';

        if ($userId <= 0 || !in_array($role, ['admin', 'agent'], true)) {
            $errors[] = 'Valid user and role are required.';
        }

        if ($currentUser['user_id'] === $userId) {
            $errors[] = 'You cannot change your own role.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                $stmt->execute([
                    ':role' => $role,
                    ':id' => $userId
                ]);
                logAction($currentUser['user_id'] ?? null, 'role_updated', sprintf('Updated role for user %d to %s', $userId, $role));
                $notice = 'Role updated successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to update role.';
                logAction($currentUser['user_id'] ?? null, 'role_update_error', $error->getMessage());
            }
        }
    }

    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $errors[] = 'Please select a user to delete.';
        }

        if ($currentUser['user_id'] === $userId) {
            $errors[] = 'You cannot delete your own account.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute([':id' => $userId]);
                logAction($currentUser['user_id'] ?? null, 'user_deleted', sprintf('Deleted user %d', $userId));
                $notice = 'User deleted successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete user.';
                logAction($currentUser['user_id'] ?? null, 'user_delete_error', $error->getMessage());
            }
        }
    }
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY username');
    $users = $stmt->fetchAll();
} catch (Throwable $error) {
    $users = [];
    $errors[] = 'Failed to load users.';
    logAction($currentUser['user_id'] ?? null, 'user_list_error', $error->getMessage());
}

logAction($currentUser['user_id'] ?? null, 'view_user_management', 'User opened user management');
?>
<?php renderPageStart('Venue Database - User Management', ['theme' => getCurrentTheme($currentUser['ui_theme'] ?? null)]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>User Management</h1>
        </div>

        <?php if ($notice): ?>
          <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <div class="grid">
          <div class="card card-section">
            <h2>Create User</h2>
            <form method="POST" action="" class="create-user-form">
              <input type="hidden" name="action" value="create">
              <div class="create-user-row">
                <div class="form-group">
                  <label for="username">Username</label>
                  <input type="text" id="username" name="username" class="input" required>
                </div>
                <div class="form-group">
                  <label for="password">Password</label>
                  <input type="password" id="password" name="password" class="input" required>
                </div>
                <div class="form-group">
                  <label for="role">Role</label>
                  <select id="role" name="role" class="input">
                    <option value="agent">Agent</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <div class="form-group">
                  <button type="submit" class="btn">Create User</button>
                </div>
              </div>
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
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><?php echo htmlspecialchars($user['username']); ?></td>
                  <td>
                    <form method="POST" action="" class="table-actions" onsubmit="return confirm('Update role for this user?');">
                      <input type="hidden" name="action" value="update_role">
                      <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                      <select name="role" class="inline-select" onchange="this.form.submit()" <?php echo ($currentUser['user_id'] ?? 0) === (int) $user['id'] ? 'disabled' : ''; ?>>
                        <option value="agent" <?php echo $user['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                      </select>
                    </form>
                  </td>
                  <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                  <td class="table-actions">
                    <form method="POST" action="" onsubmit="return confirm('Reset password for this user?');">
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                      <button type="submit" class="icon-button secondary" aria-label="Reset password" title="Reset password">
                        <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-reset.svg" alt="Reset password">
                      </button>
                    </form>
                    <form method="POST" action="" onsubmit="return confirm('Delete this user?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
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
      </div>
<?php renderPageEnd(); ?>
