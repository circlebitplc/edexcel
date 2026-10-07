<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class CommunicationThreadService
{
    public function __construct(private PDO $pdo)
    {
        $this->ensure();
    }

    private function ensure(): void
    {
        foreach ([
            "CREATE TABLE IF NOT EXISTS communication_threads (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                subject VARCHAR(255) NOT NULL,
                thread_type VARCHAR(40) NOT NULL DEFAULT 'staff',
                related_student_id INT NULL,
                related_class_id INT NULL,
                related_ticket_id BIGINT UNSIGNED NULL,
                related_announcement_id BIGINT UNSIGNED NULL,
                created_by INT NULL,
                created_by_role VARCHAR(20) NULL,
                last_message_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_threads_student (related_student_id, last_message_at),
                KEY idx_threads_class (related_class_id, last_message_at),
                KEY idx_threads_ticket (related_ticket_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS communication_thread_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_id BIGINT UNSIGNED NOT NULL,
                sender_role VARCHAR(20) NOT NULL,
                sender_id INT NOT NULL,
                body TEXT NOT NULL,
                attachment_path VARCHAR(1000) NULL,
                original_lang VARCHAR(10) NOT NULL DEFAULT 'en',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_thread_msgs (thread_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS communication_thread_participants (
                thread_id BIGINT UNSIGNED NOT NULL,
                participant_role VARCHAR(20) NOT NULL,
                participant_id INT NOT NULL,
                last_read_at DATETIME NULL,
                PRIMARY KEY (thread_id, participant_role, participant_id),
                KEY idx_thread_participant (participant_role, participant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ] as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Throwable $e) {
            }
        }
    }

    /** @param array<string,mixed> $data */
    public function open(array $data, string $role, int $userId): int
    {
        $subject = trim((string)($data['subject'] ?? 'College communication'));
        if ($subject === '') {
            throw new RuntimeException('Subject is required.');
        }
        $type = in_array(($data['thread_type'] ?? 'staff'), ['staff', 'support', 'class', 'parent', 'admission'], true)
            ? $data['thread_type'] : 'staff';
        $this->pdo->prepare('INSERT INTO communication_threads(subject,thread_type,related_student_id,related_class_id,related_ticket_id,related_announcement_id,created_by,created_by_role,last_message_at) VALUES(?,?,?,?,?,?,?,?,NOW())')
            ->execute([
                mb_substr($subject, 0, 255), $type,
                ((int)($data['related_student_id'] ?? 0)) ?: null,
                ((int)($data['related_class_id'] ?? 0)) ?: null,
                ((int)($data['related_ticket_id'] ?? 0)) ?: null,
                ((int)($data['related_announcement_id'] ?? 0)) ?: null,
                $userId ?: null, $role,
            ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->addParticipant($id, $role, $userId);
        foreach ((array)($data['participants'] ?? []) as $p) {
            $this->addParticipant($id, (string)($p['role'] ?? ''), (int)($p['id'] ?? 0));
        }
        if (!empty($data['body'])) {
            $this->reply($id, $role, $userId, (string)$data['body']);
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'thread_opened', 'communication_threads', $id, null, ['type' => $type]);
        }
        return $id;
    }

    public function reply(int $threadId, string $role, int $userId, string $body, ?string $attachment = null): int
    {
        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException('Message body is required.');
        }
        if (!$this->canAccess($threadId, $role, $userId) && $role !== 'admin') {
            throw new RuntimeException('You cannot reply on this thread.');
        }
        $this->assertSafe($body);
        $this->pdo->prepare('INSERT INTO communication_thread_messages(thread_id,sender_role,sender_id,body,attachment_path) VALUES(?,?,?,?,?)')
            ->execute([$threadId, $role, $userId, mb_substr($body, 0, 5000), $attachment]);
        $mid = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE communication_threads SET last_message_at=NOW() WHERE id=?')->execute([$threadId]);
        $this->addParticipant($threadId, $role, $userId);
        $this->markRead($threadId, $role, $userId);
        return $mid;
    }

    public function markRead(int $threadId, string $role, int $userId): void
    {
        try {
            $this->pdo->prepare('INSERT INTO communication_thread_participants(thread_id,participant_role,participant_id,last_read_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE last_read_at=NOW()')
                ->execute([$threadId, $role, $userId]);
        } catch (Throwable $e) {
        }
    }

    public function canAccess(int $threadId, string $role, int $userId): bool
    {
        if ($role === 'admin') {
            return true;
        }
        try {
            $s = $this->pdo->prepare('SELECT 1 FROM communication_thread_participants WHERE thread_id=? AND participant_role=? AND participant_id=?');
            $s->execute([$threadId, $role, $userId]);
            if ($s->fetchColumn()) {
                return true;
            }
            // Teachers: class-owned threads
            if ($role === 'teacher') {
                $t = $this->pdo->prepare('SELECT related_class_id FROM communication_threads WHERE id=?');
                $t->execute([$threadId]);
                $classId = (int)$t->fetchColumn();
                return $classId > 0 && CommunicationAuth::teacherOwnsClass($this->pdo, (int)($_SESSION['teacher_id'] ?? 0), $classId);
            }
            // Parents: related student must be linked
            if ($role === 'parent') {
                $t = $this->pdo->prepare('SELECT related_student_id FROM communication_threads WHERE id=?');
                $t->execute([$threadId]);
                $sid = (int)$t->fetchColumn();
                if ($sid < 1) {
                    return false;
                }
                $p = $this->pdo->prepare('SELECT 1 FROM parent_students WHERE parent_id=? AND student_id=?');
                $p->execute([$userId, $sid]);
                return (bool)$p->fetchColumn();
            }
            // Students: related student is self
            if ($role === 'student') {
                $t = $this->pdo->prepare('SELECT related_student_id, created_by FROM communication_threads WHERE id=?');
                $t->execute([$threadId]);
                $row = $t->fetch(PDO::FETCH_ASSOC);
                return $row && ((int)($row['related_student_id'] ?? 0) === $userId || (int)($row['created_by'] ?? 0) === $userId);
            }
        } catch (Throwable $e) {
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    public function inbox(string $role, int $userId, int $limit = 50): array
    {
        try {
            if ($role === 'admin') {
                $s = $this->pdo->prepare('SELECT * FROM communication_threads ORDER BY COALESCE(last_message_at,created_at) DESC LIMIT ?');
                $s->bindValue(1, $limit, PDO::PARAM_INT);
                $s->execute();
                return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            $s = $this->pdo->prepare('SELECT t.* FROM communication_threads t JOIN communication_thread_participants p ON p.thread_id=t.id WHERE p.participant_role=? AND p.participant_id=? ORDER BY COALESCE(t.last_message_at,t.created_at) DESC LIMIT ?');
            $s->bindValue(1, $role);
            $s->bindValue(2, $userId, PDO::PARAM_INT);
            $s->bindValue(3, $limit, PDO::PARAM_INT);
            $s->execute();
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM communication_threads WHERE id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return list<array<string,mixed>> */
    public function messages(int $threadId): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM communication_thread_messages WHERE thread_id=? ORDER BY id ASC');
            $s->execute([$threadId]);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function addParticipant(int $threadId, string $role, int $userId): void
    {
        if ($threadId < 1 || $userId < 1 || $role === '') {
            return;
        }
        try {
            $this->pdo->prepare('INSERT IGNORE INTO communication_thread_participants(thread_id,participant_role,participant_id) VALUES(?,?,?)')
                ->execute([$threadId, $role, $userId]);
        } catch (Throwable $e) {
        }
    }

    private function assertSafe(string $text): void
    {
        $lower = strtolower($text);
        foreach (['password', 'otp', 'api_key', 'secret', 'cvv'] as $bad) {
            if (str_contains($lower, $bad)) {
                throw new RuntimeException('Thread messages must not include secrets.');
            }
        }
    }
}
