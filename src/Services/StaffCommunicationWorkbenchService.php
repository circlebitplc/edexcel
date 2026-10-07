<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Staff workbench: per-student communication timeline + filtered failed-delivery ops.
 */
final class StaffCommunicationWorkbenchService
{
    public function __construct(private PDO $pdo) {}

    public function canViewStudent(int $viewerId, string $role, int $studentId): bool
    {
        return (new Student360Service($this->pdo))->canView($viewerId, $role, $studentId);
    }

    /**
     * @return list<array{when:string,kind:string,title:string,body:string,channel:?string,status:?string,link:?string}>
     */
    public function studentTimeline(int $studentId, int $limit = 80): array
    {
        $events = [];
        $parentIds = [];
        try {
            $s = $this->pdo->prepare('SELECT parent_id FROM parent_students WHERE student_id=?');
            $s->execute([$studentId]);
            $parentIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
        }

        try {
            $s = $this->pdo->prepare("SELECT id,title,body,category,created_at,is_read,link_url FROM notification_center WHERE audience='student' AND (user_id=? OR user_id IS NULL) ORDER BY id DESC LIMIT 40");
            $s->execute([$studentId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $events[] = [
                    'when' => (string)$row['created_at'],
                    'kind' => 'notification',
                    'title' => (string)$row['title'],
                    'body' => mb_substr((string)$row['body'], 0, 200),
                    'channel' => 'in_app',
                    'status' => !empty($row['is_read']) ? 'read' : 'unread',
                    'link' => $row['link_url'] ? (string)$row['link_url'] : null,
                ];
            }
        } catch (Throwable $e) {
        }

        try {
            $s = $this->pdo->prepare("SELECT r.id,r.channel,r.status,r.created_at,r.updated_at,r.exclusion_reason,m.subject,m.body,m.category
                FROM communication_recipients r
                JOIN communication_messages m ON m.id=r.message_id
                WHERE r.audience='student' AND r.user_id=?
                ORDER BY r.id DESC LIMIT 50");
            $s->execute([$studentId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $events[] = [
                    'when' => (string)($row['updated_at'] ?? $row['created_at']),
                    'kind' => 'delivery',
                    'title' => (string)($row['subject'] ?: 'College message'),
                    'body' => mb_substr((string)$row['body'], 0, 200),
                    'channel' => (string)$row['channel'],
                    'status' => (string)$row['status'],
                    'link' => defined('BASE_URL') ? BASE_URL.'admin/communication_ops.php?q='.(int)$row['id'] : null,
                    'meta' => ['recipient_id' => (int)$row['id'], 'reason' => $row['exclusion_reason'] ?? ''],
                ];
            }
        } catch (Throwable $e) {
        }

        if ($parentIds !== []) {
            $in = implode(',', array_fill(0, count($parentIds), '?'));
            try {
                $s = $this->pdo->prepare("SELECT r.id,r.channel,r.status,r.created_at,r.updated_at,r.parent_id,m.subject,m.body
                    FROM communication_recipients r
                    JOIN communication_messages m ON m.id=r.message_id
                    WHERE r.audience='parent' AND r.parent_id IN ($in)
                    ORDER BY r.id DESC LIMIT 30");
                $s->execute($parentIds);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $events[] = [
                        'when' => (string)($row['updated_at'] ?? $row['created_at']),
                        'kind' => 'parent_delivery',
                        'title' => 'Parent: '.(string)($row['subject'] ?: 'College message'),
                        'body' => mb_substr((string)$row['body'], 0, 200),
                        'channel' => (string)$row['channel'],
                        'status' => (string)$row['status'],
                        'link' => null,
                        'meta' => ['parent_id' => (int)$row['parent_id']],
                    ];
                }
            } catch (Throwable $e) {
            }
        }

        try {
            $s = $this->pdo->prepare('SELECT id,subject,thread_type,last_message_at,created_at FROM communication_threads WHERE related_student_id=? ORDER BY COALESCE(last_message_at,created_at) DESC LIMIT 25');
            $s->execute([$studentId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $events[] = [
                    'when' => (string)($row['last_message_at'] ?? $row['created_at']),
                    'kind' => 'thread',
                    'title' => (string)$row['subject'],
                    'body' => 'Thread · '.(string)($row['thread_type'] ?? 'staff'),
                    'channel' => 'in_app',
                    'status' => 'open',
                    'link' => defined('BASE_URL') ? BASE_URL.'admin/communication_threads.php?id='.(int)$row['id'] : null,
                ];
            }
        } catch (Throwable $e) {
        }

        usort($events, static fn($a, $b) => strcmp((string)$b['when'], (string)$a['when']));
        return array_slice($events, 0, max(1, min(150, $limit)));
    }

    /** @return array{deliveries:int,failed:int,threads:int,unread_in_app:int} */
    public function studentStats(int $studentId): array
    {
        $q = function (string $sql, array $p = []): int {
            try {
                $s = $this->pdo->prepare($sql);
                $s->execute($p);
                return (int)$s->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        return [
            'deliveries' => $q("SELECT COUNT(*) FROM communication_recipients WHERE audience='student' AND user_id=?", [$studentId]),
            'failed' => $q("SELECT COUNT(*) FROM communication_recipients WHERE audience='student' AND user_id=? AND status='failed'", [$studentId]),
            'threads' => $q('SELECT COUNT(*) FROM communication_threads WHERE related_student_id=?', [$studentId]),
            'unread_in_app' => $q("SELECT COUNT(*) FROM notification_center WHERE audience='student' AND user_id=? AND is_read=0", [$studentId]),
        ];
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function searchStudents(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        try {
            $like = '%'.mb_substr($q, 0, 60).'%';
            $s = $this->pdo->prepare("SELECT u.id, COALESCE(NULLIF(sp.full_name,''), u.username) label
                FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id
                WHERE u.role='student' AND u.deleted_at IS NULL AND (u.username LIKE ? OR sp.full_name LIKE ? OR CAST(u.id AS CHAR)=?)
                ORDER BY label LIMIT ?");
            $s->bindValue(1, $like);
            $s->bindValue(2, $like);
            $s->bindValue(3, $q);
            $s->bindValue(4, max(1, min(50, $limit)), PDO::PARAM_INT);
            $s->execute();
            $rows = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $rows[] = ['id' => (int)$r['id'], 'label' => (string)$r['label']];
            }
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }
}
