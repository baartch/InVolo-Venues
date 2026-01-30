<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if (($currentUser['role'] ?? '') !== 'admin') {
    logAction($currentUser['user_id'] ?? null, 'admin_access_denied', 'User attempted to access admin-only page');
    http_response_code(403);
    echo 'Access denied.';
    exit;
}
