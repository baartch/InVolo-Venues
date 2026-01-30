<?php
require_once __DIR__ . '/config/admin_check.php';
require_once __DIR__ . '/config/database.php';

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
        $password = (string) ($_POST['new_password'] ?? '');

        if ($userId <= 0 || $password === '') {
            $errors[] = 'Password reset requires a user and new password.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                $stmt->execute([
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $userId
                ]);
                logAction($currentUser['user_id'] ?? null, 'password_reset', sprintf('Reset password for user %d', $userId));
                $notice = 'Password reset successfully.';
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

        if ($currentUser['user_id'] === $userId && $role !== 'admin') {
            $errors[] = 'You cannot remove your own admin role.';
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - User Management</title>
  <link rel="stylesheet" href="public/styles.css">
  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }

    .page-header h1 {
      font-size: 24px;
      color: var(--color-primary-dark);
    }

    .content-wrapper {
      padding: 32px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
    }

    .card-section {
      padding: 24px;
    }

    .card-section h2 {
      margin-bottom: 16px;
      font-size: 18px;
      color: var(--color-primary-dark);
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--color-text);
    }

    .input,
    select.input {
      width: 100%;
      padding: 12px;
    }

    .btn {
      padding: 12px 16px;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
    }

    .table th,
    .table td {
      text-align: left;
      padding: 12px;
      border-bottom: 1px solid var(--color-border);
      font-size: 14px;
    }

    .table th {
      color: var(--color-muted);
      font-weight: 600;
    }

    .table td .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      background: var(--color-light);
      color: var(--color-primary-dark);
      font-size: 12px;
      font-weight: 600;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .actions form {
      flex: 1;
      min-width: 180px;
    }

    .warning {
      color: var(--color-danger);
      font-size: 12px;
      margin-top: 6px;
    }
  </style>
</head>
<body class="map-page">
  <div class="app-layout">
    <aside class="sidebar">
      <nav class="sidebar-nav">
        <a href="index.php" class="sidebar-link" aria-label="Map">
          <img src="public/assets/icon-map.svg" alt="Map">
        </a>
        <a href="user_management.php" class="sidebar-link active" aria-label="User management">
          <img src="public/assets/icon-user.svg" alt="User management">
        </a>
      </nav>
      <div class="sidebar-spacer"></div>
      <a href="auth/logout.php" class="sidebar-link" aria-label="Logout">
        <img src="public/assets/icon-logout.svg" alt="Logout">
      </a>
    </aside>

    <main class="main-content">
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
            <form method="POST" action="">
              <input type="hidden" name="action" value="create">
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
              <button type="submit" class="btn">Create User</button>
            </form>
          </div>

          <div class="card card-section">
            <h2>Reset Password</h2>
            <form method="POST" action="">
              <input type="hidden" name="action" value="reset_password">
              <div class="form-group">
                <label for="reset_user">User</label>
                <select id="reset_user" name="user_id" class="input">
                  <option value="">Select user</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user['id']; ?>">
                      <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="input" required>
              </div>
              <button type="submit" class="btn">Reset Password</button>
            </form>
          </div>

          <div class="card card-section">
            <h2>Role Management</h2>
            <form method="POST" action="">
              <input type="hidden" name="action" value="update_role">
              <div class="form-group">
                <label for="role_user">User</label>
                <select id="role_user" name="user_id" class="input">
                  <option value="">Select user</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user['id']; ?>">
                      <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="role_select">Role</label>
                <select id="role_select" name="role" class="input">
                  <option value="agent">Agent</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <?php if (!empty($currentUser['user_id'])): ?>
                <p class="warning">You cannot remove your own admin role.</p>
              <?php endif; ?>
              <button type="submit" class="btn">Update Role</button>
            </form>
          </div>

          <div class="card card-section">
            <h2>Delete User</h2>
            <form method="POST" action="">
              <input type="hidden" name="action" value="delete">
              <div class="form-group">
                <label for="delete_user">User</label>
                <select id="delete_user" name="user_id" class="input">
                  <option value="">Select user</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user['id']; ?>">
                      <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php if (!empty($currentUser['user_id'])): ?>
                <p class="warning">You cannot delete your own account.</p>
              <?php endif; ?>
              <button type="submit" class="btn">Delete User</button>
            </form>
          </div>
        </div>

        <div class="card card-section" style="margin-top: 24px;">
          <h2>Current Users</h2>
          <table class="table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><?php echo htmlspecialchars($user['username']); ?></td>
                  <td><span class="badge"><?php echo htmlspecialchars($user['role']); ?></span></td>
                  <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
