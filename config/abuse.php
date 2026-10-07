<?php
declare(strict_types=1);

/**
 * Early abuse guard. Included from config/security.php so login, APIs,
 * classrooms, and pages that already load the security bootstrap are covered.
 * Safe to require more than once.
 */

if (!function_exists('eck_client_ip')) {
    require_once __DIR__ . '/client_ip.php';
}

if (!defined('ABUSE_GUARD_BOOTED')) {
    define('ABUSE_GUARD_BOOTED', true);
    $eckAutoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($eckAutoload)) {
        require_once $eckAutoload;
    }
    if (PHP_SAPI !== 'cli' && class_exists(\Edexcel\Http\AbuseGuard::class)) {
        \Edexcel\Http\AbuseGuard::enforceRequest();
    }
}
