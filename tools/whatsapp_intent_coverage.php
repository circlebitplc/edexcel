<?php
declare(strict_types=1);

/**
 * Intent coverage check against representative question phrases.
 * Does not print database facts — only intent routing.
 *
 * Usage: php tools/whatsapp_intent_coverage.php
 */

require_once dirname(__DIR__) . '/config/cron_http_guard.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';

use Edexcel\Services\WhatsAppAssistant;
use Edexcel\Services\WhatsAppIntentMap;

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$engine = new WhatsAppAssistant($pdo);
$phrases = WhatsAppIntentMap::coveragePhrases();
$report = [];
$pass = 0;
$fail = 0;

foreach ($phrases as $row) {
    $expected = $row['intent'];
    $got = $engine->detectIntent($row['q']);
    $ok = $got === $expected
        || ($expected === 'GET_TOMORROW_CLASSES' && in_array($got, ['GET_TIMETABLE', 'GET_SUBJECT_SCHEDULE', 'GET_TOMORROW_CLASSES'], true))
        || ($expected === 'GET_TODAY_CLASSES' && in_array($got, ['GET_TIMETABLE', 'GET_SUBJECT_SCHEDULE', 'GET_TODAY_CLASSES'], true))
        || ($expected === 'GET_SUBJECT_SCHEDULE' && in_array($got, ['GET_TIMETABLE', 'GET_SUBJECT_SCHEDULE', 'GET_TOMORROW_CLASSES'], true))
        || ($expected === 'GET_TEACHER_CONTACT' && in_array($got, ['GET_TEACHER', 'GET_TEACHER_CONTACT'], true))
        || ($expected === 'GET_TEACHER_SCHEDULE' && in_array($got, ['GET_TEACHER', 'GET_TEACHER_SCHEDULE', 'GET_TIMETABLE'], true))
        || ($expected === 'GET_CLASS_ROOM' && in_array($got, ['GET_CLASS_ROOM', 'GET_SUBJECT_SCHEDULE', 'GET_TIMETABLE'], true));
    if ($ok) {
        $pass++;
        $status = 'PASS';
    } else {
        $fail++;
        $status = 'FAIL';
    }
    $report[] = [
        $row['q'],
        $expected,
        $got,
        'live-db-on-ask',
        $status,
    ];
}

$outDir = dirname(__DIR__) . '/storage';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0775, true);
}
$path = $outDir . '/whatsapp_intent_coverage.csv';
$fh = fopen($path, 'w');
if ($fh) {
    fputcsv($fh, ['Question', 'Expected intent', 'Detected intent', 'Query', 'Status']);
    foreach ($report as $line) {
        fputcsv($fh, $line);
    }
    fclose($fh);
}

echo "Intent coverage: {$pass} pass, {$fail} fail, " . count($phrases) . " phrases\n";
echo "Report: {$path}\n";
if ($fail > 0) {
    echo "Failures:\n";
    foreach ($report as $line) {
        if ($line[4] === 'FAIL') {
            echo '  [' . $line[2] . '] expected ' . $line[1] . ' :: ' . $line[0] . "\n";
        }
    }
}

exit($fail > 8 ? 1 : 0);
