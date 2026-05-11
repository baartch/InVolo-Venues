<?php
// Shared bootstrap for admin routes
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/auth/admin_check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/settings.php';
require_once __DIR__ . '/../../models/communication/mailbox_helpers.php';
require_once __DIR__ . '/../../views/core/layout.php';

$errors = [];
$notice = '';

$activeTab = $_GET['tab'] ?? 'users';
$validTabs = ['users', 'teams', 'api-keys', 'smtp', 'logs'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'users';
}
