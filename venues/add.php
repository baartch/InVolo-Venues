<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$notice = '';
$editVenue = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$venueTypes = ['Kulturlokal', 'Kneipe', 'Festival', 'Shop', 'Café', 'Bar', 'Restaurant'];
$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];

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
$resetForm = false;
$action = '';

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

function loadSettingValues(array $keys): array
{
    $values = array_fill_keys($keys, '');
    if ($keys === []) {
        return $values;
    }

    try {
        $pdo = getDatabaseConnection();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            sprintf('SELECT setting_key, setting_value FROM settings WHERE setting_key IN (%s)', $placeholders)
        );
        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $key = (string) $row['setting_key'];
            if (array_key_exists($key, $values)) {
                $values[$key] = decryptSettingValue($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $error) {
        return $values;
    }

    return $values;
}

function fetchJsonResponse(string $url, array $headers, array &$errors, string $context): ?array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = sprintf('%s: %s', $name, $value);
    }

    $contextOptions = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines),
            'timeout' => 15,
            'ignore_errors' => true
        ]
    ];

    $response = @file_get_contents($url, false, stream_context_create($contextOptions));
    if ($response === false) {
        $errors[] = sprintf('%s request failed.', $context);
        return null;
    }

    $statusLine = $http_response_header[0] ?? '';
    if (preg_match('/HTTP\/[\d.]+\s+(\d{3})/', $statusLine, $matches)) {
        $statusCode = (int) $matches[1];
        if ($statusCode < 200 || $statusCode >= 300) {
            $errors[] = sprintf('%s request failed (HTTP %d).', $context, $statusCode);
            return null;
        }
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $errors[] = sprintf('Unable to parse %s response.', $context);
        return null;
    }

    return $decoded;
}

function getBraveSearchLanguage(string $countryCode): string
{
    $countryCode = strtoupper($countryCode);
    if ($countryCode === 'IT') {
        return 'it';
    }
    if ($countryCode === 'FR') {
        return 'fr';
    }
    return 'de';
}

function isBraveGeolocal(array $searchResult): bool
{
    if (empty($searchResult['query']['is_geolocal'])) {
        return false;
    }

    $infoBox = $searchResult['infobox']['results'][0] ?? null;
    if (!$infoBox || empty($infoBox['is_location'])) {
        return false;
    }

    return !empty($infoBox['location']['postal_address']['displayAddress']);
}

function getMapboxTranslatedName(array $contextEntry): string
{
    return $contextEntry['translations']['de']['name'] ?? ($contextEntry['name'] ?? '');
}

function fetchMapboxData(array $mapboxResult): ?array
{
    $features = $mapboxResult['features'] ?? [];
    if (!is_array($features) || !isset($features[0])) {
        return null;
    }

    $feature = $features[0];
    $context = $feature['properties']['context'] ?? [];
    $addressContext = $context['address'] ?? [];
    $streetContext = $context['street'] ?? [];
    $postcodeContext = $context['postcode'] ?? [];
    $placeContext = $context['place'] ?? [];
    $localityContext = $context['locality'] ?? [];
    $regionContext = $context['region'] ?? [];

    $street = $addressContext['name'] ?? '';
    if ($street === '') {
        $streetName = $addressContext['street_name'] ?? ($streetContext['name'] ?? '');
        $streetNumber = $addressContext['address_number'] ?? '';
        $street = trim(sprintf('%s %s', $streetName, $streetNumber));
    }

    $postalCode = $postcodeContext['name'] ?? '';
    $city = getMapboxTranslatedName($placeContext);
    if ($city === '') {
        $city = getMapboxTranslatedName($localityContext);
    }

    $state = getMapboxTranslatedName($regionContext);
    $cleanedState = str_replace('Kanton ', '', (string) $state);

    $coordinates = $feature['properties']['coordinates'] ?? [];
    $latitude = $coordinates['latitude'] ?? null;
    $longitude = $coordinates['longitude'] ?? null;

    return [
        'street' => $street,
        'postalCode' => $postalCode,
        'city' => $city,
        'state' => $cleanedState,
        'latitude' => $latitude,
        'longitude' => $longitude
    ];
}

$webSearchQuery = trim((string) ($_GET['web_search'] ?? ''));
$webSearchCountry = strtoupper(trim((string) ($_GET['web_search_country'] ?? '')));
if ($webSearchCountry === '') {
    $webSearchCountry = $formValues['country'] !== '' ? $formValues['country'] : 'DE';
}

if ($webSearchQuery !== '' && $editId === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = loadSettingValues([
        'brave_search_api_key',
        'brave_spellcheck_api_key',
        'mapbox_api_key'
    ]);

    if ($settings['brave_search_api_key'] === '' || $settings['brave_spellcheck_api_key'] === '') {
        $errors[] = 'Missing Brave API keys in settings.';
    } elseif ($settings['mapbox_api_key'] === '') {
        $errors[] = 'Missing Mapbox API key in settings.';
    } else {
        $spellcheckUrl = 'https://api.search.brave.com/res/v1/spellcheck/search?' . http_build_query([
            'q' => $webSearchQuery,
            'country' => $webSearchCountry
        ]);
        $spellcheckResult = fetchJsonResponse(
            $spellcheckUrl,
            [
                'Accept' => 'application/json',
                'X-Subscription-Token' => $settings['brave_spellcheck_api_key'],
                'User-Agent' => 'InVoloVenue/1.0'
            ],
            $errors,
            'Brave spellcheck'
        );

        $spellcheckedQuery = $webSearchQuery;
        if (isset($spellcheckResult['results'][0]['query'])) {
            $spellcheckedQuery = (string) $spellcheckResult['results'][0]['query'];
        }

        $braveSearchUrl = 'https://api.search.brave.com/res/v1/web/search?' . http_build_query([
            'q' => $spellcheckedQuery . ' address',
            'country' => $webSearchCountry,
            'search_lang' => getBraveSearchLanguage($webSearchCountry)
        ]);
        $braveSearchResult = fetchJsonResponse(
            $braveSearchUrl,
            [
                'Accept' => 'application/json',
                'X-Subscription-Token' => $settings['brave_search_api_key'],
                'User-Agent' => 'InVoloVenue/1.0'
            ],
            $errors,
            'Brave search'
        );

        if ($braveSearchResult && isBraveGeolocal($braveSearchResult)) {
            $braveInfo = $braveSearchResult['infobox']['results'][0];
            $displayAddress = $braveInfo['location']['postal_address']['displayAddress'] ?? '';

            $mapboxUrl = 'https://api.mapbox.com/search/geocode/v6/forward?' . http_build_query([
                'q' => $displayAddress,
                'access_token' => $settings['mapbox_api_key'],
                'language' => 'de',
                'country' => $webSearchCountry,
                'limit' => 1,
                'types' => 'address'
            ]);
            $mapboxResult = fetchJsonResponse(
                $mapboxUrl,
                [
                    'Accept' => '*/*',
                    'User-Agent' => 'InVoloVenue/1.0'
                ],
                $errors,
                'Mapbox geocoding'
            );

            $mapboxData = $mapboxResult ? fetchMapboxData($mapboxResult) : null;
            if ($mapboxData) {
                $formValues['name'] = (string) ($braveInfo['title'] ?? $formValues['name']);
                $formValues['website'] = (string) ($braveInfo['website_url'] ?? $formValues['website']);
                $formValues['address'] = (string) ($mapboxData['street'] ?? $formValues['address']);
                $formValues['postal_code'] = (string) ($mapboxData['postalCode'] ?? $formValues['postal_code']);
                $formValues['city'] = (string) ($mapboxData['city'] ?? $formValues['city']);
                $formValues['state'] = (string) ($mapboxData['state'] ?? $formValues['state']);
                $formValues['country'] = $webSearchCountry;
                if ($mapboxData['latitude'] !== null) {
                    $formValues['latitude'] = (string) $mapboxData['latitude'];
                }
                if ($mapboxData['longitude'] !== null) {
                    $formValues['longitude'] = (string) $mapboxData['longitude'];
                }

                if ($notice === '') {
                    $notice = 'Search completed and form details filled in.';
                }
            } else {
                $errors[] = 'No address details found from Mapbox.';
            }
        } elseif (!$errors) {
            $errors[] = 'Brave search did not return a geolocal result.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $payload = [];
    foreach ($fields as $field) {
        $payload[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($payload['name'] === '' || $payload['city'] === '') {
        $errors[] = 'Name and city are required.';
    }

    if ($payload['type'] !== '' && !in_array($payload['type'], $venueTypes, true)) {
        $errors[] = 'Invalid venue type selected.';
    }

    if ($payload['country'] !== '' && !in_array($payload['country'], $countryOptions, true)) {
        $errors[] = 'Invalid country selected.';
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
                header('Location: ' . BASE_PATH . '/venues/index.php');
                exit;
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
                    $resetForm = true;
                }
            }
        } catch (Throwable $error) {
            $errors[] = 'Failed to save venue.';
            logAction($currentUser['user_id'] ?? null, 'venue_save_error', $error->getMessage());
        }
    }

    foreach ($fields as $field) {
        $formValues[$field] = $payload[$field] ?? $formValues[$field];
    }
}

if ($resetForm && !$errors) {
    $formValues = array_fill_keys($fields, '');
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

logAction($currentUser['user_id'] ?? null, 'view_venue_form', $editVenue ? sprintf('Editing venue %d', $editId) : 'Opened add venue');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - <?php echo $editVenue ? 'Edit Venue' : 'Add Venue'; ?></title>
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

    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      border: 0;
      white-space: nowrap;
    }

    .page-header h1 {
      font-size: 24px;
      color: var(--color-primary-dark);
    }

    .page-header-actions {
      display: flex;
      gap: 12px;
      align-items: center;
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

    .web-search-form {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }

    .web-search-form .input {
      flex: 1;
      min-width: 220px;
    }

    .web-search-form select.input {
      flex: 0 0 120px;
      min-width: 120px;
    }

    .web-search-form .icon-button {
      width: 44px;
      height: 44px;
      padding: 0;
    }

    .web-search-form .icon-button img {
      width: 22px;
      height: 22px;
    }
  </style>
</head>
<body class="map-page">
  <div class="app-layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="content-wrapper">
        <div class="page-header">
          <div>
            <h1><?php echo $editVenue ? 'Edit Venue' : 'Add Venue'; ?></h1>
            <p class="text-muted">Manage venue details below.</p>
          </div>
        </div>

        <?php if ($notice): ?>
          <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <form method="GET" action="" class="web-search-form">
          <label for="web_search" class="sr-only">Search the web</label>
          <input type="text" id="web_search" name="web_search" class="input" placeholder="Search the web for venue info" value="<?php echo htmlspecialchars($webSearchQuery); ?>">
          <label for="web_search_country" class="sr-only">Search country</label>
          <select id="web_search_country" name="web_search_country" class="input">
            <?php foreach ($countryOptions as $country): ?>
              <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $webSearchCountry === $country ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($country); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="icon-button" aria-label="Search">
            <img src="<?php echo BASE_PATH; ?>/public/assets/icon-compass.svg" alt="">
          </button>
        </form>

        <div class="card card-section">
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
                <label for="state">State</label>
                <input type="text" id="state" name="state" class="input" value="<?php echo htmlspecialchars($formValues['state']); ?>">
              </div>
              <div class="form-group">
                <label for="city">City *</label>
                <input type="text" id="city" name="city" class="input" required value="<?php echo htmlspecialchars($formValues['city']); ?>">
              </div>
              <div class="form-group">
                <label for="postal_code">Postal Code</label>
                <input type="text" id="postal_code" name="postal_code" class="input" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
              </div>
              <div class="form-group">
                <label for="country">Country</label>
                <select id="country" name="country" class="input">
                  <option value="">Select country</option>
                  <?php foreach ($countryOptions as $country): ?>
                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $formValues['country'] === $country ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($country); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
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
              <a href="<?php echo BASE_PATH; ?>/venues/index.php" class="text-muted">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
