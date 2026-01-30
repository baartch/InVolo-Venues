<?php
// Include this file at the top of any protected page
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit;
}
