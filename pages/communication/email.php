<?php
require_once __DIR__ . '/../../src-php/email_helpers.php';

$errors = [];
$notice = '';
$messages = [];
$message = null;
$attachments = [];
$folderCounts = [];
$templates = [];
$teamMailboxes = [];
$selectedMailbox = null;
$pdo = null;
$userId = (int) ($currentUser['user_id'] ?? 0);

$folderOptions = getEmailFolderOptions();
$sortOptions = getEmailSortOptions();
$folder = (string) ($_GET['folder'] ?? 'inbox');
if (!array_key_exists($folder, $folderOptions)) {
    $folder = 'inbox';
}

$sortKey = (string) ($_GET['sort'] ?? 'received_desc');
if (!array_key_exists($sortKey, $sortOptions)) {
    $sortKey = 'received_desc';
}

$filter = trim((string) ($_GET['filter'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = EMAIL_PAGE_SIZE_DEFAULT;
$composeMode = ($_GET['compose'] ?? '') === '1';
$replyId = (int) ($_GET['reply'] ?? 0);
$forwardId = (int) ($_GET['forward'] ?? 0);
$templateId = (int) ($_GET['template_id'] ?? 0);

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'sent') {
    $notice = 'Email sent successfully.';
} elseif ($noticeKey === 'draft_saved') {
    $notice = 'Draft saved successfully.';
} elseif ($noticeKey === 'deleted') {
    $notice = 'Email deleted.';
} elseif ($noticeKey === 'send_failed') {
    $notice = 'Email could not be sent. Saved as draft.';
}

try {
    $pdo = getDatabaseConnection();
    $teamMailboxes = fetchTeamMailboxes($pdo, $userId);
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailboxes.';
    logAction($userId, 'email_mailbox_load_error', $error->getMessage());
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
        logAction($userId, 'email_mailbox_access_error', $error->getMessage());
    }
}

$quotaUsed = 0;
$quotaTotal = EMAIL_ATTACHMENT_QUOTA_DEFAULT;
if ($pdo && $selectedMailbox) {
    try {
        $quotaUsed = fetchMailboxQuotaUsage($pdo, (int) $selectedMailbox['id']);
        $quotaTotal = (int) ($selectedMailbox['attachment_quota_bytes'] ?? EMAIL_ATTACHMENT_QUOTA_DEFAULT);
    } catch (Throwable $error) {
        $errors[] = 'Failed to load attachment quota.';
        logAction($userId, 'email_quota_load_error', $error->getMessage());
    }
}

$composeValues = [
    'to_emails' => '',
    'cc_emails' => '',
    'bcc_emails' => '',
    'subject' => '',
    'body' => ''
];

$selectedMessageId = (int) ($_GET['message_id'] ?? 0);
if ($pdo && $selectedMailbox && $selectedMessageId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT em.*
             FROM email_messages em
             WHERE em.id = :id AND em.mailbox_id = :mailbox_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $selectedMessageId,
            ':mailbox_id' => $selectedMailbox['id']
        ]);
        $message = $stmt->fetch();

        if ($message && $message['folder'] === 'drafts') {
            $composeValues['to_emails'] = (string) ($message['to_emails'] ?? '');
            $composeValues['cc_emails'] = (string) ($message['cc_emails'] ?? '');
            $composeValues['bcc_emails'] = (string) ($message['bcc_emails'] ?? '');
            $composeValues['subject'] = (string) ($message['subject'] ?? '');
            $composeValues['body'] = (string) ($message['body'] ?? '');
            $composeMode = true;
        } elseif ($message && !(bool) $message['is_read']) {
            $updateStmt = $pdo->prepare('UPDATE email_messages SET is_read = 1 WHERE id = :id');
            $updateStmt->execute([':id' => $selectedMessageId]);
        }

        if ($message) {
            $attachmentsStmt = $pdo->prepare(
                'SELECT * FROM email_attachments WHERE email_id = :email_id ORDER BY id'
            );
            $attachmentsStmt->execute([':email_id' => $selectedMessageId]);
            $attachments = $attachmentsStmt->fetchAll();
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load email message.';
        logAction($userId, 'email_message_load_error', $error->getMessage());
    }
}

$prefillMessageId = $replyId > 0 ? $replyId : $forwardId;
if ($pdo && $selectedMailbox && $prefillMessageId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT em.*
             FROM email_messages em
             WHERE em.id = :id AND em.mailbox_id = :mailbox_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $prefillMessageId,
            ':mailbox_id' => $selectedMailbox['id']
        ]);
        $prefillMessage = $stmt->fetch();
        if ($prefillMessage) {
            $originalSubject = (string) ($prefillMessage['subject'] ?? '');
            $subjectPrefix = $replyId > 0 ? 'Re: ' : 'Fwd: ';
            $subject = $originalSubject;
            if ($subject !== '' && stripos($subject, $subjectPrefix) !== 0) {
                $subject = $subjectPrefix . $subject;
            }
            $composeValues['subject'] = $subject;
            if ($replyId > 0) {
                $composeValues['to_emails'] = (string) ($prefillMessage['from_email'] ?? '');
            }
            $bodyLines = [];
            if ($replyId > 0) {
                $bodyLines[] = '';
                $bodyLines[] = sprintf('On %s, %s wrote:', $prefillMessage['received_at'] ?? '', $prefillMessage['from_name'] ?? $prefillMessage['from_email'] ?? '');
                $bodyLines[] = (string) ($prefillMessage['body'] ?? '');
            } elseif ($forwardId > 0) {
                $bodyLines[] = '';
                $bodyLines[] = '---- Forwarded message ----';
                $bodyLines[] = sprintf('From: %s', $prefillMessage['from_email'] ?? '');
                $bodyLines[] = sprintf('Date: %s', $prefillMessage['received_at'] ?? '');
                $bodyLines[] = sprintf('Subject: %s', $prefillMessage['subject'] ?? '');
                $bodyLines[] = sprintf('To: %s', $prefillMessage['to_emails'] ?? '');
                $bodyLines[] = '';
                $bodyLines[] = (string) ($prefillMessage['body'] ?? '');
            }
            $composeValues['body'] = trim(implode("\n", $bodyLines));
            $composeMode = true;
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to prepare reply.';
        logAction($userId, 'email_reply_load_error', $error->getMessage());
    }
}

if ($pdo && $selectedMailbox) {
    try {
        $templates = fetchTeamTemplates($pdo, $userId, (int) $selectedMailbox['team_id']);
        if ($templateId > 0) {
            $template = ensureTemplateAccess($pdo, $templateId, $userId);
            if ($template && (int) $template['team_id'] === (int) $selectedMailbox['team_id']) {
                $composeValues['subject'] = $composeValues['subject'] !== '' ? $composeValues['subject'] : (string) ($template['subject'] ?? '');
                $composeValues['body'] = $composeValues['body'] !== '' ? $composeValues['body'] : (string) ($template['body'] ?? '');
                $composeMode = true;
            }
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load templates.';
        logAction($userId, 'email_template_load_error', $error->getMessage());
    }
}

$totalMessages = 0;
$totalPages = 1;
if ($pdo && $selectedMailbox) {
    try {
        $filterSql = '';
        $params = [
            ':mailbox_id' => $selectedMailbox['id'],
            ':folder' => $folder
        ];
        if ($filter !== '') {
            $filterSql = 'AND (subject LIKE :filter OR ';
            if ($folder === 'inbox') {
                $filterSql .= 'from_name LIKE :filter OR from_email LIKE :filter';
            } else {
                $filterSql .= 'to_emails LIKE :filter';
            }
            $filterSql .= ')';
            $params[':filter'] = '%' . $filter . '%';
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM email_messages
             WHERE mailbox_id = :mailbox_id AND folder = :folder ' . $filterSql
        );
        $countStmt->execute($params);
        $totalMessages = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalMessages / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $sortColumn = $sortOptions[$sortKey]['column'];
        $sortDirection = $sortOptions[$sortKey]['direction'];
        if ($sortColumn === 'received_at' && $folder !== 'inbox') {
            $sortColumn = $folder === 'sent' ? 'sent_at' : 'created_at';
        }

        $listStmt = $pdo->prepare(
            'SELECT id, subject, from_name, from_email, to_emails, is_read,
                    received_at, sent_at, created_at
             FROM email_messages
             WHERE mailbox_id = :mailbox_id AND folder = :folder ' . $filterSql .
            ' ORDER BY ' . $sortColumn . ' ' . $sortDirection .
            ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $messages = $listStmt->fetchAll();

        $countStmt = $pdo->prepare(
            'SELECT folder, COUNT(*) AS total
             FROM email_messages
             WHERE mailbox_id = :mailbox_id
             GROUP BY folder'
        );
        $countStmt->execute([':mailbox_id' => $selectedMailbox['id']]);
        $counts = $countStmt->fetchAll();
        foreach ($counts as $row) {
            $folderCounts[$row['folder']] = (int) $row['total'];
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load emails.';
        logAction($userId, 'email_list_error', $error->getMessage());
    }
}

$quotaPercent = calculateQuotaPercent($quotaUsed, $quotaTotal);
$baseEmailUrl = BASE_PATH . '/pages/communication/index.php';

$baseQuery = [
    'tab' => 'email',
    'mailbox_id' => $selectedMailbox['id'] ?? null,
    'folder' => $folder,
    'sort' => $sortKey,
    'filter' => $filter,
    'page' => $page
];
$baseQuery = array_filter($baseQuery, static fn($value) => $value !== null && $value !== '');
?>
<div class="columns is-variable is-3">
  <aside class="column is-3">
    <div class="box">
      <h3 class="title is-6">Mailbox</h3>
      <?php if (!$teamMailboxes): ?>
        <p>No mailboxes assigned.</p>
      <?php else: ?>
        <form method="GET" action="<?php echo htmlspecialchars($baseEmailUrl); ?>" class="field has-addons">
          <input type="hidden" name="tab" value="email">
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
      <?php endif; ?>

      <div class="block">
        <a href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1]))); ?>" class="button is-primary is-fullwidth">New eMail</a>
      </div>

      <?php if ($notice): ?>
        <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
      <?php endif; ?>

      <?php foreach ($errors as $error): ?>
        <div class="notification"><?php echo htmlspecialchars($error); ?></div>
      <?php endforeach; ?>

      <?php if ($selectedMailbox): ?>
        <div class="block">
          <h3 class="title is-6">Folders</h3>
          <aside class="menu">
            <ul class="menu-list">
              <?php foreach ($folderOptions as $folderKey => $folderLabel): ?>
                <?php
                  $folderLink = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
                      'folder' => $folderKey,
                      'page' => 1,
                      'message_id' => null
                  ]));
                  $folderCount = $folderCounts[$folderKey] ?? 0;
                ?>
                <li>
                  <a href="<?php echo htmlspecialchars($folderLink); ?>" class="<?php echo $folder === $folderKey ? 'is-active' : ''; ?>">
                    <span><?php echo htmlspecialchars($folderLabel); ?></span>
                    <span class="tag is-pulled-right"><?php echo (int) $folderCount; ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </aside>
        </div>

        <div class="block">
          <h3 class="title is-6">Attachment quota</h3>
          <progress class="progress" value="<?php echo (int) $quotaUsed; ?>" max="<?php echo (int) $quotaTotal; ?>"></progress>
          <p><?php echo htmlspecialchars(formatBytes($quotaUsed)); ?> / <?php echo htmlspecialchars(formatBytes($quotaTotal)); ?></p>
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <section class="column is-4">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5"><?php echo htmlspecialchars($folderOptions[$folder] ?? 'Inbox'); ?></h2>
        </div>
        <?php if ($selectedMailbox): ?>
          <div class="level-right">
            <form method="GET" action="<?php echo htmlspecialchars($baseEmailUrl); ?>" class="field has-addons">
              <input type="hidden" name="tab" value="email">
              <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
              <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
              <input type="hidden" name="page" value="1">
              <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
              <div class="control">
                <input type="text" name="filter" value="<?php echo htmlspecialchars($filter); ?>" placeholder="Search" class="input">
              </div>
              <div class="control">
                <button type="submit" class="button">Filter</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($selectedMailbox): ?>
        <div class="level mb-2">
          <div class="level-left">
            <span class="tag"><?php echo (int) $totalMessages; ?> emails</span>
          </div>
          <div class="level-right">
            <form method="GET" action="<?php echo htmlspecialchars($baseEmailUrl); ?>" class="field has-addons">
              <input type="hidden" name="tab" value="email">
              <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
              <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
              <input type="hidden" name="page" value="1">
              <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
              <div class="control">
                <div class="select">
                  <select name="sort">
                    <?php foreach ($sortOptions as $key => $option): ?>
                      <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $sortKey === $key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($option['label']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="control">
                <button type="submit" class="button">Sort</button>
              </div>
            </form>
          </div>
        </div>

        <div class="menu">
          <ul class="menu-list">
            <?php if (!$messages): ?>
              <li><span>No emails found.</span></li>
            <?php else: ?>
              <?php foreach ($messages as $row): ?>
                <?php
                  $messageLink = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
                      'message_id' => $row['id']
                  ]));
                  $displayName = $folder === 'inbox'
                      ? trim(($row['from_name'] ?? '') !== '' ? $row['from_name'] : ($row['from_email'] ?? 'Unknown'))
                      : trim((string) ($row['to_emails'] ?? ''));
                  $dateValue = $folder === 'inbox' ? ($row['received_at'] ?? $row['created_at']) : ($row['sent_at'] ?? $row['created_at']);
                  $dateLabel = $dateValue ? date('Y-m-d H:i', strtotime((string) $dateValue)) : '';
                ?>
                <li>
                  <a href="<?php echo htmlspecialchars($messageLink); ?>" class="<?php echo (int) $row['id'] === $selectedMessageId ? 'is-active' : ''; ?>">
                    <div class="is-flex is-justify-content-space-between">
                      <div>
                        <div class="has-text-weight-semibold"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="is-size-7"><?php echo htmlspecialchars($row['subject'] ?? '(No subject)'); ?></div>
                        <?php if (!$row['is_read'] && $folder === 'inbox'): ?>
                          <span class="tag is-small">Unread</span>
                        <?php endif; ?>
                      </div>
                      <div class="is-size-7"><?php echo htmlspecialchars($dateLabel); ?></div>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="pagination is-centered" role="navigation" aria-label="pagination">
            <ul class="pagination-list">
              <?php for ($pageIndex = 1; $pageIndex <= $totalPages; $pageIndex++): ?>
                <?php
                  $pageLink = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
                      'page' => $pageIndex
                  ]));
                ?>
                <li>
                  <a class="pagination-link<?php echo $pageIndex === $page ? ' is-current' : ''; ?>" href="<?php echo htmlspecialchars($pageLink); ?>">
                    <?php echo (int) $pageIndex; ?>
                  </a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <p>Select a mailbox to view emails.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="column">
    <div class="box">
      <?php if (!$selectedMailbox): ?>
        <p>Pick a mailbox to view details.</p>
      <?php elseif ($composeMode): ?>
        <div class="level mb-3">
          <div class="level-left">
            <h2 class="title is-5">Compose</h2>
          </div>
          <div class="level-right">
            <a href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query($baseQuery)); ?>" class="button">Cancel</a>
          </div>
        </div>

        <?php if ($templates): ?>
          <form method="GET" action="<?php echo htmlspecialchars($baseEmailUrl); ?>" class="field has-addons mb-4">
            <input type="hidden" name="tab" value="email">
            <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
            <input type="hidden" name="compose" value="1">
            <input type="hidden" name="page" value="1">
            <div class="control is-expanded">
              <div class="select is-fullwidth">
                <select name="template_id">
                  <option value="">Select template</option>
                  <?php foreach ($templates as $template): ?>
                    <option value="<?php echo (int) $template['id']; ?>" <?php echo $templateId === (int) $template['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($template['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="control">
              <button type="submit" class="button">Use Template</button>
            </div>
          </form>
        <?php endif; ?>

        <?php require __DIR__ . '/email_compose_form.php'; ?>
      <?php elseif ($message): ?>
        <div class="level mb-3">
          <div class="level-left">
            <div>
              <h2 class="title is-5"><?php echo htmlspecialchars($message['subject'] ?? '(No subject)'); ?></h2>
              <p class="is-size-7">
                <?php if ($folder === 'inbox'): ?>
                  From: <?php echo htmlspecialchars($message['from_name'] ?? $message['from_email'] ?? ''); ?>
                <?php else: ?>
                  To: <?php echo htmlspecialchars($message['to_emails'] ?? ''); ?>
                <?php endif; ?>
                · <?php echo htmlspecialchars($message['received_at'] ?? $message['sent_at'] ?? $message['created_at'] ?? ''); ?>
              </p>
            </div>
          </div>
          <div class="level-right">
            <div class="buttons are-small">
              <a href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1, 'reply' => $message['id']]))); ?>" class="button">Reply</a>
              <a href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1, 'forward' => $message['id']]))); ?>" class="button">Forward</a>
              <form method="POST" action="<?php echo BASE_PATH; ?>/routes/email/delete.php" onsubmit="return confirm('Delete this email?');">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
                <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
                <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                <input type="hidden" name="tab" value="email">
                <button type="submit" class="button">Delete</button>
              </form>
            </div>
          </div>
        </div>

        <div class="content">
          <?php echo nl2br(htmlspecialchars($message['body'] ?? '')); ?>
        </div>

        <?php if ($attachments): ?>
          <div class="content">
            <h3 class="title is-6">Attachments</h3>
            <ul>
              <?php foreach ($attachments as $attachment): ?>
                <li>
                  <a href="<?php echo BASE_PATH; ?>/routes/email/attachment.php?id=<?php echo (int) $attachment['id']; ?>">
                    <?php echo htmlspecialchars($attachment['filename'] ?? 'Attachment'); ?> (<?php echo htmlspecialchars(formatBytes((int) $attachment['file_size'])); ?>)
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p>Select an email to view its details.</p>
      <?php endif; ?>
    </div>
  </section>
</div>
