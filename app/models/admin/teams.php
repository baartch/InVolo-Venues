<?php
require_once __DIR__ . '/../core/database.php';

function adminCreateTeam(array $currentUser, string $teamName, string $teamDescription): array
{
    $errors = [];
    $notice = '';

    $teamName = trim($teamName);
    $teamDescription = trim($teamDescription);

    if ($teamName === '') {
        $errors[] = 'Team name is required.';
        return ['errors' => $errors, 'notice' => $notice];
    }

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT id FROM teams WHERE name = :name');
        $stmt->execute([':name' => $teamName]);
        if ($stmt->fetch()) {
            $errors[] = 'Team name already exists.';
            return ['errors' => $errors, 'notice' => $notice];
        }

        $stmt = $pdo->prepare('INSERT INTO teams (name, description) VALUES (:name, :description)');
        $stmt->execute([
            ':name' => $teamName,
            ':description' => $teamDescription !== '' ? $teamDescription : null
        ]);

        logAction($currentUser['user_id'] ?? null, 'team_created', sprintf('Created team %s', $teamName));
        $notice = 'Team created successfully.';
    } catch (Throwable $error) {
        $errors[] = 'Failed to create team.';
        logAction($currentUser['user_id'] ?? null, 'team_create_error', $error->getMessage());
    }

    return ['errors' => $errors, 'notice' => $notice];
}

function adminUpdateTeam(array $currentUser, int $teamId, string $teamName, string $teamDescription, array $teamMemberIds, array $teamAdminIds): array
{
    $errors = [];
    $notice = '';

    $teamName = trim($teamName);
    $teamDescription = trim($teamDescription);
    $teamMemberIds = array_unique(array_map('intval', $teamMemberIds));
    $teamAdminIds = array_unique(array_map('intval', $teamAdminIds));

    if ($teamId <= 0 || $teamName === '') {
        $errors[] = 'Team name is required.';
    }

    $overlap = array_intersect($teamMemberIds, $teamAdminIds);
    if ($overlap) {
        $errors[] = 'A user cannot be both member and admin for the same team.';
    }

    if ($errors) {
        return ['errors' => $errors, 'notice' => $notice];
    }

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
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Failed to update team.';
        logAction($currentUser['user_id'] ?? null, 'team_update_error', $error->getMessage());
    }

    return ['errors' => $errors, 'notice' => $notice];
}

function adminDeleteTeam(array $currentUser, int $teamId): array
{
    $errors = [];
    $notice = '';

    if ($teamId <= 0) {
        $errors[] = 'Please select a team to delete.';
        return ['errors' => $errors, 'notice' => $notice];
    }

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

    return ['errors' => $errors, 'notice' => $notice];
}

function fetchAdminTeamsData(): array
{
    $pdo = getDatabaseConnection();

    $teamsStmt = $pdo->query('SELECT id, name, description, created_at FROM teams ORDER BY name');
    $teams = $teamsStmt->fetchAll();

    $usersStmt = $pdo->query('SELECT id, username, display_name, role, created_at FROM users ORDER BY username');
    $users = $usersStmt->fetchAll();

    $teamMembersStmt = $pdo->query(
        'SELECT tm.team_id, tm.user_id, tm.role
         FROM team_members tm'
    );
    $teamMembersRows = $teamMembersStmt->fetchAll();

    $memberIdsByTeam = [];
    $adminIdsByTeam = [];
    foreach ($teamMembersRows as $row) {
        $teamId = (int) $row['team_id'];
        $userId = (int) $row['user_id'];
        $role = (string) $row['role'];
        if ($role === 'admin') {
            $adminIdsByTeam[$teamId][] = $userId;
        } else {
            $memberIdsByTeam[$teamId][] = $userId;
        }
    }

    return [
        'teams' => $teams,
        'users' => $users,
        'memberIdsByTeam' => $memberIdsByTeam,
        'adminIdsByTeam' => $adminIdsByTeam,
    ];
}
