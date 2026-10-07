<?php
/**
 * Central payment configuration.
 *
 * Payment is calculated according to lesson duration:
 *
 * 0–2 hours   = Rs 500 per student
 * 2.5 hours   = Rs 500 per student
 * 3 hours     = Rs 700 per student
 * 3.5 hours   = Rs 700 per student
 * 4 hours     = Rs 900 per student
 * 5 hours     = Rs 1100 per student
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/payment_controls.php';

if (!function_exists('payment_normalize_currency_symbol')) {
    function payment_normalize_currency_symbol(string $raw): string
    {
        $raw = trim($raw);
        // "Rshtt" is "Rs" + truncated "http" from the 5-character settings field.
        if ($raw === '' || preg_match('/https?:|www\.|htt/i', $raw)) {
            if (preg_match('/^rs/i', $raw)) {
                return 'Rs';
            }
            return 'Rs';
        }
        $raw = preg_replace('/[^\p{L}\p{Sc}.]/u', '', $raw) ?? $raw;
        $raw = trim($raw);
        if ($raw === '' || mb_strlen($raw) > 5) {
            return 'Rs';
        }
        return $raw;
    }
}

if (!function_exists('payment_settings')) {
    function payment_settings(PDO $pdo): array {
        $defaults = [
            'currency_symbol' => 'Rs',
            'class_duration' => 120,
        ];

        try {
            $stmt = $pdo->query(
                "SELECT setting_key, setting_value
                 FROM settings
                 WHERE setting_key IN ('currency_symbol','class_duration')"
            );

            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
                if ($key === 'class_duration') {
                    $defaults[$key] = max(1, (int)$value);
                } else {
                    $defaults[$key] = payment_normalize_currency_symbol((string)$value);
                    if ($defaults[$key] !== (string)$value) {
                        try {
                            $fix = $pdo->prepare(
                                "UPDATE settings SET setting_value = ? WHERE setting_key = 'currency_symbol'"
                            );
                            $fix->execute([$defaults[$key]]);
                        } catch (Throwable $e) {
                            // Display the clean symbol even if the row cannot be updated.
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Older installations may not have the settings table.
        }

        return $defaults;
    }

    /**
     * Determine the payment rate per student from lesson duration.
     *
     * Duration is calculated in minutes.
     */
    function lesson_rate_per_student(int $durationMinutes): float {
        $hours = $durationMinutes / 60;

        if ($hours <= 2.5) {
            return 500.0;
        }

        if ($hours <= 3.5) {
            return 700.0;
        }

        if ($hours <= 4.5) {
            return 900.0;
        }

        return 1100.0;
    }

    /**
     * Calculate lesson duration from HH:MM[:SS] values.
     */
    function lesson_duration_minutes(
        string $startTime,
        string $endTime
    ): int {
        $start = strtotime($startTime);
        $end = strtotime($endTime);

        if ($start === false || $end === false) {
            return 0;
        }

        // Handle lessons that cross midnight.
        if ($end < $start) {
            $end += 86400;
        }

        return max(0, (int)round(($end - $start) / 60));
    }

    /**
     * Calculate the amount payable for a lesson.
     */
    function lesson_amount(
        int $studentCount,
        string $startTime,
        string $endTime
    ): float {
        $duration = lesson_duration_minutes($startTime, $endTime);
        $rate = lesson_rate_per_student($duration);

        return max(0, $studentCount) * $rate;
    }

    /**
     * SQL minutes between two TIME columns, including lessons that cross midnight.
     */
    function lesson_duration_sql(string $startExpr = 'start_time', string $endExpr = 'end_time'): string {
        return "(CASE
            WHEN TIME_TO_SEC({$endExpr}) >= TIME_TO_SEC({$startExpr})
            THEN (TIME_TO_SEC({$endExpr}) - TIME_TO_SEC({$startExpr})) / 60
            ELSE (TIME_TO_SEC({$endExpr}) + 86400 - TIME_TO_SEC({$startExpr})) / 60
        END)";
    }

    /**
     * SQL rate per student. Must stay aligned with lesson_rate_per_student().
     */
    function lesson_rate_sql(string $startExpr = 'start_time', string $endExpr = 'end_time'): string {
        $duration = lesson_duration_sql($startExpr, $endExpr);
        return "(CASE
            WHEN {$duration} <= 150 THEN 500
            WHEN {$duration} <= 210 THEN 700
            WHEN {$duration} <= 270 THEN 900
            ELSE 1100
        END)";
    }

    /**
     * SQL amount for a lesson row.
     */
    function lesson_amount_sql(
        string $countExpr = 'COALESCE(student_count, 0)',
        string $startExpr = 'start_time',
        string $endExpr = 'end_time'
    ): string {
        return "(({$countExpr}) * " . lesson_rate_sql($startExpr, $endExpr) . ')';
    }

    /**
     * Monday–Sunday of the week containing $date (Y-m-d).
     *
     * @return array{0:string,1:string}
     */
    function week_bounds(?string $date = null): array {
        $ts = $date ? strtotime($date) : time();
        if ($ts === false) {
            $ts = time();
        }
        $isoDay = (int)date('N', $ts);
        $monday = date('Y-m-d', strtotime('-' . ($isoDay - 1) . ' days', $ts));
        $sunday = date('Y-m-d', strtotime($monday . ' +6 days'));
        return [$monday, $sunday];
    }

    /**
     * Backwards-compatible helper.
     *
     * Used by pages that already have a duration value.
     */
    function lesson_amount_from_duration(
        int $studentCount,
        int $durationMinutes
    ): float {
        return max(0, $studentCount)
            * lesson_rate_per_student($durationMinutes);
    }
}

if (!function_exists('bank_transfer_config')) {
    /**
     * @return array{
     *   enabled:bool,
     *   bank_name:string,
     *   account_name:string,
     *   account_number:string,
     *   branch:string,
     *   instructions:string
     * }
     */
    function bank_transfer_config(?PDO $pdo = null): array
    {
        $cfg = [
            'enabled' => true,
            'bank_name' => '',
            'account_name' => '',
            'account_number' => '',
            'branch' => '',
            'instructions' => 'Transfer the class fee and upload a clear photo or PDF of the bank slip. Access unlocks after the office confirms the payment.',
        ];
        if (!$pdo instanceof PDO) {
            return $cfg;
        }
        try {
            $stmt = $pdo->query("
                SELECT setting_key, setting_value
                FROM settings
                WHERE setting_key IN (
                    'bank_transfer_enabled',
                    'bank_name',
                    'bank_account_name',
                    'bank_account_number',
                    'bank_branch',
                    'bank_instructions'
                )
            ");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];
            $cfg['enabled'] = !isset($rows['bank_transfer_enabled']) || (string)$rows['bank_transfer_enabled'] === '1';
            $cfg['bank_name'] = trim((string)($rows['bank_name'] ?? ''));
            $cfg['account_name'] = trim((string)($rows['bank_account_name'] ?? ''));
            $cfg['account_number'] = trim((string)($rows['bank_account_number'] ?? ''));
            $cfg['branch'] = trim((string)($rows['bank_branch'] ?? ''));
            $note = trim((string)($rows['bank_instructions'] ?? ''));
            if ($note !== '') {
                $cfg['instructions'] = $note;
            }
        } catch (Throwable $e) {
            // Older installs still allow the upload form; staff can save details later.
        }
        if ($cfg['account_number'] === '' && $cfg['account_name'] === '' && $cfg['bank_name'] === '') {
            $cfg['enabled'] = $cfg['enabled'] && true;
        }
        return $cfg;
    }
}

if (!function_exists('bank_transfer_ready')) {
    function bank_transfer_ready(?PDO $pdo = null): bool
    {
        $cfg = bank_transfer_config($pdo);
        return !empty($cfg['enabled']);
    }
}

$PAYMENT_SETTINGS = payment_settings($pdo);

$CURRENCY_SYMBOL_LIVE =
    $PAYMENT_SETTINGS['currency_symbol'];

$CLASS_DURATION_MINUTES =
    (int)$PAYMENT_SETTINGS['class_duration'];

/*
 * Kept for compatibility with existing pages.
 *
 * New calculations should use the actual lesson duration.
 */
$FEE_PER_STUDENT_LIVE = 500.0;
