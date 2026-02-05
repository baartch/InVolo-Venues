<?php
?>
<form method="POST" action="<?php echo BASE_PATH; ?>/routes/email/send.php" class="email-compose-form">
  <?php renderCsrfField(); ?>
  <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
  <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
  <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
  <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
  <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
  <input type="hidden" name="tab" value="email">
  <input type="hidden" name="draft_id" value="<?php echo $message && ($message['folder'] ?? '') === 'drafts' ? (int) $message['id'] : ''; ?>">

  <div class="form-group">
    <label for="email_to">To</label>
    <input type="text" id="email_to" name="to_emails" class="input" value="<?php echo htmlspecialchars($composeValues['to_emails']); ?>" required>
  </div>
  <div class="form-group">
    <label for="email_cc">Cc</label>
    <input type="text" id="email_cc" name="cc_emails" class="input" value="<?php echo htmlspecialchars($composeValues['cc_emails']); ?>">
  </div>
  <div class="form-group">
    <label for="email_bcc">Bcc</label>
    <input type="text" id="email_bcc" name="bcc_emails" class="input" value="<?php echo htmlspecialchars($composeValues['bcc_emails']); ?>">
  </div>
  <div class="form-group">
    <label for="email_subject">Subject</label>
    <input type="text" id="email_subject" name="subject" class="input" value="<?php echo htmlspecialchars($composeValues['subject']); ?>">
  </div>
  <div class="form-group">
    <label for="email_body">Body</label>
    <textarea id="email_body" name="body" class="input" rows="10"><?php echo htmlspecialchars($composeValues['body']); ?></textarea>
  </div>
  <div class="page-header-actions">
    <button type="submit" class="btn" name="action" value="send_email">Send</button>
    <button type="submit" class="btn" name="action" value="save_draft">Save Draft</button>
  </div>
</form>
