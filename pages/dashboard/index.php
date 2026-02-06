<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
logAction($currentUser['user_id'] ?? null, 'view_dashboard', 'User opened dashboard');
?>
<?php renderPageStart('Dashboard', ['bodyClass' => 'has-background-grey-dark has-text-light is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container">
          <div class="level mb-5">
            <div class="level-left">
              <h1 class="title is-3 has-text-light">Dashboard</h1>
            </div>
          </div>
          <div class="box has-background-dark has-text-light">
            <p class="has-text-grey-light">Dashboard content coming soon.</p>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
