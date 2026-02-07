<?php
?>
<aside class="column is-3 email-column">
  <h3 class="title is-6">Mailbox</h3>
  <?php if (!$teamMailboxes): ?>
    <p>No mailboxes assigned.</p>
  <?php elseif ($mailboxCount > 1): ?>
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
  <?php else: ?>
    <p><?php echo htmlspecialchars($teamMailboxes[0]['team_name'] . ' · ' . $teamMailboxes[0]['name']); ?></p>
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
            <?php if ($folderKey === 'trash') {
                continue;
            }
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
            <?php if ($folderKey === 'sent'): ?>
              <?php
                $trashLink = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
                    'folder' => 'trash',
                    'page' => 1,
                    'message_id' => null
                ]));
                $trashCount = $folderCounts['trash'] ?? 0;
              ?>
              <li>
                <a href="<?php echo htmlspecialchars($trashLink); ?>" class="<?php echo $folder === 'trash' ? 'is-active' : ''; ?>">
                  <span><?php echo htmlspecialchars($folderOptions['trash'] ?? 'Trash bin'); ?></span>
                  <span class="tag is-pulled-right"><?php echo (int) $trashCount; ?></span>
                </a>
              </li>
            <?php endif; ?>
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
</aside>
