<?php
/**
 * Variables expected:
 * - array $venues
 * - int $venuesPage
 * - int $venuesPerPage
 * - string $venuesQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$mapIcon = '<span class="icon"><i class="fa-solid fa-location-dot"></i></span>';

$teamRatings = $teamRatings ?? [];
$activeTeamId = (int) ($activeTeamId ?? 0);

$listColumns = [
    buildListColumn('Marker Icon', null, static function (array $venue) use ($mapIcon, $activeTeamId): string {
        $latitude = $venue['latitude'] ?? null;
        $longitude = $venue['longitude'] ?? null;
        if (empty($latitude) || empty($longitude)) {
            return '';
        }
        $lat = number_format((float) $latitude, 6, '.', '');
        $lng = number_format((float) $longitude, 6, '.', '');
        $mapLink = BASE_PATH . '/app/controllers/map/index.php?' . http_build_query([
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => 13,
            'team_id' => $activeTeamId > 0 ? $activeTeamId : null
        ]);

        return '<a href="' . htmlspecialchars($mapLink) . '" class="icon" aria-label="Open map" title="Open map">' . $mapIcon . '</a>';
    }, true, null),
    buildListColumn('Name', null, static function (array $venue) use ($teamRatings): string {
        $name = htmlspecialchars((string) ($venue['name'] ?? ''));
        $rating = $teamRatings[(int) ($venue['id'] ?? 0)] ?? '';
        if ($rating === '') {
            return $name;
        }
        $ratingClass = match ($rating) {
            'A' => 'venue-rating-a',
            'B' => 'venue-rating-b',
            'C' => 'venue-rating-c',
            default => 'venue-rating-default'
        };
        return $name . ' <span class="tag ' . $ratingClass . '">' . htmlspecialchars($rating) . '</span>';
    }, true, 'name'),
    buildListColumn('City', null, static function (array $venue): string {
        return (string) ($venue['city'] ?? '');
    }, false, 'city'),
    buildListColumn('Country', null, static function (array $venue): string {
        return (string) ($venue['country'] ?? '');
    }, false, 'country'),
    buildListColumn('Contact', null, static function (array $venue): string {
        $buttons = [];
        if (!empty($venue['website'])) {
            $websiteUrl = (string) $venue['website'];
            $buttons[] = '<div class="dropdown" data-email-menu>'
                . '<div class="dropdown-trigger">'
                . '<button type="button" class="button is-small" aria-label="Website" title="Website" aria-haspopup="true" data-email-menu-trigger>'
                . '<span class="icon"><i class="fa-solid fa-globe"></i></span>'
                . '</button>'
                . '</div>'
                . '<div class="dropdown-menu" role="menu">'
                . '<div class="dropdown-content">'
                . '<a class="dropdown-item" href="' . htmlspecialchars($websiteUrl) . '" target="_blank" rel="noopener noreferrer">Open</a>'
                . '<button type="button" class="dropdown-item" data-copy-value="' . htmlspecialchars($websiteUrl) . '">Copy URL</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }
        if (!empty($venue['contact_email'])) {
            $emailAddress = (string) $venue['contact_email'];
            $composeUrl = BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query([
                'tab' => 'email',
                'compose' => 1,
                'to' => $emailAddress
            ]);
            $buttons[] = '<div class="dropdown" data-email-menu>'
                . '<div class="dropdown-trigger">'
                . '<button type="button" class="button is-small" aria-label="Email" title="Email" aria-haspopup="true" data-email-menu-trigger>'
                . '<span class="icon"><i class="fa-solid fa-envelope"></i></span>'
                . '</button>'
                . '</div>'
                . '<div class="dropdown-menu" role="menu">'
                . '<div class="dropdown-content">'
                . '<a class="dropdown-item" href="' . htmlspecialchars($composeUrl) . '">Send eMail</a>'
                . '<button type="button" class="dropdown-item" data-copy-email="' . htmlspecialchars($emailAddress) . '">Copy eMail address</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }
        if (!empty($venue['contact_phone'])) {
            $phoneNumber = (string) $venue['contact_phone'];
            $buttons[] = '<div class="dropdown" data-email-menu>'
                . '<div class="dropdown-trigger">'
                . '<button type="button" class="button is-small" aria-label="Phone" title="Phone" aria-haspopup="true" data-email-menu-trigger>'
                . '<span class="icon"><i class="fa-solid fa-phone"></i></span>'
                . '</button>'
                . '</div>'
                . '<div class="dropdown-menu" role="menu">'
                . '<div class="dropdown-content">'
                . '<a class="dropdown-item" href="tel:' . htmlspecialchars($phoneNumber) . '">Call</a>'
                . '<button type="button" class="dropdown-item" data-copy-value="' . htmlspecialchars($phoneNumber) . '">Copy phone number</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        if (!$buttons) {
            return '';
        }

        return '<div class="buttons are-small">' . implode('', $buttons) . '</div>';
    }, true, null)
];

$listSort = [
    'tableType' => 'venues',
    'currentKey' => (string) ($sort ?? 'name'),
    'currentDirection' => (string) ($dir ?? 'asc'),
    'paramKey' => 'sort',
    'paramDirection' => 'dir',
    'pageParam' => 'page'
];

$listRows = $venues;
$listEmptyMessage = 'No venues found.';

$listRowLink = static function (array $venue) use ($venuesPage, $venuesPerPage, $venuesQuery, $activeTeamId): array {
    $venueId = (int) ($venue['id'] ?? 0);
    $params = [
        'venue_id' => $venueId,
        'page' => $venuesPage,
        'per_page' => $venuesPerPage,
        'team_id' => $activeTeamId > 0 ? $activeTeamId : null
    ];
    if ($venuesQuery !== '') {
        $params['filter'] = $venuesQuery;
    }
    $detailLink = BASE_PATH . '/app/controllers/venues/index.php?' . http_build_query(array_filter($params, static fn($value) => $value !== null && $value !== ''));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#venues-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $venue) use ($currentUser, $activeTeamId): string {
    $venueId = (int) ($venue['id'] ?? 0);
    $editLink = BASE_PATH . '/app/controllers/venues/add.php?edit=' . $venueId;
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <form method="GET" action="<?php echo BASE_PATH; ?>/app/controllers/venues/add.php">
          <input type="hidden" name="edit" value="<?php echo $venueId; ?>">
          <button type="submit" class="button" aria-label="Edit venue" title="Edit venue">
            <span class="icon"><i class="fa-solid fa-pen"></i></span>
          </button>
        </form>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/venues/index.php" onsubmit="return confirm('Delete this venue?');">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="venue_id" value="<?php echo $venueId; ?>">
            <?php if ($activeTeamId > 0): ?>
              <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
            <?php endif; ?>
            <button type="submit" class="button" aria-label="Delete venue" title="Delete venue">
              <span class="icon"><i class="fa-solid fa-trash"></i></span>
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
