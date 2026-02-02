<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

$errors = [];
$notice = '';
$activeTab = $_GET['tab'] ?? 'account';
$themeOptions = ['forest' => 'Forest', 'dracula' => 'Dracula'];
$currentTheme = getCurrentTheme($currentUser['ui_theme'] ?? null, array_keys($themeOptions));
$defaultPageSize = 25;
$minPageSize = 25;
$maxPageSize = 500;
$currentPageSize = (int) ($currentUser['venues_page_size'] ?? $defaultPageSize);
$currentPageSize = max($minPageSize, min($maxPageSize, $currentPageSize));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $action = $_POST['action'] ?? '';
    $activeTab = $_POST['tab'] ?? $activeTab;

    if ($action === 'update_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'All password fields are required.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $currentUser['user_id']]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $stmt->execute([
                        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        ':id' => $currentUser['user_id']
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'profile_password_reset', 'User updated their password');
                    $notice = 'Password updated successfully.';
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to update password.';
                logAction($currentUser['user_id'] ?? null, 'profile_password_error', $error->getMessage());
            }
        }
    }

    if ($action === 'update_theme') {
        $selectedTheme = (string) ($_POST['theme'] ?? 'forest');
        if (!array_key_exists($selectedTheme, $themeOptions)) {
            $errors[] = 'Invalid theme selected.';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('UPDATE users SET ui_theme = :ui_theme WHERE id = :id');
                $stmt->execute([
                    ':ui_theme' => $selectedTheme,
                    ':id' => $currentUser['user_id']
                ]);
                $currentTheme = $selectedTheme;
                $currentUser['ui_theme'] = $selectedTheme;
                logAction($currentUser['user_id'] ?? null, 'profile_theme_updated', sprintf('Theme set to %s', $selectedTheme));
                $notice = 'Theme updated successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to update theme.';
                logAction($currentUser['user_id'] ?? null, 'profile_theme_error', $error->getMessage());
            }
        }
    }

    if ($action === 'update_page_size') {
        $requestedPageSize = (int) ($_POST['venues_page_size'] ?? $defaultPageSize);
        $requestedPageSize = max($minPageSize, min($maxPageSize, $requestedPageSize));

        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare('UPDATE users SET venues_page_size = :venues_page_size WHERE id = :id');
            $stmt->execute([
                ':venues_page_size' => $requestedPageSize,
                ':id' => $currentUser['user_id']
            ]);
            $currentPageSize = $requestedPageSize;
            $currentUser['venues_page_size'] = $requestedPageSize;
            logAction($currentUser['user_id'] ?? null, 'profile_page_size_updated', sprintf('Page size set to %d', $requestedPageSize));
            $notice = 'Page size updated successfully.';
        } catch (Throwable $error) {
            $errors[] = 'Failed to update page size.';
            logAction($currentUser['user_id'] ?? null, 'profile_page_size_error', $error->getMessage());
        }
    }
}

logAction($currentUser['user_id'] ?? null, 'view_profile', 'User opened profile');
?>
<?php renderPageStart('Venue Database - Profile', [
    'theme' => $currentTheme,
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/public/js/tabs.js" defer></script>'
    ]
]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Profile</h1>
        </div>

        <?php if ($notice): ?>
          <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <div class="tabs" role="tablist">
          <button type="button" class="tab-button <?php echo $activeTab === 'account' ? 'active' : ''; ?>" data-tab="account" role="tab" aria-selected="<?php echo $activeTab === 'account' ? 'true' : 'false'; ?>">Account</button>
          <button type="button" class="tab-button <?php echo $activeTab === 'appearance' ? 'active' : ''; ?>" data-tab="appearance" role="tab" aria-selected="<?php echo $activeTab === 'appearance' ? 'true' : 'false'; ?>">Appearance</button>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'account' ? 'active' : ''; ?>" data-tab-panel="account" role="tabpanel">
          <div class="card card-section">
            <h2>Reset Password</h2>
            <form method="POST" action="" class="create-user-form">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="update_password">
              <input type="hidden" name="tab" value="account">
              <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="input" required>
              </div>
              <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="input" required>
              </div>
              <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="input" required>
              </div>
              <button type="submit" class="btn">Update Password</button>
            </form>
          </div>
        </div>

        <div class="tab-panel <?php echo $activeTab === 'appearance' ? 'active' : ''; ?>" data-tab-panel="appearance" role="tabpanel">
          <div class="card card-section profile-theme-card">
            <h2>Theme</h2>
            <form method="POST" action="" class="create-user-form">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="update_theme">
              <input type="hidden" name="tab" value="appearance">
              <div class="form-group">
                <label for="theme">Color Theme</label>
                <select id="theme" name="theme" class="input">
                  <?php foreach ($themeOptions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $currentTheme === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn">Update Theme</button>
            </form>
          </div>

          <div class="card card-section">
            <h2>Venues List</h2>
            <form method="POST" action="" class="create-user-form">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="update_page_size">
              <input type="hidden" name="tab" value="appearance">
              <div class="form-group">
                <label for="venues_page_size">Venues per page (25-500)</label>
                <input
                  type="number"
                  id="venues_page_size"
                  name="venues_page_size"
                  class="input"
                  min="<?php echo (int) $minPageSize; ?>"
                  max="<?php echo (int) $maxPageSize; ?>"
                  value="<?php echo (int) $currentPageSize; ?>"
                  required
                >
              </div>
              <button type="submit" class="btn">Update Page Size</button>
            </form>
          </div>
        </div>
      </div>
<?php renderPageEnd(); ?>
