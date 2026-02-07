<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../core/database.php';

if (!($currentUser['is_team_admin'] ?? false)) {
    logAction($currentUser['user_id'] ?? null, 'team_admin_access_denied', 'User attempted to access team admin page');
    http_response_code(403);
    echo 'Access denied.';
    exit;
}
