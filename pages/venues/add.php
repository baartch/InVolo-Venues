<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/form_helpers.php';
require_once __DIR__ . '/../../src-php/search_helpers.php';
require_once __DIR__ . '/../../src-php/settings.php';
require_once __DIR__ . '/../../src-php/layout.php';

$errors = [];
$notice = '';
$editVenue = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($editId <= 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = (int) ($_POST['venue_id'] ?? 0);
}
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

$webSearchQuery = trim((string) ($_GET['web_search'] ?? ''));
$webSearchCountry = strtoupper(trim((string) ($_GET['web_search_country'] ?? '')));
if ($webSearchCountry === '') {
    $webSearchCountry = $formValues['country'] !== '' ? $formValues['country'] : 'DE';
}

$mapboxSearchRequested = ($_GET['mapbox_search'] ?? '') === '1';
$mapboxSearchAddress = trim((string) ($_GET['mapbox_address'] ?? ''));
$mapboxSearchCity = trim((string) ($_GET['mapbox_city'] ?? ''));
$mapboxSearchCountry = strtoupper(trim((string) ($_GET['mapbox_country'] ?? '')));
if ($mapboxSearchCountry === '') {
    $mapboxSearchCountry = $formValues['country'] !== '' ? $formValues['country'] : 'DE';
}

if ($mapboxSearchRequested && $_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach ($fields as $field) {
        if (isset($_GET[$field])) {
            $formValues[$field] = trim((string) $_GET[$field]);
        }
    }
    if ($mapboxSearchAddress !== '') {
        $formValues['address'] = $mapboxSearchAddress;
    }
    if ($mapboxSearchCity !== '') {
        $formValues['city'] = $mapboxSearchCity;
    }
    if ($mapboxSearchCountry !== '') {
        $formValues['country'] = $mapboxSearchCountry;
    }
}

$mapboxSearchNotice = '';
if ($mapboxSearchRequested && $mapboxSearchAddress === '' && $mapboxSearchCity === '') {
    $mapboxSearchNotice = 'Enter address and city to run a Mapbox search.';
} elseif ($mapboxSearchRequested && $mapboxSearchAddress === '') {
    $mapboxSearchNotice = 'Enter an address to run a Mapbox search.';
} elseif ($mapboxSearchRequested && $mapboxSearchCity === '') {
    $mapboxSearchNotice = 'Enter a city to run a Mapbox search.';
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

if ($mapboxSearchRequested && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($mapboxSearchAddress === '' || $mapboxSearchCity === '') {
        $errors[] = 'Enter both address and city before running a Mapbox search.';
    } else {
        $settings = loadSettingValues(['mapbox_api_key']);
        if ($settings['mapbox_api_key'] === '') {
            $errors[] = 'Missing Mapbox API key in settings.';
        } else {
            $mapboxQuery = trim(sprintf('%s, %s', $mapboxSearchAddress, $mapboxSearchCity));
            $mapboxUrl = 'https://api.mapbox.com/search/geocode/v6/forward?' . http_build_query([
                'q' => $mapboxQuery,
                'access_token' => $settings['mapbox_api_key'],
                'language' => 'de',
                'country' => $mapboxSearchCountry,
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
                $formValues['postal_code'] = (string) ($mapboxData['postalCode'] ?? $formValues['postal_code']);
                $formValues['state'] = (string) ($mapboxData['state'] ?? $formValues['state']);
                if ($mapboxData['latitude'] !== null) {
                    $formValues['latitude'] = (string) $mapboxData['latitude'];
                }
                if ($mapboxData['longitude'] !== null) {
                    $formValues['longitude'] = (string) $mapboxData['longitude'];
                }

                if ($notice === '') {
                    $notice = 'Mapbox search completed and location details updated.';
                }
            } else {
                $errors[] = 'No address details found from Mapbox.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
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
                header('Location: ' . BASE_PATH . '/pages/venues/index.php');
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
                    header('Location: ' . BASE_PATH . '/pages/venues/index.php');
                    exit;
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

if ($editVenue && !$mapboxSearchRequested) {
    foreach ($fields as $field) {
        $formValues[$field] = (string) ($editVenue[$field] ?? '');
    }
}

logAction($currentUser['user_id'] ?? null, 'view_venue_form', $editVenue ? sprintf('Editing venue %d', $editId) : 'Opened add venue');
?>
<?php renderPageStart($editVenue ? 'Edit Venue' : 'Add Venue', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => ['<script type="module" src="' . BASE_PATH . '/public/js/venue-form.js" defer></script>']
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <div>
                <h1 class="title is-3"><?php echo $editVenue ? 'Edit Venue' : 'Add Venue'; ?></h1>
                <p class="subtitle is-6">Manage venue details below.</p>
              </div>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <?php if ($mapboxSearchNotice !== ''): ?>
            <div class="notification"><?php echo htmlspecialchars($mapboxSearchNotice); ?></div>
          <?php endif; ?>

          <form method="GET" action="" id="mapbox_search_form" class="is-hidden">
            <input type="hidden" name="mapbox_search" value="1">
            <input type="hidden" name="mapbox_address" id="mapbox_address" value="<?php echo htmlspecialchars($mapboxSearchAddress !== '' ? $mapboxSearchAddress : $formValues['address']); ?>">
            <input type="hidden" name="mapbox_city" id="mapbox_city" value="<?php echo htmlspecialchars($mapboxSearchCity !== '' ? $mapboxSearchCity : $formValues['city']); ?>">
            <input type="hidden" name="mapbox_country" id="mapbox_country" value="<?php echo htmlspecialchars($mapboxSearchCountry !== '' ? $mapboxSearchCountry : $formValues['country']); ?>">
            <?php if ($editVenue): ?>
              <input type="hidden" name="edit" value="<?php echo (int) $editVenue['id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="name" id="mapbox_name" value="<?php echo htmlspecialchars($formValues['name']); ?>">
            <input type="hidden" name="postal_code" id="mapbox_postal_code" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
            <input type="hidden" name="state" id="mapbox_state" value="<?php echo htmlspecialchars($formValues['state']); ?>">
            <input type="hidden" name="latitude" id="mapbox_latitude" value="<?php echo htmlspecialchars($formValues['latitude']); ?>">
            <input type="hidden" name="longitude" id="mapbox_longitude" value="<?php echo htmlspecialchars($formValues['longitude']); ?>">
            <input type="hidden" name="type" id="mapbox_type" value="<?php echo htmlspecialchars($formValues['type']); ?>">
            <input type="hidden" name="contact_email" id="mapbox_contact_email" value="<?php echo htmlspecialchars($formValues['contact_email']); ?>">
            <input type="hidden" name="contact_phone" id="mapbox_contact_phone" value="<?php echo htmlspecialchars($formValues['contact_phone']); ?>">
            <input type="hidden" name="contact_person" id="mapbox_contact_person" value="<?php echo htmlspecialchars($formValues['contact_person']); ?>">
            <input type="hidden" name="capacity" id="mapbox_capacity" value="<?php echo htmlspecialchars($formValues['capacity']); ?>">
            <input type="hidden" name="website" id="mapbox_website" value="<?php echo htmlspecialchars($formValues['website']); ?>">
            <input type="hidden" name="notes" id="mapbox_notes" value="<?php echo htmlspecialchars($formValues['notes']); ?>">
          </form>

          <div class="box mb-4">
            <form method="GET" action="" class="columns is-multiline is-vcentered">
              <div class="column is-5">
                <label for="web_search" class="label">Search the web</label>
                <div class="control has-icons-left">
                  <input type="text" id="web_search" name="web_search" class="input" placeholder="Search the web for venue info" value="<?php echo htmlspecialchars($webSearchQuery); ?>">
                  <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
                </div>
              </div>
              <div class="column is-3">
                <label for="web_search_country" class="label">Search country</label>
                <div class="control">
                  <div class="select is-fullwidth">
                    <select id="web_search_country" name="web_search_country">
                      <?php foreach ($countryOptions as $country): ?>
                        <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $webSearchCountry === $country ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($country); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="column is-2">
                <label class="label">&nbsp;</label>
                <div class="control">
                  <button type="submit" class="button is-fullwidth">
                    <span class="icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <span>Search</span>
                  </button>
                </div>
              </div>
            </form>
          </div>

          <div class="box">
            <form method="POST" action="" class="columns is-multiline">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="<?php echo $editVenue ? 'update' : 'create'; ?>">
              <?php if ($editVenue): ?>
                <input type="hidden" name="venue_id" value="<?php echo (int) $editVenue['id']; ?>">
              <?php endif; ?>

              <div class="column is-6">
                <div class="field">
                  <label for="name" class="label">Name *</label>
                  <div class="control">
                    <input type="text" id="name" name="name" class="input" required value="<?php echo htmlspecialchars($formValues['name']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-6">
                <div class="field">
                  <label for="type" class="label">Type</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="type" name="type">
                        <option value="">Select type</option>
                        <?php foreach ($venueTypes as $type): ?>
                          <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $formValues['type'] === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-4">
                <div class="field">
                  <label for="state" class="label">State</label>
                  <div class="control">
                    <input type="text" id="state" name="state" class="input" value="<?php echo htmlspecialchars($formValues['state']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-4">
                <div class="field">
                  <label for="city" class="label">City *</label>
                  <div class="control">
                    <input type="text" id="city" name="city" class="input" required value="<?php echo htmlspecialchars($formValues['city']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-4">
                <div class="field">
                  <label for="postal_code" class="label">Postal Code</label>
                  <div class="control">
                    <input type="text" id="postal_code" name="postal_code" class="input" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-4">
                <div class="field">
                  <label for="country" class="label">Country</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="country" name="country">
                        <option value="">Select country</option>
                        <?php foreach ($countryOptions as $country): ?>
                          <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $formValues['country'] === $country ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($country); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-8">
                <div class="field">
                  <label for="address" class="label">Address</label>
                  <div class="field has-addons">
                    <div class="control is-expanded">
                      <input type="text" id="address" name="address" class="input" value="<?php echo htmlspecialchars($formValues['address']); ?>">
                    </div>
                    <div class="control">
                      <button type="submit" form="mapbox_search_form" class="button" id="address_mapbox_button" aria-label="Search address" disabled>
                        <span class="icon"><i class="fa-solid fa-location-crosshairs"></i></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label for="latitude" class="label">Latitude</label>
                  <div class="control">
                    <input type="number" step="0.000001" id="latitude" name="latitude" class="input" value="<?php echo htmlspecialchars($formValues['latitude']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label for="longitude" class="label">Longitude</label>
                  <div class="control">
                    <input type="number" step="0.000001" id="longitude" name="longitude" class="input" value="<?php echo htmlspecialchars($formValues['longitude']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label for="capacity" class="label">Capacity</label>
                  <div class="control">
                    <input type="number" step="1" id="capacity" name="capacity" class="input" value="<?php echo htmlspecialchars($formValues['capacity']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-6">
                <div class="field">
                  <label for="website" class="label">Website</label>
                  <div class="control">
                    <input type="url" id="website" name="website" class="input" value="<?php echo htmlspecialchars($formValues['website']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-6">
                <div class="field">
                  <label for="contact_email" class="label">Contact Email</label>
                  <div class="control">
                    <input type="email" id="contact_email" name="contact_email" class="input" value="<?php echo htmlspecialchars($formValues['contact_email']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-6">
                <div class="field">
                  <label for="contact_phone" class="label">Contact Phone</label>
                  <div class="control">
                    <input type="text" id="contact_phone" name="contact_phone" class="input" value="<?php echo htmlspecialchars($formValues['contact_phone']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-6">
                <div class="field">
                  <label for="contact_person" class="label">Contact Person</label>
                  <div class="control">
                    <input type="text" id="contact_person" name="contact_person" class="input" value="<?php echo htmlspecialchars($formValues['contact_person']); ?>">
                  </div>
                </div>
              </div>
              <div class="column is-12">
                <div class="field">
                  <label for="notes" class="label">Notes</label>
                  <div class="control">
                    <textarea id="notes" name="notes" class="textarea" rows="4"><?php echo htmlspecialchars($formValues['notes']); ?></textarea>
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="buttons">
                  <button type="submit" class="button"><?php echo $editVenue ? 'Update Venue' : 'Add Venue'; ?></button>
                  <a href="<?php echo BASE_PATH; ?>/pages/venues/index.php" class="button">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
