<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class LeadService
{
    public const STATUSES = ['NEW','CONTACTED','INTERESTED','APPLICATION_STARTED','APPLICATION_SUBMITTED','UNDER_REVIEW','APPROVED','ENROLLED','NOT_INTERESTED','LOST','DEFERRED'];
    public const SOURCES = ['website','facebook','instagram','whatsapp','walk-in','referral','existing_student','other'];
    public const OPEN = ['NEW','CONTACTED','INTERESTED','APPLICATION_STARTED','APPLICATION_SUBMITTED','UNDER_REVIEW','APPROVED'];

    public function __construct(private PDO $pdo)
    {
        self::ensureTables($this->pdo);
    }

    public static function ensureTables(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/database/migrations/032_student_lifecycle_admissions.sql';
        if (!is_file($path)) {
            return;
        }
        $sql = (string)file_get_contents($path);
        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || !preg_match('/CREATE\s+TABLE/i', $stmt)) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
            }
        }
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $userId = 0): int
    {
        $name = trim((string)($data['full_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }
        $dupes = $this->duplicates($data);
        $status = in_array(($data['status'] ?? 'NEW'), self::STATUSES, true) ? $data['status'] : 'NEW';
        $source = in_array(($data['source'] ?? 'other'), self::SOURCES, true) ? $data['source'] : 'other';
        $this->pdo->prepare("
            INSERT INTO admission_leads(full_name,phone,whatsapp,email,programme_label,subject_id,qualification_label,location_pref,delivery_pref,academic_year,source,notes,assigned_to,status,created_by)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            mb_substr($name, 0, 255),
            $this->phone($data['phone'] ?? '') ?: null,
            $this->phone($data['whatsapp'] ?? $data['phone'] ?? '') ?: null,
            trim((string)($data['email'] ?? '')) ?: null,
            $data['programme_label'] ?? null,
            ((int)($data['subject_id'] ?? 0)) ?: null,
            $data['qualification_label'] ?? null,
            $data['location_pref'] ?? null,
            in_array(($data['delivery_pref'] ?? 'either'), ['onsite','online','either'], true) ? $data['delivery_pref'] : 'either',
            $data['academic_year'] ?? null,
            $source,
            mb_substr((string)($data['notes'] ?? ''), 0, 1000) ?: null,
            ((int)($data['assigned_to'] ?? 0)) ?: null,
            $status,
            $userId ?: null,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->event($id, null, null, 'enquiry_created', 'Lead created from '.$source, $userId);
        if ($dupes !== []) {
            $this->event($id, null, null, 'possible_duplicate', 'Possible matches: '.implode(', ', array_column($dupes, 'label')), $userId);
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'lead_created', 'admission_leads', $id, null, ['source' => $source, 'duplicates' => $dupes]);
        }
        try {
            (new AutomationService($this->pdo))->handle('enquiry_created', 'lead-'.$id, ['lead_id' => $id, 'source' => $source]);
        } catch (Throwable $e) {
        }
        $this->ensureFollowup($id, 'New enquiry follow-up', $userId, (int)($data['assigned_to'] ?? 0));
        return $id;
    }

    public function transition(int $leadId, string $status, int $userId, string $note = ''): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid lead status.');
        }
        $row = $this->get($leadId);
        if (!$row) {
            throw new RuntimeException('Lead not found.');
        }
        $this->pdo->prepare('UPDATE admission_leads SET status=?,notes=COALESCE(NULLIF(?,""),notes),updated_at=NOW() WHERE id=?')
            ->execute([$status, $note, $leadId]);
        $this->event($leadId, ((int)($row['application_id'] ?? 0)) ?: null, ((int)($row['student_id'] ?? 0)) ?: null, 'lead_status', $row['status'].' → '.$status.($note !== '' ? ': '.$note : ''), $userId);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'lead_status', 'admission_leads', $leadId, ['status' => $row['status']], ['status' => $status]);
        }
    }

    /** @param array<string,mixed> $data */
    public function scheduleFollowup(array $data, int $userId): int
    {
        $due = strtotime((string)($data['due_at'] ?? '+1 day'));
        if (!$due) {
            throw new RuntimeException('Follow-up due date is required.');
        }
        $this->pdo->prepare("
            INSERT INTO admission_followups(lead_id,application_id,channel,reason,due_at,next_action,assigned_to,notes,created_by)
            VALUES(?,?,?,?,?,?,?,?,?)
        ")->execute([
            ((int)($data['lead_id'] ?? 0)) ?: null,
            ((int)($data['application_id'] ?? 0)) ?: null,
            in_array(($data['channel'] ?? 'note'), ['phone','whatsapp','sms','note','appointment','other'], true) ? $data['channel'] : 'note',
            mb_substr(trim((string)($data['reason'] ?? 'Follow-up')), 0, 255),
            date('Y-m-d H:i:s', $due),
            $data['next_action'] ?? null,
            ((int)($data['assigned_to'] ?? $userId)) ?: null,
            mb_substr((string)($data['notes'] ?? ''), 0, 1000) ?: null,
            $userId ?: null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function completeFollowup(int $id, int $userId, string $notes = ''): void
    {
        $this->pdo->prepare("UPDATE admission_followups SET status='done',notes=COALESCE(NULLIF(?,''),notes),completed_at=NOW(),last_contact_at=NOW() WHERE id=? AND status='open'")
            ->execute([$notes, $id]);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'followup_done', 'admission_followups', $id, null, ['notes' => $notes]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function dueToday(int $assignedTo = 0): array
    {
        try {
            $sql = "SELECT f.*,l.full_name,l.phone,l.status lead_status FROM admission_followups f LEFT JOIN admission_leads l ON l.id=f.lead_id WHERE f.status='open' AND f.due_at<=DATE_ADD(CURDATE(),INTERVAL 1 DAY) ORDER BY f.due_at ASC";
            $p = [];
            if ($assignedTo > 0) {
                $sql = str_replace('ORDER BY', 'AND f.assigned_to=? ORDER BY', $sql);
                $p[] = $assignedTo;
            }
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function pipeline(array $filters = []): array
    {
        $where = ['1=1'];
        $p = [];
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'status=?';
            $p[] = $filters['status'];
        }
        if (!empty($filters['source']) && in_array($filters['source'], self::SOURCES, true)) {
            $where[] = 'source=?';
            $p[] = $filters['source'];
        }
        if ((int)($filters['assigned_to'] ?? 0) > 0) {
            $where[] = 'assigned_to=?';
            $p[] = (int)$filters['assigned_to'];
        }
        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
            $where[] = 'DATE(created_at)>=?';
            $p[] = $filters['from'];
        }
        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
            $where[] = 'DATE(created_at)<=?';
            $p[] = $filters['to'];
        }
        try {
            $s = $this->pdo->prepare('SELECT * FROM admission_leads WHERE '.implode(' AND ', $where).' ORDER BY updated_at DESC,id DESC LIMIT 300');
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM admission_leads WHERE id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return list<array<string,mixed>> */
    public function duplicates(array $data): array
    {
        $out = [];
        $phone = $this->phone($data['phone'] ?? $data['whatsapp'] ?? '');
        $email = trim((string)($data['email'] ?? ''));
        $name = trim((string)($data['full_name'] ?? ''));
        try {
            if ($phone !== '') {
                $s = $this->pdo->prepare("SELECT id,username,'user' kind FROM users WHERE username=? AND deleted_at IS NULL LIMIT 3");
                $s->execute([$phone]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = ['kind' => 'student', 'id' => (int)$r['id'], 'label' => 'Existing account '.$r['username']];
                }
                $s = $this->pdo->prepare("SELECT id,full_name,'lead' kind FROM admission_leads WHERE phone=? OR whatsapp=? ORDER BY id DESC LIMIT 3");
                $s->execute([$phone, $phone]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = ['kind' => 'lead', 'id' => (int)$r['id'], 'label' => 'Lead #'.$r['id'].' '.$r['full_name']];
                }
            }
            if ($email !== '') {
                $s = $this->pdo->prepare("SELECT user_id,full_name FROM student_profiles WHERE email=? LIMIT 3");
                $s->execute([$email]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = ['kind' => 'student', 'id' => (int)$r['user_id'], 'label' => 'Profile '.$r['full_name']];
                }
            }
            if ($name !== '' && strlen($name) > 4) {
                $s = $this->pdo->prepare("SELECT user_id,full_name FROM student_profiles WHERE full_name=? LIMIT 3");
                $s->execute([$name]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = ['kind' => 'student', 'id' => (int)$r['user_id'], 'label' => 'Same name '.$r['full_name']];
                }
            }
        } catch (Throwable $e) {
        }
        return $out;
    }

    public function linkApplication(int $leadId, int $applicationId): void
    {
        $this->pdo->prepare("UPDATE admission_leads SET application_id=?,status=IF(status IN ('NEW','CONTACTED','INTERESTED','APPLICATION_STARTED'),'APPLICATION_SUBMITTED',status) WHERE id=?")
            ->execute([$applicationId, $leadId]);
    }

    public function event(?int $leadId, ?int $applicationId, ?int $studentId, string $type, ?string $detail, int $userId = 0): void
    {
        try {
            $this->pdo->prepare('INSERT INTO admission_lifecycle_events(lead_id,application_id,student_id,event_type,detail,created_by) VALUES(?,?,?,?,?,?)')
                ->execute([$leadId, $applicationId, $studentId, mb_substr($type, 0, 80), $detail !== null ? mb_substr($detail, 0, 500) : null, $userId ?: null]);
        } catch (Throwable $e) {
        }
    }

    private function ensureFollowup(int $leadId, string $reason, int $userId, int $assigned): void
    {
        try {
            $s = $this->pdo->prepare("SELECT 1 FROM admission_followups WHERE lead_id=? AND status='open' LIMIT 1");
            $s->execute([$leadId]);
            if ($s->fetchColumn()) {
                return;
            }
            $this->scheduleFollowup(['lead_id' => $leadId, 'reason' => $reason, 'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')), 'assigned_to' => $assigned ?: $userId], $userId);
        } catch (Throwable $e) {
        }
    }

    private function phone(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }
}
