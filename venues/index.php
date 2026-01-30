<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

logAction($currentUser['user_id'] ?? null, 'view_venues', 'User opened venue management');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - Venues</title>
  <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/public/styles.css">
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
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

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
