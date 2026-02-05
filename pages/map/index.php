<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
require_once __DIR__ . '/../../src-php/theme.php';
logAction($currentUser['user_id'] ?? null, 'view_map', 'User opened map');
?>
<?php renderPageStart('Map', ['leaflet' => true, 'theme' => getCurrentTheme($currentUser['ui_theme'] ?? null)]); ?>
      <div id="search-container">
        <div class="search-input-wrapper">
          <input type="text" id="waypoint-search" placeholder="Search for venues...">
          <span class="keyboard-hint">Ctrl+K</span>
        </div>
        <div id="search-results"></div>
      </div>
      <div id="map-zoom-hint" class="map-zoom-hint" role="status" aria-live="polite" aria-hidden="true">
        Zoom in to load venues.
      </div>
      <div id="mapid"></div>
      <script type="module" src="<?php echo BASE_PATH; ?>/public/js/map.js" defer></script>
<?php renderPageEnd(); ?>
