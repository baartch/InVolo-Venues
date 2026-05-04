<?php
require_once __DIR__ . '/../../models/admin/logs.php';

/**
 * Admin logs tab controller.
 * Expects: $currentUser, &$errors, &$notice
 * Provides: $logsRows, $logsPagination
 */

$logsPage = isset($_GET['logs_page']) ? max(1, (int) $_GET['logs_page']) : 1;
$logsPerPage = isset($_GET['logs_per_page']) ? max(10, min(500, (int) $_GET['logs_per_page'])) : 100;
$logsQuery = isset($_GET['logs_q']) ? trim((string) $_GET['logs_q']) : '';

try {
    $logsResult = fetchAdminLogsPage($logsPage, $logsPerPage, $logsQuery);
    $logsRows = $logsResult['rows'];
    $logsPagination = $logsResult['pagination'];
} catch (Throwable $error) {
    $logsRows = [];
    $logsPagination = [
        'page' => $logsPage,
        'perPage' => $logsPerPage,
        'total' => 0,
        'totalPages' => 1,
        'query' => $logsQuery
    ];
    $errors[] = 'Failed to load logs.';
    logAction($currentUser['user_id'] ?? null, 'admin_logs_load_error', $error->getMessage());
}
