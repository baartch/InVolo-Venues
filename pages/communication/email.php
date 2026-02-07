<?php
require_once __DIR__ . '/../../src-php/email_view.php';
?>
<div class="box">
  <div class="columns is-variable is-3 email-columns has-text-primary-40">
    <?php require __DIR__ . '/email_sidebar.php'; ?>
    <?php require __DIR__ . '/email_list.php'; ?>
    <?php require __DIR__ . '/email_detail.php'; ?>
  </div>
</div>
