<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$notice = '';
$settings = [
    'brave_search_api_key' => '',
    'brave_spellcheck_api_key' => '',
    'mapbox_api_key' => ''
];
$settingsStatus = [
    'brave_search_api_key' => false,
    'brave_spellcheck_api_key' => false,
    'mapbox_api_key' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['brave_search_api_key'] = trim((string) ($_POST['brave_search_api_key'] ?? ''));
    $settings['brave_spellcheck_api_key'] = trim((string) ($_POST['brave_spellcheck_api_key'] ?? ''));
    $settings['mapbox_api_key'] = trim((string) ($_POST['mapbox_api_key'] ?? ''));

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value)
             VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($settings as $key => $value) {
            if ($value === '') {
                continue;
            }

            $stmt->execute([
                ':setting_key' => $key,
                ':setting_value' => encryptSettingValue($value)
            ]);
        }

        logAction($currentUser['user_id'] ?? null, 'settings_updated', 'Updated API keys');
        $notice = 'API keys saved successfully.';
    } catch (Throwable $error) {
        $errors[] = 'Failed to save settings.';
        logAction($currentUser['user_id'] ?? null, 'settings_update_error', $error->getMessage());
    }
}

try {
    $pdo = getDatabaseConnection();
    $placeholders = implode(',', array_fill(0, count($settings), '?'));
    $stmt = $pdo->prepare(
        sprintf('SELECT setting_key, setting_value FROM settings WHERE setting_key IN (%s)', $placeholders)
    );
    $stmt->execute(array_keys($settings));
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $key = (string) $row['setting_key'];
        if (array_key_exists($key, $settings)) {
            $settingsStatus[$key] = !empty($row['setting_value']);
        }
    }
} catch (Throwable $error) {
    $errors[] = 'Failed to load settings.';
    logAction($currentUser['user_id'] ?? null, 'settings_load_error', $error->getMessage());
}

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

            <?php if ($notice): ?>
              <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
              <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>

            <form method="POST" action="">
              <div class="form-group">
                <label for="brave_search_api_key">Brave Search</label>
                <input
                  type="password"
                  id="brave_search_api_key"
                  name="brave_search_api_key"
                  class="input"
                  placeholder="<?php echo $settingsStatus['brave_search_api_key'] ? 'Saved' : 'Not set'; ?>"
                >
              </div>
              <div class="form-group">
                <label for="brave_spellcheck_api_key">Brave Spellcheck</label>
                <input
                  type="password"
                  id="brave_spellcheck_api_key"
                  name="brave_spellcheck_api_key"
                  class="input"
                  placeholder="<?php echo $settingsStatus['brave_spellcheck_api_key'] ? 'Saved' : 'Not set'; ?>"
                >
              </div>
              <div class="form-group">
                <label for="mapbox_api_key">Mapbox</label>
                <input
                  type="password"
                  id="mapbox_api_key"
                  name="mapbox_api_key"
                  class="input"
                  placeholder="<?php echo $settingsStatus['mapbox_api_key'] ? 'Saved' : 'Not set'; ?>"
                >
              </div>
              <button type="submit" class="btn">Save API Keys</button>
            </form>
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
