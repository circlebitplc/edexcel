<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AdmissionLifecycleService
{
    public const APP_STATUSES = ['draft','submitted','under_review','approved','payment_required','payment_received','allocated','enrolled','rejected','hold','withdrawn'];

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->pdo->inTransaction()) {
            return;
        }
        LeadService::ensureTables($this->pdo);
        $cols = [
            'lifecycle_status' => "VARCHAR(40) NOT NULL DEFAULT 'submitted'",
            'tracking_token' => 'CHAR(32) NULL',
            'source' => 'VARCHAR(40) NULL',
            'qualification_label' => 'VARCHAR(120) NULL',
            'location_pref' => 'VARCHAR(120) NULL',
            'delivery_pref' => 'VARCHAR(20) NULL',
            'academic_year' => 'VARCHAR(40) NULL',
            'address' => 'VARCHAR(500) NULL',
            'previous_school' => 'VARCHAR(255) NULL',
            'parent_relationship' => 'VARCHAR(40) NULL',
            'lead_id' => 'BIGINT UNSIGNED NULL',
            'assigned_to' => 'INT NULL',
            'applicant_message' => 'VARCHAR(500) NULL',
        ];
        foreach ($cols as $col => $def) {
            try {
                if (function_exists('campus_column_exists') && campus_column_exists($this->pdo, 'admission_applications', $col)) {
                    continue;
                }
                $this->pdo->exec("ALTER TABLE admission_applications ADD COLUMN {$col} {$def}");
            } catch (Throwable $e) {
            }
        }
    }

    public static function normalizeToken(?string $raw): string
    {
        $token = strtolower(preg_replace('/[^a-f0-9]/', '', (string)$raw) ?? '');
        return strlen($token) === 32 ? $token : bin2hex(random_bytes(16));
    }

    /** @param array<string,mixed> $data */
    public function submitApplication(array $data, bool $draft = false): array
    {
        $token = self::normalizeToken(isset($data['tracking_token']) ? (string)$data['tracking_token'] : null);
        $existingId = (int)($data['application_id'] ?? 0);
        if ($existingId < 1) {
            $found = $this->byToken($token);
            $existingId = (int)($found['id'] ?? 0);
        }
        $payload = [
            'full_name' => $data['full_name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'parent_name' => $data['parent_name'] ?? '',
            'parent_phone' => $data['parent_phone'] ?? '',
            'subject_ids' => $data['subject_ids'] ?? [],
            'class_ids' => $data['class_ids'] ?? [],
        ];
        if ($existingId > 0) {
            $this->pdo->prepare("UPDATE admission_applications SET full_name=?,phone=?,email=?,date_of_birth=?,parent_name=?,parent_phone=?,selected_subjects_json=?,selected_classes_json=?,qualification_label=?,location_pref=?,delivery_pref=?,academic_year=?,address=?,previous_school=?,parent_relationship=?,source=?,lifecycle_status=?,status=? WHERE id=?")
                ->execute([
                    $payload['full_name'], $payload['phone'] ?: null, $payload['email'] ?: null, $payload['date_of_birth'] ?: null,
                    $payload['parent_name'] ?: null, $payload['parent_phone'] ?: null,
                    json_encode(array_values(array_map('intval', (array)$payload['subject_ids']))),
                    json_encode(array_values(array_map('intval', (array)$payload['class_ids']))),
                    $data['qualification_label'] ?? null, $data['location_pref'] ?? null, $data['delivery_pref'] ?? null,
                    $data['academic_year'] ?? null, $data['address'] ?? null, $data['previous_school'] ?? null,
                    $data['parent_relationship'] ?? null, $data['source'] ?? 'website',
                    $draft ? 'draft' : 'submitted', $draft ? 'pending' : 'pending', $existingId,
                ]);
            $id = $existingId;
            $row = $this->get($id);
            $no = (string)($row['application_no'] ?? '');
        } else {
            $id = (new AdmissionService($this->pdo))->createApplication($payload, true);
            $this->pdo->prepare("UPDATE admission_applications SET tracking_token=?,lifecycle_status=?,source=?,qualification_label=?,location_pref=?,delivery_pref=?,academic_year=?,address=?,previous_school=?,parent_relationship=?,lead_id=? WHERE id=?")
                ->execute([
                    $token, $draft ? 'draft' : 'submitted', $data['source'] ?? 'website',
                    $data['qualification_label'] ?? null, $data['location_pref'] ?? null, $data['delivery_pref'] ?? null,
                    $data['academic_year'] ?? null, $data['address'] ?? null, $data['previous_school'] ?? null,
                    $data['parent_relationship'] ?? null, ((int)($data['lead_id'] ?? 0)) ?: null, $id,
                ]);
            $row = $this->get($id);
            $no = (string)($row['application_no'] ?? '');
        }
        if (!$draft) {
            if ((int)($data['lead_id'] ?? 0) > 0) {
                (new LeadService($this->pdo))->linkApplication((int)$data['lead_id'], $id);
            }
            $this->pdo->prepare('UPDATE admission_applications SET applicant_message=? WHERE id=?')->execute([AdmissionMessageTemplates::RECEIVED, $id]);
            (new LeadService($this->pdo))->event(((int)($data['lead_id'] ?? 0)) ?: null, $id, null, 'application_submitted', 'Application '.$no.' submitted', 0);
            try {
                (new AutomationService($this->pdo))->handle('application_submitted', 'app-'.$id, ['application_id' => $id]);
            } catch (Throwable $e) {
            }
            try {
                (new NotificationCenterService($this->pdo))->create('admin', 'system', 'New application submitted', $payload['full_name'].' — '.$no, BASE_URL.'admin/admission_review.php?id='.$id, null, null, 'normal', ['application_id' => $id]);
            } catch (Throwable $e) {
            }
        }
        return ['id' => $id, 'application_no' => $no, 'tracking_token' => $token, 'lifecycle_status' => $draft ? 'draft' : 'submitted'];
    }

    public function review(int $id, string $action, int $userId, string $notes = ''): void
    {
        $app = $this->get($id);
        if (!$app) {
            throw new RuntimeException('Application not found.');
        }
        $map = [
            'under_review' => ['lifecycle' => 'under_review', 'status' => 'pending'],
            'hold' => ['lifecycle' => 'hold', 'status' => 'pending'],
            'request_information' => ['lifecycle' => 'hold', 'status' => 'pending'],
            'reject' => ['lifecycle' => 'rejected', 'status' => 'rejected'],
            'approve' => ['lifecycle' => 'approved', 'status' => 'approved'],
        ];
        if (!isset($map[$action])) {
            throw new RuntimeException('Invalid review action.');
        }
        if ($action === 'approve' && !AdmissionAuth::can($this->pdo, $userId, 'admissions.approve') && !AdmissionAuth::isAdmin()) {
            throw new RuntimeException('Approval permission required.');
        }
        if ($action === 'reject' && !AdmissionAuth::can($this->pdo, $userId, 'admissions.reject') && !AdmissionAuth::isAdmin()) {
            throw new RuntimeException('Rejection permission required.');
        }
        if ($action === 'approve') {
            $this->issueOffer($id, $userId, $notes);
            return;
        }
        if ($action === 'reject') {
            (new AdmissionService($this->pdo))->decide($id, 'rejected', $userId, $notes);
        }
        $this->pdo->prepare('UPDATE admission_applications SET lifecycle_status=?,status=?,admission_notes=?,reviewed_by=?,reviewed_at=NOW(),applicant_message=? WHERE id=?')
            ->execute([$map[$action]['lifecycle'], $map[$action]['status'], $notes ?: null, $userId, $action === 'request_information' ? $notes : ($app['applicant_message'] ?? null), $id]);
        (new LeadService($this->pdo))->event((int)($app['lead_id'] ?? 0) ?: null, $id, (int)($app['student_id'] ?? 0) ?: null, 'application_'.$action, $notes, $userId);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'application_'.$action, 'admission_applications', $id, ['status' => $app['status']], ['action' => $action]);
        }
    }

    public function issueOffer(int $applicationId, int $userId, string $notes = ''): int
    {
        $app = $this->get($applicationId);
        if (!$app) {
            throw new RuntimeException('Application not found.');
        }
        $studentId = $this->ensureStudentAccount($app, $userId);
        $classes = json_decode((string)($app['selected_classes_json'] ?? '[]'), true) ?: [];
        $classId = (int)($classes[0] ?? 0);
        $class = $classId ? $this->classRow($classId) : null;
        $no = 'OFR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        $this->pdo->prepare("INSERT INTO admission_offers(application_id,offer_no,programme_label,subject_label,class_id,teacher_label,location_label,schedule_text,fee_amount,payment_instructions,terms_text,issued_by,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 14 DAY))")
            ->execute([
                $applicationId, $no, $app['qualification_label'] ?? 'Programme', $app['qualification_label'] ?? null,
                $classId ?: null, $class['teacher_name'] ?? null, $class['name'] ?? ($app['location_pref'] ?? null),
                $class['schedule'] ?? null, 0,
                'Pay using the existing college payment methods (cash, bank transfer, or OnePay) after you accept this offer.',
                'This offer is an operational admission offer. It is not a legally reviewed contract.',
                $userId ?: null,
            ]);
        $offerId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("UPDATE admission_applications SET status='approved',lifecycle_status='payment_required',student_id=?,admission_notes=?,reviewed_by=?,reviewed_at=NOW(),applicant_message=? WHERE id=?")
            ->execute([$studentId, $notes ?: null, $userId, AdmissionMessageTemplates::APPROVED, $applicationId]);
        (new LeadService($this->pdo))->event((int)($app['lead_id'] ?? 0) ?: null, $applicationId, $studentId, 'offer_issued', $no, $userId);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'admission_offer_issued', 'admission_offers', $offerId, null, ['application_id' => $applicationId]);
        }
        try {
            (new NotificationCenterService($this->pdo))->create('admin', 'system', 'Admission offer issued', $app['full_name'].' — '.$no, BASE_URL.'admin/admission_review.php?id='.$applicationId, null, null, 'normal', ['offer_id' => $offerId]);
        } catch (Throwable $e) {
        }
            try {
                (new AutomationService($this->pdo))->handle('application_approved', 'app-'.$applicationId, ['application_id' => $applicationId, 'student_id' => $studentId]);
            } catch (Throwable $e) {
            }
            try {
                (new CommunicationEventService($this->pdo))->admissionNotice(
                    'application_approved',
                    $studentId ?: null,
                    'Application approved',
                    'Your application has been approved. Check the admissions portal for next steps.',
                    ['application_id' => $applicationId, 'actor_id' => $userId]
                );
            } catch (Throwable $e) {
            }
        return $offerId;
    }

    public function acceptOffer(int $offerId, string $token): void
    {
        $s = $this->pdo->prepare('SELECT o.*,a.tracking_token FROM admission_offers o JOIN admission_applications a ON a.id=o.application_id WHERE o.id=?');
        $s->execute([$offerId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals((string)$row['tracking_token'], $token)) {
            throw new RuntimeException('Offer not found.');
        }
        if ($row['status'] !== 'issued') {
            throw new RuntimeException('This offer is no longer open.');
        }
        $this->pdo->prepare("UPDATE admission_offers SET status='accepted',accepted_at=NOW() WHERE id=?")->execute([$offerId]);
        $this->pdo->prepare("UPDATE admission_applications SET lifecycle_status='payment_required' WHERE id=?")->execute([(int)$row['application_id']]);
        (new LeadService($this->pdo))->event(null, (int)$row['application_id'], null, 'offer_accepted', (string)$row['offer_no'], 0);
    }

    public function recordVerifiedPayment(int $applicationId, array $data, int $userId): int
    {
        $amount = max(0, (float)($data['amount'] ?? 0));
        $method = in_array(($data['method'] ?? 'cash'), ['cash','bank','onepay'], true) ? $data['method'] : 'cash';
        $ref = trim((string)($data['reference'] ?? '')) ?: ('ADM-'.$applicationId.'-'.date('YmdHis'));
        try {
            $this->pdo->prepare("INSERT INTO admission_payments(application_id,payment_id,amount,method,reference,status,verified_by,verified_at) VALUES(?,?,?,?,?,'verified',?,NOW())")
                ->execute([$applicationId, ((int)($data['payment_id'] ?? 0)) ?: null, $amount, $method, $ref, $userId ?: null]);
        } catch (Throwable $e) {
            throw new RuntimeException('Duplicate or invalid payment reference.');
        }
        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("UPDATE admission_applications SET lifecycle_status='payment_received',applicant_message=? WHERE id=?")->execute([AdmissionMessageTemplates::PAYMENT_CONFIRMED, $applicationId]);
        (new LeadService($this->pdo))->event(null, $applicationId, null, 'payment_verified', $method.' '.$ref, $userId);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'admission_payment_verified', 'admission_payments', $id, null, ['application_id' => $applicationId]);
        }
        try {
            (new AutomationService($this->pdo))->handle('admission_payment_completed', 'pay-'.$id, ['application_id' => $applicationId]);
        } catch (Throwable $e) {
        }
        return $id;
    }

    public function enroll(int $applicationId, array $classIds, int $userId, bool $override = false): int
    {
        $app = $this->get($applicationId);
        if (!$app) {
            throw new RuntimeException('Application not found.');
        }
        $studentId = (int)($app['student_id'] ?? 0);
        if ($studentId < 1) {
            $studentId = $this->ensureStudentAccount($app, $userId);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if ($ids === []) {
            $ids = array_values(array_filter(array_map('intval', json_decode((string)($app['selected_classes_json'] ?? '[]'), true) ?: [])));
        }
        $capacity = new CapacityService($this->pdo);
        foreach ($ids as $classId) {
            $s = $this->pdo->prepare('SELECT 1 FROM student_enrollments WHERE student_id=? AND class_id=? LIMIT 1');
            $s->execute([$studentId, $classId]);
            if ($s->fetchColumn()) {
                continue;
            }
            try {
                $capacity->assertCanEnroll($classId, $override);
                $this->pdo->prepare('INSERT IGNORE INTO student_enrollments(student_id,class_id) VALUES(?,?)')->execute([$studentId, $classId]);
                $this->pdo->prepare("INSERT INTO enrollment_history(student_id,class_id,action,notes,created_by) VALUES(?,?,'enrolled',?,?)")
                    ->execute([$studentId, $classId, 'Admission enrollment', $userId]);
            } catch (RuntimeException $e) {
                if (function_exists('campus_waitlist_join')) {
                    campus_waitlist_join($this->pdo, $studentId, $classId);
                }
                (new LeadService($this->pdo))->event((int)($app['lead_id'] ?? 0) ?: null, $applicationId, $studentId, 'waitlisted', 'Class '.$classId.' full', $userId);
            }
        }
        $enrolledCount = 0;
        try {
            $c = $this->pdo->prepare('SELECT COUNT(*) FROM student_enrollments WHERE student_id=?');
            $c->execute([$studentId]);
            $enrolledCount = (int)$c->fetchColumn();
        } catch (Throwable $e) {
        }
        if ($enrolledCount > 0) {
            $this->pdo->prepare("UPDATE admission_applications SET lifecycle_status='enrolled',student_id=? WHERE id=?")->execute([$studentId, $applicationId]);
        } else {
            $this->pdo->prepare('UPDATE admission_applications SET student_id=? WHERE id=?')->execute([$studentId, $applicationId]);
        }
        if ((int)($app['lead_id'] ?? 0) > 0) {
            if ($enrolledCount > 0) {
                $this->pdo->prepare("UPDATE admission_leads SET status='ENROLLED',student_id=? WHERE id=?")->execute([$studentId, (int)$app['lead_id']]);
            } else {
                $this->pdo->prepare('UPDATE admission_leads SET student_id=? WHERE id=?')->execute([$studentId, (int)$app['lead_id']]);
            }
        }
        $this->createOnboarding($studentId, $applicationId);
        $this->linkParent($studentId, $app);
        (new LeadService($this->pdo))->event((int)($app['lead_id'] ?? 0) ?: null, $applicationId, $studentId, $enrolledCount > 0 ? 'enrolled' : 'waitlisted', 'Student account '.$studentId, $userId);
        if ($enrolledCount > 0) {
            try {
                $this->pdo->prepare("UPDATE admission_applications SET applicant_message=? WHERE id=?")->execute([AdmissionMessageTemplates::WELCOME.' '.AdmissionMessageTemplates::CLASS_ALLOCATED, $applicationId]);
                (new NotificationCenterService($this->pdo))->create('student', 'system', AdmissionMessageTemplates::WELCOME, AdmissionMessageTemplates::CLASS_ALLOCATED.' Open the student portal to see your timetable.', BASE_URL.'student/onboarding.php', $studentId, null, 'normal', ['application_id' => $applicationId]);
            } catch (Throwable $e) {
            }
            try {
                (new AutomationService($this->pdo))->handle('enrollment_completed', 'enroll-'.$applicationId, ['student_id' => $studentId, 'application_id' => $applicationId]);
            } catch (Throwable $e) {
            }
            try {
                (new CommunicationEventService($this->pdo))->admissionNotice(
                    'enrollment_completed',
                    $studentId,
                    'Welcome to Edexcel College',
                    'Enrollment is complete. Open the student portal for timetable, fees, and onboarding.',
                    ['application_id' => $applicationId, 'actor_id' => $userId]
                );
            } catch (Throwable $e) {
            }
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'admission_enrolled', 'admission_applications', $applicationId, null, ['student_id' => $studentId, 'classes' => $ids]);
        }
        return $studentId;
    }

    public function acceptAgreement(int $applicationId, string $key, string $version, string $acceptedBy, ?int $studentId = null): void
    {
        $this->pdo->prepare('INSERT INTO admission_agreements(application_id,student_id,document_key,version,accepted,accepted_by,accepted_at,ip_address) VALUES(?,?,?,?,1,?,NOW(),?)')
            ->execute([$applicationId, $studentId, mb_substr($key, 0, 80), mb_substr($version, 0, 40), mb_substr($acceptedBy, 0, 120), mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64)]);
    }

    public function storeDocument(int $applicationId, array $file, string $type, ?int $userId): int
    {
        $stored = (new SecureUploadService())->store($file, 'admissions/'.$applicationId, 8_388_608);
        $this->pdo->prepare('INSERT INTO admission_documents(application_id,document_type,original_name,storage_path,mime_type,size_bytes,uploaded_by) VALUES(?,?,?,?,?,?,?)')
            ->execute([$applicationId, mb_substr($type, 0, 80), $stored['original_name'], $stored['relative_path'], $stored['mime'], $stored['size'], $userId]);
        $id = (int)$this->pdo->lastInsertId();
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'admission_document_uploaded', 'admission_documents', $id, null, ['application_id' => $applicationId, 'type' => $type]);
        }
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function documents(int $applicationId): array
    {
        try {
            $s = $this->pdo->prepare('SELECT id,document_type,original_name,mime_type,size_bytes,created_at FROM admission_documents WHERE application_id=? ORDER BY id');
            $s->execute([$applicationId]);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    public function publicStatus(string $token): ?array
    {
        $app = $this->byToken($token);
        if (!$app) {
            return null;
        }
        $offer = $this->openOffer((int)$app['id']);
        return [
            'application_no' => $app['application_no'],
            'full_name' => $app['full_name'],
            'lifecycle_status' => $app['lifecycle_status'] ?? $app['status'],
            'applicant_message' => $app['applicant_message'] ?? null,
            'created_at' => $app['created_at'],
            'offer' => $offer ? [
                'id' => (int)$offer['id'],
                'offer_no' => $offer['offer_no'],
                'programme_label' => $offer['programme_label'],
                'subject_label' => $offer['subject_label'],
                'teacher_label' => $offer['teacher_label'],
                'location_label' => $offer['location_label'],
                'schedule_text' => $offer['schedule_text'],
                'fee_amount' => $offer['fee_amount'],
                'payment_instructions' => $offer['payment_instructions'],
                'terms_text' => $offer['terms_text'],
                'status' => $offer['status'],
            ] : null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function dashboard(array $filters = []): array
    {
        $where = ['1=1'];
        $p = [];
        if (!empty($filters['status'])) {
            $where[] = '(a.status=? OR a.lifecycle_status=?)';
            $p[] = $filters['status'];
            $p[] = $filters['status'];
        }
        if (!empty($filters['qualification'])) {
            $where[] = 'a.qualification_label=?';
            $p[] = $filters['qualification'];
        }
        if ((int)($filters['assigned_to'] ?? 0) > 0) {
            $where[] = 'a.assigned_to=?';
            $p[] = (int)$filters['assigned_to'];
        }
        if (!empty($filters['source'])) {
            $where[] = 'a.source=?';
            $p[] = $filters['source'];
        }
        if (!empty($filters['location'])) {
            $where[] = 'a.location_pref=?';
            $p[] = $filters['location'];
        }
        if (!empty($filters['academic_year'])) {
            $where[] = 'a.academic_year=?';
            $p[] = $filters['academic_year'];
        }
        if ((int)($filters['subject_id'] ?? 0) > 0) {
            $where[] = 'JSON_CONTAINS(a.selected_subjects_json, ?)';
            $p[] = (string)(int)$filters['subject_id'];
        }
        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
            $where[] = 'DATE(a.created_at)>=?';
            $p[] = $filters['from'];
        }
        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
            $where[] = 'DATE(a.created_at)<=?';
            $p[] = $filters['to'];
        }
        try {
            $s = $this->pdo->prepare('SELECT a.* FROM admission_applications a WHERE '.implode(' AND ', $where).' ORDER BY a.created_at DESC LIMIT 300');
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
            $s = $this->pdo->prepare('SELECT * FROM admission_applications WHERE id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function byToken(string $token): ?array
    {
        $token = strtolower(preg_replace('/[^a-f0-9]/', '', $token) ?? '');
        if (strlen($token) !== 32) {
            return null;
        }
        try {
            $s = $this->pdo->prepare('SELECT * FROM admission_applications WHERE tracking_token=? LIMIT 1');
            $s->execute([$token]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return list<array<string,mixed>> */
    public function timeline(?int $leadId, ?int $applicationId, ?int $studentId): array
    {
        $out = [];
        try {
            $s = $this->pdo->prepare('SELECT created_at,event_type,detail FROM admission_lifecycle_events WHERE (? IS NOT NULL AND lead_id=?) OR (? IS NOT NULL AND application_id=?) OR (? IS NOT NULL AND student_id=?) ORDER BY created_at');
            $s->execute([$leadId, $leadId, $applicationId, $applicationId, $studentId, $studentId]);
            $out = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
        }
        if ($studentId) {
            foreach ((new AdmissionService($this->pdo))->history($studentId) as $h) {
                $out[] = ['created_at' => $h['created_at'], 'event_type' => $h['action'], 'detail' => $h['notes'] ?? ''];
            }
        }
        usort($out, static fn($a, $b) => strcmp((string)$a['created_at'], (string)$b['created_at']));
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function openOffer(int $applicationId): ?array
    {
        try {
            $s = $this->pdo->prepare("SELECT * FROM admission_offers WHERE application_id=? ORDER BY id DESC LIMIT 1");
            $s->execute([$applicationId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function syncFromExistingPayment(int $studentId, int $paymentId, float $amount, string $method, string $reference): void
    {
        if ($studentId < 1 || $paymentId < 1) {
            return;
        }
        try {
            $s = $this->pdo->prepare("SELECT id FROM admission_applications WHERE student_id=? AND lifecycle_status IN ('payment_required','approved') ORDER BY id DESC LIMIT 1");
            $s->execute([$studentId]);
            $applicationId = (int)$s->fetchColumn();
        } catch (Throwable $e) {
            return;
        }
        if ($applicationId < 1) {
            return;
        }
        $gateway = strtolower($method);
        $mapped = in_array($gateway, ['cash', 'bank', 'onepay'], true) ? $gateway : 'onepay';
        $ref = trim($reference) !== '' ? trim($reference) : ('PAY-'.$paymentId);
        try {
            $this->recordVerifiedPayment($applicationId, [
                'amount' => $amount,
                'method' => $mapped,
                'reference' => $ref,
                'payment_id' => $paymentId,
            ], 0);
        } catch (RuntimeException $e) {
            // Duplicate verified callback — application status stays unchanged.
        }
    }

    public function transfer(int $studentId, int $fromClass, int $toClass, int $userId, string $reason, bool $override = false): void
    {
        (new ClassOperationsService($this->pdo))->transferStudent($studentId, $fromClass, $toClass, $userId, $reason, $override);
        (new LeadService($this->pdo))->event(null, null, $studentId, 'transferred', $fromClass.' → '.$toClass.($reason !== '' ? ': '.$reason : ''), $userId);
    }

    public function reenroll(int $studentId, array $classIds, int $userId): void
    {
        $s = $this->pdo->prepare("SELECT id FROM users WHERE id=? AND role='student' AND deleted_at IS NULL");
        $s->execute([$studentId]);
        if (!$s->fetchColumn()) {
            throw new RuntimeException('Existing student not found.');
        }
        $this->enrollFromStudent($studentId, $classIds, $userId);
        $this->pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$studentId]);
        (new LeadService($this->pdo))->event(null, null, $studentId, 'reenrolled', 'Returning student new period', $userId);
    }

    public function completeProgramme(int $studentId, int $classId, int $userId, string $notes = ''): void
    {
        $this->pdo->prepare("INSERT INTO enrollment_history(student_id,class_id,action,notes,created_by) VALUES(?,?,'completed',?,?)")
            ->execute([$studentId, $classId, $notes ?: 'Programme completion recorded', $userId]);
        (new LeadService($this->pdo))->event(null, null, $studentId, 'completed', $notes, $userId);
    }

    public function withdraw(int $studentId, int $classId, int $userId, string $reason, string $effective = ''): void
    {
        if (function_exists('campus_unenroll_from_class')) {
            campus_unenroll_from_class($this->pdo, $studentId, $classId, $reason, $userId);
        } else {
            $this->pdo->prepare('DELETE FROM student_enrollments WHERE student_id=? AND class_id=?')->execute([$studentId, $classId]);
        }
        $this->pdo->prepare("INSERT INTO enrollment_history(student_id,class_id,action,notes,created_by) VALUES(?,?,'withdrawn',?,?)")
            ->execute([$studentId, $classId, trim($reason.' '.$effective), $userId]);
        (new LeadService($this->pdo))->event(null, null, $studentId, 'withdrawn', $reason, $userId);
    }

    /** @return array<string,mixed> */
    public function onboarding(int $studentId): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM admission_onboarding WHERE student_id=?');
            $s->execute([$studentId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['checklist'] = json_decode((string)$row['checklist_json'], true) ?: [];
                return $row;
            }
        } catch (Throwable $e) {
        }
        return ['checklist' => $this->defaultChecklist($studentId)];
    }

    public function refreshOnboarding(int $studentId): void
    {
        $this->createOnboarding($studentId, null);
    }

    private function createOnboarding(int $studentId, ?int $applicationId): void
    {
        $list = $this->defaultChecklist($studentId);
        try {
            $this->pdo->prepare('INSERT INTO admission_onboarding(student_id,application_id,checklist_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE checklist_json=VALUES(checklist_json),application_id=COALESCE(VALUES(application_id),application_id),updated_at=NOW()')
                ->execute([$studentId, $applicationId, json_encode($list)]);
        } catch (Throwable $e) {
        }
    }

    /** @return list<array{key:string,label:string,done:bool}> */
    private function defaultChecklist(int $studentId): array
    {
        $enrolled = 0;
        $paid = 0;
        $lessons = 0;
        $homework = 0;
        try {
            $enrolled = (int)$this->pdo->query('SELECT COUNT(*) FROM student_enrollments WHERE student_id='.(int)$studentId)->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $s = $this->pdo->prepare("SELECT COUNT(*) FROM assessment_attempts WHERE student_id=? OR 1=0");
            $s->execute([$studentId]);
        } catch (Throwable $e) {
        }
        try {
            $s = $this->pdo->prepare("SELECT COUNT(*) FROM student_attendance WHERE student_id=? LIMIT 1");
            $s->execute([$studentId]);
            $lessons = (int)$s->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $s = $this->pdo->prepare("SELECT COUNT(*) FROM student_homework_submissions WHERE student_id=?");
            $s->execute([$studentId]);
            $homework = (int)$s->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $s = $this->pdo->prepare("SELECT COUNT(*) FROM payment_transactions WHERE student_id=? AND status IN ('paid','waived','verified','success')");
            $s->execute([$studentId]);
            $paid = (int)$s->fetchColumn();
        } catch (Throwable $e) {
        }
        return [
            ['key' => 'account', 'label' => 'Account created', 'done' => $studentId > 0],
            ['key' => 'class', 'label' => 'Class assigned', 'done' => $enrolled > 0],
            ['key' => 'payment', 'label' => 'Payment completed', 'done' => $paid > 0],
            ['key' => 'timetable', 'label' => 'Timetable available', 'done' => $enrolled > 0],
            ['key' => 'portal', 'label' => 'Learning portal activated', 'done' => true],
            ['key' => 'first_lesson', 'label' => 'First lesson attended', 'done' => $lessons > 0],
            ['key' => 'first_homework', 'label' => 'First homework completed', 'done' => $homework > 0],
        ];
    }

    /** @param array<string,mixed> $app */
    public function ensureStudentAccount(array $app, int $userId): int
    {
        if ((int)($app['student_id'] ?? 0) > 0) {
            return (int)$app['student_id'];
        }
        $phone = trim((string)($app['phone'] ?? ''));
        if ($phone !== '') {
            $s = $this->pdo->prepare("SELECT id FROM users WHERE username=? AND role='student' AND deleted_at IS NULL LIMIT 1");
            $s->execute([$phone]);
            $existing = (int)$s->fetchColumn();
            if ($existing > 0) {
                $this->pdo->prepare('UPDATE admission_applications SET student_id=? WHERE id=?')->execute([$existing, (int)$app['id']]);
                return $existing;
            }
        }
        $username = $phone !== '' ? $phone : 'student_'.$app['id'];
        $hash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
        try {
            $this->pdo->prepare("INSERT INTO users (username,password_hash,role,is_active) VALUES (?,?,'student',1)")->execute([$username, $hash]);
            $studentId = (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            $q = $this->pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            $q->execute([$username]);
            $studentId = (int)$q->fetchColumn();
        }
        if ($studentId > 0) {
            $this->pdo->prepare("INSERT INTO student_profiles (user_id, full_name, email, whatsapp_number, parent_name, parent_whatsapp) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),email=VALUES(email),whatsapp_number=VALUES(whatsapp_number),parent_name=VALUES(parent_name),parent_whatsapp=VALUES(parent_whatsapp)")
                ->execute([$studentId, $app['full_name'], $app['email'] ?: null, $app['phone'] ?: null, $app['parent_name'] ?: null, $app['parent_phone'] ?: null]);
            $this->pdo->prepare('UPDATE admission_applications SET student_id=? WHERE id=?')->execute([$studentId, (int)$app['id']]);
        }
        return $studentId;
    }

    private function enrollFromStudent(int $studentId, array $classIds, int $userId): void
    {
        $capacity = new CapacityService($this->pdo);
        foreach (array_map('intval', $classIds) as $classId) {
            if ($classId < 1) {
                continue;
            }
            $capacity->assertCanEnroll($classId, false);
            $this->pdo->prepare('INSERT IGNORE INTO student_enrollments(student_id,class_id) VALUES(?,?)')->execute([$studentId, $classId]);
            $this->pdo->prepare("INSERT INTO enrollment_history(student_id,class_id,action,notes,created_by) VALUES(?,?,'enrolled','Re-enrollment',$userId)")->execute([$studentId, $classId]);
        }
    }

    private function linkParent(int $studentId, array $app): void
    {
        $phone = preg_replace('/\D+/', '', (string)($app['parent_phone'] ?? '')) ?? '';
        if ($phone === '') {
            return;
        }
        try {
            $s = $this->pdo->prepare('SELECT id FROM parent_accounts WHERE phone=? LIMIT 1');
            $s->execute([$phone]);
            $pid = (int)$s->fetchColumn();
            if ($pid < 1) {
                $this->pdo->prepare('INSERT INTO parent_accounts(phone,name) VALUES(?,?)')->execute([$phone, $app['parent_name'] ?? 'Parent']);
                $pid = (int)$this->pdo->lastInsertId();
            }
            $this->pdo->prepare('INSERT IGNORE INTO parent_students(parent_id,student_id) VALUES(?,?)')->execute([$pid, $studentId]);
        } catch (Throwable $e) {
        }
    }

    /** @return array<string,mixed>|null */
    private function classRow(int $classId): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT c.id,c.name,t.name teacher_name FROM student_classes c LEFT JOIN teachers t ON t.id=c.teacher_id WHERE c.id=?');
            $s->execute([$classId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $t = $this->pdo->prepare("SELECT CONCAT(DAYNAME(date),' ',TIME_FORMAT(start_time,'%h:%i %p')) s FROM timetable WHERE class_id=? AND deleted_at IS NULL ORDER BY date DESC LIMIT 1");
            $t->execute([$classId]);
            $row['schedule'] = (string)($t->fetchColumn() ?: '');
            return $row;
        } catch (Throwable $e) {
            return null;
        }
    }
}
