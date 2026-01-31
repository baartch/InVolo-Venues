<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src-php/form_helpers.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$errors = [];
$notice = '';
$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];
$importPayload = '';
$showImportModal = false;
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $stmt = $pdo->query('SELECT * FROM venues ORDER BY name');
    $venues = $stmt->fetchAll();
} catch (Throwable $error) {
    $venues = [];
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
          <div class="modal-backdrop" data-import-modal>
            <div class="modal-card">
              <h3>Import Venues (JSON)</h3>
              <form method="POST" action="">
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
                  <th data-sort>Notes</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($venues as $venue): ?>
                  <tr>
                    <td>
                      <?php if (!empty($venue['latitude']) && !empty($venue['longitude'])): ?>
                        <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-compass.svg" alt="Has coordinates" class="table-icon">
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
                    <td class="table-notes"><?php echo htmlspecialchars($venue['notes'] ?? ''); ?></td>
                    <td>
                      <div class="venue-actions">
                        <div class="venue-actions-buttons">
                          <a href="<?php echo BASE_PATH; ?>/pages/venues/add.php?edit=<?php echo (int) $venue['id']; ?>" class="icon-button secondary" aria-label="Edit venue" title="Edit venue">
                            <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-pen.svg" alt="Edit">
                          </a>
                          <form method="POST" action="" onsubmit="return confirm('Delete this venue?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="venue_id" value="<?php echo (int) $venue['id']; ?>">
                            <button type="submit" class="icon-button" aria-label="Delete venue" title="Delete venue">
                              <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-basket.svg" alt="Delete">
                            </button>
                          </form>
                        </div>
                        <div class="row-meta">
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
        </div>
      </div>
      <script>
        (function () {
          const importToggle = document.querySelector('[data-import-toggle]');
          const importModal = document.querySelector('[data-import-modal]');
          const importClose = document.querySelector('[data-import-close]');
          const venuesTable = document.querySelector('.table');

          if (importToggle && importModal) {
            importToggle.addEventListener('click', (event) => {
              event.stopPropagation();
              importModal.classList.add('open');
            });
          }

          if (importClose && importModal) {
            importClose.addEventListener('click', (event) => {
              event.stopPropagation();
              importModal.classList.remove('open');
            });
          }

          if (importModal) {
            importModal.addEventListener('click', (event) => {
              if (event.target === importModal) {
                importModal.classList.remove('open');
              }
            });
          }

          if (importModal && <?php echo $showImportModal ? 'true' : 'false'; ?>) {
            importModal.classList.add('open');
          }

          if (venuesTable) {
            const headers = venuesTable.querySelectorAll('thead th[data-sort]');
            let sortIndex = null;
            let sortDirection = 'asc';

            const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

            const getCellValue = (row, index) => {
              const cell = row.children[index];
              if (!cell) {
                return '';
              }
              const link = cell.querySelector('a');
              if (link) {
                return link.textContent.trim();
              }
              return cell.textContent.trim();
            };

            const compareValues = (a, b, type) => {
              if (type === 'number') {
                const numberA = parseFloat(a.replace(/[^0-9.-]/g, ''));
                const numberB = parseFloat(b.replace(/[^0-9.-]/g, ''));
                if (Number.isNaN(numberA) && Number.isNaN(numberB)) {
                  return 0;
                }
                if (Number.isNaN(numberA)) {
                  return 1;
                }
                if (Number.isNaN(numberB)) {
                  return -1;
                }
                return numberA - numberB;
              }
              return collator.compare(a, b);
            };

            const updateHeaderState = () => {
              headers.forEach((header) => {
                const label = header.dataset.sortLabel || header.textContent.trim();
                header.dataset.sortLabel = label;
                header.classList.remove('is-sorted', 'is-sorted-asc', 'is-sorted-desc');
                if (header.cellIndex === sortIndex) {
                  const arrow = sortDirection === 'asc' ? ' ▲' : ' ▼';
                  header.textContent = `${label}${arrow}`;
                  header.classList.add('is-sorted', sortDirection === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
                } else {
                  header.textContent = label;
                }
              });
            };

            const sortRows = (cellIndex, direction, header) => {
              const body = venuesTable.querySelector('tbody');
              if (!body) {
                return;
              }
              const type = header.dataset.sortType || 'text';
              const rows = Array.from(body.querySelectorAll('tr'));

              rows.sort((rowA, rowB) => {
                const valueA = getCellValue(rowA, cellIndex);
                const valueB = getCellValue(rowB, cellIndex);
                const comparison = compareValues(valueA, valueB, type);
                return direction === 'asc' ? comparison : -comparison;
              });

              rows.forEach((row) => body.appendChild(row));
            };

            headers.forEach((header) => {
              header.setAttribute('role', 'button');
              header.setAttribute('tabindex', '0');
              header.addEventListener('click', () => {
                const cellIndex = header.cellIndex;
                const isSameColumn = sortIndex === cellIndex;
                sortIndex = cellIndex;
                sortDirection = isSameColumn && sortDirection === 'asc' ? 'desc' : 'asc';
                sortRows(cellIndex, sortDirection, header);
                updateHeaderState();
              });
              header.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  header.click();
                }
              });
            });
          }
        })();
      </script>
<?php renderPageEnd(); ?>
