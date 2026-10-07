<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../includes/i18n.php';

use Edexcel\Services\StudentLessonFeeService;

$failed = 0;
$passed = 0;

function expect_true(bool $ok, string $label): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo " PASS  {$label}\n";
        return;
    }
    $failed++;
    echo " FAIL  {$label}\n";
}

expect_true(OPS_SCHEMA_VERSION === '016', 'ops schema version is 016');
expect_true(in_array('meta_access_token', ops_secret_setting_keys(), true), 'backup redacts Meta token');
expect_true(in_array('onepay_hash_salt', ops_secret_setting_keys(), true), 'backup redacts OnePay salt');
expect_true(eck_t('fees.title', 'en') === 'Fee statement', 'English fee title');
expect_true(eck_t('fees.title', 'si') === 'ගාස්තු ප්‍රකාශය', 'Sinhala fee title');
expect_true(eck_t('missing.key', 'en') === 'missing.key', 'i18n falls back to key');
expect_true(StudentLessonFeeService::teacherMayUnpay('paid', false, 500.0), 'paid lesson can be unpaid');
expect_true(!StudentLessonFeeService::teacherMayUnpay('paid', false, 0.0), 'zero fee cannot be unpaid');
expect_true(StudentLessonFeeService::isUnlocked('paid'), 'paid is unlocked');
expect_true(!StudentLessonFeeService::isUnlocked('pending'), 'pending is locked');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
