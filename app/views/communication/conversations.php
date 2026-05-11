<?php
require_once __DIR__ . '/../../models/core/link_helpers.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/communication/conversation_helpers.php';

$errors = [];
$conversations = [];
$conversationMessages = [];
$userId = (int) ($currentUser['user_id'] ?? 0);
$folderOptions = getEmailFolderOptions();

$conversationId = (int) ($_GET['conversation_id'] ?? 0);

try {
  $conversations = fetchConversationsForUser($userId);
} catch (Throwable $error) {
  $errors[] = 'Failed to load conversations.';
  logAction($userId, 'conversation_list_error', $error->getMessage());
}

if ($conversationId > 0) {
    try {
    $messages = fetchConversationMessagesForUser($conversationId, $userId);
    if ($messages === null) {
            $errors[] = 'Conversation access denied.';
        } else {
      $conversationMessages = $messages;
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load conversation emails.';
        logAction($userId, 'conversation_messages_error', $error->getMessage());
    }
}

$baseUrl = BASE_PATH . '/app/controllers/communication/index.php';
$baseQuery = [
    'tab' => 'conversations'
];
$baseQuery = array_filter($baseQuery, static fn($value) => $value !== null && $value !== '');
$cooldownSeconds = 14 * 24 * 60 * 60;
?>
<div class="columns is-variable is-4">
  <section class="column is-6">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5">Conversations</h2>
        </div>
      </div>


      <?php foreach ($errors as $error): ?>
        <div class="notification"><?php echo htmlspecialchars($error); ?></div>
      <?php endforeach; ?>

      <?php
        $openConversations = [];
        $closedConversations = [];

        foreach ($conversations as $conversation) {
            if (!empty($conversation['is_closed'])) {
                $closedConversations[] = $conversation;
            } else {
                $openConversations[] = $conversation;
            }
        }

        $trimConversationSubject = static function (?string $subject, int $limit = 30): string {
            $subject = formatConversationSubject($subject);
            if (mb_strlen($subject) <= $limit) {
                return $subject;
            }
            return rtrim(mb_substr($subject, 0, $limit - 1)) . '…';
        };

        $incomingConversations = [];
        $outgoingConversations = [];

        foreach ($openConversations as $conversation) {
            $lastFolder = (string) ($conversation['last_message_folder'] ?? '');
            if ($lastFolder === 'inbox') {
                $incomingConversations[] = $conversation;
            } else {
                $outgoingConversations[] = $conversation;
            }
        }

        $mailboxIdentityList = [];
        try {
          $mailboxIdentityList = fetchMailboxIdentityListForUser($userId);
        } catch (Throwable $error) {
          logAction($userId, 'conversation_mailbox_identity_error', $error->getMessage());
        }

        $resolveParticipantLabel = static function (string $participantKey, array $mailboxIdentityList): string {
            $participantKey = trim($participantKey);
            if ($participantKey === '' || $participantKey === 'unknown') {
                return 'Unknown participants';
            }

            $participants = array_map('trim', explode('|', $participantKey));
            $participants = array_values(array_filter($participants, static fn(string $value): bool => $value !== '' && !in_array(strtolower($value), $mailboxIdentityList, true)));

            if (!$participants) {
                return 'Unknown participants';
            }

            return implode(' · ', $participants);
        };

        $sortByHeat = static function (array $left, array $right): int {
            $leftActivity = $left['last_activity_at'] ?? $left['created_at'] ?? null;
            $rightActivity = $right['last_activity_at'] ?? $right['created_at'] ?? null;
            $leftTime = $leftActivity ? strtotime((string) $leftActivity) : 0;
            $rightTime = $rightActivity ? strtotime((string) $rightActivity) : 0;

            return $leftTime <=> $rightTime;
        };

        usort($incomingConversations, $sortByHeat);
        usort($outgoingConversations, $sortByHeat);
        usort($closedConversations, $sortByHeat);

        $orderedOpenConversations = array_merge($incomingConversations, $outgoingConversations);
      ?>
        <div class="menu">
          <ul class="menu-list">
            <?php if (!$conversations): ?>
              <li><span>No conversations found.</span></li>
            <?php elseif (!$orderedOpenConversations): ?>
              <li><span>No open conversations.</span></li>
            <?php else: ?>
              <?php foreach ($orderedOpenConversations as $conversation): ?>
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

                  $participantLabel = $resolveParticipantLabel((string) ($conversation['participant_key'] ?? ''), $mailboxIdentityList);
                  $ageDays = $lastActivityTime ? (int) floor((time() - $lastActivityTime) / 86400) : null;
                  $activityLabel = $ageDays !== null ? ($ageDays . 'd') : '—';
                  $messageCount = (int) ($conversation['message_count'] ?? 0);
                  $lastFolder = (string) ($conversation['last_message_folder'] ?? '');
                  $arrowIcon = $lastFolder === 'inbox' ? 'fa-arrow-right' : 'fa-arrow-left';
                  $scopeIcon = !empty($conversation['team_id']) ? 'fa-users' : 'fa-user';
                  $trimmedSubject = $trimConversationSubject((string) ($conversation['subject'] ?? ''));
                ?>
                <li class="mb-2">
                  <div class="is-flex is-justify-content-space-between">
                    <a href="<?php echo htmlspecialchars($conversationLink); ?>" class="is-flex-grow-1 <?php echo (int) $conversation['id'] === $conversationId ? 'is-active' : ''; ?>">
                      <div class="conversation-row">
                        <div class="conversation-row-main">
                          <span class="icon is-small mr-1"><i class="fa-solid <?php echo $scopeIcon; ?>"></i></span>
                          <span class="icon is-small mr-1"><i class="fa-solid <?php echo $arrowIcon; ?>"></i></span>
                          <span class="has-text-weight-semibold mr-1"><?php echo (int) $conversation['id']; ?>:</span>
                          <span class="is-size-7 mr-1 conversation-participant"><?php echo htmlspecialchars($participantLabel); ?></span>
                          <span class="has-text-weight-semibold conversation-subject" title="<?php echo htmlspecialchars(formatConversationSubject((string) ($conversation['subject'] ?? ''))); ?>">
                            <?php echo htmlspecialchars(formatConversationSubject((string) ($conversation['subject'] ?? ''))); ?>
                          </span>
                        </div>
                        <div class="conversation-row-progress">
                          <div class="conversation-progress">
                            <progress class="progress is-small is-cooldown-step-<?php echo $colorStep; ?>" value="<?php echo $heatPercent; ?>" max="100"></progress>
                          </div>
                        </div>
                        <div class="conversation-row-meta is-size-7">
                          <div>
                            <?php echo htmlspecialchars($activityLabel); ?>/<?php echo (int) $messageCount; ?>
                          </div>
                          <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_close.php" class="is-flex is-align-items-center" data-list-ignore>
                            <?php renderCsrfField(); ?>
                            <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                            <button type="submit" class="button is-small" aria-label="Close conversation" title="Close conversation">
                              <span class="icon"><i class="fa-solid fa-circle-xmark"></i></span>
                            </button>
                          </form>
                        </div>
                      </div>
                    </a>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <?php if ($closedConversations): ?>
          <div class="mt-4">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-2">
              <h3 class="title is-6 mb-0">Closed</h3>
              <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_delete_all_closed.php" onsubmit="return confirm('Delete all closed conversations?');">
                <?php renderCsrfField(); ?>
                <button type="submit" class="button is-small" title="Delete all closed conversations">Delete all</button>
              </form>
            </div>
            <div class="menu">
              <ul class="menu-list">
                <?php foreach ($closedConversations as $conversation): ?>
                  <?php
                    $conversationLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
                        'conversation_id' => $conversation['id']
                    ]));
                    $lastActivity = $conversation['last_activity_at'] ?? $conversation['created_at'] ?? null;
                    $lastActivityTime = $lastActivity ? strtotime((string) $lastActivity) : null;
                    $participantLabel = $resolveParticipantLabel((string) ($conversation['participant_key'] ?? ''), $mailboxIdentityList);
                    $ageDays = $lastActivityTime ? (int) floor((time() - $lastActivityTime) / 86400) : null;
                    $activityLabel = $ageDays !== null ? ($ageDays . 'd') : '—';
                    $messageCount = (int) ($conversation['message_count'] ?? 0);
                    $lastFolder = (string) ($conversation['last_message_folder'] ?? '');
                    $arrowIcon = $lastFolder === 'inbox' ? 'fa-arrow-right' : 'fa-arrow-left';
                    $scopeIcon = !empty($conversation['team_id']) ? 'fa-users' : 'fa-user';
                    $trimmedSubject = $trimConversationSubject((string) ($conversation['subject'] ?? ''));
                  ?>
                  <li class="mb-2">
                    <div class="is-flex is-justify-content-space-between">
                      <a href="<?php echo htmlspecialchars($conversationLink); ?>" class="is-flex-grow-1 <?php echo (int) $conversation['id'] === $conversationId ? 'is-active' : ''; ?>">
                        <div class="conversation-row">
                          <div class="conversation-row-main">
                            <span class="icon is-small mr-1"><i class="fa-solid <?php echo $scopeIcon; ?>"></i></span>
                            <span class="icon is-small mr-1"><i class="fa-solid <?php echo $arrowIcon; ?>"></i></span>
                            <span class="has-text-weight-semibold mr-1"><?php echo (int) $conversation['id']; ?>:</span>
                            <span class="is-size-7 mr-1 conversation-participant"><?php echo htmlspecialchars($participantLabel); ?></span>
                            <span class="has-text-weight-semibold conversation-subject" title="<?php echo htmlspecialchars(formatConversationSubject((string) ($conversation['subject'] ?? ''))); ?>">
                              <?php echo htmlspecialchars(formatConversationSubject((string) ($conversation['subject'] ?? ''))); ?>
                            </span>
                          </div>
                          <div class="conversation-row-meta is-size-7">
                            <div>
                              <?php echo htmlspecialchars($activityLabel); ?>/<?php echo (int) $messageCount; ?>
                            </div>
                            <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_reopen.php" class="is-flex is-align-items-center" data-list-ignore>
                              <?php renderCsrfField(); ?>
                              <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                              <button type="submit" class="button is-small" aria-label="Reopen conversation" title="Reopen conversation">
                                <span class="icon"><i class="fa-solid fa-rotate-left"></i></span>
                              </button>
                            </form>
                            <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_delete.php" class="is-flex is-align-items-center" onsubmit="return confirm('Delete this conversation?');" data-list-ignore>
                              <?php renderCsrfField(); ?>
                              <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                              <button type="submit" class="button is-small" aria-label="Delete conversation" title="Delete conversation">
                                <span class="icon"><i class="fa-solid fa-trash"></i></span>
                              </button>
                            </form>
                          </div>
                        </div>
                      </a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>
    </div>
  </section>

  <section class="column is-6">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5">Conversation emails</h2>
        </div>
      </div>

      <?php if (!$conversationId): ?>
        <p>Select a conversation to view emails.</p>
      <?php elseif (!$conversationMessages): ?>
        <p>No emails found for this conversation.</p>
      <?php else: ?>
        <div class="content">
          <?php foreach ($conversationMessages as $messageItem): ?>
            <?php
              $messageFolder = $messageItem['folder'] ?? 'inbox';
              $displayName = $messageFolder === 'inbox'
                  ? trim(($messageItem['from_name'] ?? '') !== '' ? $messageItem['from_name'] : ($messageItem['from_email'] ?? 'Unknown'))
                  : trim((string) ($messageItem['to_emails'] ?? ''));
              $dateValue = $messageFolder === 'inbox'
                  ? ($messageItem['received_at'] ?? $messageItem['created_at'])
                  : ($messageItem['sent_at'] ?? $messageItem['created_at']);
              $dateLabel = $dateValue ? date('Y-m-d H:i', strtotime((string) $dateValue)) : '';
              $folderLabel = $folderOptions[$messageFolder] ?? ucfirst($messageFolder);
              $isUnread = empty($messageItem['is_read']) && $messageFolder === 'inbox';
              $messageBody = (string) ($messageItem['body_html'] ?? '');
              if ($messageBody === '') {
                  $messageBody = (string) ($messageItem['body'] ?? '');
              }
              $isPersonalMessage = empty($messageItem['team_id']) && !empty($messageItem['user_id']);
              $isPersonalPlaceholder = $isPersonalMessage
                  && (int) $messageItem['user_id'] !== (int) $userId;
              $placeholderLabel = $isPersonalPlaceholder
                  ? sprintf('Personal reply from %s (hidden)', $messageItem['user_name'] ?? 'user')
                  : '';
              $selectedMailbox = [
                  'id' => (int) ($messageItem['mailbox_id'] ?? 0),
                  'team_id' => $messageItem['team_id'] ?? null,
                  'user_id' => $messageItem['user_id'] ?? null,
                  'display_name' => ''
              ];
              $messageLinks = [];
                if (!empty($messageItem['id'])) {
                  try {
                      $linkTeamId = !empty($messageItem['team_id']) ? (int) $messageItem['team_id'] : null;
                      $linkUserId = !empty($messageItem['user_id']) ? (int) $messageItem['user_id'] : null;
                    $messageLinks = fetchEmailMessageLinks((int) $messageItem['id'], $linkTeamId, $linkUserId);
                  } catch (Throwable $error) {
                      logAction($userId, 'conversation_message_links_error', $error->getMessage());
                  }
              }
              $baseEmailUrl = BASE_PATH . '/app/controllers/communication/index.php';
              $baseQuery = [
                  'tab' => 'conversations',
                  'conversation_id' => $conversationId
              ];
              $messageFolderForLink = (string) ($messageItem['folder'] ?? 'inbox');
              $emailDetailSubjectUrl = BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query([
                  'tab' => 'email',
                  'mailbox_id' => (int) ($messageItem['mailbox_id'] ?? 0),
                  'folder' => $messageFolderForLink,
                  'message_id' => (int) ($messageItem['id'] ?? 0)
              ]);
              $emailDetailWrapperTag = 'article';
              $emailDetailWrapperClass = 'box mb-4';
              $emailDetailIncludeLinkEditor = false;
              $emailDetailShowActions = false;
              $emailDetailShowLinks = false;
              $emailDetailShowAttachments = false;
              $composeMode = false;
              $templates = [];
              $attachments = [];
              $sortKey = 'received_desc';
              $filter = '';
              $page = 1;
              $message = $messageItem;
              require __DIR__ . '/email_detail.php';
            ?>
            <div class="is-flex is-align-items-center is-size-7 mt-2">
              <span><?php echo htmlspecialchars($dateLabel); ?></span>
              <?php if ($isUnread): ?>
                <span class="tag is-small ml-2">Unread</span>
              <?php endif; ?>
              <span class="ml-2">· <?php echo htmlspecialchars($folderLabel); ?></span>
              <span class="ml-2 has-text-weight-semibold">
                <?php echo htmlspecialchars($isPersonalPlaceholder ? $placeholderLabel : $displayName); ?>
              </span>
              <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_rm_message.php" class="ml-2" onsubmit="return confirm('Remove this email from the conversation?');">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="conversation_id" value="<?php echo (int) $conversationId; ?>">
                <input type="hidden" name="message_id" value="<?php echo (int) $messageItem['id']; ?>">
                <button type="submit" class="button is-small" aria-label="Remove from conversation" title="Remove from conversation">
                  <span class="icon"><i class="fa-solid fa-link-slash"></i></span>
                </button>
              </form>
            </div>
            <?php $message = null; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
