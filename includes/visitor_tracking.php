<?php
declare(strict_types=1);

/**
 * Visitor tracking script helper.
 * Outputs the non-blocking visitor analytics script tag.
 */
if (!function_exists('visitor_tracking_tag')) {
    function visitor_tracking_tag(): void
    {
        $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
        $trackerPath = dirname(__DIR__) . '/assets/js/visitor-tracker.js';
        $v = is_file($trackerPath) ? (string)filemtime($trackerPath) : '1';
        $url = ($base !== '' ? $base : '') . '/assets/js/visitor-tracker.js?v=' . rawurlencode($v);
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }
}
