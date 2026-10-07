<?php
declare(strict_types=1);

/**
 * Cron/tools scripts live under the web root. Refuse browser hits unless
 * CRON_KEY is set in the environment and supplied as ?key=.
 */
if (PHP_SAPI !== 'cli') {
    $key = (string)($_GET['key'] ?? '');
    $expected = trim((string)(getenv('CRON_KEY') ?: ''));
    if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "CLI only.\n";
        exit(1);
    }
}
