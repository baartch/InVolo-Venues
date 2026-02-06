<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/form_helpers.php';
require_once __DIR__ . '/../../src-php/layout.php';

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
<?php renderPageStart('Venues', ['bodyClass' => 'has-background-grey-dark has-text-light is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <div>
                <h1 class="title is-3 has-text-light">Venue Management</h1>
                <p class="subtitle is-6 has-text-grey-light">Manage the venues stored in the database.</p>
              </div>
            </div>
            <div class="level-right">
              <div class="buttons">
                <a href="<?php echo BASE_PATH; ?>/pages/venues/add.php" class="button is-link">Add Venue</a>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                  <button type="button" class="button is-light" data-import-toggle>Import</button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification is-success is-light"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification is-danger is-light"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <div class="modal" data-import-modal data-initial-open="<?php echo $showImportModal ? 'true' : 'false'; ?>">
              <div class="modal-background" data-import-close></div>
              <div class="modal-card">
                <header class="modal-card-head">
                  <p class="modal-card-title">Import Venues (JSON)</p>
                  <button class="delete" aria-label="close" data-import-close></button>
                </header>
                <section class="modal-card-body">
                  <form method="POST" action="" id="import_form">
                    <?php renderCsrfField(); ?>
                    <input type="hidden" name="action" value="import">
                    <div class="field">
                      <div class="control">
                        <textarea class="textarea" name="import_json" placeholder="Paste JSON here" rows="8"><?php echo htmlspecialchars($importPayload); ?></textarea>
                      </div>
                    </div>
                  </form>
                </section>
                <footer class="modal-card-foot">
                  <button type="button" class="button" data-import-close>Close</button>
                  <button type="submit" class="button is-link" form="import_form">Import</button>
                </footer>
              </div>
            </div>
          <?php endif; ?>

          <div class="box has-background-dark has-text-light">
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
            <div class="level mb-3">
              <div class="level-left">
                <h2 class="title is-5 has-text-light">All Venues</h2>
              </div>
              <div class="level-right">
                <form method="GET" action="<?php echo BASE_PATH; ?>/pages/venues/index.php" class="field has-addons" data-filter-form>
                  <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                  <div class="control has-icons-left">
                    <input
                      class="input has-background-grey-darker has-text-light"
                      type="text"
                      name="filter"
                      value="<?php echo htmlspecialchars($filter); ?>"
                      placeholder="Filter venues"
                      autocomplete="off"
                    >
                    <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
                  </div>
                  <div class="control">
                    <button class="button is-link" type="submit">Filter</button>
                  </div>
                  <div class="control">
                    <button type="button" class="button is-light" data-filter-clear aria-label="Clear filter">Clear</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="level mb-3">
              <div class="level-left">
                <span class="tag is-dark"><?php echo (int) $totalVenues; ?> venues</span>
                <span class="tag is-dark">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
                <span class="tag is-dark">Page size <?php echo (int) $pageSize; ?></span>
              </div>
              <div class="level-right">
                <a class="button is-light is-small" href="<?php echo htmlspecialchars($firstLink); ?>" <?php echo $page <= 1 ? 'aria-disabled="true"' : ''; ?>>First</a>
                <a class="button is-light is-small" href="<?php echo htmlspecialchars($lastLink); ?>" <?php echo $page >= $totalPages ? 'aria-disabled="true"' : ''; ?>>Last</a>
              </div>
            </div>
            <div class="table-container">
              <table class="table is-striped is-hoverable is-fullwidth is-dark" data-venues-table>
                <thead>
                  <tr>
                    <th></th>
                    <th class="has-text-light" data-sort>Name</th>
                    <th class="has-text-light" data-sort>Address</th>
                    <th class="has-text-light" data-sort>State</th>
                    <th class="has-text-light" data-sort>Country</th>
                    <th class="has-text-light" data-sort>Type</th>
                    <th class="has-text-light" data-sort>Contact Email</th>
                    <th class="has-text-light" data-sort>Contact Phone</th>
                    <th class="has-text-light" data-sort>Contact Person</th>
                    <th class="has-text-light" data-sort data-sort-type="number">Capacity</th>
                    <th class="has-text-light" data-sort>Website</th>
                    <th class="has-text-light">Actions</th>
                  </tr>
                </thead>
                <tbody class="has-text-light">
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
                          <a href="<?php echo htmlspecialchars($mapLink); ?>" class="icon has-text-link" aria-label="Open map at venue" title="Open map">
                            <i class="fa-solid fa-location-dot"></i>
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
                          <a href="<?php echo htmlspecialchars($venue['website']); ?>" target="_blank" rel="noopener noreferrer" class="has-text-link-light">
                            <?php echo htmlspecialchars($venue['website']); ?>
                          </a>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="buttons are-small">
                          <button type="button" class="button is-light" data-venue-info-toggle aria-label="Show venue details" title="Show venue details">
                            <span class="icon"><i class="fa-solid fa-circle-info"></i></span>
                          </button>
                          <a href="<?php echo BASE_PATH; ?>/pages/venues/add.php?edit=<?php echo (int) $venue['id']; ?>" class="button is-light" aria-label="Edit venue" title="Edit venue">
                            <span class="icon"><i class="fa-solid fa-pen"></i></span>
                          </a>
                          <form method="POST" action="" onsubmit="return confirm('Delete this venue?');">
                            <?php renderCsrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="venue_id" value="<?php echo (int) $venue['id']; ?>">
                            <button type="submit" class="button is-danger is-light" aria-label="Delete venue" title="Delete venue">
                              <span class="icon"><i class="fa-solid fa-trash"></i></span>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                    <tr class="is-hidden" data-venue-details>
                      <td colspan="12">
                        <article class="message is-dark">
                          <div class="message-body">
                            <?php if (!empty($venue['notes'])): ?>
                              <div class="content has-text-light">
                                <?php echo nl2br(htmlspecialchars($venue['notes'] ?? '')); ?>
                              </div>
                            <?php else: ?>
                              <p class="has-text-grey-light">No notes.</p>
                            <?php endif; ?>
                            <div class="level is-mobile mt-3">
                              <div class="level-left">
                                <span class="tag is-dark">Created: <?php echo htmlspecialchars($venue['created_at'] ?? ''); ?></span>
                                <span class="tag is-dark">Updated: <?php echo htmlspecialchars($venue['updated_at'] ?? ''); ?></span>
                              </div>
                            </div>
                          </div>
                        </article>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <nav class="pagination is-centered" role="navigation" aria-label="pagination">
              <a class="pagination-previous" href="<?php echo htmlspecialchars($prevLink); ?>" <?php echo $page <= 1 ? 'aria-disabled="true"' : ''; ?>>Previous</a>
              <a class="pagination-next" href="<?php echo htmlspecialchars($nextLink); ?>" <?php echo $page >= $totalPages ? 'aria-disabled="true"' : ''; ?>>Next</a>
              <ul class="pagination-list">
                <?php for ($pageIndex = $startPage; $pageIndex <= $endPage; $pageIndex++): ?>
                  <?php
                    $query['page'] = $pageIndex;
                    $pageLink = $baseUrl . '?' . http_build_query($query);
                  ?>
                  <li>
                    <a class="pagination-link<?php echo $pageIndex === $page ? ' is-current' : ''; ?>" href="<?php echo htmlspecialchars($pageLink); ?>">
                      <?php echo (int) $pageIndex; ?>
                    </a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          </div>
        </div>
      </section>
      <script type="module" src="<?php echo BASE_PATH; ?>/public/js/venues.js" defer></script>
<?php renderPageEnd(); ?>
