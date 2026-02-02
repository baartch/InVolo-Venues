<?php
require_once __DIR__ . '/../../src-php/admin_check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$errors = [];
$notice = '';
$activeTab = $_GET['tab'] ?? 'users';
$editUserId = isset($_GET['edit_user_id']) ? (int) $_GET['edit_user_id'] : 0;
$editUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $action = $_POST['action'] ?? '';
    $activeTab = $_POST['tab'] ?? $activeTab;

    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = $_POST['role'] ?? 'agent';
        $password = (string) ($_POST['password'] ?? '');
        $teamIds = array_map('intval', $_POST['team_ids'] ?? []);

        if ($username === '' || $password === '') {
            $errors[] = 'Username and password are required.';
        }

        if (!in_array($role, ['admin', 'agent', 'team_admin'], true)) {
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
                    $userId = (int) $pdo->lastInsertId();
                    if ($teamIds) {
                        $teamStmt = $pdo->prepare(
                            'INSERT INTO team_members (team_id, user_id)
                             SELECT :team_id, :user_id
                             WHERE EXISTS (SELECT 1 FROM teams WHERE id = :team_id_check)'
                        );
                        foreach ($teamIds as $teamId) {
                            $teamStmt->execute([
                                ':team_id' => $teamId,
                                ':user_id' => $userId,
                                ':team_id_check' => $teamId
                            ]);
                        }
                    }
                    logAction($currentUser['user_id'] ?? null, 'user_created', sprintf('Created user %s', $username));
                    $notice = 'User created successfully.';
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to create user.';
                logAction($currentUser['user_id'] ?? null, 'user_create_error', $error->getMessage());
            }
        }
    }

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = $_POST['role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');
        $teamIds = array_map('intval', $_POST['team_ids'] ?? []);

        if ($userId <= 0 || $username === '') {
            $errors[] = 'Username is required.';
        }

        if ($role !== '' && !in_array($role, ['admin', 'agent', 'team_admin'], true)) {
            $errors[] = 'Invalid role selected.';
        }

        if ($currentUser['user_id'] === $userId && $role !== '' && $role !== ($currentUser['role'] ?? '')) {
            $errors[] = 'You cannot change your own role.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id');
                $stmt->execute([
                    ':username' => $username,
                    ':id' => $userId
                ]);
                if ($stmt->fetch()) {
                    $errors[] = 'Username already exists.';
                } else {
                    $pdo->beginTransaction();
                    $updateFields = [
                        'username' => $username,
                        'role' => $role !== '' ? $role : 'agent'
                    ];
                    $sql = 'UPDATE users SET username = :username, role = :role';
                    if ($password !== '') {
                        $sql .= ', password_hash = :password_hash';
                        $updateFields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= ' WHERE id = :id';
                    $updateFields['id'] = $userId;

                    $stmt = $pdo->prepare($sql);
                    $params = [
                        ':username' => $updateFields['username'],
                        ':role' => $updateFields['role'],
                        ':id' => $updateFields['id']
                    ];
                    if (isset($updateFields['password_hash'])) {
                        $params[':password_hash'] = $updateFields['password_hash'];
                    }
                    $stmt->execute($params);

                    $deleteStmt = $pdo->prepare('DELETE FROM team_members WHERE user_id = :user_id');
                    $deleteStmt->execute([':user_id' => $userId]);

                    if ($teamIds) {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO team_members (team_id, user_id)
                             SELECT :team_id, :user_id
                             WHERE EXISTS (SELECT 1 FROM teams WHERE id = :team_id_check)'
                        );
                        foreach ($teamIds as $teamId) {
                            $insertStmt->execute([
                                ':team_id' => $teamId,
                                ':user_id' => $userId,
                                ':team_id_check' => $teamId
                            ]);
                        }
                    }

                    $pdo->commit();
                    logAction($currentUser['user_id'] ?? null, 'user_updated', sprintf('Updated user %d', $userId));
                    $notice = 'User updated successfully.';
                    $editUserId = 0;
                }
            } catch (Throwable $error) {
                if ($pdo && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to update user.';
                logAction($currentUser['user_id'] ?? null, 'user_update_error', $error->getMessage());
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

    if ($action === 'update_user_teams') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $teamIds = array_map('intval', $_POST['team_ids'] ?? []);

        if ($userId <= 0) {
            $errors[] = 'Please select a user to update teams.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $pdo->beginTransaction();
                $deleteStmt = $pdo->prepare('DELETE FROM team_members WHERE user_id = :user_id');
                $deleteStmt->execute([':user_id' => $userId]);

                if ($teamIds) {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO team_members (team_id, user_id)
                         SELECT :team_id, :user_id
                         WHERE EXISTS (SELECT 1 FROM teams WHERE id = :team_id_check)'
                    );
                    foreach ($teamIds as $teamId) {
                        $insertStmt->execute([
                            ':team_id' => $teamId,
                            ':user_id' => $userId,
                            ':team_id_check' => $teamId
                        ]);
                    }
                }

                $pdo->commit();
                logAction($currentUser['user_id'] ?? null, 'user_teams_updated', sprintf('Updated teams for user %d', $userId));
                $notice = 'User teams updated successfully.';
            } catch (Throwable $error) {
                if ($pdo && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to update user teams.';
                logAction($currentUser['user_id'] ?? null, 'user_teams_update_error', $error->getMessage());
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

    if ($action === 'create_team') {
        $teamName = trim((string) ($_POST['team_name'] ?? ''));
        $teamDescription = trim((string) ($_POST['team_description'] ?? ''));

        if ($teamName === '') {
            $errors[] = 'Team name is required.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT id FROM teams WHERE name = :name');
                $stmt->execute([':name' => $teamName]);
                if ($stmt->fetch()) {
                    $errors[] = 'Team name already exists.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO teams (name, description) VALUES (:name, :description)');
                    $stmt->execute([
                        ':name' => $teamName,
                        ':description' => $teamDescription !== '' ? $teamDescription : null
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'team_created', sprintf('Created team %s', $teamName));
                    $notice = 'Team created successfully.';
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to create team.';
                logAction($currentUser['user_id'] ?? null, 'team_create_error', $error->getMessage());
            }
        }
    }

    if ($action === 'update_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        $teamName = trim((string) ($_POST['team_name'] ?? ''));
        $teamDescription = trim((string) ($_POST['team_description'] ?? ''));

        if ($teamId <= 0 || $teamName === '') {
            $errors[] = 'Team name is required.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('UPDATE teams SET name = :name, description = :description WHERE id = :id');
                $stmt->execute([
                    ':name' => $teamName,
                    ':description' => $teamDescription !== '' ? $teamDescription : null,
                    ':id' => $teamId
                ]);
                logAction($currentUser['user_id'] ?? null, 'team_updated', sprintf('Updated team %d', $teamId));
                $notice = 'Team updated successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to update team.';
                logAction($currentUser['user_id'] ?? null, 'team_update_error', $error->getMessage());
            }
        }
    }

    if ($action === 'delete_team') {
        $teamId = (int) ($_POST['team_id'] ?? 0);

        if ($teamId <= 0) {
            $errors[] = 'Please select a team to delete.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('DELETE FROM teams WHERE id = :id');
                $stmt->execute([':id' => $teamId]);
                logAction($currentUser['user_id'] ?? null, 'team_deleted', sprintf('Deleted team %d', $teamId));
                $notice = 'Team deleted successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete team.';
                logAction($currentUser['user_id'] ?? null, 'team_delete_error', $error->getMessage());
            }
        }
    }
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY username');
    $users = $stmt->fetchAll();
    $teamsStmt = $pdo->query('SELECT id, name, description, created_at FROM teams ORDER BY name');
    $teams = $teamsStmt->fetchAll();
    $teamMembersStmt = $pdo->query(
        'SELECT tm.team_id, tm.user_id, u.username
         FROM team_members tm
         JOIN users u ON u.id = tm.user_id
         ORDER BY u.username'
    );
    $teamMembersRows = $teamMembersStmt->fetchAll();
    $teamsByUser = [];
    $membersByTeam = [];
    foreach ($teamMembersRows as $row) {
        $teamId = (int) $row['team_id'];
        $userId = (int) $row['user_id'];
        $teamsByUser[$userId][] = $teamId;
        $membersByTeam[$teamId][] = (string) $row['username'];
    }

    if ($editUserId > 0) {
        foreach ($users as $user) {
            if ((int) $user['id'] === $editUserId) {
                $editUser = $user;
                break;
            }
        }
        if (!$editUser) {
            $errors[] = 'User not found.';
            $editUserId = 0;
        }
    }
} catch (Throwable $error) {
    $users = $users ?? [];
    $teams = $teams ?? [];
    $teamsByUser = $teamsByUser ?? [];
    $membersByTeam = $membersByTeam ?? [];
    $errors[] = 'Failed to load users or teams.';
    logAction($currentUser['user_id'] ?? null, 'user_team_list_error', $error->getMessage());
}

logAction($currentUser['user_id'] ?? null, 'view_user_management', 'User opened user management');
?>
<?php renderPageStart('Venue Database - User Management', [
    'theme' => getCurrentTheme($currentUser['ui_theme'] ?? null),
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/public/js/settings.js" defer></script>'
    ]
]); ?>
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

        <div class="tabs" role="tablist">
          <button type="button" class="tab-button <?php echo $activeTab === 'users' ? 'active' : ''; ?>" data-tab="users" role="tab" aria-selected="<?php echo $activeTab === 'users' ? 'true' : 'false'; ?>">Users</button>
          <button type="button" class="tab-button <?php echo $activeTab === 'teams' ? 'active' : ''; ?>" data-tab="teams" role="tab" aria-selected="<?php echo $activeTab === 'teams' ? 'true' : 'false'; ?>">Teams</button>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'users' ? 'active' : ''; ?>" data-tab-panel="users" role="tabpanel">
          <?php require __DIR__ . '/user_management_users.php'; ?>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'teams' ? 'active' : ''; ?>" data-tab-panel="teams" role="tabpanel">
          <?php require __DIR__ . '/user_management_teams.php'; ?>
        </div>
      </div>
<?php renderPageEnd(); ?>
