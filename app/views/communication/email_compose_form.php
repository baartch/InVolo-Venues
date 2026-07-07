<?php
?>
<form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/email/send.php" data-email-compose-form>
  <?php renderCsrfField(); ?>
  <?php if (!empty($composeConversationId)): ?>
    <input type="hidden" name="conversation_id" value="<?php echo (int) $composeConversationId; ?>" data-email-conversation-id>
  <?php else: ?>
    <input type="hidden" name="conversation_id" value="" data-email-conversation-id>
  <?php endif; ?>

  <?php if (isset($teamMailboxes) && count($teamMailboxes) > 1): ?>
    <div class="field">
      <div class="control">
        <div class="select is-fullwidth">
          <select id="compose_mailbox_id" name="mailbox_id">
            <?php foreach ($teamMailboxes as $mailbox): ?>
              <?php
                $displayLabel = trim((string) ($mailbox['display_name'] ?? ''));
                $nameLabel = $displayLabel !== '' ? $displayLabel : ($mailbox['name'] ?? '');
                $label = $mailbox['user_id']
                    ? 'Personal · ' . $nameLabel
                    : (($mailbox['team_name'] ?? 'Team') . ' · ' . $nameLabel);
              ?>
              <option value="<?php echo (int) $mailbox['id']; ?>" <?php echo (int) $selectedMailbox['id'] === (int) $mailbox['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  <?php else: ?>
    <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
  <?php endif; ?>

  <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
  <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
  <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
  <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
  <input type="hidden" name="tab" value="email">
  <input type="hidden" name="draft_id" value="<?php echo $message && ($message['folder'] ?? '') === 'drafts' ? (int) $message['id'] : ''; ?>">
  <input type="hidden" name="schedule_date" value="<?php echo htmlspecialchars($composeValues['schedule_date'] ?? ''); ?>">
  <input type="hidden" name="schedule_time" value="<?php echo htmlspecialchars($composeValues['schedule_time'] ?? ''); ?>">

  <div class="field" data-email-recipient-toggle>
    <div class="control has-icons-left has-icons-right">
      <div class="dropdown is-fullwidth email-recipient-dropdown" data-email-lookup data-lookup-url="<?php echo BASE_PATH; ?>/app/controllers/email/email_recipient_lookup.php">
        <div class="dropdown-trigger">
          <div class="email-recipient-row">
            <input
              class="input"
              type="text"
              id="email_to"
              name="to_emails"
              placeholder="To"
              value="<?php echo htmlspecialchars($composeValues['to_emails']); ?>"
              data-email-input
            >
            <button type="button" class="button is-small email-recipient-toggle" data-email-recipient-toggle-button aria-expanded="false" aria-controls="email-recipient-extra">
              <span class="icon is-small"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
          </div>
          <span class="icon is-small is-left">
            <i class="fas fa-envelope"></i>
          </span>
          <span class="icon is-small is-right is-hidden" data-email-icon>
            <i class="fas fa-exclamation-triangle"></i>
          </span>
        </div>
        <div class="dropdown-menu is-hidden" role="menu">
          <div class="dropdown-content"></div>
        </div>
      </div>
    </div>
    <p class="help is-danger is-hidden" data-email-help>This email is invalid</p>
  </div>
  <div id="email-recipient-extra" class="email-recipient-extra is-hidden" data-email-recipient-extra>
    <div class="field">
      <div class="control has-icons-left has-icons-right">
        <div class="dropdown is-fullwidth email-recipient-dropdown" data-email-lookup data-lookup-url="<?php echo BASE_PATH; ?>/app/controllers/email/email_recipient_lookup.php">
          <div class="dropdown-trigger">
            <input
              class="input"
              type="text"
              id="email_cc"
              name="cc_emails"
              placeholder="Cc"
              value="<?php echo htmlspecialchars($composeValues['cc_emails']); ?>"
              data-email-input
            >
            <span class="icon is-small is-left" title="Cc">
              <i class="fas fa-users"></i>
            </span>
            <span class="icon is-small is-right is-hidden" data-email-icon>
              <i class="fas fa-exclamation-triangle"></i>
            </span>
          </div>
          <div class="dropdown-menu is-hidden" role="menu">
            <div class="dropdown-content"></div>
          </div>
        </div>
      </div>
      <p class="help is-danger is-hidden" data-email-help>This email is invalid</p>
    </div>
    <div class="field">
      <div class="control has-icons-left has-icons-right">
        <div class="dropdown is-fullwidth email-recipient-dropdown" data-email-lookup data-lookup-url="<?php echo BASE_PATH; ?>/app/controllers/email/email_recipient_lookup.php">
          <div class="dropdown-trigger">
            <input
              class="input"
              type="text"
              id="email_bcc"
              name="bcc_emails"
              placeholder="Bcc"
              value="<?php echo htmlspecialchars($composeValues['bcc_emails']); ?>"
              data-email-input
            >
            <span class="icon is-small is-left" title="Bcc">
              <i class="fas fa-user-secret"></i>
            </span>
            <span class="icon is-small is-right is-hidden" data-email-icon>
              <i class="fas fa-exclamation-triangle"></i>
            </span>
          </div>
          <div class="dropdown-menu is-hidden" role="menu">
            <div class="dropdown-content"></div>
          </div>
        </div>
      </div>
      <p class="help is-danger is-hidden" data-email-help>This email is invalid</p>
    </div>
  </div>
  <div class="field">
    <div class="control">
      <input type="text" id="email_subject" name="subject" class="input" placeholder="Subject" value="<?php echo htmlspecialchars($composeValues['subject']); ?>">
    </div>
  </div>
  <div class="field">
    <div class="control is-flex is-align-items-center is-justify-content-space-between">
      <label class="checkbox">
        <input type="checkbox" name="start_new_conversation" value="1" <?php echo !empty($composeValues['start_new_conversation']) ? 'checked' : ''; ?>>
        Start a new conversation
      </label>
    </div>
    <div class="is-size-7 email-link-metadata" data-email-links>
      Links:
      <span
        class="detail-link-list"
        data-email-links-list
        data-team-id="<?php echo (int) ($selectedMailbox['team_id'] ?? 0); ?>"
        data-contact-url-base="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/communication/index.php'); ?>"
        data-venue-url-base="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/venues/index.php'); ?>"
        data-email-url-base="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/communication/index.php'); ?>"
        data-task-url-base="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/team/index.php'); ?>"
        data-conversation-url-base="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/communication/index.php'); ?>"
      >
        <span class="has-text-grey is-size-7">No links yet</span>
      </span>
      <span class="mr-1" data-email-conversation-pill>
        <?php if (!empty($composeValues['conversation_id'])): ?>
          <span class="detail-link-pill">
            <span class="icon is-small"><i class="fa-solid fa-comments"></i></span>
            <span><?php echo htmlspecialchars((string) ($composeValues['conversation_label'] ?? ('Conversation #' . (int) $composeValues['conversation_id']))); ?></span>
          </span>
        <?php endif; ?>
      </span>
      <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-email-0'); ?>" title="Edit links">
        <span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span>
      </a>
    </div>
    <div id="email-compose-link-collector" data-email-link-inputs data-link-editor-collector>
      <?php if (!empty($composeValues['link_items']) && is_array($composeValues['link_items'])): ?>
        <?php foreach ($composeValues['link_items'] as $linkItem): ?>
          <?php
            $linkType = (string) ($linkItem['type'] ?? '');
            $linkId = (int) ($linkItem['id'] ?? 0);
            $linkLabel = trim((string) ($linkItem['label'] ?? ''));
            if ($linkType === '' || $linkId <= 0) {
                continue;
            }
          ?>
          <input
            type="hidden"
            name="link_items[]"
            value="<?php echo htmlspecialchars($linkType . ':' . $linkId); ?>"
            data-link-label="<?php echo htmlspecialchars($linkLabel !== '' ? $linkLabel : ($linkType . ' #' . $linkId)); ?>"
          >
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="field">
    <div class="control">
      <textarea id="email_body" name="body" class="textarea" rows="10"><?php echo htmlspecialchars($composeValues['body']); ?></textarea>
    </div>
    <div class="email-detail-quote-toggle is-hidden" data-email-compose-quote-toggle-wrapper>
      <button type="button" class="button is-small is-light" data-email-compose-quote-toggle data-email-compose-quote-state="collapsed">
        <span class="icon is-small"><i class="fa-solid fa-quote-left"></i></span>
      </button>
    </div>
  </div>
  <div class="field">
    <div class="control">
      <div class="field has-addons">
        <div class="control">
          <div class="dropdown" data-email-send-menu>
            <div class="dropdown-trigger">
              <button type="button" class="button is-primary" aria-haspopup="true" aria-controls="send-email-menu">
                <span>Send</span>
                <span class="icon is-small"><i class="fa-solid fa-angle-down"></i></span>
              </button>
            </div>
            <div class="dropdown-menu" id="send-email-menu" role="menu">
              <div class="dropdown-content">
                <button type="submit" class="dropdown-item" name="action" value="send_email">Immediately</button>
                <button type="button" class="dropdown-item" data-email-schedule-trigger>On schedule</button>
              </div>
            </div>
          </div>
        </div>
        <div class="control">
          <button type="submit" class="button" name="action" value="save_draft">Save Draft</button>
        </div>
        <div class="control">
          <a href="<?php echo htmlspecialchars($composeCancelUrl ?? ($baseEmailUrl . '?' . http_build_query($baseQuery))); ?>" class="button">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
  $composeLinkEditorSourceType = 'email';
  $composeLinkEditorSourceId = 0;
  $composeLinkEditorMailboxId = (int) ($selectedMailbox['id'] ?? 0);
  $composeLinkEditorLinks = [];
  if (!empty($composeValues['link_items']) && is_array($composeValues['link_items'])) {
      foreach ($composeValues['link_items'] as $linkItem) {
          $type = (string) ($linkItem['type'] ?? '');
          $id = (int) ($linkItem['id'] ?? 0);
          $label = trim((string) ($linkItem['label'] ?? ''));
          if ($type === '' || $id <= 0) {
              continue;
          }
          $composeLinkEditorLinks[] = [
              'type' => $type,
              'id' => $id,
              'label' => $label !== '' ? $label : ($type . ' #' . $id)
          ];
      }
  }
  $composeLinkEditorConversationId = !empty($composeValues['conversation_id'])
      ? (int) $composeValues['conversation_id']
      : null;
  $composeLinkEditorConversationLabel = (string) ($composeValues['conversation_label'] ?? '');
  $linkEditorSourceType = $composeLinkEditorSourceType;
  $linkEditorSourceId = $composeLinkEditorSourceId;
  $linkEditorMailboxId = $composeLinkEditorMailboxId;
  $linkEditorLinks = $composeLinkEditorLinks;
  $linkEditorConversationId = $composeLinkEditorConversationId;
  $linkEditorConversationLabel = $composeLinkEditorConversationLabel;
  $linkEditorSearchTypes = 'contact,venue';
  $linkEditorLocalOnly = true;
  $linkEditorCollectorSelector = '#email-compose-link-collector';
  require __DIR__ . '/../../partials/link_editor_modal.php';
?>

<div class="modal" data-email-schedule-modal>
  <div class="modal-background" data-email-schedule-close></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Schedule send</p>
      <button class="delete" aria-label="close" data-email-schedule-close></button>
    </header>
    <section class="modal-card-body">
      <div class="field">
        <label class="label" for="email_schedule_date">Date</label>
        <div class="control">
          <input class="input" type="date" id="email_schedule_date" name="schedule_date" data-email-schedule-date>
        </div>
      </div>
      <div class="field">
        <label class="label" for="email_schedule_time">Time</label>
        <div class="control">
          <input class="input" type="time" id="email_schedule_time" name="schedule_time" data-email-schedule-time>
        </div>
      </div>
      <p class="help" data-email-schedule-help>Select when to send this email.</p>
    </section>
    <footer class="modal-card-foot">
      <button type="button" class="button" data-email-schedule-close>Cancel</button>
      <button type="button" class="button is-primary" data-email-schedule-submit>Schedule</button>
    </footer>
  </div>
</div>
