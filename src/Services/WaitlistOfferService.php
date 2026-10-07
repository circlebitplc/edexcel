<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class WaitlistOfferService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    public function offerNext(int $classId): ?int
    {
        if (function_exists('campus_class_is_full') && campus_class_is_full($this->pdo, $classId)) {
            return null;
        }
        $open = $this->pdo->prepare("
            SELECT id FROM waitlist_offers
            WHERE class_id = ? AND status = 'offered' AND expires_at > NOW()
            LIMIT 1
        ");
        $open->execute([$classId]);
        if ($open->fetchColumn()) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT student_id FROM student_waitlist
            WHERE class_id = ?
            ORDER BY created_at ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$classId]);
        $studentId = (int)$stmt->fetchColumn();
        if ($studentId < 1) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $this->pdo->prepare("
            INSERT INTO waitlist_offers (class_id, student_id, token, status, expires_at)
            VALUES (?, ?, ?, 'offered', DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ")->execute([$classId, $studentId, $token]);

        $this->notify($studentId, $classId, $token);
        return $studentId;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $token = strtolower(preg_replace('/[^a-f0-9]/', '', $token) ?? '');
        if (strlen($token) !== 32) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT o.*, c.name AS class_name
            FROM waitlist_offers o
            JOIN student_classes c ON c.id = o.class_id
            WHERE o.token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function accept(string $token, int $studentId): void
    {
        $this->pdo->beginTransaction();
        try {
            $token = strtolower(preg_replace('/[^a-f0-9]/', '', $token) ?? '');
            if (strlen($token) !== 32) {
                throw new RuntimeException('This seat offer is not for your account.');
            }
            $stmt = $this->pdo->prepare("
                SELECT o.*, c.name AS class_name
                FROM waitlist_offers o
                JOIN student_classes c ON c.id = o.class_id
                WHERE o.token = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$token]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$offer || (int)$offer['student_id'] !== $studentId) {
                throw new RuntimeException('This seat offer is not for your account.');
            }
            if ((string)$offer['status'] !== 'offered' || strtotime((string)$offer['expires_at']) < time()) {
                throw new RuntimeException('This seat offer has expired.');
            }
            $classId = (int)$offer['class_id'];
            $this->pdo->prepare('SELECT id FROM student_classes WHERE id = ? FOR UPDATE')->execute([$classId]);
            if (function_exists('campus_class_is_full') && campus_class_is_full($this->pdo, $classId)) {
                $this->pdo->prepare("UPDATE waitlist_offers SET status = 'expired' WHERE id = ?")->execute([(int)$offer['id']]);
                $this->pdo->commit();
                throw new RuntimeException('That class is full again.');
            }
            $this->pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id, class_id) VALUES (?, ?)')
                ->execute([$studentId, $classId]);
            if (function_exists('campus_waitlist_leave')) {
                campus_waitlist_leave($this->pdo, $studentId, $classId);
            }
            $this->pdo->prepare("UPDATE waitlist_offers SET status = 'accepted' WHERE id = ?")->execute([(int)$offer['id']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        try {
            $contacts = function_exists('campus_student_contacts')
                ? campus_student_contacts($this->pdo, $studentId)
                : ['name' => 'Student', 'student_phone' => ''];
            if (function_exists('campus_notify_class_join')) {
                campus_notify_class_join(
                    $this->pdo,
                    $studentId,
                    $classId,
                    (string)$offer['class_name'],
                    (string)($contacts['student_phone'] ?? ''),
                    (string)($contacts['name'] ?? ''),
                    []
                );
            }
        } catch (Throwable $e) {
        }
    }

    public function expireOpen(): int
    {
        $stmt = $this->pdo->query("
            SELECT id, class_id FROM waitlist_offers
            WHERE status = 'offered' AND expires_at <= NOW()
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $n = 0;
        foreach ($rows as $row) {
            $this->pdo->prepare("UPDATE waitlist_offers SET status = 'expired' WHERE id = ?")->execute([(int)$row['id']]);
            $this->offerNext((int)$row['class_id']);
            $n++;
        }
        return $n;
    }

    private function notify(int $studentId, int $classId, string $token): void
    {
        $className = 'class';
        try {
            $stmt = $this->pdo->prepare('SELECT name FROM student_classes WHERE id = ?');
            $stmt->execute([$classId]);
            $className = (string)($stmt->fetchColumn() ?: 'class');
        } catch (Throwable $e) {
        }
        $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
        $host = rtrim((string)(getenv('APP_URL') ?: 'https://edexcel.college'), '/');
        $url = $host . '/student/waitlist_offer.php?t=' . $token;
        $body = "A seat opened in *{$className}*. You have 24 hours to join:\n{$url}";
        if (function_exists('campus_portal_notify')) {
            campus_portal_notify($this->pdo, [$studentId], 'Seat available: ' . $className, $body, 'waitlist_offer.php?t=' . $token);
        }
        if (function_exists('campus_notify_phones')) {
            campus_notify_phones(
                $this->pdo,
                $studentId,
                "🎟️ *Seat available*\n\nClass: *{$className}*\nYou have 24 hours to join.\n{$url}\n\nEdexcel College",
                'WAITLIST_OFFER'
            );
        }
    }
}
