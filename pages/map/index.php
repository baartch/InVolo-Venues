<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/database.php';
require_once __DIR__ . '/../../src-php/layout.php';
logAction($currentUser['user_id'] ?? null, 'view_map', 'User opened map');
?>
<?php renderPageStart('Map', ['leaflet' => true, 'bodyClass' => 'has-background-grey-dark has-text-light is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="hero is-fullheight is-dark">
        <div class="hero-body is-flex is-flex-direction-column">
          <div class="container is-fluid is-flex is-flex-direction-column is-flex-grow-1">
            <div class="level mb-4">
              <div class="level-left">
                <h1 class="title is-4 has-text-light">Map</h1>
              </div>
              <div class="level-right">
                <div class="field has-addons">
                  <div class="control has-icons-left">
                    <input class="input has-background-grey-darker has-text-light" type="text" id="waypoint-search" placeholder="Search for venues...">
                    <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
                  </div>
                  <p class="control">
                    <span class="button is-static">Ctrl+K</span>
                  </p>
                </div>
              </div>
            </div>
            <div id="search-results" class="panel is-hidden has-background-dark has-text-light"></div>
            <div id="map-zoom-hint" class="notification is-warning is-light is-hidden" role="status" aria-live="polite" aria-hidden="true">
              Zoom in to load venues.
            </div>
            <div id="mapid" class="box is-flex-grow-1 has-background-grey-darker"></div>
          </div>
        </div>
      </section>
      <script type="module" src="<?php echo BASE_PATH; ?>/public/js/map.js" defer></script>
<?php renderPageEnd(); ?>
