<?php
require_once __DIR__ . '/../../models/admin/users.php';

/**
 * Admin users tab controller.
 * Expects: $currentUser, &$errors, &$notice
 * Provides: $users, $teamsByUser, $editUserId, $editUser
 */

$editUserId = isset($_GET['edit_user_id']) ? (int) $_GET['edit_user_id'] : 0;
$editUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = adminCreateUser(
            $currentUser,
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['role'] ?? 'agent')
        );
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
    }

    if ($action === 'update_user') {
        $result = adminUpdateUser(
            $currentUser,
            (int) ($_POST['user_id'] ?? 0),
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['role'] ?? ''),
            (string) ($_POST['display_name'] ?? '')
        );
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
        if (!empty($result['resetEditUserId'])) {
            $editUserId = 0;
        }
    }

    if ($action === 'delete') {
        $result = adminDeleteUser($currentUser, (int) ($_POST['user_id'] ?? 0));
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
    }
}

try {
    $usersData = fetchAdminUsersData($editUserId);
    $users = $usersData['users'];
    $teamsByUser = $usersData['teamsByUser'];
    $editUser = $usersData['editUser'];

    if ($editUserId > 0 && empty($usersData['editUserFound'])) {
        $errors[] = 'User not found.';
        $editUserId = 0;
    }
} catch (Throwable $error) {
    $users = $users ?? [];
    $teamsByUser = $teamsByUser ?? [];
    $errors[] = 'Failed to load users.';
    logAction($currentUser['user_id'] ?? null, 'admin_users_load_error', $error->getMessage());
}
