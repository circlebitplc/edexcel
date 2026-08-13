<?php
declare(strict_types=1);

/**
 * Canonical application bootstrap for timetable modules.
 * Safe to require_once from pages/AJAX/API endpoints.
 */
require_once __DIR__ . '/load_env.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/response_security.php';
require_once __DIR__ . '/timetable_services.php';
