<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AdmissionService
{
    public function __construct(private PDO $pdo) {}

    public function createApplication(array $data, bool $allowExistingStudent = false): int
    {
        $name = trim((string)($data['full_name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        if ($name === '') throw new RuntimeException('Student name is required.');
        if ($phone !== '') {
            $existing = $this->pdo->prepare("SELECT id FROM admission_applications WHERE phone=? AND status IN ('pending','approved') LIMIT 1");
            $existing->execute([$phone]);
            if ($existing->fetchColumn()) throw new RuntimeException('An active application already exists for this phone number.');
            if (!$allowExistingStudent) {
                $existingUser = $this->pdo->prepare("SELECT id FROM users WHERE username=? AND deleted_at IS NULL LIMIT 1");
                $existingUser->execute([$phone]);
                if ($existingUser->fetchColumn()) throw new RuntimeException('A student account already exists for this phone number.');
            }
        }
        $no = 'ADM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $this->pdo->prepare("
            INSERT INTO admission_applications
                (application_no,full_name,phone,email,date_of_birth,parent_name,parent_phone,selected_subjects_json,selected_classes_json)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $no,$name,$phone ?: null,trim((string)($data['email']??'')) ?: null,
            ($data['date_of_birth']??null) ?: null,trim((string)($data['parent_name']??'')) ?: null,
            trim((string)($data['parent_phone']??'')) ?: null,
            json_encode(array_values(array_map('intval',(array)($data['subject_ids']??[])))),
            json_encode(array_values(array_map('intval',(array)($data['class_ids']??[])))),
        ]);
        $id=(int)$this->pdo->lastInsertId();
        if (function_exists('log_audit')) log_audit($this->pdo,'admission_created','admission_applications',$id,null,['application_no'=>$no]);
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function pending(int $limit=100): array
    {
        try {
            $stmt=$this->pdo->prepare("SELECT * FROM admission_applications WHERE status='pending' ORDER BY created_at ASC LIMIT ?");
            $stmt->bindValue(1,max(1,min(300,$limit)),PDO::PARAM_INT); $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(Throwable $e){return [];}
    }

    public function decide(int $applicationId, string $decision, int $reviewer, string $notes=''): ?int
    {
        if (!in_array($decision,['approved','rejected'],true)) throw new RuntimeException('Invalid decision.');
        $stmt=$this->pdo->prepare("SELECT * FROM admission_applications WHERE id=? FOR UPDATE");
        $this->pdo->beginTransaction();
        try {
            $stmt->execute([$applicationId]); $app=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app || $app['status'] !== 'pending') throw new RuntimeException('Application is no longer pending.');
            $studentId=null;
            if ($decision==='approved') {
                $username=(string)($app['phone'] ?: 'student_'.$applicationId);
                $hash=password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT);
                $u=$this->pdo->prepare("INSERT INTO users (username,password_hash,role,is_active) VALUES (?,?, 'student',1)");
                try {$u->execute([$username,$hash]);$studentId=(int)$this->pdo->lastInsertId();} catch(Throwable $e) {
                    $q=$this->pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");$q->execute([$username]);$studentId=(int)$q->fetchColumn();
                }
                if ($studentId > 0) {
                    $this->pdo->prepare("
                        INSERT INTO student_profiles (user_id, full_name, email, whatsapp_number, parent_name, parent_whatsapp)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            full_name=VALUES(full_name), email=VALUES(email),
                            whatsapp_number=VALUES(whatsapp_number),
                            parent_name=VALUES(parent_name), parent_whatsapp=VALUES(parent_whatsapp)
                    ")->execute([
                        $studentId,
                        $app['full_name'],
                        $app['email'] ?: null,
                        $app['phone'] ?: null,
                        $app['parent_name'] ?: null,
                        $app['parent_phone'] ?: null,
                    ]);
                }
                $classes=json_decode((string)$app['selected_classes_json'],true) ?: [];
                foreach($classes as $classId){
                    $en=$this->pdo->prepare("INSERT IGNORE INTO student_enrollments (student_id,class_id) VALUES (?,?)");
                    $en->execute([$studentId,(int)$classId]);
                    $this->pdo->prepare("INSERT INTO enrollment_history (student_id,class_id,action,notes,created_by) VALUES (?,?,'enrolled',?,?)")->execute([$studentId,(int)$classId,'Admission approval',$reviewer]);
                }
            }
            $this->pdo->prepare("UPDATE admission_applications SET status=?,student_id=?,admission_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")
                ->execute([$decision,$studentId,$notes ?: null,$reviewer,$applicationId]);
            $this->pdo->commit();
            if(function_exists('log_audit')) log_audit($this->pdo,'admission_'.$decision,'admission_applications',$applicationId,null,['student_id'=>$studentId,'notes'=>$notes]);
            return $studentId;
        } catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @return list<array<string,mixed>> */
    public function history(int $studentId): array
    {
        try{$s=$this->pdo->prepare('SELECT * FROM enrollment_history WHERE student_id=? ORDER BY created_at DESC');$s->execute([$studentId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return [];}
    }
}
