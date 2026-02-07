<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/form_helpers.php';

function handleVenueImport(array $currentUser, array $countryOptions, string $importPayload): array
{
    $errors = [];
    $notice = '';
    $showImportModal = true;

    if (($currentUser['role'] ?? '') !== 'admin') {
        $errors[] = 'You are not authorized to import venues.';
        return [
            'errors' => $errors,
            'notice' => $notice,
            'importPayload' => $importPayload,
            'showImportModal' => $showImportModal
        ];
    }

    if ($importPayload === '') {
        $errors[] = 'Please paste JSON to import.';
        return [
            'errors' => $errors,
            'notice' => $notice,
            'importPayload' => $importPayload,
            'showImportModal' => $showImportModal
        ];
    }

    $decoded = json_decode($importPayload, true);
    if (!is_array($decoded)) {
        $errors[] = 'Invalid JSON payload.';
        return [
            'errors' => $errors,
            'notice' => $notice,
            'importPayload' => $importPayload,
            'showImportModal' => $showImportModal
        ];
    }

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

    return [
        'errors' => $errors,
        'notice' => $notice,
        'importPayload' => $importPayload,
        'showImportModal' => $showImportModal
    ];
}

function handleVenueDelete(array $currentUser, int $venueId): array
{
    $errors = [];
    $notice = '';

    if ($venueId <= 0) {
        $errors[] = 'Select a venue to delete.';
        return ['errors' => $errors, 'notice' => $notice];
    }

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

    return ['errors' => $errors, 'notice' => $notice];
}
