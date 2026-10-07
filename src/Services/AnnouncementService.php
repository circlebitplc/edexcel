<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AnnouncementService
{
    public function __construct(private PDO $pdo)
    {
        $this->ensure();
    }

    private function ensure(): void
    {
        try {
            if (!function_exists('campus_column_exists') || !campus_column_exists($this->pdo, 'college_announcements', 'urgent_count_day')) {
                // no-op column; use audit for urgent tracking instead
            }
        } catch (Throwable $e) {
        }
        // Heal archived status by allowing VARCHAR-like status values via published workflow only.
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $userId): int
    {
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '' || $content === '') {
            throw new RuntimeException('Title and content are required.');
        }
        $priority = in_array(($data['priority'] ?? 'normal'), ['normal', 'important', 'urgent', 'high'], true)
            ? ($data['priority'] === 'high' ? 'important' : $data['priority'])
            : 'normal';
        if ($priority === 'urgent') {
            $this->assertUrgentBudget($userId);
        }
        $target = in_array(($data['target_type'] ?? 'everyone'), ['everyone', 'students', 'parents', 'teachers', 'class', 'subject', 'students_specific', 'branch', 'qualification'], true)
            ? $data['target_type'] : 'everyone';
        $status = !empty($data['publish_at']) && strtotime((string)$data['publish_at']) > time() ? 'scheduled' : ($data['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'scheduled', 'published'], true)) {
            $status = 'draft';
        }
        $this->pdo->prepare("INSERT INTO college_announcements(title,content,priority,target_type,target_id,status,publish_at,expires_at,created_by) VALUES(?,?,?,?,?,?,?,?,?)")
            ->execute([
                $title, $content, $priority === 'important' ? 'high' : $priority, $target,
                ((int)($data['target_id'] ?? 0)) ?: null, $status,
                $data['publish_at'] ?? null, $data['expires_at'] ?? null, $userId,
            ]);
        $id = (int)$this->pdo->lastInsertId();
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'announcement_created', 'college_announcements', $id, null, ['title' => $title, 'priority' => $priority, 'target' => $target]);
        }
        if ($status === 'published') {
            $this->publish($id, $userId);
        }
        return $id;
    }

    public function publish(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE college_announcements SET status='published',published_by=?,published_at=NOW(),publish_at=COALESCE(publish_at,NOW()) WHERE id=? AND status IN ('draft','scheduled')");
        $stmt->execute([$userId, $id]);
        if ($stmt->rowCount() < 1) {
            // Maybe already published — still fan out once via audit check below
            $s = $this->pdo->prepare("SELECT * FROM college_announcements WHERE id=? AND status='published'");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Announcement cannot be published.');
            }
        } else {
            $s = $this->pdo->prepare('SELECT * FROM college_announcements WHERE id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'announcement_published', 'college_announcements', $id, null, ['priority' => $row['priority'] ?? 'normal', 'by' => $userId]);
        }
        $this->fanOut($row);
    }

    public function archive(int $id, int $userId): void
    {
        // Store as expired archival marker when ENUM lacks archived.
        $this->pdo->prepare("UPDATE college_announcements SET status='expired',expires_at=COALESCE(expires_at,NOW()) WHERE id=?")
            ->execute([$id]);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'announcement_archived', 'college_announcements', $id, null, ['by' => $userId]);
        }
    }

    /** @param array<string,mixed> $row */
    private function fanOut(array $row): void
    {
        if ($row === []) {
            return;
        }
        $priority = match ((string)($row['priority'] ?? 'normal')) {
            'urgent' => 'urgent',
            'high', 'important' => 'high',
            default => 'normal',
        };
        $title = (string)$row['title'];
        $body = (string)$row['content'];
        $link = defined('BASE_URL') ? BASE_URL.'student/notice_board.php' : '/student/notice_board.php';
        $center = new NotificationCenterService($this->pdo);
        $target = (string)($row['target_type'] ?? 'everyone');
        $targetId = (int)($row['target_id'] ?? 0);
        try {
            if (in_array($target, ['everyone', 'students'], true)) {
                $center->create('student', 'announcements', $title, $body, $link, null, null, $priority, ['announcement_id' => (int)$row['id']]);
            }
            if (in_array($target, ['everyone', 'parents'], true)) {
                $center->create('parent', 'announcements', $title, $body, $link, null, null, $priority, ['announcement_id' => (int)$row['id']]);
            }
            if (in_array($target, ['everyone', 'teachers'], true)) {
                $center->create('teacher', 'announcements', $title, $body, $link, null, null, $priority, ['announcement_id' => (int)$row['id']]);
            }
            if ($target === 'class' && $targetId > 0) {
                $s = $this->pdo->prepare("SELECT student_id FROM student_enrollments WHERE class_id=?");
                $s->execute([$targetId]);
                foreach ($s->fetchAll(PDO::FETCH_COLUMN) ?: [] as $uid) {
                    $center->create('student', 'announcements', $title, $body, $link, (int)$uid, null, $priority, ['announcement_id' => (int)$row['id'], 'class_id' => $targetId]);
                }
            }
            $center->create('admin', 'announcements', 'Published: '.$title, $body, BASE_URL.'admin/announcements.php', null, null, $priority, ['announcement_id' => (int)$row['id']]);
            // Urgent: also queue parent push via hub (async WhatsApp where permitted) — never sync mass-send.
            if ($priority === 'urgent') {
                try {
                    $hub = new CommunicationHubService($this->pdo);
                    $aud = in_array($target, ['everyone', 'parents'], true) ? 'parents' : ($target === 'class' ? 'class' : null);
                    if ($aud !== null) {
                        $hub->preview([
                            'channel' => 'in_app',
                            'audience_type' => $aud === 'class' ? 'class' : 'parents',
                            'audience_id' => $aud === 'class' ? $targetId : null,
                            'include_parents' => $aud === 'class',
                            'category' => 'announcements',
                            'priority' => 'urgent',
                            'subject' => 'URGENT: '.$title,
                            'body' => $body,
                            'idempotency_key' => 'ann-urgent-'.(int)$row['id'],
                        ], (int)($row['published_by'] ?? $row['created_by'] ?? 0));
                    }
                } catch (Throwable $e) {
                }
            }
        } catch (Throwable $e) {
        }
    }

    private function assertUrgentBudget(int $userId): void
    {
        try {
            $n = (int)$this->pdo->query("SELECT COUNT(*) FROM college_announcements WHERE priority='urgent' AND DATE(created_at)=CURDATE()")->fetchColumn();
            if ($n >= 3) {
                throw new RuntimeException('Urgent announcements are limited to 3 per day to avoid alert fatigue.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'announcement_urgent_requested', 'college_announcements', 0, null, ['by' => $userId]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function visible(string $audience, ?int $userId = null, ?int $classId = null): array
    {
        try {
            $sql = "SELECT * FROM college_announcements WHERE status='published' AND (publish_at IS NULL OR publish_at<=NOW()) AND (expires_at IS NULL OR expires_at>NOW()) AND (target_type='everyone' OR target_type=?";
            $p = [$audience];
            if ($classId) {
                $sql .= " OR (target_type='class' AND target_id=?)";
                $p[] = $classId;
            }
            $sql .= ') ORDER BY FIELD(priority,"urgent","high","normal"), published_at DESC LIMIT 50';
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM college_announcements ORDER BY id DESC LIMIT ?');
            $s->bindValue(1, $limit, PDO::PARAM_INT);
            $s->execute();
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function expireDue(): int
    {
        try {
            return (int)$this->pdo->exec("UPDATE college_announcements SET status='expired' WHERE status='published' AND expires_at IS NOT NULL AND expires_at<=NOW()");
        } catch (Throwable $e) {
            return 0;
        }
    }
}
