<?php if (($currentUser['role'] ?? '') === 'admin'): ?>
  <div class="modal" data-import-modal data-initial-open="<?php echo $showImportModal ? 'true' : 'false'; ?>">
    <div class="modal-background" data-import-close></div>
    <div class="modal-card">
      <header class="modal-card-head">
        <p class="modal-card-title">Import Venues (JSON)</p>
        <button class="delete" aria-label="close" data-import-close></button>
      </header>
      <section class="modal-card-body">
        <form method="POST" action="" id="import_form">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="action" value="import">
          <div class="field">
            <div class="control">
              <textarea class="textarea" name="import_json" placeholder="Paste JSON here" rows="8"><?php echo htmlspecialchars($importPayload); ?></textarea>
            </div>
          </div>
        </form>
      </section>
      <footer class="modal-card-foot">
        <button type="button" class="button" data-import-close>Close</button>
        <button type="submit" class="button" form="import_form">Import</button>
      </footer>
    </div>
  </div>
<?php endif; ?>
