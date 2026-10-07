<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Lightweight hooks from academic/finance events into the communication hub.
 * Always queues asynchronously via CommunicationHubService::preview — never sends sync.
 */
final class CommunicationEventService
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $ctx */
    public function attendanceAbsent(int $studentId, array $ctx = []): void
    {
        if ($studentId < 1) {
            return;
        }
        $this->queueSafe([
            'channel' => 'in_app',
            'audience_type' => 'individual',
            'audience_id' => $studentId,
            'include_parents' => true,
            'category' => 'attendance',
            'subject' => 'Attendance notice',
            'body' => 'Your child / you were marked absent' . (!empty($ctx['class_name']) ? ' from '.$ctx['class_name'] : '') . (!empty($ctx['date']) ? ' on '.$ctx['date'] : '') . '.',
            'variables' => $ctx,
            'idempotency_key' => 'att-abs-'.$studentId.'-'.($ctx['date'] ?? date('Y-m-d')).'-'.($ctx['class_id'] ?? 0),
        ], (int)($ctx['actor_id'] ?? 0));
        // Parent-facing copy via audience parents of student when hub supports it — also fire automation.
        try {
            (new AutomationService($this->pdo))->handle('attendance.absent', 'att-'.$studentId.'-'.($ctx['date'] ?? date('Y-m-d')), array_merge($ctx, ['student_id' => $studentId]));
        } catch (Throwable $e) {
        }
    }

    /** @param array<string,mixed> $ctx */
    public function paymentReceived(int $studentId, float $amount, array $ctx = []): void
    {
        $this->queueSafe([
            'channel' => 'in_app',
            'audience_type' => 'individual',
            'audience_id' => $studentId,
            'category' => 'payments',
            'subject' => 'Payment received',
            'body' => 'A payment of LKR ' . number_format($amount, 2) . ' has been recorded' . (!empty($ctx['reference']) ? ' (ref '.$ctx['reference'].')' : '') . '.',
            'variables' => array_merge($ctx, ['amount' => $amount]),
            'idempotency_key' => 'pay-ok-'.($ctx['payment_id'] ?? md5($studentId.'|'.$amount.'|'.($ctx['reference'] ?? ''))),
        ], (int)($ctx['actor_id'] ?? 0));
    }

    /** @param array<string,mixed> $ctx */
    public function homeworkAssigned(int $classId, string $title, array $ctx = []): void
    {
        $this->queueSafe([
            'channel' => 'in_app',
            'audience_type' => 'class',
            'audience_id' => $classId,
            'category' => 'homework',
            'subject' => 'New homework',
            'body' => 'New homework has been posted: '.$title,
            'variables' => $ctx,
            'idempotency_key' => 'hw-'.($ctx['homework_id'] ?? md5($classId.'|'.$title)),
        ], (int)($ctx['actor_id'] ?? 0));
    }

    /** @param array<string,mixed> $ctx */
    public function timetableChanged(int $classId, string $summary, array $ctx = []): void
    {
        $this->queueSafe([
            'channel' => 'in_app',
            'audience_type' => 'class',
            'audience_id' => $classId,
            'include_parents' => true,
            'category' => 'classes',
            'subject' => 'Timetable update',
            'body' => $summary,
            'variables' => $ctx,
            'idempotency_key' => 'tt-'.($ctx['timetable_id'] ?? md5($classId.'|'.$summary.'|'.($ctx['date'] ?? ''))),
        ], (int)($ctx['actor_id'] ?? 0));
        try {
            (new AutomationService($this->pdo))->handle(
                (string)($ctx['event_name'] ?? 'timetable.changed'),
                'tt-'.($ctx['timetable_id'] ?? $classId).'-'.md5($summary),
                array_merge($ctx, ['class_id' => $classId, 'summary' => $summary])
            );
        } catch (Throwable $e) {
        }
    }

    /** @param array<string,mixed> $ctx */
    public function feeOverdue(int $studentId, float $amountDue, array $ctx = []): void
    {
        if ($studentId < 1 || $amountDue < 1) {
            return;
        }
        $this->queueSafe([
            'channel' => 'in_app',
            'audience_type' => 'individual',
            'audience_id' => $studentId,
            'include_parents' => true,
            'category' => 'payments',
            'subject' => 'Fees due reminder',
            'body' => 'An outstanding balance of LKR '.number_format($amountDue, 2).' is recorded. Please pay via the portal or college counter.',
            'variables' => array_merge($ctx, ['amount' => $amountDue]),
            'idempotency_key' => 'fee-due-'.$studentId.'-'.($ctx['ping_date'] ?? date('Y-m-d')),
        ], (int)($ctx['actor_id'] ?? 0));
        try {
            (new AutomationService($this->pdo))->handle('fee.overdue', 'fee-'.$studentId.'-'.($ctx['ping_date'] ?? date('Y-m-d')), array_merge($ctx, ['student_id' => $studentId, 'amount' => $amountDue]));
        } catch (Throwable $e) {
        }
    }

    /** @param array<string,mixed> $ctx */
    public function admissionNotice(string $eventName, ?int $studentId, string $subject, string $body, array $ctx = []): void
    {
        if ($studentId && $studentId > 0) {
            $this->queueSafe([
                'channel' => 'in_app',
                'audience_type' => 'individual',
                'audience_id' => $studentId,
                'category' => 'admissions',
                'subject' => $subject,
                'body' => $body,
                'variables' => $ctx,
                'idempotency_key' => 'adm-'.$eventName.'-'.($ctx['application_id'] ?? $studentId).'-'.md5($subject),
            ], (int)($ctx['actor_id'] ?? 0));
        }
        try {
            (new AutomationService($this->pdo))->handle($eventName, 'adm-'.$eventName.'-'.($ctx['application_id'] ?? $studentId ?? 0), array_merge($ctx, ['student_id' => $studentId]));
        } catch (Throwable $e) {
        }
    }

    /** Link a support ticket to a communication thread (or open one). */
    public function ensureSupportThread(int $ticketId, string $subject, string $role, int $userId, ?int $relatedStudentId = null): int
    {
        try {
            $s = $this->pdo->prepare('SELECT id FROM communication_threads WHERE related_ticket_id=? LIMIT 1');
            $s->execute([$ticketId]);
            $existing = (int)($s->fetchColumn() ?: 0);
            if ($existing > 0) {
                return $existing;
            }
            return (new CommunicationThreadService($this->pdo))->open([
                'subject' => $subject !== '' ? $subject : ('Support ticket #'.$ticketId),
                'thread_type' => 'support',
                'related_ticket_id' => $ticketId,
                'related_student_id' => $relatedStudentId,
            ], $role, $userId);
        } catch (Throwable $e) {
            error_log('ensureSupportThread: '.$e->getMessage());
            return 0;
        }
    }

    /** @param array<string,mixed> $data */
    private function queueSafe(array $data, int $actorId): void
    {
        try {
            $hub = new CommunicationHubService($this->pdo);
            $preview = $hub->preview($data, $actorId);
            if (!empty($preview['needs_confirm']) && !empty($preview['message_id']) && !empty($preview['confirm_token'])) {
                // Automated class notices under threshold should already be queued; if confirm required, leave for admin.
            }
        } catch (Throwable $e) {
            error_log('CommunicationEventService: '.$e->getMessage());
        }
    }
}
