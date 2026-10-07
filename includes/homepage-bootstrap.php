<?php
declare(strict_types=1);

/**
 * Public homepage bootstrap: DB, auth POST handling, shared data, active layout.
 */

if (!defined('HOMEPAGE_ROOT')) {
    define('HOMEPAGE_ROOT', dirname(__DIR__));
}

if (!defined('DB_ALLOW_FAILURE')) {
    define('DB_ALLOW_FAILURE', true);
}

require_once HOMEPAGE_ROOT . '/config/database.php';
require_once HOMEPAGE_ROOT . '/config/auth.php';
require_once HOMEPAGE_ROOT . '/config/config.php';
require_once HOMEPAGE_ROOT . '/includes/helpers.php';
require_once HOMEPAGE_ROOT . '/student/otp_helpers.php';
if (function_exists('student_device_issue_cookie')) {
    student_device_issue_cookie($pdo instanceof PDO ? $pdo : null);
}
require_once HOMEPAGE_ROOT . '/includes/homepage-layouts.php';
require_once HOMEPAGE_ROOT . '/includes/homepage-functions.php';
if (is_file(HOMEPAGE_ROOT . '/config/ops.php')) {
    require_once HOMEPAGE_ROOT . '/config/ops.php';
}
require_once HOMEPAGE_ROOT . '/includes/college_contact.php';
require_once HOMEPAGE_ROOT . '/includes/homepage-data.php';
require_once HOMEPAGE_ROOT . '/includes/homepage-components.php';

$homepagePreviewMode = !empty($homepagePreviewMode);
$rawLayout = strtolower(preg_replace('/[^a-z0-9]/', '', (string)($_GET['layout'] ?? '')) ?? '');

if ($homepagePreviewMode) {
    $activeLayout = homepage_is_allowed_layout($rawLayout) ? $rawLayout : HOMEPAGE_LAYOUT_DEFAULT;
} else {
    $activeLayout = getActiveHomepageLayout($pdo instanceof PDO ? $pdo : null);
}

if (!is_file(homepage_layout_file($activeLayout))) {
    $activeLayout = HOMEPAGE_LAYOUT_DEFAULT;
}

$homepageFormAction = $homepagePreviewMode
    ? 'preview.php?layout=' . rawurlencode($activeLayout)
    : 'index.php';

require_once HOMEPAGE_ROOT . '/includes/homepage-auth.php';

$hp = homepage_load_data($pdo instanceof PDO ? $pdo : null);
