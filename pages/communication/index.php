<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';

logAction($currentUser['user_id'] ?? null, 'view_communication', 'User opened communication page');
?>
<?php renderPageStart('Communication', ['theme' => getCurrentTheme($currentUser['ui_theme'] ?? null)]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Communication</h1>
        </div>
        <div class="card card-section">
          <p>Communication tools are coming soon.</p>
        </div>
      </div>
<?php renderPageEnd(); ?>
