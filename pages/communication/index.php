<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$activeTab = $_GET['tab'] ?? 'conversations';

logAction($currentUser['user_id'] ?? null, 'view_communication', 'User opened communication page');
?>
<?php renderPageStart('Communication', [
    'theme' => getCurrentTheme($currentUser['ui_theme'] ?? null),
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/public/js/tabs.js" defer></script>'
    ]
]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Communication</h1>
        </div>

        <div class="tabs" role="tablist">
          <button type="button" class="tab-button <?php echo $activeTab === 'conversations' ? 'active' : ''; ?>" data-tab="conversations" role="tab" aria-selected="<?php echo $activeTab === 'conversations' ? 'true' : 'false'; ?>">Conversations</button>
          <button type="button" class="tab-button <?php echo $activeTab === 'email' ? 'active' : ''; ?>" data-tab="email" role="tab" aria-selected="<?php echo $activeTab === 'email' ? 'true' : 'false'; ?>">eMail</button>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'conversations' ? 'active' : ''; ?>" data-tab-panel="conversations" role="tabpanel">
          <?php require __DIR__ . '/conversations.php'; ?>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'email' ? 'active' : ''; ?>" data-tab-panel="email" role="tabpanel">
          <?php require __DIR__ . '/email.php'; ?>
        </div>
      </div>
<?php renderPageEnd(); ?>
