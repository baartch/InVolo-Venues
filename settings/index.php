<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

logAction($currentUser['user_id'] ?? null, 'view_settings', 'User opened app settings');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - Settings</title>
  <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/public/styles.css">
  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }

    .content-wrapper {
      padding: 32px;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }

    .page-header h1 {
      font-size: 24px;
      color: var(--color-primary-dark);
    }

    .tabs {
      display: flex;
      gap: 16px;
      border-bottom: 2px solid var(--color-border);
      margin-bottom: 24px;
    }

    .tabs .tab-button {
      background: none;
      border: none;
      border-radius: 0;
      box-shadow: none;
      font-size: 15px;
      font-weight: 600;
      color: var(--color-muted);
      padding: 12px 4px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }

    .tabs .tab-button:hover,
    .tabs .tab-button:active {
      transform: none;
    }

    .tabs .tab-button.active {
      color: var(--color-primary-dark);
      border-bottom-color: var(--color-primary);
    }

    .tab-panel {
      display: none;
    }

    .tab-panel.active {
      display: block;
    }

    .panel-header {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 16px;
    }

    .panel-header h2 {
      font-size: 20px;
      color: var(--color-primary-dark);
    }
  </style>
</head>
<body class="map-page">
  <div class="app-layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main-content">
      <div class="content-wrapper">
        <div class="page-header">
          <h1>App Settings</h1>
        </div>

        <div class="tabs" role="tablist">
          <button type="button" class="tab-button active" data-tab="api-keys" role="tab" aria-selected="true">API Keys</button>
        </div>

        <div class="tab-panel active" data-tab-panel="api-keys" role="tabpanel">
          <div class="card card-section">
            <div class="panel-header">
              <h2>API Keys</h2>
              <p class="text-muted">Store tokens used for map tiles and integrations.</p>
            </div>
            <p class="text-muted">Add API key management here.</p>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script>
    (function () {
      const tabs = Array.from(document.querySelectorAll('[data-tab]'));
      const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));

      tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
          const target = tab.getAttribute('data-tab');
          tabs.forEach((button) => {
            button.classList.toggle('active', button === tab);
            button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
          });
          panels.forEach((panel) => {
            panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === target);
          });
        });
      });
    })();
  </script>
</body>
</html>
