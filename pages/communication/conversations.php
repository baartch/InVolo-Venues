<?php
require_once __DIR__ . '/../../src-php/email_helpers.php';

$errors = [];
$conversations = [];
$conversationMessages = [];
$teamMailboxes = [];
$selectedMailbox = null;
$pdo = null;
$userId = (int) ($currentUser['user_id'] ?? 0);
$folderOptions = getEmailFolderOptions();

try {
    $pdo = getDatabaseConnection();
    $teamMailboxes = fetchTeamMailboxes($pdo, $userId);
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailboxes.';
    logAction($userId, 'conversation_mailbox_load_error', $error->getMessage());
}

$selectedMailboxId = (int) ($_GET['mailbox_id'] ?? 0);
if ($selectedMailboxId <= 0 && $teamMailboxes) {
    $selectedMailboxId = (int) ($teamMailboxes[0]['id'] ?? 0);
}

if ($pdo && $selectedMailboxId > 0) {
    try {
        $selectedMailbox = ensureMailboxAccess($pdo, $selectedMailboxId, $userId);
        if (!$selectedMailbox) {
            $errors[] = 'Mailbox access denied.';
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load mailbox.';
        logAction($userId, 'conversation_mailbox_access_error', $error->getMessage());
    }
}

$conversationId = (int) ($_GET['conversation_id'] ?? 0);

if ($pdo && $selectedMailbox) {
    try {
        $stmt = $pdo->prepare(
            'SELECT c.*, COUNT(em.id) AS message_count
             FROM email_conversations c
             LEFT JOIN email_messages em ON em.conversation_id = c.id
             WHERE c.mailbox_id = :mailbox_id
             GROUP BY c.id
             ORDER BY c.last_activity_at DESC, c.id DESC'
        );
        $stmt->execute([':mailbox_id' => $selectedMailbox['id']]);
        $conversations = $stmt->fetchAll();

        if ($conversationId <= 0 && $conversations) {
            $conversationId = (int) ($conversations[0]['id'] ?? 0);
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load conversations.';
        logAction($userId, 'conversation_list_error', $error->getMessage());
    }
}

if ($pdo && $selectedMailbox && $conversationId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT em.id, em.subject, em.body, em.from_name, em.from_email, em.to_emails, em.folder,
                    em.is_read, em.received_at, em.sent_at, em.created_at
             FROM email_messages em
             WHERE em.mailbox_id = :mailbox_id AND em.conversation_id = :conversation_id
             ORDER BY COALESCE(em.received_at, em.sent_at, em.created_at) DESC'
        );
        $stmt->execute([
            ':mailbox_id' => $selectedMailbox['id'],
            ':conversation_id' => $conversationId
        ]);
        $conversationMessages = $stmt->fetchAll();
    } catch (Throwable $error) {
        $errors[] = 'Failed to load conversation emails.';
        logAction($userId, 'conversation_messages_error', $error->getMessage());
    }
}

$baseUrl = BASE_PATH . '/pages/communication/index.php';
$baseQuery = [
    'tab' => 'conversations',
    'mailbox_id' => $selectedMailbox['id'] ?? null
];
$baseQuery = array_filter($baseQuery, static fn($value) => $value !== null && $value !== '');
$mailboxCount = count($teamMailboxes);
$cooldownSeconds = 14 * 24 * 60 * 60;
?>
<div class="columns is-variable is-4">
  <section class="column is-4">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5">Conversations</h2>
        </div>
      </div>

      <?php if (!$teamMailboxes): ?>
        <p>No mailboxes assigned.</p>
      <?php elseif ($mailboxCount > 1): ?>
        <form method="GET" action="<?php echo htmlspecialchars($baseUrl); ?>" class="field has-addons">
          <input type="hidden" name="tab" value="conversations">
          <div class="control is-expanded">
            <div class="select is-fullwidth">
              <select name="mailbox_id">
                <?php foreach ($teamMailboxes as $mailbox): ?>
                  <option value="<?php echo (int) $mailbox['id']; ?>" <?php echo (int) ($selectedMailbox['id'] ?? 0) === (int) $mailbox['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($mailbox['team_name'] . ' · ' . $mailbox['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="control">
            <button type="submit" class="button">Go</button>
          </div>
        </form>
      <?php else: ?>
        <p><?php echo htmlspecialchars($teamMailboxes[0]['team_name'] . ' · ' . $teamMailboxes[0]['name']); ?></p>
      <?php endif; ?>

      <?php foreach ($errors as $error): ?>
        <div class="notification"><?php echo htmlspecialchars($error); ?></div>
      <?php endforeach; ?>

      <?php if ($selectedMailbox): ?>
        <div class="menu">
          <ul class="menu-list">
            <?php if (!$conversations): ?>
              <li><span>No conversations found.</span></li>
            <?php else: ?>
              <?php foreach ($conversations as $conversation): ?>
                <?php
                  $conversationLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
                      'conversation_id' => $conversation['id']
                  ]));
                  $lastActivity = $conversation['last_activity_at'] ?? $conversation['created_at'] ?? null;
                  $lastActivityTime = $lastActivity ? strtotime((string) $lastActivity) : null;
                  $elapsedSeconds = $lastActivityTime ? max(0, time() - $lastActivityTime) : $cooldownSeconds;
                  $cooldownPercent = $cooldownSeconds > 0
                      ? min(100, (int) round(($elapsedSeconds / $cooldownSeconds) * 100))
                      : 100;
                  $heatPercent = max(0, 100 - $cooldownPercent);
                  $colorStep = (int) round($cooldownPercent / 5) * 5;
                  $colorStep = max(0, min(100, $colorStep));

                  $participantLabel = $conversation['participant_key'] === 'unknown'
                      ? 'Unknown participants'
                      : str_replace('|', ' · ', $conversation['participant_key']);
                  $activityLabel = $lastActivityTime ? date('Y-m-d H:i', $lastActivityTime) : '';
                  $messageCount = (int) ($conversation['message_count'] ?? 0);
                  $messageLabel = $messageCount === 1 ? 'mail' : 'mails';
                ?>
                <li class="mb-3">
                  <div class="is-flex is-justify-content-space-between">
                    <a href="<?php echo htmlspecialchars($conversationLink); ?>" class="is-flex-grow-1 <?php echo (int) $conversation['id'] === $conversationId ? 'is-active' : ''; ?>">
                      <div class="is-flex is-justify-content-space-between">
                        <div>
                          <div class="has-text-weight-semibold"><?php echo htmlspecialchars($conversation['subject'] ?? '(No subject)'); ?></div>
                          <div class="is-size-7"><?php echo htmlspecialchars($participantLabel); ?></div>
                        </div>
                        <div class="is-size-7 has-text-right">
                          <div><?php echo htmlspecialchars($activityLabel); ?></div>
                          <div><span class="tag is-small"><?php echo $messageCount; ?> <?php echo $messageLabel; ?></span></div>
                        </div>
                      </div>
                      <div class="mt-2">
                        <progress class="progress is-small is-cooldown-step-<?php echo $colorStep; ?>" value="<?php echo $heatPercent; ?>" max="100"></progress>
                        <?php if (!empty($conversation['is_closed'])): ?>
                          <div class="mt-2">
                            <span class="tag is-small is-light">Closed</span>
                          </div>
                        <?php endif; ?>
                      </div>
                    </a>
                    <?php if (empty($conversation['is_closed'])): ?>
                      <form method="POST" action="<?php echo BASE_PATH; ?>/routes/communication/close_conversation.php" class="ml-2">
                        <?php renderCsrfField(); ?>
                        <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
                        <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                        <button type="submit" class="button is-small" aria-label="Close conversation" title="Close conversation">
                          <span class="icon"><i class="fa-solid fa-circle-xmark"></i></span>
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" action="<?php echo BASE_PATH; ?>/routes/communication/delete_conversation.php" class="ml-2" onsubmit="return confirm('Delete this conversation?');">
                        <?php renderCsrfField(); ?>
                        <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
                        <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                        <button type="submit" class="button is-small" aria-label="Delete conversation" title="Delete conversation">
                          <span class="icon"><i class="fa-solid fa-trash"></i></span>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="column">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5">Conversation emails</h2>
        </div>
      </div>

      <?php if (!$selectedMailbox): ?>
        <p>Select a mailbox to view conversations.</p>
      <?php elseif (!$conversationId): ?>
        <p>Select a conversation to view emails.</p>
      <?php elseif (!$conversationMessages): ?>
        <p>No emails found for this conversation.</p>
      <?php else: ?>
        <div class="content">
          <?php foreach ($conversationMessages as $message): ?>
            <?php
              $messageFolder = $message['folder'] ?? 'inbox';
              $displayName = $messageFolder === 'inbox'
                  ? trim(($message['from_name'] ?? '') !== '' ? $message['from_name'] : ($message['from_email'] ?? 'Unknown'))
                  : trim((string) ($message['to_emails'] ?? ''));
              $dateValue = $messageFolder === 'inbox'
                  ? ($message['received_at'] ?? $message['created_at'])
                  : ($message['sent_at'] ?? $message['created_at']);
              $dateLabel = $dateValue ? date('Y-m-d H:i', strtotime((string) $dateValue)) : '';
              $folderLabel = $folderOptions[$messageFolder] ?? ucfirst($messageFolder);
              $isUnread = empty($message['is_read']) && $messageFolder === 'inbox';
              $messageBody = (string) ($message['body'] ?? '');
            ?>
            <article class="box mb-4">
              <div class="is-flex is-justify-content-space-between is-size-7 mb-2">
                <div>
                  <span class="has-text-weight-semibold"><?php echo htmlspecialchars($displayName); ?></span>
                  <span class="mx-1">·</span>
                  <span><?php echo htmlspecialchars($folderLabel); ?></span>
                  <?php if ($isUnread): ?>
                    <span class="tag is-small ml-2">Unread</span>
                  <?php endif; ?>
                </div>
                <div><?php echo htmlspecialchars($dateLabel); ?></div>
              </div>
              <h3 class="title is-6 mb-2"><?php echo htmlspecialchars($message['subject'] ?? '(No subject)'); ?></h3>
              <div class="content is-size-7">
                <?php
                  if ($messageBody !== '' && $messageBody !== strip_tags($messageBody)) {
                      echo $messageBody;
                  } else {
                      echo nl2br(htmlspecialchars($messageBody));
                  }
                ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
