<div class="level mb-3">
  <div class="level-left">
    <h2 class="title is-5">All Venues</h2>
  </div>
  <div class="level-right">
    <form method="GET" action="<?php echo BASE_PATH; ?>/pages/venues/index.php" data-filter-form>
      <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
      <div class="field has-addons">
        <div class="control has-icons-left is-expanded">
          <div class="dropdown is-fullwidth map-search-dropdown">
            <div class="dropdown-trigger">
              <input
                class="input"
                type="text"
                id="venue-filter"
                name="filter"
                value="<?php echo htmlspecialchars($filter); ?>"
                placeholder="Search for venues..."
                autocomplete="off"
              >
              <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
            </div>
          </div>
        </div>
        <p class="control">
          <span class="button is-static">Ctrl+K</span>
        </p>
      </div>
    </form>
  </div>
</div>
<div class="level mb-3">
  <div class="level-left">
    <span class="tag"><?php echo (int) $totalVenues; ?> venues</span>
    <span class="tag">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
  </div>
</div>
