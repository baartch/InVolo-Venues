<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/form_helpers.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/venues_actions.php';
require_once __DIR__ . '/../../src-php/venues_repository.php';

$errors = [];
$notice = '';
$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];
$importPayload = '';
$showImportModal = false;
$action = '';
$filter = trim((string) ($_GET['filter'] ?? ''));
$pageSize = (int) ($currentUser['venues_page_size'] ?? 25);
$pageSize = max(25, min(500, $pageSize));
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

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
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = $result['notice'];
        }
    }
}

try {
    $result = fetchVenuesWithPagination($filter, $page, $pageSize);
    $venues = $result['venues'];
    $totalVenues = $result['totalVenues'];
    $totalPages = $result['totalPages'];
    $page = $result['page'];
} catch (Throwable $error) {
    $venues = [];
    $totalVenues = 0;
    $totalPages = 1;
    $errors[] = 'Failed to load venues.';
    logAction($currentUser['user_id'] ?? null, 'venue_list_error', $error->getMessage());
}

$query = $_GET;
$baseUrl = BASE_PATH . '/pages/venues/index.php';

$range = 2;
$startPage = max(1, $page - $range);
$endPage = min($totalPages, $page + $range);
if ($endPage - $startPage < $range * 2) {
    $startPage = max(1, min($startPage, $endPage - $range * 2));
}

logAction($currentUser['user_id'] ?? null, 'view_venues', 'User opened venue management');
?>
<?php renderPageStart('Venues', ['bodyClass' => 'is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container is-fluid">
          <?php require __DIR__ . '/../../partials/venues/header.php'; ?>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <?php require __DIR__ . '/../../partials/venues/import_modal.php'; ?>

          <div class="box">
            <?php require __DIR__ . '/../../partials/venues/filter_bar.php'; ?>
            <?php require __DIR__ . '/../../partials/venues/venues_table.php'; ?>
            <?php require __DIR__ . '/../../partials/venues/pagination.php'; ?>
          </div>
        </div>
      </section>
      <script type="module" src="<?php echo BASE_PATH; ?>/public/js/venues.js" defer></script>
<?php renderPageEnd(); ?>
