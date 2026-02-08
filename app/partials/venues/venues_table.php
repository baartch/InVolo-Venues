<div class="table-container">
  <table class="table is-striped is-hoverable is-fullwidth" data-venues-table>
    <thead>
      <tr>
        <th></th>
        <th data-sort>Name</th>
        <th data-sort>Address</th>
        <th data-sort>Country</th>
        <th data-sort>Type</th>
        <th>Contact</th>
        <th data-sort>Contact Person</th>
        <th data-sort data-sort-type="number">Capacity</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($venues as $venue): ?>
        <tr>
          <td>
            <?php if (!empty($venue['latitude']) && !empty($venue['longitude'])): ?>
              <?php
                $lat = number_format((float) $venue['latitude'], 6, '.', '');
                $lng = number_format((float) $venue['longitude'], 6, '.', '');
                $mapLink = BASE_PATH . '/app/pages/map/index.php?' . http_build_query([
                    'lat' => $lat,
                    'lng' => $lng,
                    'zoom' => 13
                ]);
              ?>
              <a href="<?php echo htmlspecialchars($mapLink); ?>" class="icon" aria-label="Open map at venue" title="Open map">
                <i class="fa-solid fa-location-dot"></i>
              </a>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($venue['name']); ?></td>
          <td>
            <?php
              $addressParts = array_filter([
                  $venue['address'] ?? '',
                  implode(' ', array_filter([
                      $venue['postal_code'] ?? '',
                      $venue['city'] ?? ''
                  ]))
              ]);
              $state = trim((string) ($venue['state'] ?? ''));
              echo nl2br(htmlspecialchars(implode("\n", $addressParts)));
              if ($state !== '') {
                echo '<br><span class="is-size-7">' . htmlspecialchars($state) . '</span>';
              }
            ?>
          </td>
          <td><?php echo htmlspecialchars($venue['country'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($venue['type'] ?? ''); ?></td>
          <td>
            <div class="buttons are-small">
              <?php if (!empty($venue['website'])): ?>
                <a href="<?php echo htmlspecialchars($venue['website']); ?>" target="_blank" rel="noopener noreferrer" class="button" aria-label="Website" title="Website">
                  <span class="icon"><i class="fa-solid fa-globe"></i></span>
                </a>
              <?php endif; ?>
              <?php if (!empty($venue['contact_email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($venue['contact_email']); ?>" class="button" aria-label="Email" title="Email">
                  <span class="icon"><i class="fa-solid fa-envelope"></i></span>
                </a>
              <?php endif; ?>
              <?php if (!empty($venue['contact_phone'])): ?>
                <a href="tel:<?php echo htmlspecialchars($venue['contact_phone']); ?>" class="button" aria-label="Phone" title="Phone">
                  <span class="icon"><i class="fa-solid fa-phone"></i></span>
                </a>
              <?php endif; ?>
            </div>
          </td>
          <td><?php echo htmlspecialchars($venue['contact_person'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($venue['capacity'] ?? ''); ?></td>
          <td>
            <div class="buttons are-small">
              <button type="button" class="button" data-venue-info-toggle aria-label="Show venue details" title="Show venue details">
                <span class="icon"><i class="fa-solid fa-circle-info"></i></span>
              </button>
              <a href="<?php echo BASE_PATH; ?>/app/pages/venues/add.php?edit=<?php echo (int) $venue['id']; ?>" class="button" aria-label="Edit venue" title="Edit venue">
                <span class="icon"><i class="fa-solid fa-pen"></i></span>
              </a>
              <form method="POST" action="" onsubmit="return confirm('Delete this venue?');">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="venue_id" value="<?php echo (int) $venue['id']; ?>">
                <button type="submit" class="button" aria-label="Delete venue" title="Delete venue">
                  <span class="icon"><i class="fa-solid fa-trash"></i></span>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <tr class="is-hidden" data-venue-details>
          <td colspan="9">
            <article class="message">
              <div class="message-body">
                <?php if (!empty($venue['notes'])): ?>
                  <div class="content">
                    <?php echo nl2br(htmlspecialchars($venue['notes'] ?? '')); ?>
                  </div>
                <?php else: ?>
                  <p>No notes.</p>
                <?php endif; ?>
                <div class="level is-mobile mt-3">
                  <div class="level-left">
                    <span class="tag">Created: <?php echo htmlspecialchars($venue['created_at'] ?? ''); ?></span>
                    <span class="tag">Updated: <?php echo htmlspecialchars($venue['updated_at'] ?? ''); ?></span>
                  </div>
                </div>
              </div>
            </article>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
