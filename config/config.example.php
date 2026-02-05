<?php
// Configuration file for venue crawler frontend
// Copy this file to config.php and update the credentials

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'involo_venues');
define('DB_USER', 'dbuservenues');
define('DB_PASSWORD', 'change-this-password'); // CHANGE THIS!

// Encryption key for settings values (32+ chars recommended)
define('ENCRYPTION_KEY', 'change-this-encryption-key');


// Session configuration
// Managed in the database using the sessions table
// Sessions expire after 24 hours of inactivity, capped at 7 days from creation
// (Idle timeout is refreshed on each authenticated request).
define('SESSION_IDLE_LIFETIME', 86400); // 24 hours in seconds
define('SESSION_MAX_LIFETIME', 604800); // 7 days in seconds
