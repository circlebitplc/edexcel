<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class ClassAllocationService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Explainable class recommendations. Never auto-allocates.
     * @return list<array<string,mixed>>
     */
    public function recommend(array $prefs): array
    {
        $subjectId = (int)($prefs['subject_id'] ?? 0);
        $classIds = array_values(array_filter(array_map('intval', (array)($prefs['class_ids'] ?? []))));
        $delivery = (string)($prefs['delivery_pref'] ?? 'either');
        $capacity = new CapacityService($this->pdo);
        $rows = [];
        try {
            $sql = "SELECT c.id,c.name,c.teacher_id,t.name teacher_name,c.subject_id,s.name subject_name
                    FROM student_classes c
                    LEFT JOIN teachers t ON t.id=c.teacher_id
                    LEFT JOIN subjects s ON s.id=c.subject_id
                    WHERE c.deleted_at IS NULL";
            $p = [];
            if ($subjectId > 0) {
                $sql .= ' AND c.subject_id=?';
                $p[] = $subjectId;
            }
            $sql .= ' ORDER BY c.name LIMIT 40';
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $status = $capacity->status((int)$row['id']);
            $schedule = $this->schedule((int)$row['id']);
            $scored = self::scoreFromSignals(
                $subjectId > 0 && (int)$row['subject_id'] === $subjectId,
                in_array((int)$row['id'], $classIds, true),
                !$status['full'],
                $delivery !== 'either' && $schedule['delivery'] !== '' && $schedule['delivery'] !== $delivery,
                $delivery !== 'either'
            );
            $score = $scored['score'];
            $reasons = $scored['reasons'];
            if (!$status['full']) {
                $reasons[] = $status['available_seats'].' seat(s) available ('.$status['current_students'].'/'.$status['max_students'].').';
            }
            if ($status['full']) {
                $reasons[] = 'Class is full — waitlist or alternative required.';
            }
            if ($schedule['label'] !== '') {
                $reasons[] = 'Recent/typical slot: '.$schedule['label'].'.';
            }
            $out[] = [
                'class_id' => (int)$row['id'],
                'class_name' => $row['name'],
                'teacher' => $row['teacher_name'] ?: '—',
                'subject' => $row['subject_name'] ?: '—',
                'capacity' => $status['current_students'].'/'.$status['max_students'],
                'available_seats' => $status['available_seats'],
                'full' => $status['full'],
                'schedule' => $schedule['label'],
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
        usort($out, static fn($a, $b) => $b['score'] <=> $a['score']);
        return $out;
    }

    /**
     * Explainable scoring used by recommend(). Never a hidden model.
     * @return array{score:int,reasons:list<string>}
     */
    public static function scoreFromSignals(bool $subjectMatch, bool $preferredClass, bool $hasSeats, bool $deliveryMismatch, bool $deliverySpecified): array
    {
        $score = 40;
        $reasons = [];
        if ($subjectMatch) {
            $score += 25;
            $reasons[] = 'Matches requested subject.';
        }
        if ($preferredClass) {
            $score += 20;
            $reasons[] = 'Matches the applicant preferred class.';
        }
        if ($hasSeats) {
            $score += 20;
        } else {
            $score -= 15;
        }
        if ($deliveryMismatch) {
            $score -= 10;
            $reasons[] = 'Delivery mode may not match the stated preference.';
        } elseif ($deliverySpecified) {
            $reasons[] = 'Delivery preference was considered.';
        }
        return ['score' => $score, 'reasons' => $reasons];
    }

    public function allocate(int $applicationId, int $classId, int $userId, bool $override = false): string
    {
        $life = new AdmissionLifecycleService($this->pdo);
        $app = $life->get($applicationId);
        if (!$app) {
            throw new \RuntimeException('Application not found.');
        }
        $status = (new CapacityService($this->pdo))->status($classId);
        if ($status['full'] && !$override) {
            $studentId = (int)($app['student_id'] ?? 0);
            if ($studentId < 1) {
                $studentId = $life->ensureStudentAccount($app, $userId);
            }
            if ($studentId > 0 && function_exists('campus_waitlist_join')) {
                campus_waitlist_join($this->pdo, $studentId, $classId);
            }
            (new LeadService($this->pdo))->event((int)($app['lead_id'] ?? 0) ?: null, $applicationId, $studentId ?: null, 'class_full', 'Class '.$classId.' full; waitlist used', $userId);
            return 'waitlist';
        }
        $life->enroll($applicationId, [$classId], $userId, $override);
        $this->pdo->prepare("UPDATE admission_applications SET lifecycle_status='allocated' WHERE id=? AND lifecycle_status<>'enrolled'")->execute([$applicationId]);
        return 'enrolled';
    }

    /** @return array{label:string,delivery:string} */
    private function schedule(int $classId): array
    {
        try {
            $s = $this->pdo->prepare("SELECT start_time,end_time,delivery_mode,DAYNAME(date) d FROM timetable WHERE class_id=? AND deleted_at IS NULL ORDER BY date DESC LIMIT 1");
            $s->execute([$classId]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if (!$r) {
                return ['label' => '', 'delivery' => ''];
            }
            $label = trim(($r['d'] ?? '').' '.substr((string)$r['start_time'], 0, 5).'–'.substr((string)$r['end_time'], 0, 5));
            return ['label' => $label, 'delivery' => (string)($r['delivery_mode'] ?? '')];
        } catch (Throwable $e) {
            return ['label' => '', 'delivery' => ''];
        }
    }
}
