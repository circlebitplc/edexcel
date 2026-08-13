<?php
// config/config.php
// Define the base URL of your project.
// If your project is in the root of your domain (e.g. http://localhost/),
// set BASE_URL to '/'.
// If it's in a subfolder (e.g. http://localhost/edexcel-timetable/),
// set BASE_URL to '/edexcel-timetable/'.

define('BASE_URL', '/');  // <-- Change this to match your folder name

// If you're not sure, you can auto-detect:
// $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
// define('BASE_URL', $base_path);
?>