<?php
require_once __DIR__ . '/../core/database.php';

/**
 * @return array{rows:array,pagination:array{page:int,perPage:int,total:int,totalPages:int,query:string}}
 */
function fetchAdminLogsPage(int $page, int $perPage, string $query = ''): array
{
    $page = max(1, $page);
    $perPage = max(10, min(500, $perPage));
    $query = trim($query);

    $pdo = getDatabaseConnection();

    $offset = ($page - 1) * $perPage;
    $params = [];
    $where = '';

    if ($query !== '') {
        $where = 'WHERE (u.username LIKE :q OR l.action LIKE :q OR l.details LIKE :q)';
        $params[':q'] = '%' . $query . '%';
    }

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM logs l
         LEFT JOIN users u ON u.id = l.user_id
         ' . $where
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $listStmt = $pdo->prepare(
        'SELECT l.id, l.created_at AS timestamp, u.username, l.action, l.details
         FROM logs l
         LEFT JOIN users u ON u.id = l.user_id
         ' . $where . '
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT :limit OFFSET :offset'
    );

    foreach ($params as $key => $value) {
        $listStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();

    $rows = $listStmt->fetchAll();
    $totalPages = max(1, (int) ceil($total / $perPage));

    return [
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'query' => $query,
        ],
    ];
}
