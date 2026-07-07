<?php
?>
<?php renderPageStart('Template', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script src="https://cdn.jsdelivr.net/npm/hugerte@1/hugerte.min.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/template-editor.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/mailboxes.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3"><?php echo $editTemplate ? 'Edit Template' : 'Add Template'; ?></h1>
            </div>
            <div class="level-right">
              <a href="<?php echo BASE_PATH; ?>/app/controllers/team/index.php?tab=templates" class="button">Back to Templates</a>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <div class="box">
            <form method="POST" action="" class="columns is-multiline">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="<?php echo $editTemplate ? 'update_template' : 'create_template'; ?>">
              <?php if ($editTemplate): ?>
                <input type="hidden" name="template_id" value="<?php echo (int) $editTemplate['id']; ?>">
              <?php endif; ?>

              <div class="column is-4">
                <div class="field">
                  <label class="label" for="template_team_id">Team</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="template_team_id" name="team_id" required>
                        <option value="">Select team</option>
                        <?php foreach ($teams as $team): ?>
                          <?php $teamId = (int) ($team['id'] ?? 0); ?>
                          <option value="<?php echo $teamId; ?>" <?php echo $formValues['team_id'] === $teamId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) ($team['name'] ?? ('Team #' . $teamId))); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="column is-8">
                <div class="field">
                  <label class="label" for="template_name">Template name</label>
                  <div class="control">
                    <input type="text" id="template_name" name="name" class="input" required value="<?php echo htmlspecialchars($formValues['name']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="field">
                  <label class="label" for="template_subject">Subject</label>
                  <div class="control">
                    <input type="text" id="template_subject" name="subject" class="input" value="<?php echo htmlspecialchars($formValues['subject']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="field template-editor">
                  <label class="label" for="template_body">Body</label>
                  <div class="control">
                    <textarea id="template_body" name="body" class="textarea" rows="8"><?php echo htmlspecialchars($formValues['body']); ?></textarea>
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="buttons">
                  <button type="submit" class="button is-primary"><?php echo $editTemplate ? 'Update Template' : 'Create Template'; ?></button>
                  <a href="<?php echo BASE_PATH; ?>/app/controllers/team/index.php?tab=templates" class="button">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
