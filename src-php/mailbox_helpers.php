<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/form_helpers.php';

function fetchTeamMailbox(PDO $pdo, int $mailboxId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, t.name AS team_name
         FROM mailboxes m
         JOIN team_members tm ON tm.team_id = m.team_id
         JOIN teams t ON t.id = m.team_id
         WHERE m.id = :id AND tm.user_id = :user_id AND tm.role = "admin"
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $mailboxId,
        ':user_id' => $userId
    ]);
    $mailbox = $stmt->fetch();

    return $mailbox ?: null;
}

function loadTeamAdminTeams(PDO $pdo, int $userId): array
{
    $teamsStmt = $pdo->prepare(
        'SELECT t.id, t.name
         FROM teams t
         JOIN team_members tm ON tm.team_id = t.id
         WHERE tm.user_id = :user_id AND tm.role = "admin"
         ORDER BY t.name'
    );
    $teamsStmt->execute([':user_id' => $userId]);
    $teams = $teamsStmt->fetchAll();
    $teamIds = array_map('intval', array_column($teams, 'id'));

    return [$teams, $teamIds];
}
