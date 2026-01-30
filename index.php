<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/config/database.php';
logAction($currentUser['user_id'] ?? null, 'view_map', 'User opened map');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?php echo BASE_PATH; ?>/">
  <title>Venue Database - Map</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
    integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
    crossorigin="" />
  <link rel="stylesheet" href="public/styles.css">
  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
    integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
    crossorigin=""></script>

  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }
  </style>
</head>

<body class="map-page">
  <div class="app-layout">
    <aside class="sidebar">
      <nav class="sidebar-nav">
        <a href="index.php" class="sidebar-link active" aria-label="Map">
          <img src="public/assets/icon-map.svg" alt="Map">
        </a>
        <a href="user_management.php" class="sidebar-link" aria-label="User management">
          <img src="public/assets/icon-user.svg" alt="User management">
        </a>
      </nav>
      <div class="sidebar-spacer"></div>
      <a href="auth/logout.php" class="sidebar-link" aria-label="Logout">
        <img src="public/assets/icon-logout.svg" alt="Logout">
      </a>
    </aside>

    <main class="main-content">
      <div id="search-container">
        <div style="position: relative;">
          <input type="text" id="waypoint-search" placeholder="Search for venues...">
          <span class="keyboard-hint">Ctrl+K</span>
        </div>
        <div id="search-results"></div>
      </div>
      <div id="mapid"></div>
    </main>
  </div>

  <script type="module" src="public/map.js" defer></script>
</body>

</html>
