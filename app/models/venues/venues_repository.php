<?php

require_once __DIR__ . '/../core/database.php';

function fetchVenuesWithPagination(string $filter, int $page, int $pageSize, string $sortKey = 'name', string $sortDirection = 'asc'): array
{
    $pdo = getDatabaseConnection();

    $allowedSortColumns = [
        'name' => 'name',
        'city' => 'city',
        'country' => 'country',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'id' => 'id'
    ];
    $sortColumn = $allowedSortColumns[$sortKey] ?? 'name';
    $sortDirectionSql = strtolower($sortDirection) === 'desc' ? 'DESC' : 'ASC';
    $orderBy = ' ORDER BY ' . $sortColumn . ' ' . $sortDirectionSql . ', id ASC';

    if ($filter !== '') {
        $filterParam = '%' . $filter . '%';
        if (function_exists('mb_strtolower')) {
            $loweredFilter = mb_strtolower($filter, 'UTF-8');
        } else {
            $loweredFilter = strtolower($filter);
        }
        $normalizedFilter = preg_replace('/[^\p{L}\p{N}]+/u', '', $loweredFilter);

        $where = '(
            name LIKE ?
            OR address LIKE ?
            OR postal_code LIKE ?
            OR city LIKE ?
            OR state LIKE ?
            OR country LIKE ?
            OR type LIKE ?
            OR contact_email LIKE ?
            OR contact_phone LIKE ?
            OR contact_person LIKE ?
            OR website LIKE ?
            OR notes LIKE ?';

        $params = array_fill(0, 12, $filterParam);

        if ($normalizedFilter !== '') {
            $where .= '
            OR REPLACE(REPLACE(REPLACE(REPLACE(LOWER(name), "-", ""), "_", ""), " ", ""), ".", "") LIKE ?
            OR REPLACE(REPLACE(REPLACE(REPLACE(LOWER(address), "-", ""), "_", ""), " ", ""), ".", "") LIKE ?
            OR REPLACE(REPLACE(REPLACE(REPLACE(LOWER(city), "-", ""), "_", ""), " ", ""), ".", "") LIKE ?
            OR REPLACE(REPLACE(REPLACE(REPLACE(LOWER(contact_person), "-", ""), "_", ""), " ", ""), ".", "") LIKE ?
            OR REPLACE(REPLACE(REPLACE(REPLACE(LOWER(website), "-", ""), "_", ""), " ", ""), ".", "") LIKE ?';
            $normalizedParam = '%' . $normalizedFilter . '%';
            $params = array_merge($params, array_fill(0, 5, $normalizedParam));
        }

        $where .= '
        )';

        $countSql = 'SELECT COUNT(*) FROM venues WHERE ' . $where;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);

        $totalVenues = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $sql = 'SELECT * FROM venues WHERE ' . $where . $orderBy . ' LIMIT ? OFFSET ?';
        $stmt = $pdo->prepare($sql);

        $position = 1;
        foreach ($params as $value) {
            $stmt->bindValue($position++, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue($position++, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue($position, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $totalVenues = (int) $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn();
        $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $stmt = $pdo->prepare('SELECT * FROM venues' . $orderBy . ' LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    $venues = $stmt->fetchAll();

    return [
        'venues' => $venues,
        'totalVenues' => $totalVenues,
        'totalPages' => $totalPages,
        'page' => $page
    ];
}

function fetchVenueById(int $venueId): ?array
{
    if ($venueId <= 0) {
        return null;
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('SELECT * FROM venues WHERE id = :id');
    $stmt->execute([':id' => $venueId]);
    $venue = $stmt->fetch();

    return $venue ?: null;
}

function findVenueNearCoordinates(PDO $pdo, float $latitude, float $longitude, float $radiusMeters = 100.0): ?array
{
    $earthRadius = 6371000.0;
    $latDelta = rad2deg($radiusMeters / $earthRadius);
    $lngDelta = $latDelta / max(cos(deg2rad($latitude)), 0.00001);

    $minLat = $latitude - $latDelta;
    $maxLat = $latitude + $latDelta;
    $minLng = $longitude - $lngDelta;
    $maxLng = $longitude + $lngDelta;

    $query = 'SELECT id, name, latitude, longitude,
                 (6371000 * ACOS(
                   COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) *
                   COS(RADIANS(longitude) - RADIANS(:lng1)) +
                   SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
                 )) AS distance
              FROM venues
              WHERE latitude BETWEEN :min_lat AND :max_lat
                AND longitude BETWEEN :min_lng AND :max_lng
              HAVING distance <= :radius
              ORDER BY distance
              LIMIT 1';

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':lat1', $latitude);
    $stmt->bindValue(':lat2', $latitude);
    $stmt->bindValue(':lng1', $longitude);
    $stmt->bindValue(':min_lat', $minLat);
    $stmt->bindValue(':max_lat', $maxLat);
    $stmt->bindValue(':min_lng', $minLng);
    $stmt->bindValue(':max_lng', $maxLng);
    $stmt->bindValue(':radius', $radiusMeters);
    $stmt->execute();
    $row = $stmt->fetch();

    return $row ?: null;
}
