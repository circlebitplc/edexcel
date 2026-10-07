<?php
$results = [];
if (function_exists('opcache_reset')) {
    $results[] = 'opcache_reset(): ' . (opcache_reset() ? 'OK' : 'FAILED');
}
$files = [
    __DIR__ . '/student/device_helpers.php',
];
foreach ($files as $f) {
    if (function_exists('opcache_invalidate')) {
        $ok = opcache_invalidate($f, true);
        $results[] = 'invalidate ' . basename($f) . ': ' . ($ok ? 'OK' : 'FAILED/not cached');
    }
}
header('Content-Type: text/plain');
echo implode("\n", $results) . "\n";
