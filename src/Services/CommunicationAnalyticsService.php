<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CommunicationAnalyticsService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function summary(string $from, string $to): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        $count = function (string $sql, array $p = []) {
            try {
                $s = $this->pdo->prepare($sql);
                $s->execute($p);
                return (int)$s->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        $group = function (string $sql, array $p) {
            try {
                $s = $this->pdo->prepare($sql);
                $s->execute($p);
                return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                return [];
            }
        };
        $sent = $count("SELECT COUNT(*) FROM communication_recipients WHERE status='sent' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
        $failed = $count("SELECT COUNT(*) FROM communication_recipients WHERE status='failed' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
        $queued = $count("SELECT COUNT(*) FROM communication_recipients WHERE status='queued'");
        $read = $count("SELECT COUNT(*) FROM notification_center WHERE is_read=1 AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
        $created = $count("SELECT COUNT(*) FROM notification_center WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
        $out = [
            'from' => $from,
            'to' => $to,
            'messages_created' => $count("SELECT COUNT(*) FROM communication_messages WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to]),
            'recipients_sent' => $sent,
            'recipients_failed' => $failed,
            'recipients_queued' => $queued,
            'in_app_created' => $created,
            'in_app_read' => $read,
            'read_rate' => $created > 0 ? round($read / $created * 100, 1) : null,
            'channels' => $group("SELECT channel label, COUNT(*) total FROM communication_recipients WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY channel ORDER BY total DESC", [$from, $to]),
            'categories' => $group("SELECT COALESCE(category,'system') label, COUNT(*) total FROM communication_messages WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY category ORDER BY total DESC", [$from, $to]),
            'provider_delivered' => $count("SELECT COUNT(*) FROM communication_recipients WHERE status='delivered' AND DATE(updated_at) BETWEEN ? AND ?", [$from, $to]),
            'provider_read' => $count("SELECT COUNT(*) FROM communication_recipients WHERE status='read' AND DATE(updated_at) BETWEEN ? AND ?", [$from, $to]),
            'delivery_events' => $count("SELECT COUNT(*) FROM communication_delivery_events WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to]),
            'delivery_event_types' => $group("SELECT event_type label, COUNT(*) total FROM communication_delivery_events WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY event_type ORDER BY total DESC LIMIT 12", [$from, $to]),
            'note' => 'Gateway "sent" means accepted by WhatsApp/SMS API. "Delivered"/"read" only appear after provider webhooks confirm handset delivery.',
        ];
        try {
            $this->pdo->prepare('INSERT INTO communication_analytics_cache(cache_key,payload_json) VALUES(?,?) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),calculated_at=NOW()')
                ->execute(['summary-'.$from.'-'.$to, json_encode($out)]);
        } catch (Throwable $e) {
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function engagement(): array
    {
        $q = function (string $sql): int {
            try {
                return (int)$this->pdo->query($sql)->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        return [
            'students_active_7d' => $q("SELECT COUNT(DISTINCT user_id) FROM notification_center WHERE audience='student' AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"),
            'parents_active_7d' => $q("SELECT COUNT(DISTINCT parent_id) FROM notification_center WHERE audience='parent' AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"),
            'homework_submissions_7d' => $q("SELECT COUNT(*) FROM student_homework_submissions WHERE submitted_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"),
            'assessment_attempts_7d' => $q("SELECT COUNT(*) FROM assessment_attempts WHERE started_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) OR created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"),
            'portal_notifications_7d' => $q("SELECT COUNT(*) FROM notification_center WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"),
            'note' => 'Engagement signals only. Not opening a notification is not treated as academic risk by itself.',
        ];
    }
}
