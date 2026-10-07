<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CoursoCommunityService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_courso_schema')) {
            ensure_courso_schema($this->pdo);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function thread(int $studentId, int $classId): array
    {
        $this->assertEnrolled($studentId, $classId);
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.class_id, p.student_id, p.parent_id, p.body, p.created_at,
                       COALESCE(sp.full_name, u.username) AS author
                FROM courso_posts p
                JOIN users u ON u.id = p.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = p.student_id
                WHERE p.class_id = ?
                ORDER BY p.id ASC
                LIMIT 80
            ");
            $stmt->execute([$classId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $byId = [];
        foreach ($rows as $row) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
            $row['mine'] = (int)$row['student_id'] === $studentId;
            $row['replies'] = [];
            $byId[$row['id']] = $row;
        }
        $roots = [];
        foreach ($byId as $id => $row) {
            $parent = $row['parent_id'];
            if ($parent && isset($byId[$parent])) {
                $byId[$parent]['replies'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }
        unset($row);
        return array_values($roots);
    }

    public function post(int $studentId, int $classId, string $body, ?int $parentId = null): int
    {
        $this->assertEnrolled($studentId, $classId);
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        if (mb_strlen($body) < 3) {
            throw new \RuntimeException('Write a little more so classmates can help.');
        }
        $body = mb_substr($body, 0, 800);
        if ($parentId !== null && $parentId > 0) {
            $check = $this->pdo->prepare('SELECT id FROM courso_posts WHERE id = ? AND class_id = ? LIMIT 1');
            $check->execute([$parentId, $classId]);
            if (!$check->fetchColumn()) {
                $parentId = null;
            }
        } else {
            $parentId = null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO courso_posts (class_id, student_id, parent_id, body)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$classId, $studentId, $parentId, $body]);
        $id = (int)$this->pdo->lastInsertId();
        (new CoursoLearnerService($this->pdo))->logActivity($studentId, 'discussion', 'class', $classId);

        $this->notifyClassmates($studentId, $classId, $body);

        return $id;
    }

    /**
     * Staff view: posts for classes a teacher teaches, or all for admin.
     *
     * @return list<array<string,mixed>>
     */
    public function staffFeed(?int $teacherId, bool $isAdmin, int $limit = 40): array
    {
        try {
            if ($isAdmin) {
                $limit = max(1, min(80, $limit));
                $stmt = $this->pdo->query("
                    SELECT p.id, p.body, p.created_at, c.name AS class_name,
                           COALESCE(sp.full_name, u.username) AS author
                    FROM courso_posts p
                    JOIN student_classes c ON c.id = p.class_id
                    JOIN users u ON u.id = p.student_id
                    LEFT JOIN student_profiles sp ON sp.user_id = p.student_id
                    WHERE p.parent_id IS NULL
                    ORDER BY p.id DESC
                    LIMIT {$limit}
                ");
                return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            }
            if (!$teacherId) {
                return [];
            }
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT p.id, p.body, p.created_at, c.name AS class_name,
                       COALESCE(sp.full_name, u.username) AS author
                FROM courso_posts p
                JOIN student_classes c ON c.id = p.class_id
                JOIN users u ON u.id = p.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = p.student_id
                JOIN timetable tt ON tt.class_id = p.class_id AND tt.teacher_id = ? AND tt.deleted_at IS NULL
                WHERE p.parent_id IS NULL
                ORDER BY p.id DESC
                LIMIT 40
            ");
            $stmt->execute([$teacherId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function assertEnrolled(int $studentId, int $classId): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
        $stmt->execute([$studentId, $classId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('Join this class first to use the discussion space.');
        }
    }

    private function notifyClassmates(int $authorId, int $classId, string $body): void
    {
        try {
            $pref = $this->pdo->prepare('SELECT notify_community FROM courso_learner_profiles WHERE student_id = ?');
            $members = $this->pdo->prepare('SELECT student_id FROM student_enrollments WHERE class_id = ? AND student_id <> ?');
            $members->execute([$classId, $authorId]);
            $ids = array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids === []) {
                return;
            }
            $className = '';
            $n = $this->pdo->prepare('SELECT name FROM student_classes WHERE id = ?');
            $n->execute([$classId]);
            $className = (string)($n->fetchColumn() ?: 'class');
            $snippet = mb_substr($body, 0, 120);
            $ins = $this->pdo->prepare("
                INSERT INTO student_notifications (student_id, title, message, type, link)
                VALUES (?, ?, ?, 'courso', ?)
            ");
            $link = '/student/dashboard.php?tab=courso#community';
            foreach ($ids as $sid) {
                $pref->execute([$sid]);
                $flag = $pref->fetchColumn();
                if ($flag !== false && (int)$flag === 0) {
                    continue;
                }
                $ins->execute([
                    $sid,
                    'New question in ' . $className,
                    $snippet,
                    $link,
                ]);
            }
        } catch (Throwable $e) {
            error_log('Courso community notify: ' . $e->getMessage());
        }
    }
}
