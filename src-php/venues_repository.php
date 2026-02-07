<?php

require_once __DIR__ . '/database.php';

function fetchVenuesWithPagination(string $filter, int $page, int $pageSize): array
{
    $pdo = getDatabaseConnection();

    if ($filter !== '') {
        $searchTerms = preg_split('/\s+/', $filter) ?: [];
        $searchTokens = [];
        foreach ($searchTerms as $term) {
            $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', $term);
            if ($normalized === '') {
                continue;
            }
            if (mb_strlen($normalized) < 4) {
                continue;
            }
            $searchTokens[] = '+' . $normalized . '*';
        }

        if ($searchTokens === []) {
            $filterParam = '%' . $filter . '%';
            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM venues
                WHERE name LIKE ?
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
                   OR notes LIKE ?'
            );
            $countStmt->execute(array_fill(0, 12, $filterParam));
            $totalVenues = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;

            $stmt = $pdo->prepare(
                'SELECT * FROM venues
                WHERE name LIKE ?
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
                   OR notes LIKE ?
                ORDER BY name
                LIMIT ? OFFSET ?'
            );
            $params = array_fill(0, 12, $filterParam);
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(13, $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(14, $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $searchQuery = implode(' ', $searchTokens);
            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM venues
                WHERE MATCH(name, address, city, state, contact_email, contact_phone, contact_person, website, notes)
                AGAINST (? IN BOOLEAN MODE)'
            );
            $countStmt->execute([$searchQuery]);
            $totalVenues = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $pageSize;

            $stmt = $pdo->prepare(
                'SELECT * FROM venues
                WHERE MATCH(name, address, city, state, contact_email, contact_phone, contact_person, website, notes)
                AGAINST (? IN BOOLEAN MODE)
                ORDER BY name
                LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $searchQuery, PDO::PARAM_STR);
            $stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
        }
    } else {
        $totalVenues = (int) $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn();
        $totalPages = max(1, (int) ceil($totalVenues / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $stmt = $pdo->prepare('SELECT * FROM venues ORDER BY name LIMIT ? OFFSET ?');
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
