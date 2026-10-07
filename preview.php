<?php
/**
 * Preview a homepage layout without activating it.
 * Only the 10 catalog layout keys are allowed.
 */
$homepagePreviewMode = true;
require_once __DIR__ . '/includes/homepage-bootstrap.php';

$raw = strtolower(preg_replace('/[^a-z0-9]/', '', (string)($_GET['layout'] ?? '')) ?? '');
if (!homepage_is_allowed_layout($raw)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid layout preview.';
    exit;
}

homepage_require_layout($activeLayout);
