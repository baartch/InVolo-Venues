<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../views/core/layout.php';
require_once __DIR__ . '/../../models/core/settings.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';

$settings = loadSettingValues(['mapbox_api_key_public']);
$mapboxToken = (string) ($settings['mapbox_api_key_public'] ?? '');
$requestedTeamId = (int) ($_GET['team_id'] ?? 0);

try {
    $pdo = getDatabaseConnection();
    $userId = (int) ($currentUser['user_id'] ?? 0);
    $requestedTeamId = resolveActiveTeamId($pdo, $userId, $requestedTeamId);
} catch (Throwable $error) {
    logAction($currentUser['user_id'] ?? null, 'map_team_resolve_error', $error->getMessage());
}

require __DIR__ . '/../../views/map/index.php';
