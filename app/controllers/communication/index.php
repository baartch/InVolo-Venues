<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/htmx_class.php';
require_once __DIR__ . '/../../views/core/layout.php';

$activeTab = $_GET['tab'] ?? 'conversations';

if (HTMX::isRequest()) {
    if ($activeTab === 'contacts') {
        require __DIR__ . '/../../views/communication/contacts.php';
        return;
    }
    if ($activeTab === 'email') {
        require __DIR__ . '/../../views/communication/email.php';
        return;
    }
}

?>
<?php renderPageStart('Communication', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraStyles' => [
        BASE_PATH . '/app/public/css/communication.css',
        BASE_PATH . '/app/public/css/list-layout.css',
        BASE_PATH . '/app/public/css/wysi.min.css'
    ],
    'extraScripts' => [
        '<script src="' . BASE_PATH . '/app/public/vendor/wysi/wysi.min.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/email.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/list-panel.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/link-editor.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Communication</h1>
            </div>
          </div>

          <div class="tabs is-boxed" role="tablist">
            <ul>
              <li class="<?php echo $activeTab === 'conversations' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="conversations" role="tab" aria-selected="<?php echo $activeTab === 'conversations' ? 'true' : 'false'; ?>">Conversations</a>
              </li>
              <li class="<?php echo $activeTab === 'email' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="email" role="tab" aria-selected="<?php echo $activeTab === 'email' ? 'true' : 'false'; ?>">eMail</a>
              </li>
              <li class="<?php echo $activeTab === 'contacts' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="contacts" role="tab" aria-selected="<?php echo $activeTab === 'contacts' ? 'true' : 'false'; ?>">Contacts</a>
              </li>
            </ul>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'conversations' ? '' : 'is-hidden'; ?>" data-tab-panel="conversations" role="tabpanel">
            <?php require __DIR__ . '/../../views/communication/conversations.php'; ?>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'email' ? '' : 'is-hidden'; ?>" data-tab-panel="email" role="tabpanel">
            <?php require __DIR__ . '/../../views/communication/email.php'; ?>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'contacts' ? '' : 'is-hidden'; ?>" data-tab-panel="contacts" role="tabpanel">
            <?php require __DIR__ . '/../../views/communication/contacts.php'; ?>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
