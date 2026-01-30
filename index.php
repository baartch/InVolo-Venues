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
  <title>Venue Crawler - Map</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
    integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
    crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
    integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
    crossorigin=""></script>

  <style>
    html,
    body {
      height: 100%;
      margin: 0;
    }

    #mapid {
      min-height: 100%;
    }

    #search-container {
      position: absolute;
      top: 10px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 1000;
      width: 90%;
      max-width: 400px;
    }

    #waypoint-search {
      width: 100%;
      padding: 12px 20px;
      padding-right: 70px;
      font-size: 16px;
      border: 2px solid #ccc;
      border-radius: 25px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      outline: none;
      transition: border-color 0.3s;
      box-sizing: border-box;
    }

    .keyboard-hint {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      font-size: 11px;
      color: #999;
      background: #f5f5f5;
      padding: 3px 7px;
      border-radius: 3px;
      border: 1px solid #ddd;
      font-family: 'Courier New', monospace;
      font-weight: 600;
      transition: opacity 0.3s;
    }

    #waypoint-search:focus + .keyboard-hint {
      opacity: 0;
    }

    #waypoint-search:focus {
      border-color: #4CAF50;
    }

    #search-results {
      display: none;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      max-height: 300px;
      overflow-y: auto;
      margin-top: 5px;
      width: 100%;
      box-sizing: border-box;
    }

    .search-result-item {
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
    }

    .search-result-item:hover {
      background-color: #f0f0f0;
    }

    .search-result-item.selected {
      background-color: #4CAF50;
      color: white;
    }

    .search-result-item:last-child {
      border-bottom: none;
    }

    #logout-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 1000;
      padding: 10px 20px;
      background: white;
      border: 2px solid #ccc;
      border-radius: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      color: #333;
      text-decoration: none;
      transition: all 0.3s;
    }

    #logout-btn:hover {
      background: #f44336;
      color: white;
      border-color: #f44336;
    }
  </style>
</head>

<body>
  <a href="auth/logout.php" id="logout-btn">🚪 Logout</a>
  <div id="search-container">
    <div style="position: relative;">
      <input type="text" id="waypoint-search" placeholder="Search for venues...">
      <span class="keyboard-hint">Ctrl+K</span>
    </div>
    <div id="search-results"></div>
  </div>
  <div id="mapid"></div>

  <script type="module" src="public/map.js" defer></script>
</body>

</html>
