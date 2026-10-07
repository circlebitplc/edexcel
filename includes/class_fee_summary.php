<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\ClassSessionFeeCalculator;

function class_fee_summary_assets(?PDO $pdo = null): void
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    $config = ClassSessionFeeCalculator::config($pdo);
    $css = __DIR__ . '/../assets/css/class-session-fee.css';
    $js = __DIR__ . '/../assets/js/class-session-fee.js';
    $base = defined('BASE_URL') ? (string)BASE_URL : '/';
    $cssVer = is_file($css) ? (string)filemtime($css) : '1';
    $jsVer = is_file($js) ? (string)filemtime($js) : '1';
    $payload = json_encode([
        'instituteFeeCents' => (int)$config['institute_fee_cents'],
        'inCollegeFeeCents' => (int)$config['in_college_fee_cents'],
        'rateBps' => (int)$config['rate_bps'],
    ], JSON_UNESCAPED_SLASHES);
    echo '<link rel="stylesheet" href="' . htmlspecialchars(rtrim($base, '/') . '/assets/css/class-session-fee.css?v=' . $cssVer, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<script>window.CLASS_SESSION_FEE_CONFIG = ' . $payload . ';</script>' . "\n";
    echo '<script src="' . htmlspecialchars(rtrim($base, '/') . '/assets/js/class-session-fee.js?v=' . $jsVer, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}

/**
 * @param array<string,mixed>|null $stored
 */
function class_fee_summary_card(string $id, ?array $stored = null): void
{
    $rule = (string)($stored['fee_rule'] ?? '');
    $mode = ClassSessionFeeCalculator::normalizeMode((string)($stored['delivery_mode'] ?? 'physical'));
    $attrs = '';
    $snapshot = ($rule === ClassSessionFeeCalculator::ONLINE_RULE && $mode === 'online')
        || ($rule === ClassSessionFeeCalculator::IN_COLLEGE_RULE && $mode === 'physical');
    if ($snapshot) {
        $attrs = ' data-stored-rule="' . htmlspecialchars($rule, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-stored-mode="' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-stored-gross="' . htmlspecialchars(ClassSessionFeeCalculator::moneyString($stored['class_fee_per_student'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-stored-institute="' . htmlspecialchars(ClassSessionFeeCalculator::moneyString($stored['institute_online_fee'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-stored-txn="' . htmlspecialchars(ClassSessionFeeCalculator::moneyString($stored['transaction_handling_fee'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-stored-net="' . htmlspecialchars(ClassSessionFeeCalculator::moneyString($stored['teacher_net_amount'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"';
    }
    ?>
    <aside class="fee-summary-card" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" hidden<?= $attrs ?>>
        <div class="fee-summary-head">
            <div>
                <p class="fee-summary-title"><i class="bi bi-calculator"></i> <span data-fee="title">Class Fee Breakdown</span></p>
                <p class="fee-summary-intro" data-fee="intro">Enter a class fee to see how this session is split.</p>
            </div>
            <span class="fee-summary-live">Live update</span>
        </div>
        <p class="fee-summary-empty" data-fee-empty>Enter a class fee above 0 to see how this session is split.</p>
        <div data-fee-body hidden>
            <dl class="fee-summary-list">
                <div class="fee-summary-row">
                    <dt>Teacher's Class Fee <span>per student / session</span></dt>
                    <dd data-fee="gross">Rs. 0.00</dd>
                </div>
                <div class="fee-summary-row">
                    <dt data-fee-label="institute">Institute Fee</dt>
                    <dd data-fee="institute">Rs. 0.00</dd>
                </div>
                <div class="fee-summary-row" data-fee-row="txn">
                    <dt data-fee-label="txn">Transaction &amp; Handling Fee</dt>
                    <dd data-fee="txn">Rs. 0.00</dd>
                </div>
            </dl>
            <div class="fee-summary-net" data-fee-row="net">
                <span>Teacher Net Amount</span>
                <strong data-fee="net">Rs. 0.00</strong>
            </div>
        </div>
        <p class="fee-summary-warn" data-fee="warn" hidden>Enter a fee of 0 or more.</p>
        <p class="fee-summary-note" data-fee="note">This preview updates as you type. The class fee you entered is not changed. The college recalculates the split when you save.</p>
    </aside>
    <?php
}
