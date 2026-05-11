<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/core/htmx_class.php';
require_once __DIR__ . '/../../views/core/layout.php';
require_once __DIR__ . '/../../models/venues/venues_actions.php';
require_once __DIR__ . '/../../models/venues/venues_repository.php';
require_once __DIR__ . '/../../models/venues/venue_ratings.php';
require_once __DIR__ . '/../../models/venues/venue_task_triggers.php';
require_once __DIR__ . '/../../models/venues/venue_detail_data.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/core/link_helpers.php';

$errors = [];
$notice = '';
$triggerNotice = '';
$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];
$importPayload = '';
$showImportModal = false;
$action = '';
$filter = trim((string) ($_GET['filter'] ?? ''));
$requestedTeamId = (int) ($_GET['team_id'] ?? 0);
$selectedVenueId = (int) ($_GET['venue_id'] ?? 0);
$pageSize = (int) ($currentUser['venues_page_size'] ?? 25);
$pageSize = max(25, min(500, $pageSize));
$pageSizeOverride = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 0;
if ($pageSizeOverride >= 25 && $pageSizeOverride <= 500) {
    $pageSize = $pageSizeOverride;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = trim((string) ($_GET['sort'] ?? 'name'));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'asc')));
$selectedVenue = null;
$activeTeamId = 0;
$userTeams = [];
$teamRatings = [];
$selectedVenueRating = null;
$venueTaskTriggers = [];
$venueLinks = [];

$noticeKey = (string) ($_GET['notice'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    $selectedVenueId = (int) ($_POST['venue_id'] ?? $selectedVenueId);
    $requestedTeamId = (int) ($_POST['team_id'] ?? $requestedTeamId);

    if ($action === 'import') {
        $importPayload = trim((string) ($_POST['import_json'] ?? ''));
        $result = handleVenueImport($currentUser, $countryOptions, $importPayload);
        $errors = array_merge($errors, $result['errors'] ?? []);
        $notice = $result['notice'] ?? $notice;
        $importPayload = $result['importPayload'] ?? $importPayload;
        $showImportModal = $result['showImportModal'] ?? $showImportModal;
    }

    if ($action === 'delete') {
        $venueId = (int) ($_POST['venue_id'] ?? 0);
        $result = handleVenueDelete($currentUser, $venueId);
        $selectedVenueId = 0;
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = $result['notice'];
        }
    }

    if ($action === 'update_page_size') {
        $requestedPageSize = (int) ($_POST['venues_page_size'] ?? $pageSize);
        $result = handleVenuePageSizeUpdate($currentUser, $requestedPageSize);
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
        if (!empty($result['pageSize'])) {
            $pageSize = (int) $result['pageSize'];
            $currentUser['venues_page_size'] = $pageSize;
        }
    }
}

if ($requestedTeamId <= 0) {
    $requestedTeamId = (int) ($_GET['team_id'] ?? 0);
}

try {
    $pdo = getDatabaseConnection();
    $userId = (int) ($currentUser['user_id'] ?? 0);
    $activeTeamId = resolveActiveTeamId($pdo, $userId, $requestedTeamId);
    $userTeams = fetchUserTeams($pdo, $userId);

    $result = fetchVenuesWithPagination($filter, $page, $pageSize, $sort, $dir);
    $venues = $result['venues'];
    $totalVenues = $result['totalVenues'];
    $totalPages = $result['totalPages'];
    $page = $result['page'];

    if ($activeTeamId > 0 && $venues) {
        $venueIds = array_map('intval', array_column($venues, 'id'));
        $teamRatings = fetchVenueRatingsForList($pdo, $venueIds, $activeTeamId);
    }

    if ($selectedVenueId > 0) {
        $selectedVenue = fetchVenueById($selectedVenueId);
        if (!$selectedVenue) {
            $errors[] = 'Selected venue not found.';
        } elseif ($activeTeamId > 0) {
            $detailData = buildVenueDetailData($pdo, $selectedVenueId, $activeTeamId, $userId, $noticeKey);
            $selectedVenueRating = $detailData['selectedVenueRating'];
            $venueTaskTriggers = $detailData['venueTaskTriggers'];
            $venueLinks = $detailData['venueLinks'];
            $triggerNotice = $detailData['triggerNotice'];
        }
    }
} catch (Throwable $error) {
    $venues = [];
    $totalVenues = 0;
    $totalPages = 1;
    $errors[] = 'Failed to load venues.';
    logAction($currentUser['user_id'] ?? null, 'venue_list_error', $error->getMessage());
}

$query = $_GET;
$baseUrl = BASE_PATH . '/app/controllers/venues/index.php';

$range = 2;
$startPage = max(1, $page - $range);
$endPage = min($totalPages, $page + $range);
if ($endPage - $startPage < $range * 2) {
    $startPage = max(1, min($startPage, $endPage - $range * 2));
}

if (HTMX::isRequest()) {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/venues/venues.php';
    return;
}

require __DIR__ . '/../../views/venues/index.php';
