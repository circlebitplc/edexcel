<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class CommunicationAuth
{
    public const PERMISSIONS = [
        'communication.view',
        'communication.send',
        'communication.broadcast',
        'communication.schedule',
        'communication.templates',
        'communication.analytics',
        'communication.manage',
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
            throw new RuntimeException('Staff communication access denied.');
        }
        if (!self::can($pdo, $userId, $permission) && $role !== 'teacher') {
            throw new RuntimeException('You do not have '.$permission.'.');
        }
        if ($role === 'teacher' && in_array($permission, ['communication.broadcast', 'communication.analytics', 'communication.manage'], true) && !self::can($pdo, $userId, $permission)) {
            throw new RuntimeException('Teachers cannot use '.$permission.' unless granted.');
        }
        return ['user_id' => $userId, 'role' => $role === 'admin' ? 'admin' : ($role === 'teacher' ? 'teacher' : 'staff'), 'teacher_id' => (int)($_SESSION['teacher_id'] ?? 0)];
    }

    public static function teacherOwnsClass(PDO $pdo, int $teacherId, int $classId): bool
    {
        if ($classId < 1 || $teacherId < 1) {
            return false;
        }
        try {
            $s = $pdo->prepare('SELECT 1 FROM student_classes WHERE id=? AND teacher_id=? AND deleted_at IS NULL');
            $s->execute([$classId, $teacherId]);
            return (bool)$s->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}
