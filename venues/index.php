<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$notice = '';
$editVenue = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$venueTypes = ['Kulturlokal', 'Kneipe', 'Festival', 'Shop', 'Café', 'Bar', 'Restaurant'];

$fields = [
    'name',
    'address',
    'postal_code',
    'city',
    'state',
    'country',
    'latitude',
    'longitude',
    'type',
    'contact_email',
    'contact_phone',
    'contact_person',
    'capacity',
    'website',
    'notes'
];

$formValues = array_fill_keys($fields, '');

function normalizeOptionalString(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function normalizeOptionalNumber(string $value, string $fieldName, array &$errors, bool $integer = false): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        $errors[] = sprintf('%s must be a number.', $fieldName);
        return null;
    }

    return $integer ? (float) (int) $value : (float) $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $payload = [];
    foreach ($fields as $field) {
        $payload[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($payload['name'] === '' || $payload['state'] === '') {
        $errors[] = 'Name and state are required.';
    }

    if ($payload['type'] !== '' && !in_array($payload['type'], $venueTypes, true)) {
        $errors[] = 'Invalid venue type selected.';
    }

    $latitude = normalizeOptionalNumber($payload['latitude'], 'Latitude', $errors);
    $longitude = normalizeOptionalNumber($payload['longitude'], 'Longitude', $errors);
    $capacity = normalizeOptionalNumber($payload['capacity'], 'Capacity', $errors, true);

    if (!$errors && in_array($action, ['create', 'update'], true)) {
        try {
            $pdo = getDatabaseConnection();

            $data = [
                ':name' => $payload['name'],
                ':address' => normalizeOptionalString($payload['address']),
                ':postal_code' => normalizeOptionalString($payload['postal_code']),
                ':city' => normalizeOptionalString($payload['city']),
                ':state' => $payload['state'],
                ':country' => normalizeOptionalString($payload['country']),
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':type' => normalizeOptionalString($payload['type']),
                ':contact_email' => normalizeOptionalString($payload['contact_email']),
                ':contact_phone' => normalizeOptionalString($payload['contact_phone']),
                ':contact_person' => normalizeOptionalString($payload['contact_person']),
                ':capacity' => $capacity === null ? null : (int) $capacity,
                ':website' => normalizeOptionalString($payload['website']),
                ':notes' => normalizeOptionalString($payload['notes'])
            ];

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO venues
                    (name, address, postal_code, city, state, country, latitude, longitude, type, contact_email, contact_phone, contact_person, capacity, website, notes)
                    VALUES
                    (:name, :address, :postal_code, :city, :state, :country, :latitude, :longitude, :type, :contact_email, :contact_phone, :contact_person, :capacity, :website, :notes)'
                );
                $stmt->execute($data);
                logAction($currentUser['user_id'] ?? null, 'venue_created', sprintf('Created venue %s', $payload['name']));
                $notice = 'Venue added successfully.';
                $formValues = array_fill_keys($fields, '');
            }

            if ($action === 'update') {
                $venueId = (int) ($_POST['venue_id'] ?? 0);
                if ($venueId <= 0) {
                    $errors[] = 'Select a venue to update.';
                } else {
                    $data[':id'] = $venueId;
                    $stmt = $pdo->prepare(
                        'UPDATE venues SET
                        name = :name,
                        address = :address,
                        postal_code = :postal_code,
                        city = :city,
                        state = :state,
                        country = :country,
                        latitude = :latitude,
                        longitude = :longitude,
                        type = :type,
                        contact_email = :contact_email,
                        contact_phone = :contact_phone,
                        contact_person = :contact_person,
                        capacity = :capacity,
                        website = :website,
                        notes = :notes
                        WHERE id = :id'
                    );
                    $stmt->execute($data);
                    logAction($currentUser['user_id'] ?? null, 'venue_updated', sprintf('Updated venue %d', $venueId));
                    $notice = 'Venue updated successfully.';
                    $editId = 0;
                    $editVenue = null;
                }
            }
        } catch (Throwable $error) {
            $errors[] = 'Failed to save venue.';
            logAction($currentUser['user_id'] ?? null, 'venue_save_error', $error->getMessage());
        }
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
                if ($editId === $venueId) {
                    $editId = 0;
                    $editVenue = null;
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete venue.';
                logAction($currentUser['user_id'] ?? null, 'venue_delete_error', $error->getMessage());
            }
        }
    }

    foreach ($fields as $field) {
        $formValues[$field] = $payload[$field] ?? $formValues[$field];
    }
}

if ($editId > 0 && $editVenue === null) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT * FROM venues WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $editVenue = $stmt->fetch();
        if (!$editVenue) {
            $errors[] = 'Venue not found.';
            $editId = 0;
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load venue.';
        logAction($currentUser['user_id'] ?? null, 'venue_load_error', $error->getMessage());
    }
}

if ($editVenue) {
    foreach ($fields as $field) {
        $formValues[$field] = (string) ($editVenue[$field] ?? '');
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - Venues</title>
  <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/public/styles.css">
  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }

    .content-wrapper {
      padding: 32px;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }

    .page-header h1 {
      font-size: 24px;
      color: var(--color-primary-dark);
    }

    .venue-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .venue-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
    }

    .venue-grid .form-group {
      margin-bottom: 0;
    }

    .venue-form textarea.input {
      min-height: 120px;
      resize: vertical;
    }

    .form-footer {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
    }

    .table-wrapper {
      overflow-x: auto;
    }

    .table td {
      vertical-align: top;
    }

    .table-notes {
      min-width: 200px;
      max-width: 320px;
      white-space: pre-wrap;
    }

    .table a {
      color: var(--color-primary-dark);
    }
  </style>
</head>
<body class="map-page">
  <div class="app-layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Venue Management</h1>
        </div>

        <?php if ($notice): ?>
          <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <div class="card card-section">
          <h2><?php echo $editVenue ? 'Edit Venue' : 'Add Venue'; ?></h2>
          <form method="POST" action="" class="venue-form">
            <input type="hidden" name="action" value="<?php echo $editVenue ? 'update' : 'create'; ?>">
            <?php if ($editVenue): ?>
              <input type="hidden" name="venue_id" value="<?php echo (int) $editVenue['id']; ?>">
            <?php endif; ?>

            <div class="venue-grid">
              <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" class="input" required value="<?php echo htmlspecialchars($formValues['name']); ?>">
              </div>
              <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="input">
                  <option value="">Select type</option>
                  <?php foreach ($venueTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $formValues['type'] === $type ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($type); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="state">State *</label>
                <input type="text" id="state" name="state" class="input" required value="<?php echo htmlspecialchars($formValues['state']); ?>">
              </div>
              <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" class="input" value="<?php echo htmlspecialchars($formValues['city']); ?>">
              </div>
              <div class="form-group">
                <label for="postal_code">Postal Code</label>
                <input type="text" id="postal_code" name="postal_code" class="input" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
              </div>
              <div class="form-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" class="input" value="<?php echo htmlspecialchars($formValues['country']); ?>">
              </div>
              <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" class="input" value="<?php echo htmlspecialchars($formValues['address']); ?>">
              </div>
              <div class="form-group">
                <label for="latitude">Latitude</label>
                <input type="number" step="0.000001" id="latitude" name="latitude" class="input" value="<?php echo htmlspecialchars($formValues['latitude']); ?>">
              </div>
              <div class="form-group">
                <label for="longitude">Longitude</label>
                <input type="number" step="0.000001" id="longitude" name="longitude" class="input" value="<?php echo htmlspecialchars($formValues['longitude']); ?>">
              </div>
              <div class="form-group">
                <label for="capacity">Capacity</label>
                <input type="number" step="1" id="capacity" name="capacity" class="input" value="<?php echo htmlspecialchars($formValues['capacity']); ?>">
              </div>
              <div class="form-group">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" class="input" value="<?php echo htmlspecialchars($formValues['website']); ?>">
              </div>
              <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email" class="input" value="<?php echo htmlspecialchars($formValues['contact_email']); ?>">
              </div>
              <div class="form-group">
                <label for="contact_phone">Contact Phone</label>
                <input type="text" id="contact_phone" name="contact_phone" class="input" value="<?php echo htmlspecialchars($formValues['contact_phone']); ?>">
              </div>
              <div class="form-group">
                <label for="contact_person">Contact Person</label>
                <input type="text" id="contact_person" name="contact_person" class="input" value="<?php echo htmlspecialchars($formValues['contact_person']); ?>">
              </div>
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="input"><?php echo htmlspecialchars($formValues['notes']); ?></textarea>
              </div>
            </div>

            <div class="form-footer">
              <button type="submit" class="btn"><?php echo $editVenue ? 'Update Venue' : 'Add Venue'; ?></button>
              <?php if ($editVenue): ?>
                <a href="<?php echo BASE_PATH; ?>/venues/index.php" class="text-muted">Cancel edit</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <div class="card card-section" style="margin-top: 24px;">
          <h2>All Venues</h2>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Address</th>
                  <th>Postal Code</th>
                  <th>City</th>
                  <th>State</th>
                  <th>Country</th>
                  <th>Latitude</th>
                  <th>Longitude</th>
                  <th>Type</th>
                  <th>Contact Email</th>
                  <th>Contact Phone</th>
                  <th>Contact Person</th>
                  <th>Capacity</th>
                  <th>Website</th>
                  <th>Notes</th>
                  <th>Created</th>
                  <th>Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($venues as $venue): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($venue['name']); ?></td>
                    <td><?php echo htmlspecialchars($venue['address'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['postal_code'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['city'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['state'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['country'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['latitude'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['longitude'] ?? ''); ?></td>
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
                    <td><?php echo htmlspecialchars($venue['created_at'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($venue['updated_at'] ?? ''); ?></td>
                    <td class="table-actions">
                      <form method="GET" action="" class="table-actions">
                        <input type="hidden" name="edit" value="<?php echo (int) $venue['id']; ?>">
                        <button type="submit" class="icon-button secondary" aria-label="Edit venue" title="Edit venue">
                          <img src="<?php echo BASE_PATH; ?>/public/assets/icon-pen.svg" alt="Edit">
                        </button>
                      </form>
                      <form method="POST" action="" class="table-actions" onsubmit="return confirm('Delete this venue?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="venue_id" value="<?php echo (int) $venue['id']; ?>">
                        <button type="submit" class="icon-button" aria-label="Delete venue" title="Delete venue">
                          <img src="<?php echo BASE_PATH; ?>/public/assets/icon-basket.svg" alt="Delete">
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
