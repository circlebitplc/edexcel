<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Parent-facing engagement timeline for linked children only.
 */
final class ParentCommunicationTimelineService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @param list<int> $childIds
     * @return list<array{when:string,kind:string,title:string,body:string,link:?string,student_id:?int}>
     */
    public function timeline(int $parentId, array $childIds, int $limit = 60): array
    {
        $childIds = array_values(array_filter(array_map('intval', $childIds), static fn($id) => $id > 0));
        $events = [];

        try {
            $s = $this->pdo->prepare("SELECT id,title,body,category,link_url,created_at,is_read FROM notification_center WHERE audience='parent' AND (parent_id=? OR parent_id IS NULL) ORDER BY id DESC LIMIT 40");
            $s->execute([$parentId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $events[] = [
                    'when' => (string)$row['created_at'],
                    'kind' => 'notification',
                    'title' => (string)$row['title'],
                    'body' => (string)$row['body'],
                    'link' => $row['link_url'] ? (string)$row['link_url'] : (defined('BASE_URL') ? BASE_URL.'student/notifications.php' : null),
                    'student_id' => null,
                    'meta' => ['category' => $row['category'], 'read' => (int)$row['is_read'] === 1],
                ];
            }
        } catch (Throwable $e) {
        }

        if ($childIds !== []) {
            $in = implode(',', array_fill(0, count($childIds), '?'));
            try {
                $s = $this->pdo->prepare("SELECT id,subject,related_student_id,last_message_at,created_at FROM communication_threads WHERE related_student_id IN ($in) ORDER BY COALESCE(last_message_at,created_at) DESC LIMIT 30");
                $s->execute($childIds);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $events[] = [
                        'when' => (string)($row['last_message_at'] ?? $row['created_at']),
                        'kind' => 'thread',
                        'title' => (string)$row['subject'],
                        'body' => 'Conversation with college staff',
                        'link' => defined('BASE_URL') ? BASE_URL.'parent/communications.php?id='.(int)$row['id'] : null,
                        'student_id' => ((int)($row['related_student_id'] ?? 0)) ?: null,
                        'meta' => [],
                    ];
                }
            } catch (Throwable $e) {
            }

            try {
                $s = $this->pdo->prepare("SELECT r.id,r.status,r.channel,r.created_at,r.updated_at,m.subject,m.body,m.category FROM communication_recipients r JOIN communication_messages m ON m.id=r.message_id WHERE r.audience='parent' AND r.parent_id=? ORDER BY r.id DESC LIMIT 40");
                $s->execute([$parentId]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $events[] = [
                        'when' => (string)($row['updated_at'] ?? $row['created_at']),
                        'kind' => 'delivery',
                        'title' => (string)($row['subject'] ?: 'College message'),
                        'body' => mb_substr((string)$row['body'], 0, 180),
                        'link' => defined('BASE_URL') ? BASE_URL.'parent/notice_board.php' : null,
                        'student_id' => null,
                        'meta' => ['channel' => $row['channel'], 'status' => $row['status'], 'category' => $row['category']],
                    ];
                }
            } catch (Throwable $e) {
            }
        }

        try {
            foreach ((new AnnouncementService($this->pdo))->visible('parents') as $a) {
                $events[] = [
                    'when' => (string)($a['published_at'] ?? $a['publish_at'] ?? $a['created_at'] ?? ''),
                    'kind' => 'announcement',
                    'title' => (string)$a['title'],
                    'body' => mb_substr((string)$a['content'], 0, 180),
                    'link' => defined('BASE_URL') ? BASE_URL.'parent/notice_board.php' : null,
                    'student_id' => null,
                    'meta' => ['priority' => $a['priority'] ?? 'normal'],
                ];
            }
        } catch (Throwable $e) {
        }

        usort($events, static fn($a, $b) => strcmp((string)$b['when'], (string)$a['when']));
        return array_slice($events, 0, max(1, min(100, $limit)));
    }
}
