<?php
?>
<section class="column email-column">
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
          <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/email/mark_unread.php">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
            <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
            <input type="hidden" name="tab" value="email">
            <button type="submit" class="button">Mark as unread</button>
          </form>
          <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/email/delete.php" onsubmit="return confirm('Delete this email?');">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
            <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
            <input type="hidden" name="tab" value="email">
            <button type="submit" class="button" aria-label="Move email to trash" title="Move email to trash">
              <span class="icon"><i class="fa-solid fa-trash"></i></span>
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="content">
      <?php
        $messageBody = (string) ($message['body'] ?? '');
        if ($messageBody !== '' && $messageBody !== strip_tags($messageBody)) {
            echo $messageBody;
        } else {
            echo nl2br(htmlspecialchars($messageBody));
        }
      ?>
    </div>

    <?php if ($attachments): ?>
      <div class="content">
        <h3 class="title is-6">Attachments</h3>
        <ul>
          <?php foreach ($attachments as $attachment): ?>
            <li>
              <a href="<?php echo BASE_PATH; ?>/app/routes/email/attachment.php?id=<?php echo (int) $attachment['id']; ?>">
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
</section>
