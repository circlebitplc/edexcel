<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class AdmissionAnalyticsService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function funnel(string $from, string $to): array
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
        $enquiries = $count('SELECT COUNT(*) FROM admission_leads WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]);
        $contacted = $count("SELECT COUNT(*) FROM admission_leads WHERE DATE(created_at) BETWEEN ? AND ? AND status NOT IN ('NEW')", [$from, $to]);
        $applications = $count('SELECT COUNT(*) FROM admission_applications WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]);
        $approved = $count("SELECT COUNT(*) FROM admission_applications WHERE DATE(created_at) BETWEEN ? AND ? AND status='approved'", [$from, $to]);
        $enrolled = $count("SELECT COUNT(*) FROM admission_applications WHERE DATE(created_at) BETWEEN ? AND ? AND lifecycle_status='enrolled'", [$from, $to]);
        $lost = $count("SELECT COUNT(*) FROM admission_leads WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('LOST','NOT_INTERESTED')", [$from, $to]);
        $pct = static fn(int $a, int $b): ?float => $b > 0 ? round($a / $b * 100, 1) : null;
        $out = [
            'from' => $from,
            'to' => $to,
            'enquiries' => $enquiries,
            'contacted' => $contacted,
            'applications' => $applications,
            'approved' => $approved,
            'enrolled' => $enrolled,
            'lost' => $lost,
            'enquiry_to_application' => $pct($applications, $enquiries),
            'application_to_admission' => $pct($approved, $applications),
            'admission_to_enrollment' => $pct($enrolled, $approved),
            'sources' => $this->group("SELECT source label,COUNT(*) total FROM admission_leads WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY source ORDER BY total DESC", [$from, $to]),
            'subjects' => $this->group("SELECT COALESCE(qualification_label,'—') label,COUNT(*) total FROM admission_applications WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY qualification_label ORDER BY total DESC", [$from, $to]),
            'locations' => $this->group("SELECT COALESCE(location_pref,'—') label,COUNT(*) total FROM admission_applications WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY location_pref ORDER BY total DESC", [$from, $to]),
            'time_to_admission_days' => $count("SELECT ROUND(AVG(TIMESTAMPDIFF(DAY, created_at, reviewed_at)),1) FROM admission_applications WHERE reviewed_at IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]),
        ];
        $this->cache('funnel-'.$from.'-'.$to, $out);
        return $out;
    }

    /** @return array<string,mixed> */
    public function revenue(string $from, string $to): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        $expected = 0.0;
        $collected = 0.0;
        $pending = 0.0;
        try {
            $s = $this->pdo->prepare("SELECT COALESCE(SUM(fee_amount),0) FROM admission_offers WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('issued','accepted')");
            $s->execute([$from, $to]);
            $expected = (float)$s->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $s = $this->pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM admission_payments WHERE status='verified' AND DATE(created_at) BETWEEN ? AND ?");
            $s->execute([$from, $to]);
            $collected = (float)$s->fetchColumn();
        } catch (Throwable $e) {
        }
        $pending = max(0, $expected - $collected);
        return ['from' => $from, 'to' => $to, 'expected' => $expected, 'collected' => $collected, 'pending' => $pending, 'note' => 'Read-only view of admission-linked amounts. Payment transaction records are unchanged.'];
    }

    /** @return array<string,int> */
    public function todayCounts(): array
    {
        $q = function (string $sql): int {
            try {
                return (int)$this->pdo->query($sql)->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        return [
            'new_enquiries' => $q("SELECT COUNT(*) FROM admission_leads WHERE DATE(created_at)=CURDATE()"),
            'new_applications' => $q("SELECT COUNT(*) FROM admission_applications WHERE DATE(created_at)=CURDATE()"),
            'pending_review' => $q("SELECT COUNT(*) FROM admission_applications WHERE status='pending' AND COALESCE(lifecycle_status,'submitted') IN ('submitted','under_review','draft')"),
            'pending_payments' => $q("SELECT COUNT(*) FROM admission_applications WHERE lifecycle_status='payment_required'"),
            'awaiting_allocation' => $q("SELECT COUNT(*) FROM admission_applications WHERE lifecycle_status IN ('payment_received','approved')"),
            'enrolled_today' => $q("SELECT COUNT(*) FROM admission_applications WHERE lifecycle_status='enrolled' AND DATE(updated_at)=CURDATE()"),
            'followups_due' => $q("SELECT COUNT(*) FROM admission_followups WHERE status='open' AND due_at<=NOW()"),
            'overdue_followups' => $q("SELECT COUNT(*) FROM admission_followups WHERE status='open' AND due_at<CURDATE()"),
        ];
    }

    private function cache(string $key, array $payload): void
    {
        try {
            $this->pdo->prepare('INSERT INTO admission_analytics_cache(cache_key,payload_json) VALUES(?,?) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),calculated_at=NOW()')
                ->execute([mb_substr($key, 0, 80), json_encode($payload)]);
        } catch (Throwable $e) {
        }
    }

    /** @return list<array<string,mixed>> */
    private function group(string $sql, array $p): array
    {
        try {
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
