<?php
declare(strict_types=1);

/**
 * Shared toast + form loading assets.
 * Presentation only — does not change auth, validation, or API behaviour.
 */

if (!function_exists('ui_feedback_asset_href')) {
    function ui_feedback_asset_href(string $relative): string
    {
        $root = dirname(__DIR__);
        $path = $root . '/' . ltrim($relative, '/');
        $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/';
        $version = is_file($path) ? (string)filemtime($path) : '1';
        return $base . ltrim($relative, '/') . '?v=' . $version;
    }
}

if (!function_exists('ui_feedback_css')) {
    function ui_feedback_css(): void
    {
        $href = htmlspecialchars(ui_feedback_asset_href('assets/css/ui-feedback.css'), ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" href="' . $href . '">' . "\n";
    }
}

if (!function_exists('ui_feedback_js')) {
    function ui_feedback_js(): void
    {
        $src = htmlspecialchars(ui_feedback_asset_href('assets/js/ui-feedback.js'), ENT_QUOTES, 'UTF-8');
        echo '<script src="' . $src . '"></script>' . "\n";
    }
}
