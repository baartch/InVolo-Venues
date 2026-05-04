<?php
require_once __DIR__ . '/../../models/admin/teams.php';

/**
 * Admin teams tab controller.
 * Expects: $currentUser, &$errors, &$notice
 * Provides: $teams, $users, $memberIdsByTeam, $adminIdsByTeam
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_team') {
        $result = adminCreateTeam(
            $currentUser,
            (string) ($_POST['team_name'] ?? ''),
            (string) ($_POST['team_description'] ?? '')
        );
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
    }

    if ($action === 'update_team') {
        $result = adminUpdateTeam(
            $currentUser,
            (int) ($_POST['team_id'] ?? 0),
            (string) ($_POST['team_name'] ?? ''),
            (string) ($_POST['team_description'] ?? ''),
            is_array($_POST['team_member_ids'] ?? null) ? (array) $_POST['team_member_ids'] : [],
            is_array($_POST['team_admin_ids'] ?? null) ? (array) $_POST['team_admin_ids'] : []
        );
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
    }

    if ($action === 'delete_team') {
        $result = adminDeleteTeam($currentUser, (int) ($_POST['team_id'] ?? 0));
        $errors = array_merge($errors, $result['errors'] ?? []);
        if (!empty($result['notice'])) {
            $notice = (string) $result['notice'];
        }
    }
}

try {
    $teamsData = fetchAdminTeamsData();
    $teams = $teamsData['teams'];
    $users = $teamsData['users'];
    $memberIdsByTeam = $teamsData['memberIdsByTeam'];
    $adminIdsByTeam = $teamsData['adminIdsByTeam'];
} catch (Throwable $error) {
    $teams = $teams ?? [];
    $users = $users ?? [];
    $memberIdsByTeam = $memberIdsByTeam ?? [];
    $adminIdsByTeam = $adminIdsByTeam ?? [];
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'admin_teams_load_error', $error->getMessage());
}
