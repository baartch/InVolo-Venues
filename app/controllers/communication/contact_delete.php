<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/communication/contacts_helpers.php';
require_once __DIR__ . '/../../models/communication/navigation_helpers.php';
require_once __DIR__ . '/../../models/core/error_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$contactId = (int) ($_POST['contact_id'] ?? 0);
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);

$redirectParams = buildContactsTabQuery(
    $teamId > 0 ? $teamId : null,
    $searchQuery !== '' ? $searchQuery : null
);

try {
    if ($teamId <= 0 || !userCanAccessTeam($userId, $teamId)) {
        $redirectParams['notice'] = 'contact_error';
    } else {
        if (deleteContact($teamId, $contactId)) {
            logAction($userId, 'contact_deleted', sprintf('Deleted contact %d', $contactId));
            $redirectParams['notice'] = 'contact_deleted';
        } else {
            $redirectParams['notice'] = 'contact_error';
        }
    }
} catch (Throwable $error) {
    logThrowable($userId, 'contact_delete_error', $error);
    $redirectParams['notice'] = 'contact_error';
}

header('Location: ' . buildCommunicationUrl($redirectParams));
exit;
