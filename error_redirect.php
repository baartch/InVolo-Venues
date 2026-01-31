<?php
// Get the base path dynamically
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Redirect to login
header('Location: ' . $basePath . '/pages/auth/login.php');
exit;
