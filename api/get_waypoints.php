<?php
require_once __DIR__ . '/../auth/auth_check.php';

// Serve the GPX file only if authenticated
$gpxFile = __DIR__ . '/../public/waypoints.gpx';

if (file_exists($gpxFile)) {
    header('Content-Type: application/gpx+xml');
    header('Content-Disposition: inline; filename="waypoints.gpx"');
    header('Cache-Control: private, max-age=3600');
    readfile($gpxFile);
} else {
    http_response_code(404);
    echo 'GPX file not found';
}
exit;
