<?php
require_once __DIR__ . '/../../src-php/admin_check.php';

if (!isset($settingsStatus)):
?>
  <?php return; ?>
<?php endif; ?>

<div class="box">
  <div class="content">
    <h2 class="title is-5">API Keys</h2>
    <p>Store tokens used for map tiles and integrations.</p>
  </div>

  <form method="POST" action="">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="save_api_keys">
    <input type="hidden" name="tab" value="api-keys">
    <div class="field">
      <label for="brave_search_api_key" class="label">Brave Search</label>
      <div class="control">
        <input
          type="password"
          id="brave_search_api_key"
          name="brave_search_api_key"
          class="input"
          placeholder="<?php echo $settingsStatus['brave_search_api_key'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
    </div>
    <div class="field">
      <label for="brave_spellcheck_api_key" class="label">Brave Spellcheck</label>
      <div class="control">
        <input
          type="password"
          id="brave_spellcheck_api_key"
          name="brave_spellcheck_api_key"
          class="input"
          placeholder="<?php echo $settingsStatus['brave_spellcheck_api_key'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
    </div>
    <div class="field">
      <label for="mapbox_api_key" class="label">Mapbox</label>
      <div class="control">
        <input
          type="password"
          id="mapbox_api_key"
          name="mapbox_api_key"
          class="input"
          placeholder="<?php echo $settingsStatus['mapbox_api_key'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
    </div>
    <button type="submit" class="button">Save API Keys</button>
  </form>
</div>
