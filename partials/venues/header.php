<div class="level mb-4">
  <div class="level-left">
    <div>
      <h1 class="title is-3">Venue Management</h1>
      <p class="subtitle is-6">Manage the venues stored in the database.</p>
    </div>
  </div>
  <div class="level-right">
    <div class="buttons">
      <a href="<?php echo BASE_PATH; ?>/pages/venues/add.php" class="button is-primary">Add Venue</a>
      <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
        <button type="button" class="button" data-import-toggle>Import</button>
      <?php endif; ?>
    </div>
  </div>
</div>
