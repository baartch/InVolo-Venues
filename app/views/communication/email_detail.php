<?php
$emailDetailWrapperTag = $emailDetailWrapperTag ?? 'section';
$emailDetailWrapperClass = $emailDetailWrapperClass ?? 'column email-column email-detail-column';
if (!empty($composeMode)) {
    $emailDetailWrapperClass .= ' email-compose-column';
}
$emailDetailWrapperId = $emailDetailWrapperId ?? null;
$emailDetailIncludeLinkEditor = $emailDetailIncludeLinkEditor ?? true;
$emailDetailShowActions = $emailDetailShowActions ?? true;
$emailDetailShowLinks = $emailDetailShowLinks ?? true;
$emailDetailShowAttachments = $emailDetailShowAttachments ?? true;
$emailDetailSubjectUrl = $emailDetailSubjectUrl ?? null;
$messageLinks = $messageLinks ?? [];
$attachments = $attachments ?? [];
$composeMode = $composeMode ?? false;
$templates = $templates ?? [];
$selectedMailbox = $selectedMailbox ?? null;

$wrapperAttributes = 'class="' . htmlspecialchars($emailDetailWrapperClass) . '" data-email-detail';
if ($emailDetailWrapperId) {
    $wrapperAttributes .= ' id="' . htmlspecialchars($emailDetailWrapperId) . '"';
}

echo '<' . htmlspecialchars($emailDetailWrapperTag) . ' ' . $wrapperAttributes . '>';
?>
  <?php if (!$selectedMailbox): ?>
    <div class="email-detail-empty">
      <span class="icon is-large has-text-grey"><i class="fa-solid fa-envelope fa-2x"></i></span>
      <p class="has-text-grey mt-2">Pick a mailbox to view details.</p>
    </div>
  <?php elseif ($composeMode): ?>
    <div class="level mb-3">
      <div class="level-left">
        <h2 class="title is-5">Compose</h2>
      </div>
    </div>

    <?php $composeCancelUrl = $baseEmailUrl . '?' . http_build_query($baseQuery); ?>

    <?php if ($templates): ?>
      <form method="GET" action="<?php echo htmlspecialchars($baseEmailUrl); ?>" class="field has-addons mb-4" data-email-template-form>
        <input type="hidden" name="tab" value="email">
        <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
        <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
        <input type="hidden" name="compose" value="1">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <input type="hidden" name="to" value="<?php echo htmlspecialchars((string) ($composeValues['to_emails'] ?? '')); ?>">
        <input type="hidden" name="cc" value="<?php echo htmlspecialchars((string) ($composeValues['cc_emails'] ?? '')); ?>">
        <input type="hidden" name="bcc" value="<?php echo htmlspecialchars((string) ($composeValues['bcc_emails'] ?? '')); ?>">
        <div class="control is-expanded">
          <div class="select is-fullwidth">
            <select name="template_id" data-email-template-select>
              <option value="">Select template</option>
              <?php foreach ($templates as $template): ?>
                <option value="<?php echo (int) $template['id']; ?>" <?php echo $templateId === (int) $template['id'] ? 'selected' : ''; ?> data-subject="<?php echo htmlspecialchars((string) ($template['subject'] ?? '')); ?>" data-body="<?php echo htmlspecialchars((string) ($template['body'] ?? '')); ?>">
                  <?php echo htmlspecialchars($template['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    <?php endif; ?>

    <?php require __DIR__ . '/email_compose_form.php'; ?>
  <?php elseif ($message): ?>
    <?php
      $senderName = trim((string) ($message['from_name'] ?? ''));
      $senderEmail = trim((string) ($message['from_email'] ?? ''));
      $senderLabel = $senderEmail !== '' ? $senderEmail : $senderName;
      if ($senderName !== '' && $senderEmail !== '') {
          $senderLabel = $senderName . ' <' . $senderEmail . '>';
      } elseif ($senderName !== '') {
          $senderLabel = $senderName;
      }

      $recipientLabel = trim((string) ($message['to_emails'] ?? ''));

      $dateLabel = $message['received_at'] ?? $message['sent_at'] ?? $message['created_at'] ?? '';

      $linkItems = [];
      $linkEditorLinks = [];
      if (!empty($messageLinks)) {
          foreach ($messageLinks as $link) {
              $linkItems[] = [
                  'type' => $link['type'],
                  'label' => $link['label'],
                  'url' => buildLinkUrl($link)
              ];
              $linkEditorLinks[] = [
                  'type' => $link['type'],
                  'id' => (int) $link['id'],
                  'label' => $link['label']
              ];
          }
      }

      $linkEditorConversationId = !empty($message['conversation_id']) ? (int) $message['conversation_id'] : null;
      $linkEditorConversationLabel = '';
      if ($linkEditorConversationId !== null && isset($pdo)) {
          try {
              $convStmt = $pdo->prepare('SELECT subject FROM email_conversations WHERE id = :id LIMIT 1');
              $convStmt->execute([':id' => $linkEditorConversationId]);
              $convRow = $convStmt->fetch();
              $linkEditorConversationLabel = $convRow ? trim((string) ($convRow['subject'] ?? '')) : '';
              if ($linkEditorConversationLabel === '') {
                  $linkEditorConversationLabel = 'Conversation #' . $linkEditorConversationId;
              }
          } catch (Throwable $e) {
              $linkEditorConversationLabel = 'Conversation #' . $linkEditorConversationId;
          }
      }

      $linkIcons = getLinkIcons();

      if ($linkEditorConversationId !== null) {
          $conversationUrl = BASE_PATH . '/app/controllers/communication/index.php?tab=conversations&conversation_id=' . $linkEditorConversationId;
          $linkItems = array_merge([
              [
                  'type' => 'conversation',
                  'label' => $linkEditorConversationLabel,
                  'url' => $conversationUrl
              ]
          ], $linkItems);
      }

      $initials = '';
      if ($senderName !== '') {
          $parts = explode(' ', $senderName);
          $initials = strtoupper(substr($parts[0], 0, 1));
          if (count($parts) > 1) {
              $initials .= strtoupper(substr(end($parts), 0, 1));
          }
      } elseif ($senderEmail !== '') {
          $initials = strtoupper(substr($senderEmail, 0, 1));
      }
    ?>

    <!-- Header: Subject + Actions -->
    <div class="email-detail-header">
      <div class="email-detail-subject-row">
        <?php if (!empty($emailDetailSubjectUrl)): ?>
          <h2 class="email-detail-subject"><a href="<?php echo htmlspecialchars((string) $emailDetailSubjectUrl); ?>"><?php echo htmlspecialchars($message['subject'] ?? '(No subject)'); ?></a></h2>
        <?php else: ?>
          <h2 class="email-detail-subject"><?php echo htmlspecialchars($message['subject'] ?? '(No subject)'); ?></h2>
        <?php endif; ?>
        <?php if ($emailDetailShowActions): ?>
          <div class="field has-addons email-detail-actions">
            <div class="control">
              <a
                href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1, 'reply' => $message['id']]))); ?>"
                class="button is-small"
                aria-label="Reply"
                title="Reply"
              >
                <span class="icon is-small"><i class="fa-solid fa-reply"></i></span>
              </a>
            </div>

            <div class="control">
              <a
                href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1, 'forward' => $message['id']]))); ?>"
                class="button is-small"
                aria-label="Forward"
                title="Forward"
              >
                <span class="icon is-small"><i class="fa-solid fa-forward"></i></span>
              </a>
            </div>

            <form class="control" method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/email/mark_unread.php">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
              <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
              <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
              <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
              <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
              <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
              <input type="hidden" name="tab" value="email">
              <button type="submit" class="button is-small" aria-label="Mark as unread" title="Mark as unread">
                <span class="icon is-small"><i class="fa-solid fa-envelope"></i></span>
              </button>
            </form>

            <form class="control" method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/email/delete.php">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
              <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
              <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
              <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
              <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
              <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
              <input type="hidden" name="tab" value="email">
              <button type="submit" class="button is-small" aria-label="Move email to trash" title="Move email to trash">
                <span class="icon is-small"><i class="fa-solid fa-trash"></i></span>
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- Metadata: Sender / Recipients / Date / Links -->
      <div class="detail-meta">
        <div class="detail-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div class="detail-meta-info">
          <div class="detail-meta-row">
            <span class="detail-meta-label">From:</span>
            <span class="detail-meta-value"><?php echo htmlspecialchars($senderLabel); ?></span>
          </div>
          <?php if ($recipientLabel !== ''): ?>
            <div class="detail-meta-row">
              <span class="detail-meta-label">To:</span>
              <span class="detail-meta-value"><?php echo htmlspecialchars($recipientLabel); ?></span>
            </div>
          <?php endif; ?>
          <div class="detail-meta-row">
            <span class="detail-meta-label">Date:</span>
            <span class="detail-meta-value"><?php echo htmlspecialchars($dateLabel); ?></span>
          </div>
          <?php if ($emailDetailShowLinks): ?>
            <div class="detail-meta-row">
              <span class="detail-meta-label">Links:</span>
              <span class="detail-meta-value detail-link-list">
                <?php if (!$linkItems): ?>
                  <span class="has-text-grey is-size-7">No links yet</span>
                <?php else: ?>
                  <?php foreach ($linkItems as $index => $link): ?>
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" class="detail-link-pill">
                      <span class="icon is-small"><i class="fa-solid <?php echo htmlspecialchars($linkIcons[$link['type']] ?? 'fa-link'); ?>"></i></span>
                      <span><?php echo htmlspecialchars($link['label']); ?></span>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
                <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-email-' . (int) $message['id']); ?>" title="Edit links">
                  <span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span>
                </a>
              </span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="email-detail-body content" data-email-detail-body>
      <?php
        $messageBody = (string) ($message['body_html'] ?? '');
        if ($messageBody === '') {
            $messageBody = (string) ($message['body'] ?? '');
        }
        if ($messageBody !== '' && $messageBody !== strip_tags($messageBody)) {
            echo $messageBody;
        } else {
            echo formatPlainEmailBodyWithQuotes($messageBody);
        }
      ?>
    </div>
    <div class="email-detail-quote-toggle is-hidden">
      <button type="button" class="button is-small is-light" data-email-quote-toggle data-email-quote-state="collapsed">
        <span class="icon is-small"><i class="fa-solid fa-quote-left"></i></span>
      </button>
    </div>

    <!-- Attachments -->
    <?php if ($emailDetailShowAttachments && $attachments): ?>
      <div class="email-detail-attachments">
        <span class="email-detail-attachments-label">
          <span class="icon is-small"><i class="fa-solid fa-paperclip"></i></span>
          <span><?php echo count($attachments); ?> attachment<?php echo count($attachments) !== 1 ? 's' : ''; ?></span>
        </span>
        <div class="email-detail-attachments-list">
          <?php foreach ($attachments as $attachment): ?>
            <a href="<?php echo BASE_PATH; ?>/app/controllers/email/attachment.php?id=<?php echo (int) $attachment['id']; ?>" class="email-detail-attachment-chip" target="_blank" rel="noopener noreferrer">
              <span class="icon is-small"><i class="fa-solid fa-file"></i></span>
              <span><?php echo htmlspecialchars($attachment['filename'] ?? 'Attachment'); ?></span>
              <span class="email-detail-attachment-size"><?php echo htmlspecialchars(formatBytes((int) $attachment['file_size'])); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($emailDetailIncludeLinkEditor && $selectedMailbox): ?>
      <?php
        $linkEditorSourceType = 'email';
        $linkEditorSourceId = (int) $message['id'];
        $linkEditorMailboxId = (int) $selectedMailbox['id'];
        $linkEditorSearchTypes = 'contact,venue,email,task';
        require __DIR__ . '/../../partials/link_editor_modal.php';
      ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="email-detail-empty">
      <span class="icon is-large has-text-grey"><i class="fa-solid fa-envelope-open fa-2x"></i></span>
      <p class="has-text-grey mt-2">Select an email to view its details.</p>
    </div>
  <?php endif; ?>
<?php echo '</' . htmlspecialchars($emailDetailWrapperTag) . '>'; ?>
