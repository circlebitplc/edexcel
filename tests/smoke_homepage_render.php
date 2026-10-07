<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__) . '/index.php';
$o = ob_get_clean();
$checks = [
    'len' => strlen($o),
    'doctype' => stripos($o, '<!DOCTYPE') !== false,
    'skip' => str_contains($o, 'hp-skip-link'),
    'csrf' => str_contains($o, 'csrf'),
    'teacher' => str_contains($o, 'teacher-login'),
    'student' => str_contains($o, 'student-login'),
    'meta_desc' => str_contains($o, 'name="description"'),
    'footer' => str_contains($o, 'hp-site-footer') || str_contains($o, 'site-contact'),
    'fatal_fallback' => str_contains($o, 'We are refreshing the homepage'),
];
foreach ($checks as $k => $v) {
    echo $k . '=' . (is_bool($v) ? ($v ? 'Y' : 'N') : $v) . PHP_EOL;
}
if ($checks['fatal_fallback'] || !$checks['doctype'] || $checks['len'] < 1000) {
    fwrite(STDERR, "Homepage smoke render looked unhealthy.\n");
    exit(1);
}
echo "OK\n";
