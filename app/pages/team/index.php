<?php
require_once __DIR__ . '/../../src-php/auth/team_admin_check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';

$activeTab = $_GET['tab'] ?? 'members';
$validTabs = ['members', 'mailboxes', 'templates'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'members';
}

logAction($currentUser['user_id'] ?? null, 'view_team', 'User opened team page');
?>
<?php renderPageStart('Team', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/mailboxes.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Team</h1>
            </div>
          </div>

          <div class="tabs is-boxed" role="tablist">
            <ul>
              <li class="<?php echo $activeTab === 'members' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="members" role="tab" aria-selected="<?php echo $activeTab === 'members' ? 'true' : 'false'; ?>">Members</a>
              </li>
              <li class="<?php echo $activeTab === 'mailboxes' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="mailboxes" role="tab" aria-selected="<?php echo $activeTab === 'mailboxes' ? 'true' : 'false'; ?>">Mailboxes</a>
              </li>
              <li class="<?php echo $activeTab === 'templates' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="templates" role="tab" aria-selected="<?php echo $activeTab === 'templates' ? 'true' : 'false'; ?>">Templates</a>
              </li>
            </ul>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'members' ? '' : 'is-hidden'; ?>" data-tab-panel="members" role="tabpanel">
            <div class="box">
              <h2 class="title is-5">Team Members</h2>
              <p>Team management will be available here soon.</p>
            </div>
          </div>

          <?php require __DIR__ . '/mailboxes.php'; ?>
          <?php require __DIR__ . '/templates.php'; ?>
        </div>
      </section>
<?php renderPageEnd(); ?>
