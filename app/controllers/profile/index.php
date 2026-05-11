<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/htmx_class.php';
require_once __DIR__ . '/../../views/core/layout.php';
require_once __DIR__ . '/../../models/communication/mailbox_helpers.php';

$errors = [];
$notice = '';
$defaultPageSize = 25;
$minPageSize = 25;
$maxPageSize = 500;
$currentPageSize = (int) ($currentUser['venues_page_size'] ?? $defaultPageSize);
$currentPageSize = max($minPageSize, min($maxPageSize, $currentPageSize));
$activeTab = $_GET['tab'] ?? 'venues';
$validTabs = ['venues', 'mailboxes', 'appearance'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'venues';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

require __DIR__ . '/venues.php';
require __DIR__ . '/mailboxes.php';
require __DIR__ . '/appearance.php';

if (HTMX::isRequest() && $activeTab === 'mailboxes') {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/profile/mailboxes/mailboxes.php';
    return;
}

require __DIR__ . '/../../views/profile/index.php';
