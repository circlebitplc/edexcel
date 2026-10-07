<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Pure payment verification rules used by OnePay callbacks and return pages.
 * Amounts, students, and lessons must be compared to server-stored values.
 */
final class PaymentVerificationService
{
    public static function formatAmount(float|string|int $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    public static function amountsMatch(float|string|int $expected, float|string|int $actual, float $tolerance = 0.009): bool
    {
        return abs((float)$expected - (float)$actual) <= $tolerance;
    }

    public static function currenciesMatch(string $expected, string $actual): bool
    {
        return strtoupper(trim($expected)) === strtoupper(trim($actual));
    }

    public static function studentMatches(int $expected, int $actual): bool
    {
        return $expected > 0 && $expected === $actual;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function studentIdFromPayload(array $payload): ?int
    {
        foreach (['student_id', 'customer_id', 'user_id'] as $key) {
            if (!isset($payload[$key]) || !is_numeric($payload[$key])) {
                continue;
            }
            $id = (int)$payload[$key];
            if ($id > 0) {
                return $id;
            }
        }
        return null;
    }

    public static function lessonMatches(int $expected, int $actual): bool
    {
        return $expected > 0 && $expected === $actual;
    }

    /**
     * Duplicate paid callbacks must be treated as success without creating a second paid record.
     */
    public static function isIdempotentPaid(string $currentStatus): bool
    {
        return strtolower($currentStatus) === 'paid';
    }

    public static function canMarkPaid(string $currentStatus): bool
    {
        $status = strtolower($currentStatus);
        return in_array($status, ['initiated', 'pending', 'failed', 'cancelled', 'expired'], true);
    }
}
