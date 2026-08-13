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
                    $defaults[$key] = (string)$value;
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
