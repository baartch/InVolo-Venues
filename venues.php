<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/config/database.php';

logAction($currentUser['user_id'] ?? null, 'view_venues', 'User opened venue management');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - Venues</title>
  <link rel="stylesheet" href="public/styles.css">
  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }

    .content-wrapper {
      padding: 32px;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }

    .page-header h1 {
      font-size: 24px;
      color: var(--color-primary-dark);
    }

    .placeholder-card {
      padding: 24px;
    }
  </style>
</head>
<body class="map-page">
  <div class="app-layout">
    <aside class="sidebar">
      <nav class="sidebar-nav">
        <a href="index.php" class="sidebar-link" aria-label="Map">
          <img src="public/assets/icon-map.svg" alt="Map">
        </a>
        <a href="venues.php" class="sidebar-link active" aria-label="Venues">
          <img src="public/assets/icon-venues.svg" alt="Venues">
        </a>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <a href="user_management.php" class="sidebar-link" aria-label="User management">
            <img src="public/assets/icon-user.svg" alt="User management">
          </a>
        <?php endif; ?>
      </nav>
      <div class="sidebar-spacer"></div>
      <a href="auth/logout.php" class="sidebar-link" aria-label="Logout">
        <img src="public/assets/icon-logout.svg" alt="Logout">
      </a>
    </aside>

    <main class="main-content">
      <div class="content-wrapper">
        <div class="page-header">
          <h1>Venue Management</h1>
        </div>
        <div class="card placeholder-card">
          <p>Venue management tools will appear here.</p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
