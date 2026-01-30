<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/config/database.php';
logAction($currentUser['user_id'] ?? null, 'view_user_management', 'User opened user management placeholder');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - User Management</title>
  <link rel="stylesheet" href="public/styles.css">
  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }

    .placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      font-size: 20px;
      color: var(--color-muted);
      text-align: center;
      padding: 20px;
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
        <a href="user_management.php" class="sidebar-link active" aria-label="User management">
          <img src="public/assets/icon-user.svg" alt="User management">
        </a>
      </nav>
      <div class="sidebar-spacer"></div>
      <a href="auth/logout.php" class="sidebar-link" aria-label="Logout">
        <img src="public/assets/icon-logout.svg" alt="Logout">
      </a>
    </aside>

    <main class="main-content">
      <div class="placeholder">
        User management will be available soon.
      </div>
    </main>
  </div>
</body>
</html>
