<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/auth/admin_check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/core/search_helpers.php';
require_once __DIR__ . '/../../models/core/settings.php';
require_once __DIR__ . '/../../models/venues/venues_repository.php';
require_once __DIR__ . '/../../views/core/layout.php';

$errors = [];
$notice = '';
$editVenue = null;
$pdo = null;
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

$listPage = max(1, (int) ($_GET['page'] ?? 1));
$listFilter = trim((string) ($_GET['filter'] ?? ''));
$listPerPage = (int) ($_GET['per_page'] ?? 0);
if ($listPerPage < 25 || $listPerPage > 500) {
    $listPerPage = 0;
}

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
$mapboxDuplicateNotice = '';
if ($mapboxSearchRequested && $mapboxSearchAddress === '' && $mapboxSearchCity === '') {
    $mapboxSearchNotice = 'Enter address and city to run a Mapbox search.';
} elseif ($mapboxSearchRequested && $mapboxSearchAddress === '') {
    $mapboxSearchNotice = 'Enter an address to run a Mapbox search.';
} elseif ($mapboxSearchRequested && $mapboxSearchCity === '') {
    $mapboxSearchNotice = 'Enter a city to run a Mapbox search.';
}


// MARK: Brave & Mapbox search
if ($webSearchQuery !== '' && $editId === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = loadSettingValues([
        'brave_search_api_key',
        'brave_spellcheck_api_key',
        'mapbox_api_key_server'
    ]);

    if ($settings['brave_search_api_key'] === '' || $settings['brave_spellcheck_api_key'] === '') {
        $errors[] = 'Missing Brave API keys in settings.';
    } elseif ($settings['mapbox_api_key_server'] === '') {
        $errors[] = 'Missing Mapbox server API key in settings.';
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

            $mapboxData = runMapboxAddressLookup(
                $displayAddress,
                $webSearchCountry,
                $settings['mapbox_api_key_server'],
                $errors
            );
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

                if ($pdo === null) {
                    $pdo = getDatabaseConnection();
                }

                if ($pdo && $mapboxData['latitude'] !== null && $mapboxData['longitude'] !== null) {
                    $duplicateVenue = findVenueNearCoordinates(
                        $pdo,
                        (float) $mapboxData['latitude'],
                        (float) $mapboxData['longitude']
                    );
                    if ($duplicateVenue) {
                        $mapboxDuplicateNotice = sprintf(
                            'Mapbox result matches existing venue coordinates: %s',
                            $duplicateVenue['name'] ?? 'Unknown venue'
                        );
                    }
                }
            } else {
                $errors[] = 'No address details found from Mapbox.';
            }
        } elseif (!$errors) {
            $errors[] = 'Brave search did not return a geolocal result.';
        }
    }
}

// MARK: Mapbox Search only
if ($mapboxSearchRequested && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($mapboxSearchAddress === '' || $mapboxSearchCity === '') {
        $errors[] = 'Enter both address and city before running a Mapbox search.';
    } else {
        $settings = loadSettingValues(['mapbox_api_key_server']);
        if ($settings['mapbox_api_key_server'] === '') {
            $errors[] = 'Missing Mapbox server API key in settings.';
        } else {
            $mapboxQuery = trim(sprintf('%s, %s', $mapboxSearchAddress, $mapboxSearchCity));
            $mapboxData = runMapboxAddressLookup(
                $mapboxQuery,
                $mapboxSearchCountry,
                $settings['mapbox_api_key_server'],
                $errors
            );
            if ($mapboxData) {
                $formValues['address'] = (string) ($mapboxData['street'] ?? $formValues['address']);
                $formValues['city'] = (string) ($mapboxData['city'] ?? $formValues['city']);
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

                if ($pdo === null) {
                    $pdo = getDatabaseConnection();
                }

                if ($pdo && $mapboxData['latitude'] !== null && $mapboxData['longitude'] !== null) {
                    $duplicateVenue = findVenueNearCoordinates(
                        $pdo,
                        (float) $mapboxData['latitude'],
                        (float) $mapboxData['longitude']
                    );
                    if ($duplicateVenue) {
                        $mapboxDuplicateNotice = sprintf(
                            'Mapbox result matches existing venue coordinates: %s',
                            $duplicateVenue['name'] ?? 'Unknown venue'
                        );
                    }
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

    $listPage = max(1, (int) ($_POST['page'] ?? $listPage));
    $listFilter = trim((string) ($_POST['filter'] ?? $listFilter));
    $listPerPage = (int) ($_POST['per_page'] ?? $listPerPage);
    if ($listPerPage < 25 || $listPerPage > 500) {
        $listPerPage = 0;
    }

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

    if ($latitude !== null) {
        $latitude = round($latitude, 6);
    }
    if ($longitude !== null) {
        $longitude = round($longitude, 6);
    }

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

            $listParams = [];
            if ($listPage > 1) {
                $listParams['page'] = $listPage;
            }
            if ($listPerPage > 0) {
                $listParams['per_page'] = $listPerPage;
            }
            if ($listFilter !== '') {
                $listParams['filter'] = $listFilter;
            }
            $returnUrl = BASE_PATH . '/app/controllers/venues/index.php';
            if ($listParams) {
                $returnUrl .= '?' . http_build_query($listParams);
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO venues
                    (name, address, postal_code, city, state, country, latitude, longitude, type, contact_email, contact_phone, contact_person, capacity, website, notes)
                    VALUES
                    (:name, :address, :postal_code, :city, :state, :country, :latitude, :longitude, :type, :contact_email, :contact_phone, :contact_person, :capacity, :website, :notes)'
                );
                $stmt->execute($data);
                logAction($currentUser['user_id'] ?? null, 'venue_created', sprintf('Created venue %s', $payload['name']));
                header('Location: ' . $returnUrl);
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
                    header('Location: ' . $returnUrl);
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

$cancelParams = [];
if ($listPage > 1) {
    $cancelParams['page'] = $listPage;
}
if ($listPerPage > 0) {
    $cancelParams['per_page'] = $listPerPage;
}
if ($listFilter !== '') {
    $cancelParams['filter'] = $listFilter;
}
$cancelUrl = BASE_PATH . '/app/controllers/venues/index.php';
if ($cancelParams) {
    $cancelUrl .= '?' . http_build_query($cancelParams);
}

require __DIR__ . '/../../views/venues/add.php';
