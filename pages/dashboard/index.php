<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';
logAction($currentUser['user_id'] ?? null, 'view_dashboard', 'User opened dashboard');
?>
<?php renderPageStart('Venue Database - Dashboard', ['theme' => getCurrentTheme($currentUser['ui_theme'] ?? null)]); ?>
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Dashboard</h1>
        </div>
        <div class="card card-section">
          <p class="text-muted">Dashboard content coming soon.</p>
        </div>
      </div>
<?php renderPageEnd(); ?>
