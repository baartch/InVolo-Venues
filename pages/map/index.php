<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';
logAction($currentUser['user_id'] ?? null, 'view_map', 'User opened map');
?>
<?php renderPageStart('Map', [
    'leaflet' => true,
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraStyles' => [BASE_PATH . '/public/css/map.css']
]); ?>
      <section class="hero is-fullheight">
        <div class="hero-body is-flex is-flex-direction-column">
          <div class="container is-fluid is-flex is-flex-direction-column is-flex-grow-1">
            <div class="level mb-4">
              <div class="level-left">
                <h1 class="title is-4">Map</h1>
              </div>
              <div class="level-right">
                <div class="field has-addons">
                  <div class="control has-icons-left is-expanded">
                    <div class="dropdown is-fullwidth map-search-dropdown" data-search-dropdown>
                      <div class="dropdown-trigger">
                        <input class="input" type="text" id="waypoint-search" placeholder="Search for venues...">
                        <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
                      </div>
                      <div id="search-results" class="dropdown-menu is-hidden" role="menu">
                        <div class="dropdown-content"></div>
                      </div>
                    </div>
                  </div>
                  <p class="control">
                    <span class="button is-static">Ctrl+K</span>
                  </p>
                </div>
              </div>
            </div>
            <div id="map-zoom-hint" class="notification is-hidden" role="status" aria-live="polite" aria-hidden="true">
              Zoom in to load venues.
            </div>
            <div id="mapid" class="box is-flex-grow-1"></div>
          </div>
        </div>
      </section>
      <script type="module" src="<?php echo BASE_PATH; ?>/public/js/map.js" defer></script>
<?php renderPageEnd(); ?>
