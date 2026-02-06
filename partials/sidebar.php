<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$basePath = rtrim(BASE_PATH, '/');
$relativePath = $requestPath;

if ($basePath !== '' && strpos($relativePath, $basePath) === 0) {
  $relativePath = substr($relativePath, strlen($basePath));
}

$relativePath = '/' . ltrim($relativePath, '/');
$isDashboard = $relativePath === '/' || $relativePath === '/index.php' || strpos($relativePath, '/pages/dashboard') === 0;
$isMap = strpos($relativePath, '/pages/map') === 0;
$isVenues = strpos($relativePath, '/pages/venues') === 0;
$isTeam = strpos($relativePath, '/pages/team') === 0;
$isAdmin = strpos($relativePath, '/pages/admin') === 0;
$isProfile = strpos($relativePath, '/pages/profile') === 0;
$isCommunication = strpos($relativePath, '/pages/communication') === 0;
$isTeamAdmin = $currentUser['is_team_admin'] ?? false;
?>
<aside class="column is-narrow">
  <div class="is-flex is-flex-direction-column is-justify-content-space-between is-fullheight p-3">
    <nav class="menu">
      <ul class="menu-list">
        <li>
          <a href="<?php echo BASE_PATH; ?>/index.php" class="<?php echo $isDashboard ? 'is-active' : ''; ?>" aria-label="Dashboard">
            <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_PATH; ?>/pages/map/index.php" class="<?php echo $isMap ? 'is-active' : ''; ?>" aria-label="Map">
            <span class="icon"><i class="fa-solid fa-map"></i></span>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_PATH; ?>/pages/venues/index.php" class="<?php echo $isVenues ? 'is-active' : ''; ?>" aria-label="Venues">
            <span class="icon"><i class="fa-solid fa-location-dot"></i></span>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_PATH; ?>/pages/communication/index.php" class="<?php echo $isCommunication ? 'is-active' : ''; ?>" aria-label="Communication">
            <span class="icon"><i class="fa-solid fa-comments"></i></span>
          </a>
        </li>
        <?php if ($isTeamAdmin): ?>
          <li>
            <a href="<?php echo BASE_PATH; ?>/pages/team/index.php" class="<?php echo $isTeam ? 'is-active' : ''; ?>" aria-label="Team">
              <span class="icon"><i class="fa-solid fa-users"></i></span>
            </a>
          </li>
        <?php endif; ?>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <li>
            <a href="<?php echo BASE_PATH; ?>/pages/admin/user_management.php" class="<?php echo $isAdmin ? 'is-active' : ''; ?>" aria-label="Admin">
              <span class="icon"><i class="fa-solid fa-gear"></i></span>
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
    <nav class="menu">
      <ul class="menu-list">
        <li>
          <a href="<?php echo BASE_PATH; ?>/pages/profile/index.php" class="<?php echo $isProfile ? 'is-active' : ''; ?>" aria-label="Profile">
            <span class="icon"><i class="fa-solid fa-user"></i></span>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_PATH; ?>/pages/auth/logout.php" aria-label="Logout">
            <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>
