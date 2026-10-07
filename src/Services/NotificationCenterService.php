<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Unified notification centre (notification_center table).
 * Categories: classes, payments, homework, exams, attendance, recordings, announcements, system.
 * Audience: student|teacher|admin|parent.
 */
final class NotificationCenterService
{
    public const CATEGORIES = [
        'classes',
        'payments',
        'homework',
        'exams',
        'attendance',
        'recordings',
        'announcements',
        'system',
    ];

    public const AUDIENCES = ['student', 'teacher', 'admin', 'parent'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<string>
     */
    public static function allowedCategories(): array
    {
        return self::CATEGORIES;
    }

    public static function isAllowedCategory(string $category): bool
    {
        return in_array(strtolower(trim($category)), self::CATEGORIES, true);
    }

    /**
     * @param array<string,mixed>|null $meta
     */
    public function create(
        string $audience,
        string $category,
        string $title,
        ?string $body = null,
        ?string $linkUrl = null,
        ?int $userId = null,
        ?int $parentId = null,
        string $priority = 'normal',
        ?array $meta = null
    ): int {
        $audience = $this->normalizeAudience($audience);
        $category = $this->normalizeCategory($category);
        $priority = in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
        $title = mb_substr(trim($title), 0, 255);
        if ($title === '') {
            return 0;
        }
        try {
            $this->pdo->prepare("
                INSERT INTO notification_center
                    (audience, user_id, parent_id, category, priority, title, body, link_url, meta_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $audience,
                $userId,
                $parentId,
                $category,
                $priority,
                $title,
                $body !== null && $body !== '' ? $body : null,
                $linkUrl !== null && $linkUrl !== '' ? mb_substr($linkUrl, 0, 1000) : null,
                $meta !== null ? json_encode($meta) : null,
            ]);
            return (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('NotificationCenterService::create: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array{category?:string,unread_only?:bool,limit?:int,offset?:int} $filters
     * @return list<array<string,mixed>>
     */
    public function listFor(string $audience, ?int $userId = null, ?int $parentId = null, array $filters = []): array
    {
        $audience = $this->normalizeAudience($audience);
        $where = ['audience = ?'];
        $params = [$audience];

        if ($audience === 'parent') {
            if ($parentId === null || $parentId < 1) {
                return [];
            }
            $where[] = 'parent_id = ?';
            $params[] = $parentId;
        } else {
            if ($userId === null || $userId < 1) {
                return [];
            }
            // User-specific + broadcast (user_id IS NULL) for same audience.
            $where[] = '(user_id = ? OR user_id IS NULL)';
            $params[] = $userId;
        }

        if (!empty($filters['category'])) {
            $where[] = 'category = ?';
            $params[] = $this->normalizeCategory((string)$filters['category']);
        }
        if (!empty($filters['unread_only'])) {
            $where[] = 'is_read = 0';
        }

        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        try {
            $sql = 'SELECT * FROM notification_center WHERE ' . implode(' AND ', $where)
                . ' ORDER BY is_read ASC, created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function unreadCount(string $audience, ?int $userId = null, ?int $parentId = null): int
    {
        $audience = $this->normalizeAudience($audience);
        try {
            if ($audience === 'parent') {
                if ($parentId === null || $parentId < 1) {
                    return 0;
                }
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM notification_center
                    WHERE audience = 'parent' AND parent_id = ? AND is_read = 0
                ");
                $stmt->execute([$parentId]);
                return (int)$stmt->fetchColumn();
            }
            if ($userId === null || $userId < 1) {
                return 0;
            }
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notification_center
                WHERE audience = ? AND (user_id = ? OR user_id IS NULL) AND is_read = 0
            ");
            $stmt->execute([$audience, $userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function markRead(int $id, string $audience, ?int $userId = null, ?int $parentId = null): bool
    {
        if ($id < 1) {
            return false;
        }
        $audience = $this->normalizeAudience($audience);
        try {
            if ($audience === 'parent') {
                if ($parentId === null || $parentId < 1) {
                    return false;
                }
                $stmt = $this->pdo->prepare("
                    UPDATE notification_center
                    SET is_read = 1, read_at = COALESCE(read_at, NOW())
                    WHERE id = ? AND audience = 'parent' AND parent_id = ?
                ");
                $stmt->execute([$id, $parentId]);
                return $stmt->rowCount() > 0;
            }
            if ($userId === null || $userId < 1) {
                return false;
            }
            $stmt = $this->pdo->prepare("
                UPDATE notification_center
                SET is_read = 1, read_at = COALESCE(read_at, NOW())
                WHERE id = ? AND audience = ? AND user_id = ?
            ");
            $stmt->execute([$id, $audience, $userId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function markAllRead(string $audience, ?int $userId = null, ?int $parentId = null): int
    {
        $audience = $this->normalizeAudience($audience);
        try {
            if ($audience === 'parent') {
                if ($parentId === null || $parentId < 1) {
                    return 0;
                }
                $stmt = $this->pdo->prepare("
                    UPDATE notification_center
                    SET is_read = 1, read_at = COALESCE(read_at, NOW())
                    WHERE audience = 'parent' AND parent_id = ? AND is_read = 0
                ");
                $stmt->execute([$parentId]);
                return $stmt->rowCount();
            }
            if ($userId === null || $userId < 1) {
                return 0;
            }
            $stmt = $this->pdo->prepare("
                UPDATE notification_center
                SET is_read = 1, read_at = COALESCE(read_at, NOW())
                WHERE audience = ? AND user_id = ? AND is_read = 0
            ");
            $stmt->execute([$audience, $userId]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Copy recent unread student_notifications into notification_center (idempotent-ish).
     */
    public function syncFromStudentNotifications(int $studentId, int $limit = 40): int
    {
        if ($studentId < 1) {
            return 0;
        }
        $limit = max(1, min(100, $limit));
        $created = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, title, message, type, link, is_read, created_at
                FROM student_notifications
                WHERE student_id IS NULL OR student_id = ?
                ORDER BY created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return 0;
        }

        foreach ($rows as $row) {
            $legacyId = (int)($row['id'] ?? 0);
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            try {
                $dup = $this->pdo->prepare("
                    SELECT id FROM notification_center
                    WHERE audience = 'student' AND user_id = ?
                      AND JSON_EXTRACT(meta_json, '$.legacy_id') = ?
                    LIMIT 1
                ");
                $dup->execute([$studentId, $legacyId]);
                if ($dup->fetchColumn()) {
                    continue;
                }
            } catch (Throwable $e) {
                // meta_json may not support JSON_EXTRACT on older MySQL — fall through with title/time check
                try {
                    $dup2 = $this->pdo->prepare("
                        SELECT id FROM notification_center
                        WHERE audience = 'student' AND user_id = ? AND title = ?
                          AND created_at >= DATE_SUB(?, INTERVAL 1 MINUTE)
                          AND created_at <= DATE_ADD(?, INTERVAL 1 MINUTE)
                        LIMIT 1
                    ");
                    $createdAt = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
                    $dup2->execute([$studentId, $title, $createdAt, $createdAt]);
                    if ($dup2->fetchColumn()) {
                        continue;
                    }
                } catch (Throwable $e2) {
                }
            }

            $category = $this->mapLegacyType((string)($row['type'] ?? 'info'));
            $id = $this->create(
                'student',
                $category,
                $title,
                (string)($row['message'] ?? ''),
                (string)($row['link'] ?? '') ?: null,
                $studentId,
                null,
                'normal',
                ['legacy_id' => $legacyId, 'legacy_type' => (string)($row['type'] ?? '')]
            );
            if ($id > 0) {
                $created++;
                if (!empty($row['is_read'])) {
                    $this->markRead($id, 'student', $studentId, null);
                }
            }
        }
        return $created;
    }

    private function mapLegacyType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'payment' => 'payments',
            'class' => 'classes',
            'announcement' => 'announcements',
            'homework' => 'homework',
            'exam', 'exams' => 'exams',
            'attendance' => 'attendance',
            'recording', 'recordings' => 'recordings',
            default => 'system',
        };
    }

    private function normalizeAudience(string $audience): string
    {
        $audience = strtolower(trim($audience));
        return in_array($audience, self::AUDIENCES, true) ? $audience : 'student';
    }

    private function normalizeCategory(string $category): string
    {
        $category = strtolower(trim($category));
        return in_array($category, self::CATEGORIES, true) ? $category : 'system';
    }
}
