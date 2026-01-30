<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$basePath = rtrim(BASE_PATH, '/');
$relativePath = $requestPath;

if ($basePath !== '' && strpos($relativePath, $basePath) === 0) {
  $relativePath = substr($relativePath, strlen($basePath));
}

$relativePath = '/' . ltrim($relativePath, '/');
$isMap = $relativePath === '/' || $relativePath === '/index.php';
$isVenues = strpos($relativePath, '/venues') === 0;
$isUserManagement = strpos($relativePath, '/admin') === 0;
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <a href="<?php echo BASE_PATH; ?>/index.php" class="sidebar-link <?php echo $isMap ? 'active' : ''; ?>" aria-label="Map">
      <img src="<?php echo BASE_PATH; ?>/public/assets/icon-map.svg" alt="Map">
    </a>
    <a href="<?php echo BASE_PATH; ?>/venues/index.php" class="sidebar-link <?php echo $isVenues ? 'active' : ''; ?>" aria-label="Venues">
      <img src="<?php echo BASE_PATH; ?>/public/assets/icon-venues.svg" alt="Venues">
    </a>
    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
      <a href="<?php echo BASE_PATH; ?>/admin/user_management.php" class="sidebar-link <?php echo $isUserManagement ? 'active' : ''; ?>" aria-label="User management">
        <img src="<?php echo BASE_PATH; ?>/public/assets/icon-user.svg" alt="User management">
      </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-spacer"></div>
  <a href="<?php echo BASE_PATH; ?>/auth/logout.php" class="sidebar-link" aria-label="Logout">
    <img src="<?php echo BASE_PATH; ?>/public/assets/icon-logout.svg" alt="Logout">
  </a>
</aside>
