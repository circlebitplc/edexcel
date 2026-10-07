<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class SupportTicketService
{
    public function __construct(private PDO $pdo) {}

    public function create(string $type, int $requesterId, array $data): int
    {
        if (!in_array($type, ['student', 'parent', 'teacher', 'admin'], true) || $requesterId < 1) {
            throw new RuntimeException('Invalid requester.');
        }
        $subject = trim((string)($data['subject'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        if ($subject === '' || $description === '') {
            throw new RuntimeException('Subject and description are required.');
        }
        $no = 'SUP-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        $this->pdo->prepare("INSERT INTO support_tickets(ticket_no,requester_type,requester_id,category,subject,description,priority) VALUES(?,?,?,?,?,?,?)")
            ->execute([$no, $type, $requesterId, $data['category'] ?? 'general', $subject, $description, $data['priority'] ?? 'normal']);
        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,author_type,author_id,message) VALUES(?,?,?,?)")
            ->execute([$id, $type, $requesterId, $description]);
        $relatedStudent = ((int)($data['related_student_id'] ?? 0)) ?: ($type === 'student' ? $requesterId : null);
        try {
            $threadId = (new CommunicationEventService($this->pdo))->ensureSupportThread($id, $subject.' ('.$no.')', $type, $requesterId, $relatedStudent);
            if ($threadId > 0) {
                (new CommunicationThreadService($this->pdo))->reply($threadId, $type, $requesterId, $description);
            }
        } catch (Throwable $e) {
        }
        try {
            (new NotificationCenterService($this->pdo))->create(
                'admin',
                'system',
                'New support ticket '.$no,
                $subject,
                defined('BASE_URL') ? BASE_URL.'admin/support.php?id='.$id : '/admin/support.php?id='.$id,
                null,
                null,
                (string)($data['priority'] ?? 'normal'),
                ['ticket_id' => $id]
            );
        } catch (Throwable $e) {
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'support_ticket_created', 'support_tickets', $id, null, ['ticket_no' => $no]);
        }
        return $id;
    }

    public function reply(int $ticketId, string $type, int $authorId, string $message): int
    {
        if (trim($message) === '') {
            throw new RuntimeException('Reply cannot be empty.');
        }
        if (!$this->canAccess($ticketId, $type, $authorId)) {
            throw new RuntimeException('You cannot access this ticket.');
        }
        $this->pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,author_type,author_id,message) VALUES(?,?,?,?)")
            ->execute([$ticketId, $type, $authorId, trim($message)]);
        $id = (int)$this->pdo->lastInsertId();
        $status = $type === 'admin' ? 'in_progress' : 'waiting_user';
        $this->pdo->prepare('UPDATE support_tickets SET status=?,updated_at=NOW() WHERE id=?')->execute([$status, $ticketId]);
        try {
            $ticket = $this->get($ticketId);
            if ($ticket) {
                $threadId = (new CommunicationEventService($this->pdo))->ensureSupportThread(
                    $ticketId,
                    (string)$ticket['subject'].' ('.$ticket['ticket_no'].')',
                    $type,
                    $authorId,
                    $ticket['requester_type'] === 'student' ? (int)$ticket['requester_id'] : null
                );
                if ($threadId > 0) {
                    (new CommunicationThreadService($this->pdo))->reply($threadId, $type, $authorId, trim($message));
                }
                $notifyAudience = $type === 'admin' ? (string)$ticket['requester_type'] : 'admin';
                $uid = $type === 'admin' ? (int)$ticket['requester_id'] : null;
                (new NotificationCenterService($this->pdo))->create(
                    $notifyAudience === 'parent' ? 'parent' : ($notifyAudience === 'teacher' ? 'teacher' : ($notifyAudience === 'student' ? 'student' : 'admin')),
                    'system',
                    'Support reply on '.$ticket['ticket_no'],
                    mb_substr(trim($message), 0, 240),
                    defined('BASE_URL') ? BASE_URL.($type === 'admin' ? 'student/requests.php' : 'admin/support.php?id='.$ticketId) : null,
                    $notifyAudience === 'parent' ? null : $uid,
                    $notifyAudience === 'parent' ? $uid : null,
                    'normal',
                    ['ticket_id' => $ticketId]
                );
            }
        } catch (Throwable $e) {
        }
        return $id;
    }

    public function updateStatus(int $ticketId, string $status, int $userId): void
    {
        if (!in_array($status, ['open', 'assigned', 'in_progress', 'waiting_user', 'resolved', 'closed'], true)) {
            throw new RuntimeException('Invalid status.');
        }
        $this->pdo->prepare('UPDATE support_tickets SET status=?,assigned_to=IF(? > 0,?,assigned_to),resolved_at=IF(? IN (\'resolved\',\'closed\'),NOW(),resolved_at),updated_at=NOW() WHERE id=?')
            ->execute([$status, $userId, $userId, $status, $ticketId]);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'support_ticket_status', 'support_tickets', $ticketId, null, ['status' => $status]);
        }
    }

    public function canAccess(int $ticketId, string $type, int $userId): bool
    {
        if ($type === 'admin') {
            return true;
        }
        try {
            $s = $this->pdo->prepare('SELECT requester_type,requester_id FROM support_tickets WHERE id=?');
            $s->execute([$ticketId]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            return $r && $r['requester_type'] === $type && (int)$r['requester_id'] === $userId;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    public function get(int $ticketId): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM support_tickets WHERE id=?');
            $s->execute([$ticketId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function linkedThreadId(int $ticketId): int
    {
        try {
            $s = $this->pdo->prepare('SELECT id FROM communication_threads WHERE related_ticket_id=? LIMIT 1');
            $s->execute([$ticketId]);
            return (int)($s->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listFor(string $type, int $userId, bool $admin = false): array
    {
        try {
            $sql = 'SELECT * FROM support_tickets';
            $p = [];
            if (!$admin) {
                $sql .= ' WHERE requester_type=? AND requester_id=?';
                $p = [$type, $userId];
            }
            $sql .= ' ORDER BY FIELD(priority,\'urgent\',\'high\',\'normal\',\'low\'),updated_at DESC';
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function messages(int $ticketId, string $type, int $userId, bool $admin = false): array
    {
        if (!$admin && !$this->canAccess($ticketId, $type, $userId)) {
            return [];
        }
        try {
            $s = $this->pdo->prepare('SELECT * FROM support_ticket_messages WHERE ticket_id=? ORDER BY created_at');
            $s->execute([$ticketId]);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
