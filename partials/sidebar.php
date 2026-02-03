<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$basePath = rtrim(BASE_PATH, '/');
$relativePath = $requestPath;

if ($basePath !== '' && strpos($relativePath, $basePath) === 0) {
  $relativePath = substr($relativePath, strlen($basePath));
}

$relativePath = '/' . ltrim($relativePath, '/');
$isMap = $relativePath === '/' || $relativePath === '/index.php';
$isVenues = strpos($relativePath, '/pages/venues') === 0;
$isTeam = strpos($relativePath, '/pages/team') === 0;
$isAdmin = strpos($relativePath, '/pages/admin') === 0;
$isProfile = strpos($relativePath, '/pages/profile') === 0;
$isTeamAdmin = $currentUser['is_team_admin'] ?? false;
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <a href="<?php echo BASE_PATH; ?>/index.php" class="sidebar-link <?php echo $isMap ? 'active' : ''; ?>" aria-label="Map">
      <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-map.svg" alt="Map">
    </a>
    <a href="<?php echo BASE_PATH; ?>/pages/venues/index.php" class="sidebar-link <?php echo $isVenues ? 'active' : ''; ?>" aria-label="Venues">
      <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-venues.svg" alt="Venues">
    </a>
    <?php if ($isTeamAdmin): ?>
      <a href="<?php echo BASE_PATH; ?>/pages/team/index.php" class="sidebar-link <?php echo $isTeam ? 'active' : ''; ?>" aria-label="Team">
        <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-team.svg" alt="Team">
      </a>
    <?php endif; ?>
    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
      <a href="<?php echo BASE_PATH; ?>/pages/admin/user_management.php" class="sidebar-link <?php echo $isAdmin ? 'active' : ''; ?>" aria-label="Admin">
        <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-settings.svg" alt="Admin">
      </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-spacer"></div>
  <a href="<?php echo BASE_PATH; ?>/pages/profile/index.php" class="sidebar-link <?php echo $isProfile ? 'active' : ''; ?>" aria-label="Profile">
    <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-user.svg" alt="Profile">
  </a>
  <a href="<?php echo BASE_PATH; ?>/pages/auth/logout.php" class="sidebar-link" aria-label="Logout">
    <img src="<?php echo BASE_PATH; ?>/public/assets/icons/icon-logout.svg" alt="Logout">
  </a>
</aside>
