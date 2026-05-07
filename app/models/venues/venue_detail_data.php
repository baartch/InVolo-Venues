<?php
require_once __DIR__ . '/venue_ratings.php';
require_once __DIR__ . '/venue_task_triggers.php';
require_once __DIR__ . '/../core/link_helpers.php';

/**
 * @return array{selectedVenueRating:mixed,venueTaskTriggers:array,venueLinks:array,triggerNotice:string}
 */
function buildVenueDetailData(PDO $pdo, int $venueId, int $activeTeamId, int $userId, string $noticeKey = ''): array
{
    $selectedVenueRating = null;
    $venueTaskTriggers = [];
    $venueLinks = [];
    $triggerNotice = '';

    if ($noticeKey === 'trigger_created') {
        $triggerNotice = 'Trigger created successfully.';
    } elseif ($noticeKey === 'trigger_updated') {
        $triggerNotice = 'Trigger updated successfully.';
    } elseif ($noticeKey === 'trigger_deleted') {
        $triggerNotice = 'Trigger deleted successfully.';
    } elseif ($noticeKey === 'trigger_error') {
        $triggerNotice = 'Failed to save trigger.';
    }

    if ($activeTeamId > 0 && $venueId > 0) {
        $selectedVenueRating = fetchVenueRatingForTeam($pdo, $venueId, $activeTeamId);
        $venueTaskTriggers = fetchVenueTaskTriggers($pdo, $venueId, $activeTeamId);
        $venueLinks = fetchLinkedObjects($pdo, 'venue', $venueId, $activeTeamId, $userId);
    }

    return [
        'selectedVenueRating' => $selectedVenueRating,
        'venueTaskTriggers' => $venueTaskTriggers,
        'venueLinks' => $venueLinks,
        'triggerNotice' => $triggerNotice,
    ];
}
