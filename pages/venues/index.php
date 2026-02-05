<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/form_helpers.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

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
        if (($currentUser['role'] ?? '') !== 'admin') {
            $errors[] = 'You are not authorized to import venues.';
        }

        $importPayload = trim((string) ($_POST['import_json'] ?? ''));
        if ($importPayload === '') {
            $errors[] = 'Please paste JSON to import.';
        }

        if (!$errors) {
            $decoded = json_decode($importPayload, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid JSON payload.';
            } else {
                try {
                    $pdo = getDatabaseConnection();
                    $stmt = $pdo->prepare(
                        'INSERT INTO venues
                        (name, address, postal_code, city, state, country, latitude, longitude, type, contact_email, contact_phone, contact_person, capacity, website, notes)
                        VALUES
                        (:name, :address, :postal_code, :city, :state, :country, :latitude, :longitude, :type, :contact_email, :contact_phone, :contact_person, :capacity, :website, :notes)'
                    );

                    $importedCount = 0;
                    $rowErrors = [];

                    foreach ($decoded as $index => $entry) {
                        if (!is_array($entry)) {
                            $rowErrors[] = sprintf('Row %d is not a valid object.', $index + 1);
                            continue;
                        }

                        $name = trim((string) ($entry['name'] ?? ''));
                        $state = trim((string) ($entry['state'] ?? ''));
                        if ($name === '' || $state === '') {
                            $rowErrors[] = sprintf('Row %d: name and state are required.', $index + 1);
                            continue;
                        }

                        $country = trim((string) ($entry['country'] ?? ''));
                        if ($country !== '' && !in_array($country, $countryOptions, true)) {
                            $rowErrors[] = sprintf('Row %d: invalid country.', $index + 1);
                            continue;
                        }

                        $rowLatitudeErrors = [];
                        $rowLongitudeErrors = [];
                        $latitude = normalizeOptionalNumber((string) ($entry['latitude'] ?? ''), 'Latitude', $rowLatitudeErrors);
                        $longitude = normalizeOptionalNumber((string) ($entry['longitude'] ?? ''), 'Longitude', $rowLongitudeErrors);

                        if ($rowLatitudeErrors || $rowLongitudeErrors) {
                            $rowErrors[] = sprintf('Row %d: invalid coordinates.', $index + 1);
                            continue;
                        }

                        $address = normalizeOptionalString((string) ($entry['street'] ?? $entry['address'] ?? ''));
                        $postalCode = normalizeOptionalString((string) ($entry['postalCode'] ?? $entry['postal_code'] ?? ''));
                        $city = normalizeOptionalString((string) ($entry['city'] ?? ''));
                        $website = normalizeOptionalString((string) ($entry['url'] ?? $entry['website'] ?? ''));

                        $stmt->execute([
                            ':name' => $name,
                            ':address' => $address,
                            ':postal_code' => $postalCode,
                            ':city' => $city,
                            ':state' => $state,
                            ':country' => normalizeOptionalString($country),
                            ':latitude' => $latitude,
                            ':longitude' => $longitude,
                            ':type' => normalizeOptionalString((string) ($entry['type'] ?? '')),
                            ':contact_email' => normalizeOptionalString((string) ($entry['contact_email'] ?? $entry['contactEmail'] ?? '')),
                            ':contact_phone' => normalizeOptionalString((string) ($entry['contact_phone'] ?? $entry['contactPhone'] ?? '')),
                            ':contact_person' => normalizeOptionalString((string) ($entry['contact_person'] ?? $entry['contactPerson'] ?? '')),
                            ':capacity' => isset($entry['capacity']) && $entry['capacity'] !== '' ? (int) $entry['capacity'] : null,
                            ':website' => $website,
                            ':notes' => normalizeOptionalString((string) ($entry['notes'] ?? ''))
                        ]);

                        $importedCount++;
                    }

                    if ($rowErrors) {
                        $errors = array_merge($errors, $rowErrors);
                    }

                    if ($importedCount > 0) {
                        logAction($currentUser['user_id'] ?? null, 'venue_imported', sprintf('Imported %d venues', $importedCount));
                        $notice = sprintf('Imported %d venues successfully.', $importedCount);
                        $importPayload = '';
                    }
                } catch (Throwable $error) {
                    $errors[] = 'Failed to import venues.';
                    logAction($currentUser['user_id'] ?? null, 'venue_import_error', $error->getMessage());
                }
            }
        }

        $showImportModal = true;
    }

    if ($action === 'delete') {
        $venueId = (int) ($_POST['venue_id'] ?? 0);
        if ($venueId <= 0) {
            $errors[] = 'Select a venue to delete.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('DELETE FROM venues WHERE id = :id');
                $stmt->execute([':id' => $venueId]);
                logAction($currentUser['user_id'] ?? null, 'venue_deleted', sprintf('Deleted venue %d', $venueId));
                $notice = 'Venue deleted successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete venue.';
                logAction($currentUser['user_id'] ?? null, 'venue_delete_error', $error->getMessage());
            }
        }
    }
}

try {
    $pdo = getDatabaseConnection();
    if ($filter !== '') {
        $searchTerms = preg_split('/\s+/', $filter) ?: [];
        $searchTokens = [];
        foreach ($searchTerms as $term) {
            $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', $term);
            if ($normalized === '') {
                continue;
            }
            if (mb_strlen($normalized) < 4) {
                continue;
            }
            $searchTokens[] = '+' . $normalized . '*';
        }

        if ($searchTokens === []) {
            $filterParam = '%' . $filter . '%';
            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM venues
                WHERE name LIKE ?
                   OR address LIKE ?
                   OR postal_code LIKE ?
                   OR city LIKE ?
                   OR state LIKE ?
                   OR country LIKE ?
                   OR type LIKE ?
                   OR contact_email LIKE ?
                   OR contact_phone LIKE ?
                   OR contact_person LIKE ?
                   OR website LIKE ?
                   OR notes LIKE ?'
            );
            $countStmt->execute(array_fill(0, 12, $filterParam));
            $totalVenues = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;

            $stmt = $pdo->prepare(
                'SELECT * FROM venues
                WHERE name LIKE ?
                   OR address LIKE ?
                   OR postal_code LIKE ?
                   OR city LIKE ?
                   OR state LIKE ?
                   OR country LIKE ?
                   OR type LIKE ?
                   OR contact_email LIKE ?
                   OR contact_phone LIKE ?
                   OR contact_person LIKE ?
                   OR website LIKE ?
                   OR notes LIKE ?
                ORDER BY name
                LIMIT ? OFFSET ?'
            );
            $params = array_fill(0, 12, $filterParam);
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(13, $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(14, $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $searchQuery = implode(' ', $searchTokens);
            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM venues
                WHERE MATCH(name, address, city, state, contact_email, contact_phone, contact_person, website, notes)
                AGAINST (? IN BOOLEAN MODE)'
            );
            $countStmt->execute([$searchQuery]);
            $totalVenues = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;

            $stmt = $pdo->prepare(
                'SELECT * FROM venues
                WHERE MATCH(name, address, city, state, contact_email, contact_phone, contact_person, website, notes)
                AGAINST (? IN BOOLEAN MODE)
                ORDER BY name
                LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $searchQuery, PDO::PARAM_STR);
            $stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
        }
    } else {
        $totalVenues = (int) $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn();
        $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $stmt = $pdo->prepare('SELECT * FROM venues ORDER BY name LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    $venues = $stmt->fetchAll();
} catch (Throwable $error) {
    $venues = [];
    $totalVenues = 0;
    $totalPages = 1;
    $errors[] = 'Failed to load venues.';
    logAction($currentUser['user_id'] ?? null, 'venue_list_error', $error->getMessage());
}

logAction($currentUser['user_id'] ?? null, 'view_venues', 'User opened venue management');
?>
<?php renderPageStart('Venue Database - Venues', ['theme' => getCurrentTheme($currentUser['ui_theme'] ?? null)]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Venue Management</h1>
          <div class="page-header-actions">
            <a href="<?php echo BASE_PATH; ?>/pages/venues/add.php" class="btn">Add Venue</a>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
              <button type="button" class="btn" data-import-toggle>Import</button>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($notice): ?>
          <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <div class="modal-backdrop" data-import-modal data-initial-open="<?php echo $showImportModal ? 'true' : 'false'; ?>">
            <div class="modal-card">
              <h3>Import Venues (JSON)</h3>
              <form method="POST" action="">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="action" value="import">
                <textarea class="input" name="import_json" placeholder="Paste JSON here"><?php echo htmlspecialchars($importPayload); ?></textarea>
                <div class="modal-actions">
                  <button type="button" class="btn modal-close" data-import-close>Close</button>
                  <button type="submit" class="btn">Import</button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <div class="card card-section">
          <h2>All Venues</h2>
          <form method="GET" action="<?php echo BASE_PATH; ?>/pages/venues/index.php" class="table-filter" data-filter-form>
            <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
            <div class="table-filter-field">
              <input
                class="input"
                type="text"
                name="filter"
                value="<?php echo htmlspecialchars($filter); ?>"
                placeholder="Filter venues"
                autocomplete="off"
              >
              <span class="table-filter-clear" data-filter-clear role="button" tabindex="0" aria-label="Clear filter">❌</span>
            </div>
          </form>
          <div class="table-meta">
            <span><?php echo (int) $totalVenues; ?> venues</span>
            <span>Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
          </div>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th></th>
                  <th data-sort>Name</th>
                  <th data-sort>Address</th>
                  <th data-sort>State</th>
                  <th data-sort>Country</th>
                  <th data-sort>Type</th>
                  <th data-sort>Contact Email</th>
                  <th data-sort>Contact Phone</th>
                  <th data-sort>Contact Person</th>
                  <th data-sort data-sort-type="number">Capacity</th>
                  <th data-sort>Website</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($venues as $venue): ?>
                  <tr>
                    <td>
                      <?php if (!empty($venue['latitude']) && !empty($venue['longitude'])): ?>
                        <?php
                          $lat = number_format((float) $venue['latitude'], 6, '.', '');
                          $lng = number_format((float) $venue['longitude'], 6, '.', '');
                          $mapLink = BASE_PATH . '/pages/map/index.php?' . http_build_query([
                              'lat' => $lat,
                              'lng' => $lng,
                              'zoom' => 13
                          ]);
                        ?>
                        <a href="<?php echo htmlspecialchars($mapLink); ?>" class="table-icon" aria-label="Open map at venue" title="Open map">
                          <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-mapmarker.svg" alt="Has coordinates" class="table-icon">
                        </a>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($venue['name']); ?></td>
                    <td>
                      <?php
                        $addressParts = array_filter([
                            $venue['address'] ?? '',
                            implode(' ', array_filter([
                                $venue['postal_code'] ?? '',
                                $venue['city'] ?? ''
                            ]))
                        ]);
                        echo nl2br(htmlspecialchars(implode("\n", $addressParts)));
                      ?>
                    </td>
                    <td><?php echo htmlspecialchars($venue['state'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['country'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['contact_email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['contact_phone'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['contact_person'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['capacity'] ?? ''); ?></td>
                    <td>
                      <?php if (!empty($venue['website'])): ?>
                        <a href="<?php echo htmlspecialchars($venue['website']); ?>" target="_blank" rel="noopener noreferrer">
                          <?php echo htmlspecialchars($venue['website']); ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="table-actions">
                        <button type="button" class="icon-button secondary" data-venue-info-toggle aria-label="Show venue details" title="Show venue details">
                          <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-info.svg" alt="Info">
                        </button>
                        <a href="<?php echo BASE_PATH; ?>/pages/venues/add.php?edit=<?php echo (int) $venue['id']; ?>" class="icon-button secondary" aria-label="Edit venue" title="Edit venue">
                          <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-pen.svg" alt="Edit">
                        </a>
                        <form method="POST" action="" onsubmit="return confirm('Delete this venue?');">
                          <?php renderCsrfField(); ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="venue_id" value="<?php echo (int) $venue['id']; ?>">
                          <button type="submit" class="icon-button" aria-label="Delete venue" title="Delete venue">
                            <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-basket.svg" alt="Delete">
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <tr class="venue-details-row" data-venue-details>
                    <td colspan="12">
                      <div class="venue-details">
                        <?php if (!empty($venue['notes'])): ?>
                          <div class="venue-details-notes">
                            <?php echo nl2br(htmlspecialchars($venue['notes'] ?? '')); ?>
                          </div>
                        <?php else: ?>
                          <div class="venue-details-notes text-muted">No notes.</div>
                        <?php endif; ?>
                        <div class="venue-details-meta">
                          <span>Created: <?php echo htmlspecialchars($venue['created_at'] ?? ''); ?></span>
                          <span>Updated: <?php echo htmlspecialchars($venue['updated_at'] ?? ''); ?></span>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="table-pagination" aria-label="Venues pagination">
            <?php
              $query = $_GET;
              $query['page'] = max(1, $page - 1);
              $baseUrl = BASE_PATH . '/pages/venues/index.php';
              $prevLink = $baseUrl . '?' . http_build_query($query);
              $query['page'] = min($totalPages, $page + 1);
              $nextLink = $baseUrl . '?' . http_build_query($query);
              $query['page'] = 1;
              $firstLink = $baseUrl . '?' . http_build_query($query);
              $query['page'] = $totalPages;
              $lastLink = $baseUrl . '?' . http_build_query($query);

              $range = 2;
              $startPage = max(1, $page - $range);
              $endPage = min($totalPages, $page + $range);
              if ($endPage - $startPage < $range * 2) {
                $startPage = max(1, min($startPage, $endPage - $range * 2));
              }
            ?>
            <a class="btn" href="<?php echo htmlspecialchars($firstLink); ?>" <?php echo $page <= 1 ? 'aria-disabled="true"' : ''; ?>>First</a>
            <a class="btn" href="<?php echo htmlspecialchars($prevLink); ?>" <?php echo $page <= 1 ? 'aria-disabled="true"' : ''; ?>>Previous</a>
            <?php for ($pageIndex = $startPage; $pageIndex <= $endPage; $pageIndex++): ?>
              <?php
                $query['page'] = $pageIndex;
                $pageLink = $baseUrl . '?' . http_build_query($query);
              ?>
              <a class="pagination-page<?php echo $pageIndex === $page ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($pageLink); ?>">
                <?php echo (int) $pageIndex; ?>
              </a>
            <?php endfor; ?>
            <span class="pagination-status">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
            <a class="btn" href="<?php echo htmlspecialchars($nextLink); ?>" <?php echo $page >= $totalPages ? 'aria-disabled="true"' : ''; ?>>Next</a>
            <a class="btn" href="<?php echo htmlspecialchars($lastLink); ?>" <?php echo $page >= $totalPages ? 'aria-disabled="true"' : ''; ?>>Last</a>
          </div>
        </div>
      </div>
      <script type="module" src="<?php echo BASE_PATH; ?>/public/js/venues.js" defer></script>
<?php renderPageEnd(); ?>
