<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/security_headers.php';

setApiSecurityHeaders();
header('Content-Type: application/json');

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $limit = 20;

    if (mb_strlen($query) < 4) {
        $likeQuery = '%' . $query . '%';
        $stmt = $pdo->prepare(
            'SELECT id, name, latitude, longitude, website
             FROM venues
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
               AND (name LIKE :query
                    OR address LIKE :query
                    OR city LIKE :query
                    OR contact_person LIKE :query)
             ORDER BY name
             LIMIT :limit'
        );
        $stmt->bindValue(':query', $likeQuery, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $terms = preg_split('/\s+/', $query) ?: [];
        $tokens = [];
        foreach ($terms as $term) {
            $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', $term);
            if ($normalized === '' || mb_strlen($normalized) < 4) {
                continue;
            }
            $tokens[] = '+' . $normalized . '*';
        }
        $searchQuery = $tokens ? implode(' ', $tokens) : $query;

        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, latitude, longitude, website
                 FROM venues
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                   AND MATCH(name, address, city, state, contact_email, contact_phone, contact_person, website, notes)
                       AGAINST (:query IN BOOLEAN MODE)
                 ORDER BY name
                 LIMIT :limit'
            );
            $stmt->bindValue(':query', $searchQuery, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $error) {
            $likeQuery = '%' . $query . '%';
            $stmt = $pdo->prepare(
                'SELECT id, name, latitude, longitude, website
                 FROM venues
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                   AND (name LIKE :query
                        OR address LIKE :query
                        OR city LIKE :query
                        OR contact_person LIKE :query)
                 ORDER BY name
                 LIMIT :limit'
            );
            $stmt->bindValue(':query', $likeQuery, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    $results = $stmt->fetchAll();
    $payload = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'lat' => (float) $row['latitude'],
            'lng' => (float) $row['longitude'],
            'website' => $row['website'] !== null ? (string) $row['website'] : ''
        ];
    }, $results);

    echo json_encode($payload);
} catch (Throwable $error) {
    http_response_code(500);
    logAction($currentUser['user_id'] ?? null, 'venue_search_error', $error->getMessage());
    echo json_encode([]);
}
