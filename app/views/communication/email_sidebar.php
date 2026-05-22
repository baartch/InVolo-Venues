<?php
?>
<aside class="column is-3 email-column">
  <h3 class="title is-6">Mailbox</h3>
  <?php if (!$teamMailboxes): ?>
    <p>No mailboxes assigned.</p>
  <?php else: ?>
    <div class="email-mailbox-avatar-list">
      <?php foreach ($teamMailboxes as $mailbox): ?>
        <?php
          $mailboxId = (int) ($mailbox['id'] ?? 0);
          $displayLabel = trim((string) ($mailbox['display_name'] ?? ''));
          $nameLabel = $displayLabel !== '' ? $displayLabel : ($mailbox['name'] ?? '');
          $label = $mailbox['user_id']
              ? 'Personal · ' . $nameLabel
              : (($mailbox['team_name'] ?? 'Team') . ' · ' . $nameLabel);
          $isActive = (int) ($selectedMailbox['id'] ?? 0) === $mailboxId;
          $initialSource = $displayLabel !== '' ? $displayLabel : $nameLabel;
          $initial = strtoupper(substr(trim((string) $initialSource), 0, 1));
          if ($initial === '') {
              $initial = 'M';
          }
          $indicator = $mailboxIndicators[$mailboxId] ?? ['unread_count' => 0, 'new_count' => 0];
          $hasNew = (int) ($indicator['new_count'] ?? 0) > 0;
          $hasUnread = (int) ($indicator['unread_count'] ?? 0) > 0;
          $indicatorClass = '';
          if ($hasNew) {
              $indicatorClass = 'is-new';
          } elseif ($hasUnread) {
              $indicatorClass = 'is-unread';
          }
          $mailboxLink = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
              'mailbox_id' => $mailboxId,
              'page' => 1,
              'message_id' => null
          ]));
        ?>
        <a
          href="<?php echo htmlspecialchars($mailboxLink); ?>"
          class="email-mailbox-avatar<?php echo $isActive ? ' is-active' : ''; ?>"
          title="<?php echo htmlspecialchars($label); ?>"
          aria-label="<?php echo htmlspecialchars($label); ?>"
          data-mailbox-avatar
          data-mailbox-id="<?php echo (int) $mailboxId; ?>"
        >
          <span class="email-mailbox-avatar-initial"><?php echo htmlspecialchars($initial); ?></span>
          <?php if ($indicatorClass !== ''): ?>
            <span class="email-mailbox-avatar-indicator <?php echo htmlspecialchars($indicatorClass); ?>"></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
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
      <progress class="progress is-small mb-0" value="<?php echo (int) $quotaUsed; ?>" max="<?php echo (int) $quotaTotal; ?>"></progress>
      <p class="is-size-7"><?php echo htmlspecialchars(formatBytes($quotaUsed)); ?> / <?php echo htmlspecialchars(formatBytes($quotaTotal)); ?></p>
    </div>
  <?php endif; ?>
</aside>
