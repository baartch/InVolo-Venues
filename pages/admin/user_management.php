<?php
require_once __DIR__ . '/../../src-php/auth/admin_check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';
require_once __DIR__ . '/../../src-php/core/settings.php';

$errors = [];
$notice = '';
$activeTab = $_GET['tab'] ?? 'users';
$editUserId = isset($_GET['edit_user_id']) ? (int) $_GET['edit_user_id'] : 0;
$editUser = null;

$settings = [
    'brave_search_api_key' => '',
    'brave_spellcheck_api_key' => '',
    'mapbox_api_key' => ''
];
$settingsStatus = [
    'brave_search_api_key' => false,
    'brave_spellcheck_api_key' => false,
    'mapbox_api_key' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $action = $_POST['action'] ?? '';
    $activeTab = $_POST['tab'] ?? $activeTab;

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
                    $userId = (int) $pdo->lastInsertId();
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

        if ($userId <= 0 || $username === '') {
            $errors[] = 'Username is required.';
        }

        if ($role !== '' && !in_array($role, ['admin', 'agent'], true)) {
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
        $teamMemberIds = array_unique(array_map('intval', $_POST['team_member_ids'] ?? []));
        $teamAdminIds = array_unique(array_map('intval', $_POST['team_admin_ids'] ?? []));

        if ($teamId <= 0 || $teamName === '') {
            $errors[] = 'Team name is required.';
        }

        $overlap = array_intersect($teamMemberIds, $teamAdminIds);
        if ($overlap) {
            $errors[] = 'A user cannot be both member and admin for the same team.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE teams SET name = :name, description = :description WHERE id = :id');
                $stmt->execute([
                    ':name' => $teamName,
                    ':description' => $teamDescription !== '' ? $teamDescription : null,
                    ':id' => $teamId
                ]);

                $deleteStmt = $pdo->prepare('DELETE FROM team_members WHERE team_id = :team_id');
                $deleteStmt->execute([':team_id' => $teamId]);

                if ($teamMemberIds || $teamAdminIds) {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO team_members (team_id, user_id, role)
                         SELECT :team_id, :user_id, :role
                         WHERE EXISTS (SELECT 1 FROM teams WHERE id = :team_id_check)
                           AND EXISTS (SELECT 1 FROM users WHERE id = :user_id_check)'
                    );

                    foreach ($teamMemberIds as $memberId) {
                        $insertStmt->execute([
                            ':team_id' => $teamId,
                            ':user_id' => $memberId,
                            ':role' => 'member',
                            ':team_id_check' => $teamId,
                            ':user_id_check' => $memberId
                        ]);
                    }

                    foreach ($teamAdminIds as $adminId) {
                        $insertStmt->execute([
                            ':team_id' => $teamId,
                            ':user_id' => $adminId,
                            ':role' => 'admin',
                            ':team_id_check' => $teamId,
                            ':user_id_check' => $adminId
                        ]);
                    }
                }

                $pdo->commit();
                logAction($currentUser['user_id'] ?? null, 'team_updated', sprintf('Updated team %d', $teamId));
                $notice = 'Team updated successfully.';
            } catch (Throwable $error) {
                if ($pdo && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
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

    if ($action === 'save_api_keys') {
        $settings = [
            'brave_search_api_key' => trim((string) ($_POST['brave_search_api_key'] ?? '')),
            'brave_spellcheck_api_key' => trim((string) ($_POST['brave_spellcheck_api_key'] ?? '')),
            'mapbox_api_key' => trim((string) ($_POST['mapbox_api_key'] ?? ''))
        ];

        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value)
                 VALUES (:setting_key, :setting_value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );

            foreach ($settings as $key => $value) {
                if ($value === '') {
                    continue;
                }

                $stmt->execute([
                    ':setting_key' => $key,
                    ':setting_value' => encryptSettingValue($value)
                ]);
            }

            logAction($currentUser['user_id'] ?? null, 'settings_updated', 'Updated API keys');
            $notice = 'API keys saved successfully.';
        } catch (Throwable $error) {
            $errors[] = 'Failed to save API keys.';
            logAction($currentUser['user_id'] ?? null, 'settings_update_error', $error->getMessage());
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
        'SELECT tm.team_id, tm.user_id, tm.role, u.username, t.name AS team_name
         FROM team_members tm
         JOIN users u ON u.id = tm.user_id
         JOIN teams t ON t.id = tm.team_id
         ORDER BY u.username'
    );
    $teamMembersRows = $teamMembersStmt->fetchAll();
    $teamsByUser = [];
    $membersByTeam = [];
    $adminsByTeam = [];
    $memberIdsByTeam = [];
    $adminIdsByTeam = [];
    foreach ($teamMembersRows as $row) {
        $teamId = (int) $row['team_id'];
        $userId = (int) $row['user_id'];
        $teamName = (string) $row['team_name'];
        $role = (string) $row['role'];
        $teamsByUser[$userId][] = $teamName;
        if ($role === 'admin') {
            $adminsByTeam[$teamId][] = (string) $row['username'];
            $adminIdsByTeam[$teamId][] = $userId;
        } else {
            $membersByTeam[$teamId][] = (string) $row['username'];
            $memberIdsByTeam[$teamId][] = $userId;
        }
    }

    $settings = loadSettingValues([
        'brave_search_api_key',
        'brave_spellcheck_api_key',
        'mapbox_api_key'
    ]);
    $settingsStatus = [
        'brave_search_api_key' => $settings['brave_search_api_key'] !== '',
        'brave_spellcheck_api_key' => $settings['brave_spellcheck_api_key'] !== '',
        'mapbox_api_key' => $settings['mapbox_api_key'] !== ''
    ];

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
    $adminsByTeam = $adminsByTeam ?? [];
    $memberIdsByTeam = $memberIdsByTeam ?? [];
    $adminIdsByTeam = $adminIdsByTeam ?? [];
    $settings = $settings ?? [
        'brave_search_api_key' => '',
        'brave_spellcheck_api_key' => '',
        'mapbox_api_key' => ''
    ];
    $settingsStatus = $settingsStatus ?? [
        'brave_search_api_key' => false,
        'brave_spellcheck_api_key' => false,
        'mapbox_api_key' => false
    ];
    $errors[] = 'Failed to load users, teams, or settings.';
    logAction($currentUser['user_id'] ?? null, 'user_team_list_error', $error->getMessage());
}

logAction($currentUser['user_id'] ?? null, 'view_user_management', 'User opened admin panel');
?>
<?php renderPageStart('Admin', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/public/js/tabs.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Admin</h1>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <div class="tabs is-boxed" role="tablist">
            <ul>
              <li class="<?php echo $activeTab === 'users' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="users" role="tab" aria-selected="<?php echo $activeTab === 'users' ? 'true' : 'false'; ?>">Users</a>
              </li>
              <li class="<?php echo $activeTab === 'teams' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="teams" role="tab" aria-selected="<?php echo $activeTab === 'teams' ? 'true' : 'false'; ?>">Teams</a>
              </li>
              <li class="<?php echo $activeTab === 'api-keys' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="api-keys" role="tab" aria-selected="<?php echo $activeTab === 'api-keys' ? 'true' : 'false'; ?>">API Keys</a>
              </li>
            </ul>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'users' ? '' : 'is-hidden'; ?>" data-tab-panel="users" role="tabpanel">
            <?php require __DIR__ . '/admin_users.php'; ?>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'teams' ? '' : 'is-hidden'; ?>" data-tab-panel="teams" role="tabpanel">
            <?php require __DIR__ . '/admin_teams.php'; ?>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'api-keys' ? '' : 'is-hidden'; ?>" data-tab-panel="api-keys" role="tabpanel">
            <?php require __DIR__ . '/admin_api_keys.php'; ?>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
