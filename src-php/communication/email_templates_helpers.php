<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/form_helpers.php';
require_once __DIR__ . '/email_helpers.php';

function fetchTeamTemplate(PDO $pdo, int $templateId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT et.*
         FROM email_templates et
         JOIN team_members tm ON tm.team_id = et.team_id
         WHERE et.id = :template_id AND tm.user_id = :user_id AND tm.role = "admin"
         LIMIT 1'
    );
    $stmt->execute([
        ':template_id' => $templateId,
        ':user_id' => $userId
    ]);
    $template = $stmt->fetch();
    return $template ?: null;
}

function loadTeamTemplates(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT et.*, t.name AS team_name
         FROM email_templates et
         JOIN teams t ON t.id = et.team_id
         JOIN team_members tm ON tm.team_id = et.team_id
         WHERE tm.user_id = :user_id AND tm.role = "admin"
         ORDER BY t.name, et.name'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}
