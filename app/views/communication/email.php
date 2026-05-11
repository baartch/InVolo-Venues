<?php
require_once __DIR__ . '/../../models/core/htmx_class.php';
require_once __DIR__ . '/../../controllers/email/view_data.php';

$emailDetailWrapperId = 'email-detail-panel';

if (HTMX::isRequest()) {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/email_detail.php';
    return;
}
?>
<div class="box">
  <div class="columns is-variable is-3 email-columns">
    <?php require __DIR__ . '/email_sidebar.php'; ?>
    <?php require __DIR__ . '/email_list.php'; ?>
    <?php require __DIR__ . '/email_detail.php'; ?>
  </div>
</div>
