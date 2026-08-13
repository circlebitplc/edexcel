<?php
/**
 * Central payment configuration.
 * Reads the live fee/currency from settings when available and falls back safely.
 */
require_once __DIR__ . '/database.php';

if (!function_exists('payment_settings')) {
    function payment_settings(PDO $pdo): array {
        $defaults = [
            'fee_per_student' => defined('FEE_PER_STUDENT') ? FEE_PER_STUDENT : 500,
            'currency_symbol' => 'Rs',
            'class_duration' => 120,
        ];

        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('fee_per_student','currency_symbol','class_duration')");
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
                if ($key === 'fee_per_student') $defaults[$key] = max(0, (float)$value);
                elseif ($key === 'class_duration') $defaults[$key] = max(1, (int)$value);
                else $defaults[$key] = (string)$value;
            }
        } catch (Throwable $e) {
            // Settings table may not exist in an older installation; use defaults.
        }

        return $defaults;
    }

    function lesson_amount(int $student_count, float $fee_per_student): float {
        return max(0, $student_count) * $fee_per_student;
    }
}

$PAYMENT_SETTINGS = payment_settings($pdo);
$FEE_PER_STUDENT_LIVE = (float)$PAYMENT_SETTINGS['fee_per_student'];
$CURRENCY_SYMBOL_LIVE = $PAYMENT_SETTINGS['currency_symbol'];
$CLASS_DURATION_MINUTES = (int)$PAYMENT_SETTINGS['class_duration'];
