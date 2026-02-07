<nav class="pagination is-small" role="navigation" aria-label="pagination">
  <ul class="pagination-list">
    <?php if ($startPage > 1): ?>
      <?php
        $query['page'] = 1;
        $firstPageLink = $baseUrl . '?' . http_build_query($query);
      ?>
      <li>
        <a href="<?php echo htmlspecialchars($firstPageLink); ?>" class="pagination-link" aria-label="Goto page 1">1</a>
      </li>
      <?php if ($startPage > 2): ?>
        <li><span class="pagination-ellipsis">&hellip;</span></li>
      <?php endif; ?>
    <?php endif; ?>
    <?php for ($pageIndex = $startPage; $pageIndex <= $endPage; $pageIndex++): ?>
      <?php
        $query['page'] = $pageIndex;
        $pageLink = $baseUrl . '?' . http_build_query($query);
      ?>
      <li>
        <a
          class="pagination-link<?php echo $pageIndex === $page ? ' is-current' : ''; ?>"
          href="<?php echo htmlspecialchars($pageLink); ?>"
          aria-label="Goto page <?php echo (int) $pageIndex; ?>"
          <?php echo $pageIndex === $page ? 'aria-current="page"' : ''; ?>
        >
          <?php echo (int) $pageIndex; ?>
        </a>
      </li>
    <?php endfor; ?>
    <?php if ($endPage < $totalPages): ?>
      <?php if ($endPage < $totalPages - 1): ?>
        <li><span class="pagination-ellipsis">&hellip;</span></li>
      <?php endif; ?>
      <?php
        $query['page'] = $totalPages;
        $lastPageLink = $baseUrl . '?' . http_build_query($query);
      ?>
      <li>
        <a href="<?php echo htmlspecialchars($lastPageLink); ?>" class="pagination-link" aria-label="Goto page <?php echo (int) $totalPages; ?>">
          <?php echo (int) $totalPages; ?>
        </a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
