<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/form_helpers.php';
require_once __DIR__ . '/../core/object_links.php';
require_once __DIR__ . '/venues_repository.php';

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
        $duplicateNotices = [];

        foreach ($decoded as $index => $entry) {
            if (!is_array($entry)) {
                $name = trim((string) ($entry['name'] ?? ''));
                $city = normalizeOptionalString((string) ($entry['city'] ?? ''));
                $rowErrors[] = sprintf('Row %d: "%s" (%s) is not a valid object.', $index + 1, $name ?: 'Unknown', $city ?: 'Unknown city');
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $city = normalizeOptionalString((string) ($entry['city'] ?? ''));
            $state = trim((string) ($entry['state'] ?? ''));
            if ($name === '' || $state === '') {
                $rowErrors[] = sprintf('Row %d: "%s" (%s) - name and state are required.', $index + 1, $name ?: 'Unknown', $city ?: 'Unknown city');
                continue;
            }

            $country = trim((string) ($entry['country'] ?? ''));
            if ($country !== '' && !in_array($country, $countryOptions, true)) {
                $rowErrors[] = sprintf('Row %d: "%s" (%s) - invalid country.', $index + 1, $name, $city);
                continue;
            }

            $rowLatitudeErrors = [];
            $rowLongitudeErrors = [];
            $latitude = normalizeOptionalNumber((string) ($entry['latitude'] ?? ''), 'Latitude', $rowLatitudeErrors);
            $longitude = normalizeOptionalNumber((string) ($entry['longitude'] ?? ''), 'Longitude', $rowLongitudeErrors);

            if ($rowLatitudeErrors || $rowLongitudeErrors) {
                $rowErrors[] = sprintf('Row %d: "%s" (%s) - invalid coordinates.', $index + 1, $name, $city);
                continue;
            }

            if ($latitude !== null && $longitude !== null) {
                $duplicateVenue = findVenueNearCoordinates($pdo, $latitude, $longitude);
                if ($duplicateVenue) {
                    $duplicateNotices[] = sprintf(
                        'Row %d: "%s" (%s) - skipped: duplicate near %s.',
                        $index + 1,
                        $name,
                        $city,
                        $duplicateVenue['name'] ?? 'Unknown venue'
                    );
                    continue;
                }
            }

            $address = normalizeOptionalString((string) ($entry['street'] ?? ''));
            $postalCode = normalizeOptionalString((string) ($entry['postalCode'] ?? ''));
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
                ':contact_email' => normalizeOptionalString((string) ($entry['contact_email'] ?? $entry['contactEmail'] ?? $entry['email'] ?? '')),
                ':contact_phone' => normalizeOptionalString((string) ($entry['contact_phone'] ?? $entry['contactPhone'] ?? $entry['phone'] ?? '')),
                ':contact_person' => normalizeOptionalString((string) ($entry['contact_person'] ?? $entry['contactPerson'] ?? $entry['person'] ?? '')),
                ':capacity' => isset($entry['capacity']) && $entry['capacity'] !== '' ? (int) $entry['capacity'] : null,
                ':website' => $website,
                ':notes' => normalizeOptionalString((string) ($entry['notes'] ?? $entry['Notes'] ?? ''))
            ]);

            $importedCount++;
        }

        if ($rowErrors) {
            $errors = array_merge($errors, $rowErrors);
        }

        if ($duplicateNotices) {
            $errors = array_merge($errors, $duplicateNotices);
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

    if (($currentUser['role'] ?? '') !== 'admin') {
        $errors[] = 'You are not authorized to delete venues.';
        return ['errors' => $errors, 'notice' => $notice];
    }

    if ($venueId <= 0) {
        $errors[] = 'Select a venue to delete.';
        return ['errors' => $errors, 'notice' => $notice];
    }

    try {
        $pdo = getDatabaseConnection();
        $pdo->beginTransaction();
        try {
            clearAllObjectLinks($pdo, 'venue', $venueId);
            $stmt = $pdo->prepare('DELETE FROM venues WHERE id = :id');
            $stmt->execute([':id' => $venueId]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        logAction($currentUser['user_id'] ?? null, 'venue_deleted', sprintf('Deleted venue %d', $venueId));
        $notice = 'Venue deleted successfully.';
    } catch (Throwable $error) {
        $errors[] = 'Failed to delete venue.';
        logAction($currentUser['user_id'] ?? null, 'venue_delete_error', $error->getMessage());
    }

    return ['errors' => $errors, 'notice' => $notice];
}

function handleVenuePageSizeUpdate(array $currentUser, int $requestedPageSize): array
{
    $errors = [];
    $notice = '';

    $requestedPageSize = max(25, min(500, $requestedPageSize));

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('UPDATE users SET venues_page_size = :page_size WHERE id = :user_id');
        $stmt->execute([
            ':page_size' => $requestedPageSize,
            ':user_id' => (int) ($currentUser['user_id'] ?? 0)
        ]);
        $notice = 'Page size updated successfully.';
    } catch (Throwable $error) {
        $errors[] = 'Failed to update page size.';
        logAction($currentUser['user_id'] ?? null, 'venues_page_size_error', $error->getMessage());
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'pageSize' => $requestedPageSize,
    ];
}
