<?php
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
