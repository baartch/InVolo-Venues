<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';
logAction($currentUser['user_id'] ?? null, 'view_dashboard', 'User opened dashboard');
?>
<?php renderPageStart('Dashboard', ['bodyClass' => 'is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container">
          <div class="level mb-5">
            <div class="level-left">
              <h1 class="title is-3">Dashboard</h1>
            </div>
          </div>
          <div class="box">
            <p>Dashboard content coming soon.</p>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
