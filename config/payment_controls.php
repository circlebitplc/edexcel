<?php
declare(strict_types=1);

/**
 * Admin switches for bank-slip uploads and teacher manual payments.
 * Missing keys keep the previous behaviour: both stay on until an admin turns them off.
 */

function lesson_is_online_class(array $lesson): bool
{
    $mode = strtolower(trim((string)($lesson['delivery_mode'] ?? 'physical')));
    return in_array($mode, ['online', 'hybrid'], true);
}

function teacher_manual_payment_allowed(bool $enabled, bool $isAdmin, array $lesson): bool
{
    if ($isAdmin || !lesson_is_online_class($lesson)) {
        return true;
    }
    return $enabled;
}

function teacher_manual_payment_enabled(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['teacher_manual_payment_enabled']);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || trim((string)$value) === '') {
            return true;
        }
        return (string)$value === '1';
    } catch (Throwable $e) {
        return true;
    }
}

function manual_payment_method(string $raw): string
{
    $method = strtolower(trim($raw));
    if (!in_array($method, ['cash', 'bank', 'other'], true)) {
        throw new RuntimeException('Choose how the payment was received.');
    }
    return $method;
}

function manual_payment_amount_matches(float $posted, float $due): bool
{
    return abs(round($posted, 2) - round($due, 2)) < 0.009;
}

function manual_payment_date(string $raw, ?string $today = null): string
{
    $raw = trim($raw);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        throw new RuntimeException('Choose the date the payment was received.');
    }
    $picked = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if (!$picked || $picked->format('Y-m-d') !== $raw) {
        throw new RuntimeException('Choose the date the payment was received.');
    }
    $today = $today !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)
        ? $today
        : date('Y-m-d');
    $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today) ?: new DateTimeImmutable('today');
    if ($picked > $todayDate) {
        throw new RuntimeException('The payment date cannot be in the future.');
    }
    if ($picked < $todayDate->modify('-2 years')) {
        throw new RuntimeException('The payment date is too far in the past.');
    }
    return $raw;
}
