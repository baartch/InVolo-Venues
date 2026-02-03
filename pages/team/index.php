<?php
require_once __DIR__ . '/../../src-php/team_admin_check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$activeTab = $_GET['tab'] ?? 'members';
$validTabs = ['members', 'mailboxes'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'members';
}

logAction($currentUser['user_id'] ?? null, 'view_team', 'User opened team page');
?>
<?php renderPageStart('Venue Database - Team', [
    'theme' => getCurrentTheme($currentUser['ui_theme'] ?? null),
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/public/js/mailboxes.js" defer></script>'
    ]
]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Team</h1>
        </div>

        <div class="tabs" role="tablist">
          <button type="button" class="tab-button <?php echo $activeTab === 'members' ? 'active' : ''; ?>" data-tab="members" role="tab" aria-selected="<?php echo $activeTab === 'members' ? 'true' : 'false'; ?>">Members</button>
          <button type="button" class="tab-button <?php echo $activeTab === 'mailboxes' ? 'active' : ''; ?>" data-tab="mailboxes" role="tab" aria-selected="<?php echo $activeTab === 'mailboxes' ? 'true' : 'false'; ?>">Mailboxes</button>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'members' ? 'active' : ''; ?>" data-tab-panel="members" role="tabpanel">
          <div class="card card-section">
            <h2>Team Members</h2>
            <p>Team management will be available here soon.</p>
          </div>
        </div>

        <?php require __DIR__ . '/mailboxes.php'; ?>
      </div>
<?php renderPageEnd(); ?>
