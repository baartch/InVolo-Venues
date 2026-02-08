<?php
?>
<section class="column is-4 email-column">
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
        <?php
          $toggleSortKey = $sortKey === 'received_asc' ? 'received_desc' : 'received_asc';
          $sortArrow = $sortKey === 'received_asc' ? '↑' : '↓';
          $sortLink = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
              'sort' => $toggleSortKey,
              'page' => 1
          ]));
        ?>
        <a href="<?php echo htmlspecialchars($sortLink); ?>" aria-label="Toggle sort order" title="Toggle sort order">
          <?php echo htmlspecialchars($sortArrow); ?>
        </a>
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
              $isUnread = !$row['is_read'] && $folder === 'inbox';
              $itemClass = $isUnread ? 'email-list-item warning' : 'email-list-item';
            ?>
            <li>
              <div class="<?php echo $itemClass; ?>">
                <a href="<?php echo htmlspecialchars($messageLink); ?>" class="<?php echo (int) $row['id'] === $selectedMessageId ? 'is-active' : ''; ?>">
                  <div class="is-flex is-justify-content-space-between">
                    <div>
                      <div class="<?php echo $isUnread ? 'has-text-weight-bold' : 'has-text-weight-semibold'; ?>"><?php echo htmlspecialchars($row['subject'] ?? '(No subject)'); ?></div>
                      <div class="is-size-7"><?php echo htmlspecialchars($displayName); ?></div>
                    </div>
                    <div class="is-size-7 email-meta-right">
                      <div><?php echo htmlspecialchars($dateLabel); ?></div>
                      <div class="email-delete-action">
                        <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/email/delete.php" onsubmit="return confirm('Move this email to trash?');">
                          <?php renderCsrfField(); ?>
                          <input type="hidden" name="email_id" value="<?php echo (int) $row['id']; ?>">
                          <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
                          <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
                          <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                          <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                          <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                          <input type="hidden" name="tab" value="email">
                          <button type="submit" class="button is-small" aria-label="Move email to trash" title="Move email to trash">
                            <span class="icon"><i class="fa-solid fa-trash"></i></span>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                </a>
              </div>
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
</section>
