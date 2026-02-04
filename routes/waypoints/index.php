<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/security_headers.php';

// Set API-specific security headers
setApiSecurityHeaders();

try {
    $pdo = getDatabaseConnection();
    $minLat = isset($_GET['minLat']) ? (float) $_GET['minLat'] : null;
    $maxLat = isset($_GET['maxLat']) ? (float) $_GET['maxLat'] : null;
    $minLng = isset($_GET['minLng']) ? (float) $_GET['minLng'] : null;
    $maxLng = isset($_GET['maxLng']) ? (float) $_GET['maxLng'] : null;

    if ($minLat === null || $maxLat === null || $minLng === null || $maxLng === null) {
        $venues = [];
    } else {
        $stmt = $pdo->prepare(
            'SELECT name, website, address, postal_code, city, state, country, type, contact_email,
                    contact_phone, contact_person, capacity, notes, latitude, longitude
             FROM venues
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
               AND latitude BETWEEN :minLat AND :maxLat
               AND longitude BETWEEN :minLng AND :maxLng'
        );
        $stmt->execute([
            ':minLat' => $minLat,
            ':maxLat' => $maxLat,
            ':minLng' => $minLng,
            ':maxLng' => $maxLng
        ]);
        $venues = $stmt->fetchAll();
    }

    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $gpx = $xml->createElement('gpx');
    $gpx->setAttribute('version', '1.1');
    $xml->appendChild($gpx);

    foreach ($venues as $venue) {
        $wpt = $xml->createElement('wpt');
        $wpt->setAttribute('lat', (string) $venue['latitude']);
        $wpt->setAttribute('lon', (string) $venue['longitude']);

        $name = $xml->createElement('name');
        $name->appendChild($xml->createTextNode((string) $venue['name']));
        $wpt->appendChild($name);

        if (!empty($venue['website'])) {
            $url = $xml->createElement('url');
            $url->appendChild($xml->createTextNode((string) $venue['website']));
            $wpt->appendChild($url);
        }

        $description = $xml->createElement('desc');
        $details = [];
        $descriptionFields = [
            'address' => 'Address',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'type' => 'Type',
            'contact_email' => 'Contact email',
            'contact_phone' => 'Contact phone',
            'contact_person' => 'Contact person',
            'capacity' => 'Capacity',
            'notes' => 'Notes'
        ];

        foreach ($descriptionFields as $field => $label) {
            if ($venue[$field] === null || $venue[$field] === '') {
                continue;
            }
            $details[] = sprintf('%s: %s', $label, (string) $venue[$field]);
        }

        if (!empty($details)) {
            $description->appendChild($xml->createTextNode(implode("\n", $details)));
            $wpt->appendChild($description);
        }

        $gpx->appendChild($wpt);
    }

    header('Content-Type: application/gpx+xml');
    header('Content-Disposition: inline; filename="waypoints.gpx"');
    header('Cache-Control: private, max-age=3600');
    echo $xml->saveXML();
    logAction($currentUser['user_id'] ?? null, 'fetch_waypoints', 'Fetched venues from database');
} catch (Throwable $error) {
    http_response_code(500);
    logAction($currentUser['user_id'] ?? null, 'fetch_waypoints_error', $error->getMessage());
    echo 'Failed to load venues';
}
exit;
