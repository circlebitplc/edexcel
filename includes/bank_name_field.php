<?php
declare(strict_types=1);

use Edexcel\Services\TeacherBankAccountService;

function bank_name_field(string $current): void
{
    static $assets = false;
    $current = TeacherBankAccountService::canonicalBank($current) ?? trim($current);
    $banks = TeacherBankAccountService::banks();
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    if (!$assets) {
        $assets = true;
        $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
        $css = __DIR__ . '/../assets/css/bank-name-search.css';
        $js = __DIR__ . '/../assets/js/bank-name-search.js';
        $cssVer = is_file($css) ? (string)filemtime($css) : '1';
        $jsVer = is_file($js) ? (string)filemtime($js) : '1';
        echo '<link rel="stylesheet" href="' . $h($base . '/assets/css/bank-name-search.css?v=' . $cssVer) . '">' . "\n";
        echo '<script>window.SRI_LANKA_BANKS = ' . json_encode($banks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
        echo '<script src="' . $h($base . '/assets/js/bank-name-search.js?v=' . $jsVer) . '"></script>' . "\n";
    }
    ?>
    <div class="bank-search" data-bank-search>
        <input
            class="form-control"
            type="text"
            id="bank_name_query"
            data-bank-query
            value="<?= $h($current) ?>"
            placeholder="Search bank name"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            required
        >
        <input type="hidden" name="bank_name" data-bank-value value="<?= $h($current) ?>">
        <ul class="bank-search-list" data-bank-list hidden role="listbox"></ul>
    </div>
    <div class="form-text">Type to search the licensed banks in Sri Lanka, then choose one.</div>
    <?php
}
