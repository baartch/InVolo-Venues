<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';

$errors = [];
$notice = '';
$defaultPageSize = 25;
$minPageSize = 25;
$maxPageSize = 500;
$currentPageSize = (int) ($currentUser['venues_page_size'] ?? $defaultPageSize);
$currentPageSize = max($minPageSize, min($maxPageSize, $currentPageSize));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $action = $_POST['action'] ?? '';

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
<?php renderPageStart('Profile', ['bodyClass' => 'is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Profile</h1>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <div class="columns is-multiline">
            <div class="column is-12">
              <div class="box">
                <h2 class="title is-5">Reset Password</h2>
                <form method="POST" action="" class="columns is-multiline">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="update_password">
                  <div class="column is-4">
                    <div class="field">
                      <label for="current_password" class="label">Current Password</label>
                      <div class="control">
                        <input type="password" id="current_password" name="current_password" class="input" required>
                      </div>
                    </div>
                  </div>
                  <div class="column is-4">
                    <div class="field">
                      <label for="new_password" class="label">New Password</label>
                      <div class="control">
                        <input type="password" id="new_password" name="new_password" class="input" required>
                      </div>
                    </div>
                  </div>
                  <div class="column is-4">
                    <div class="field">
                      <label for="confirm_password" class="label">Confirm New Password</label>
                      <div class="control">
                        <input type="password" id="confirm_password" name="confirm_password" class="input" required>
                      </div>
                    </div>
                  </div>
                  <div class="column is-12">
                    <button type="submit" class="button is-primary">Update Password</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="column is-12">
              <div class="box">
                <h2 class="title is-5">Venues List</h2>
                <form method="POST" action="" class="columns is-multiline">
                  <?php renderCsrfField(); ?>
                  <input type="hidden" name="action" value="update_page_size">
                  <div class="column is-4">
                    <div class="field">
                      <label for="venues_page_size" class="label">Venues per page (25-500)</label>
                      <div class="control">
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
                    </div>
                  </div>
                  <div class="column is-12">
                    <button type="submit" class="button is-primary">Update Page Size</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

        </div>
      </section>
<?php renderPageEnd(); ?>
