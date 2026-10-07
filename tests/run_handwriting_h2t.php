<?php
declare(strict_types=1);

/**
 * Lightweight checks for handwriting recognition helpers / normalize.
 * Run: php tests/run_handwriting_h2t.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';

$fail = 0;
function assert_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
        return;
    }
    $fail++;
    echo "FAIL {$msg}\n";
}

$pen = classroom_normalize_whiteboard_stroke([
    'tool' => 'pen',
    'id' => 'oTestInk1',
    'points' => [['x' => 10, 'y' => 10], ['x' => 40, 'y' => 20]],
    'color' => '#111827',
    'width' => 3,
    'page' => 0,
    'hidden' => true,
]);
assert_true(is_array($pen) && !empty($pen['hidden']), 'pen stroke can store hidden=true');

$edu = classroom_normalize_whiteboard_stroke([
    'tool' => 'edu',
    'kind' => 'equation',
    'id' => 'oTestEq1',
    'x' => 20,
    'y' => 30,
    'w' => 200,
    'h' => 70,
    'color' => '#111827',
    'data' => ['text' => 'x² + 1 = 0', 'latex' => 'x^2 + 1 = 0'],
    'page' => 0,
]);
assert_true(is_array($edu) && ($edu['kind'] ?? '') === 'equation', 'bare edu kind equation is accepted');

$chem = classroom_normalize_whiteboard_stroke([
    'tool' => 'edu',
    'kind' => 'chem-eq',
    'id' => 'oTestChem1',
    'x' => 20,
    'y' => 30,
    'w' => 200,
    'h' => 70,
    'color' => '#111827',
    'data' => ['text' => 'H₂O', 'latex' => 'H2O'],
    'page' => 0,
]);
assert_true(is_array($chem) && ($chem['kind'] ?? '') === 'chem-eq', 'bare edu kind chem-eq is accepted');

$patch = classroom_normalize_whiteboard_stroke([
    'tool' => 'op',
    'op' => 'patch',
    'id' => 'oTestInk1',
    'patch' => ['hidden' => false],
]);
assert_true(is_array($patch) && array_key_exists('hidden', $patch['patch'] ?? []) && $patch['patch']['hidden'] === false, 'patch can unhide ink');

$text = classroom_normalize_whiteboard_stroke([
    'tool' => 'text',
    'id' => 'oTestTxt1',
    'x' => 12,
    'y' => 40,
    'text' => "Photosynthesis is the process by which plants make food.",
    'color' => '#111827',
    'size' => 18,
    'page' => 0,
]);
assert_true(is_array($text) && str_contains((string)$text['text'], 'Photosynthesis'), 'converted text object normalizes');

echo $fail === 0 ? "\nAll handwriting H2T checks passed.\n" : "\n{$fail} check(s) failed.\n";
exit($fail === 0 ? 0 : 1);
