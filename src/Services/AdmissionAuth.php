<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AdmissionAuth
{
    public const PERMISSIONS = [
        'admissions.view',
        'admissions.create',
        'admissions.edit',
        'admissions.review',
        'admissions.approve',
        'admissions.reject',
        'admissions.assign',
        'admissions.export',
        'admissions.analytics',
    ];

    public static function isAdmin(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    public static function can(PDO $pdo, int $userId, string $permission): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        if ($userId < 1 || !in_array($permission, self::PERMISSIONS, true)) {
            return false;
        }
        try {
            $s = $pdo->prepare('SELECT 1 FROM staff_permissions WHERE user_id=? AND permission=? LIMIT 1');
            $s->execute([$userId, $permission]);
            return (bool)$s->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function require(PDO $pdo, string $permission): array
    {
        if (function_exists('require_login')) {
            require_login();
        }
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        if ($role === 'student') {
            throw new RuntimeException('Admissions access denied.');
        }
        if (!self::can($pdo, $userId, $permission)) {
            throw new RuntimeException('You do not have '.$permission.'.');
        }
        return ['user_id' => $userId, 'role' => $role === 'admin' ? 'admin' : 'admissions', 'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0)];
    }

    public static function grant(PDO $pdo, int $userId, string $permission, int $grantedBy): void
    {
        if (!in_array($permission, self::PERMISSIONS, true)) {
            throw new RuntimeException('Unknown permission.');
        }
        $pdo->prepare('INSERT IGNORE INTO staff_permissions(user_id,permission,granted_by) VALUES(?,?,?)')
            ->execute([$userId, $permission, $grantedBy ?: null]);
    }
}
