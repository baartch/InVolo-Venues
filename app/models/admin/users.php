<?php
require_once __DIR__ . '/../core/database.php';

function adminCreateUser(array $currentUser, string $username, string $role): array
{
    $errors = [];
    $notice = '';

    $username = strtolower(trim($username));
    $role = trim($role) !== '' ? trim($role) : 'agent';

    if ($username === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!in_array($role, ['admin', 'agent'], true)) {
        $errors[] = 'Invalid role selected.';
    }

    if ($errors) {
        return ['errors' => $errors, 'notice' => $notice];
    }

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            return ['errors' => ['Username already exists.'], 'notice' => $notice];
        }

        $randomPassword = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, display_name, password_hash, role)
             VALUES (:username, :display_name, :password_hash, :role)'
        );
        $stmt->execute([
            ':username' => $username,
            ':display_name' => null,
            ':password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
            ':role' => $role
        ]);

        logAction($currentUser['user_id'] ?? null, 'user_created', sprintf('Created user %s', $username));
        $notice = 'User created successfully.';
    } catch (Throwable $error) {
        $errors[] = 'Failed to create user.';
        logAction($currentUser['user_id'] ?? null, 'user_create_error', $error->getMessage());
    }

    return ['errors' => $errors, 'notice' => $notice];
}

function adminUpdateUser(array $currentUser, int $userId, string $username, string $role, string $displayName): array
{
    $errors = [];
    $notice = '';
    $resetEditUserId = false;

    $username = strtolower(trim($username));
    $role = trim($role);
    $displayName = trim($displayName);

    if ($userId <= 0 || $username === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($role !== '' && !in_array($role, ['admin', 'agent'], true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (($currentUser['user_id'] ?? 0) === $userId && $role !== '' && $role !== ($currentUser['role'] ?? '')) {
        $errors[] = 'You cannot change your own role.';
    }

    if ($displayName !== '' && mb_strlen($displayName) > 120) {
        $errors[] = 'Displayname must be at most 120 characters.';
    }

    if ($errors) {
        return ['errors' => $errors, 'notice' => $notice, 'resetEditUserId' => $resetEditUserId];
    }

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id');
        $stmt->execute([
            ':username' => $username,
            ':id' => $userId
        ]);

        if ($stmt->fetch()) {
            return ['errors' => ['Username already exists.'], 'notice' => $notice, 'resetEditUserId' => $resetEditUserId];
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET username = :username, display_name = :display_name, role = :role WHERE id = :id');
        $stmt->execute([
            ':username' => $username,
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':role' => $role !== '' ? $role : 'agent',
            ':id' => $userId
        ]);
        $pdo->commit();

        logAction($currentUser['user_id'] ?? null, 'user_updated', sprintf('Updated user %d', $userId));
        $notice = 'User updated successfully.';
        $resetEditUserId = true;
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Failed to update user.';
        logAction($currentUser['user_id'] ?? null, 'user_update_error', $error->getMessage());
    }

    return ['errors' => $errors, 'notice' => $notice, 'resetEditUserId' => $resetEditUserId];
}

function adminDeleteUser(array $currentUser, int $userId): array
{
    $errors = [];
    $notice = '';

    if ($userId <= 0) {
        $errors[] = 'Please select a user to delete.';
    }

    if (($currentUser['user_id'] ?? 0) === $userId) {
        $errors[] = 'You cannot delete your own account.';
    }

    if ($errors) {
        return ['errors' => $errors, 'notice' => $notice];
    }

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

    return ['errors' => $errors, 'notice' => $notice];
}

function fetchAdminUsersData(int $editUserId): array
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->query('SELECT id, username, display_name, role, created_at FROM users ORDER BY username');
    $users = $stmt->fetchAll();

    $teamMembersStmt = $pdo->query(
        'SELECT tm.team_id, tm.user_id, tm.role, u.username, t.name AS team_name
         FROM team_members tm
         JOIN users u ON u.id = tm.user_id
         JOIN teams t ON t.id = tm.team_id
         ORDER BY u.username'
    );
    $teamMembersRows = $teamMembersStmt->fetchAll();

    $teamsByUser = [];
    foreach ($teamMembersRows as $row) {
        $userId = (int) $row['user_id'];
        $teamName = (string) $row['team_name'];
        $teamsByUser[$userId][] = $teamName;
    }

    $editUser = null;
    $userFound = true;
    if ($editUserId > 0) {
        $userFound = false;
        foreach ($users as $user) {
            if ((int) $user['id'] === $editUserId) {
                $editUser = $user;
                $userFound = true;
                break;
            }
        }
    }

    return [
        'users' => $users,
        'teamsByUser' => $teamsByUser,
        'editUser' => $editUser,
        'editUserFound' => $userFound,
    ];
}
