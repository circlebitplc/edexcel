<?php
// config/config.php
// Define the base URL of your project.
// If your project is in the root of your domain (e.g. http://localhost/),
// set BASE_URL to '/'.
// If it's in a subfolder (e.g. http://localhost/edexcel-timetable/),
// set BASE_URL to '/edexcel-timetable/'.

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');  // <-- Change this to match your folder name
}

require_once __DIR__ . '/theme.php';

// If you're not sure, you can auto-detect:
// $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
// define('BASE_URL', $base_path);
?>