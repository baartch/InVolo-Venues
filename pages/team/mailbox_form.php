<?php
require_once __DIR__ . '/../../src-php/team_admin_check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';
require_once __DIR__ . '/../../src-php/mailbox_helpers.php';

$errors = [];
$notice = '';
$editMailbox = null;
$editMailboxId = isset($_GET['edit_mailbox_id']) ? (int) $_GET['edit_mailbox_id'] : 0;
if ($editMailboxId <= 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $editMailboxId = (int) ($_POST['mailbox_id'] ?? 0);
}
$allowedEncryptions = ['ssl', 'tls', 'none'];
$defaultImapPort = 993;
$defaultSmtpPort = 587;

$formValues = [
    'team_id' => 0,
    'name' => '',
    'imap_host' => '',
    'imap_port' => $defaultImapPort,
    'imap_username' => '',
    'imap_encryption' => 'ssl',
    'smtp_host' => '',
    'smtp_port' => $defaultSmtpPort,
    'smtp_username' => '',
    'smtp_encryption' => 'tls',
    'delete_after_retrieve' => false,
    'store_sent_on_server' => false
];

try {
    $pdo = getDatabaseConnection();
    [$teams, $teamIds] = loadTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
} catch (Throwable $error) {
    $teams = [];
    $teamIds = [];
    $errors[] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_mailbox_team_load_error', $error->getMessage());
    $pdo = null;
}

if ($editMailboxId > 0 && $pdo) {
    try {
        $editMailbox = fetchTeamMailbox($pdo, $editMailboxId, (int) ($currentUser['user_id'] ?? 0));
        if (!$editMailbox) {
            $errors[] = 'Mailbox not found.';
            $editMailboxId = 0;
        } else {
            $formValues = [
                'team_id' => (int) ($editMailbox['team_id'] ?? 0),
                'name' => (string) ($editMailbox['name'] ?? ''),
                'imap_host' => (string) ($editMailbox['imap_host'] ?? ''),
                'imap_port' => (int) ($editMailbox['imap_port'] ?? $defaultImapPort),
                'imap_username' => (string) ($editMailbox['imap_username'] ?? ''),
                'imap_encryption' => (string) ($editMailbox['imap_encryption'] ?? 'ssl'),
                'smtp_host' => (string) ($editMailbox['smtp_host'] ?? ''),
                'smtp_port' => (int) ($editMailbox['smtp_port'] ?? $defaultSmtpPort),
                'smtp_username' => (string) ($editMailbox['smtp_username'] ?? ''),
                'smtp_encryption' => (string) ($editMailbox['smtp_encryption'] ?? 'tls'),
                'delete_after_retrieve' => (bool) ($editMailbox['delete_after_retrieve'] ?? false),
                'store_sent_on_server' => (bool) ($editMailbox['store_sent_on_server'] ?? false)
            ];
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load mailbox.';
        logAction($currentUser['user_id'] ?? null, 'team_mailbox_load_error', $error->getMessage());
        $editMailboxId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (!$pdo) {
        $errors[] = 'Database connection unavailable.';
    } else {
        $action = $_POST['action'] ?? '';
        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId <= 0 && count($teamIds) === 1) {
            $teamId = $teamIds[0];
        }

        $useSameCredentials = ($_POST['use_same_credentials'] ?? '') === '1';
        $mailboxName = trim((string) ($_POST['name'] ?? ''));
        $imapHost = trim((string) ($_POST['imap_host'] ?? ''));
        $imapPort = (int) ($_POST['imap_port'] ?? $defaultImapPort);
        $imapUsername = trim((string) ($_POST['imap_username'] ?? ''));
        $imapPassword = trim((string) ($_POST['imap_password'] ?? ''));
        $imapEncryption = (string) ($_POST['imap_encryption'] ?? 'ssl');
        $deleteAfterRetrieve = ($_POST['delete_after_retrieve'] ?? '') === '1';
        $storeSentOnServer = ($_POST['store_sent_on_server'] ?? '') === '1';
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = (int) ($_POST['smtp_port'] ?? $defaultSmtpPort);
        $smtpUsername = trim((string) ($_POST['smtp_username'] ?? ''));
        $smtpPassword = trim((string) ($_POST['smtp_password'] ?? ''));
        $smtpEncryption = (string) ($_POST['smtp_encryption'] ?? 'tls');

        if ($useSameCredentials) {
            if ($imapPassword !== '') {
                $smtpPassword = $imapPassword;
            }
            if ($imapUsername !== '') {
                $smtpUsername = $imapUsername;
            }
        }

        $formValues = [
            'team_id' => $teamId,
            'name' => $mailboxName,
            'imap_host' => $imapHost,
            'imap_port' => $imapPort,
            'imap_username' => $imapUsername,
            'imap_encryption' => $imapEncryption,
            'delete_after_retrieve' => $deleteAfterRetrieve,
            'store_sent_on_server' => $storeSentOnServer,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_username' => $smtpUsername,
            'smtp_encryption' => $smtpEncryption
        ];

        if ($teamId <= 0 || !in_array($teamId, $teamIds, true)) {
            $errors[] = 'Select a valid team.';
        }

        if ($mailboxName === '') {
            $errors[] = 'Mailbox name is required.';
        }

        if ($imapHost === '' || $imapUsername === '') {
            $errors[] = 'IMAP host and username are required.';
        }

        if ($smtpHost === '' || $smtpUsername === '') {
            $errors[] = 'SMTP host and username are required.';
        }

        if ($imapPort < 1 || $imapPort > 65535) {
            $errors[] = 'IMAP port must be between 1 and 65535.';
        }

        if ($smtpPort < 1 || $smtpPort > 65535) {
            $errors[] = 'SMTP port must be between 1 and 65535.';
        }

        if (!in_array($imapEncryption, $allowedEncryptions, true)) {
            $errors[] = 'Select a valid IMAP encryption setting.';
        }

        if (!in_array($smtpEncryption, $allowedEncryptions, true)) {
            $errors[] = 'Select a valid SMTP encryption setting.';
        }

        if ($action === 'create_mailbox' && $imapPassword === '') {
            $errors[] = 'IMAP password is required when creating a mailbox.';
        }

        if ($action === 'create_mailbox' && $smtpPassword === '') {
            $errors[] = 'SMTP password is required when creating a mailbox.';
        }

        if (!$errors) {
            try {
                if ($action === 'create_mailbox') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO mailboxes
                         (team_id, name, imap_host, imap_port, imap_username, imap_password, imap_encryption,
                          delete_after_retrieve, store_sent_on_server,
                          smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption)
                         VALUES
                         (:team_id, :name, :imap_host, :imap_port, :imap_username, :imap_password, :imap_encryption,
                          :delete_after_retrieve, :store_sent_on_server,
                          :smtp_host, :smtp_port, :smtp_username, :smtp_password, :smtp_encryption)'
                    );
                    $stmt->execute([
                        ':team_id' => $teamId,
                        ':name' => $mailboxName,
                        ':imap_host' => $imapHost,
                        ':imap_port' => $imapPort,
                        ':imap_username' => $imapUsername,
                        ':imap_password' => encryptSettingValue($imapPassword),
                        ':imap_encryption' => $imapEncryption,
                        ':delete_after_retrieve' => $deleteAfterRetrieve ? 1 : 0,
                        ':store_sent_on_server' => $storeSentOnServer ? 1 : 0,
                        ':smtp_host' => $smtpHost,
                        ':smtp_port' => $smtpPort,
                        ':smtp_username' => $smtpUsername,
                        ':smtp_password' => encryptSettingValue($smtpPassword),
                        ':smtp_encryption' => $smtpEncryption
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_created', sprintf('Created mailbox %s', $mailboxName));
                    header('Location: ' . BASE_PATH . '/pages/team/index.php?tab=mailboxes&notice=mailbox_created');
                    exit;
                }

                $existingMailbox = fetchTeamMailbox($pdo, $editMailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors[] = 'Mailbox not found.';
                } else {
                    $imapPasswordValue = $imapPassword !== '' ? encryptSettingValue($imapPassword) : $existingMailbox['imap_password'];
                    $smtpPasswordValue = $smtpPassword !== '' ? encryptSettingValue($smtpPassword) : $existingMailbox['smtp_password'];
                    $stmt = $pdo->prepare(
                        'UPDATE mailboxes
                         SET name = :name,
                             imap_host = :imap_host,
                             imap_port = :imap_port,
                             imap_username = :imap_username,
                             imap_password = :imap_password,
                             imap_encryption = :imap_encryption,
                             delete_after_retrieve = :delete_after_retrieve,
                             store_sent_on_server = :store_sent_on_server,
                             smtp_host = :smtp_host,
                             smtp_port = :smtp_port,
                             smtp_username = :smtp_username,
                             smtp_password = :smtp_password,
                             smtp_encryption = :smtp_encryption
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':name' => $mailboxName,
                        ':imap_host' => $imapHost,
                        ':imap_port' => $imapPort,
                        ':imap_username' => $imapUsername,
                        ':imap_password' => $imapPasswordValue,
                        ':imap_encryption' => $imapEncryption,
                        ':delete_after_retrieve' => $deleteAfterRetrieve ? 1 : 0,
                        ':store_sent_on_server' => $storeSentOnServer ? 1 : 0,
                        ':smtp_host' => $smtpHost,
                        ':smtp_port' => $smtpPort,
                        ':smtp_username' => $smtpUsername,
                        ':smtp_password' => $smtpPasswordValue,
                        ':smtp_encryption' => $smtpEncryption,
                        ':id' => $existingMailbox['id']
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_updated', sprintf('Updated mailbox %d', $existingMailbox['id']));
                    header('Location: ' . BASE_PATH . '/pages/team/index.php?tab=mailboxes&notice=mailbox_updated');
                    exit;
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save mailbox.';
                logAction($currentUser['user_id'] ?? null, 'team_mailbox_save_error', $error->getMessage());
            }
        }
    }
}

if (!in_array($formValues['imap_encryption'], $allowedEncryptions, true)) {
    $formValues['imap_encryption'] = 'ssl';
}

if (!in_array($formValues['smtp_encryption'], $allowedEncryptions, true)) {
    $formValues['smtp_encryption'] = 'tls';
}

logAction($currentUser['user_id'] ?? null, 'view_team_mailbox_form', $editMailbox ? 'User opened edit mailbox form' : 'User opened create mailbox form');
?>
<?php renderPageStart('Mailbox', [
    'theme' => getCurrentTheme($currentUser['ui_theme'] ?? null),
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/public/js/mailboxes.js" defer></script>'
    ]
]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1><?php echo $editMailbox ? 'Edit Mailbox' : 'Add Mailbox'; ?></h1>
          <div class="page-header-actions">
            <a href="<?php echo BASE_PATH; ?>/pages/team/index.php?tab=mailboxes" class="btn">Back to Mailboxes</a>
          </div>
        </div>

        <?php if ($notice): ?>
          <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <section class="card card-section mailbox-form">
          <form method="POST" action="" class="create-user-form">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="action" value="<?php echo $editMailbox ? 'update_mailbox' : 'create_mailbox'; ?>">
            <?php if ($editMailbox): ?>
              <input type="hidden" name="mailbox_id" value="<?php echo (int) $editMailbox['id']; ?>">
              <input type="hidden" name="team_id" value="<?php echo (int) $editMailbox['team_id']; ?>">
            <?php elseif (count($teams) === 1): ?>
              <input type="hidden" name="team_id" value="<?php echo (int) $teams[0]['id']; ?>">
            <?php endif; ?>

            <?php if (count($teams) > 1 && !$editMailbox): ?>
              <div class="form-group">
                <label for="team_id">Team</label>
                <select id="team_id" name="team_id" class="input" required>
                  <option value="">Select a team</option>
                  <?php foreach ($teams as $team): ?>
                    <option value="<?php echo (int) $team['id']; ?>" <?php echo (int) $formValues['team_id'] === (int) $team['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($team['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label for="mailbox_name">Mailbox Name</label>
              <input type="text" id="mailbox_name" name="name" class="input" value="<?php echo htmlspecialchars($formValues['name']); ?>" required>
            </div>

            <div class="mailbox-section">
              <div class="mailbox-section-header">
                <h3>IMAP Settings</h3>
              </div>
              <div class="mailbox-grid">
                <div class="form-group">
                  <label for="imap_host">IMAP Host</label>
                  <input type="text" id="imap_host" name="imap_host" class="input" value="<?php echo htmlspecialchars($formValues['imap_host']); ?>" required>
                </div>

                <div class="form-group mailbox-field-compact">
                  <label for="imap_port">IMAP Port</label>
                  <input type="number" id="imap_port" name="imap_port" class="input" value="<?php echo (int) $formValues['imap_port']; ?>" min="1" max="65535" required>
                </div>

                <div class="form-group">
                  <label for="imap_username">IMAP Username</label>
                  <input type="text" id="imap_username" name="imap_username" class="input" value="<?php echo htmlspecialchars($formValues['imap_username']); ?>" data-imap-username required>
                </div>

                <div class="form-group">
                  <label for="imap_password">IMAP Password</label>
                  <input type="password" id="imap_password" name="imap_password" class="input" autocomplete="new-password" data-imap-password <?php echo $editMailbox ? '' : 'required'; ?>>
                  <?php if ($editMailbox): ?>
                    <small class="text-muted">Leave blank to keep the current password.</small>
                  <?php endif; ?>
                </div>

                <div class="form-group mailbox-field-compact">
                  <label for="imap_encryption">IMAP Encryption</label>
                  <select id="imap_encryption" name="imap_encryption" class="input" required>
                    <?php foreach ($allowedEncryptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formValues['imap_encryption'] === $option ? 'selected' : ''; ?>>
                        <?php echo strtoupper($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-group mailbox-checkbox">
              <label class="checkbox-label">
                <input type="checkbox" name="delete_after_retrieve" value="1" <?php echo $formValues['delete_after_retrieve'] ? 'checked' : ''; ?>>
                Delete messages on server after retrieving
              </label>
            </div>

            <div class="form-group mailbox-checkbox">
              <label class="checkbox-label">
                <input type="checkbox" name="store_sent_on_server" value="1" <?php echo $formValues['store_sent_on_server'] ? 'checked' : ''; ?>>
                Store sent mail on server in Sent
              </label>
            </div>

            <div class="form-group mailbox-checkbox">
              <label class="checkbox-label">
                <input type="checkbox" name="use_same_credentials" value="1" data-mailbox-same-credentials <?php echo $editMailbox || $formValues['imap_username'] === '' || $formValues['smtp_username'] === $formValues['imap_username'] ? 'checked' : ''; ?>>
                Use the same username and password for SMTP and IMAP
              </label>
            </div>

            <div class="mailbox-section">
              <div class="mailbox-section-header">
                <h3>SMTP Settings</h3>
              </div>
              <div class="mailbox-grid">
                <div class="form-group">
                  <label for="smtp_host">SMTP Host</label>
                  <input type="text" id="smtp_host" name="smtp_host" class="input" value="<?php echo htmlspecialchars($formValues['smtp_host']); ?>" required>
                </div>

                <div class="form-group mailbox-field-compact">
                  <label for="smtp_port">SMTP Port</label>
                  <input type="number" id="smtp_port" name="smtp_port" class="input" value="<?php echo (int) $formValues['smtp_port']; ?>" min="1" max="65535" required>
                </div>

                <div class="form-group" data-smtp-credentials>
                  <label for="smtp_username">SMTP Username</label>
                  <input type="text" id="smtp_username" name="smtp_username" class="input" value="<?php echo htmlspecialchars($formValues['smtp_username']); ?>" data-smtp-username required>
                </div>

                <div class="form-group" data-smtp-credentials>
                  <label for="smtp_password">SMTP Password</label>
                  <input type="password" id="smtp_password" name="smtp_password" class="input" autocomplete="new-password" data-smtp-password <?php echo $editMailbox ? '' : 'required'; ?>>
                  <?php if ($editMailbox): ?>
                    <small class="text-muted">Leave blank to keep the current password.</small>
                  <?php endif; ?>
                </div>

                <div class="form-group mailbox-field-compact">
                  <label for="smtp_encryption">SMTP Encryption</label>
                  <select id="smtp_encryption" name="smtp_encryption" class="input" required>
                    <?php foreach ($allowedEncryptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formValues['smtp_encryption'] === $option ? 'selected' : ''; ?>>
                        <?php echo strtoupper($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="page-header-actions">
              <button type="submit" class="btn"><?php echo $editMailbox ? 'Update Mailbox' : 'Create Mailbox'; ?></button>
              <a href="<?php echo BASE_PATH; ?>/pages/team/index.php?tab=mailboxes" class="btn">Cancel</a>
            </div>
          </form>
        </section>
      </div>
<?php renderPageEnd(); ?>
