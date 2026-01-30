<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query(
        'SELECT name, website, latitude, longitude
         FROM venues
         WHERE latitude IS NOT NULL AND longitude IS NOT NULL'
    );
    $venues = $stmt->fetchAll();

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
