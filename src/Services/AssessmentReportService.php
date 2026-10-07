<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;

final class AssessmentReportService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function student(int $attemptId, array $auth): array
    {
        $attempt = (new AssessmentAttemptService($this->pdo))->getAttempt($attemptId);
        if (!$attempt) {
            return [];
        }
        if (!(new AssessmentService($this->pdo))->canViewStudent($auth, (int)$attempt['student_id'])) {
            return [];
        }
        $analysis = (new AssessmentAnalyticsService($this->pdo))->studentAttempt($attemptId);
        $trend = (new AssessmentAnalyticsService($this->pdo))->studentTrend((int)$attempt['student_id']);
        $readiness = (new ExamReadinessService($this->pdo))->forStudent((int)$attempt['student_id']);
        return [
            'audience' => 'student',
            'attempt' => $attempt,
            'analysis' => $analysis,
            'trend' => $trend,
            'readiness' => $readiness,
            'disclaimer' => 'Readiness and trends are learning indicators, not guaranteed examination results.',
        ];
    }

    /** @return array<string,mixed> */
    public function parent(int $studentId, array $auth): array
    {
        if (($auth['role'] ?? '') !== 'parent' && ($auth['role'] ?? '') !== 'admin') {
            return [];
        }
        if (!(new AssessmentService($this->pdo))->canViewStudent($auth, $studentId)) {
            return [];
        }
        $trend = (new AssessmentAnalyticsService($this->pdo))->studentTrend($studentId);
        $readiness = (new ExamReadinessService($this->pdo))->forStudent($studentId);
        $upcoming = $this->upcomingForStudent($studentId);
        return [
            'audience' => 'parent',
            'trend' => $trend,
            'readiness' => [
                'score' => $readiness['readiness_score'] ?? null,
                'components' => $readiness['components'] ?? [],
                'disclaimer' => 'This is a learning indicator, not a predicted grade.',
            ],
            'upcoming' => $upcoming,
            'feedback' => $trend['wording'] ?? '',
        ];
    }

    /** @return array<string,mixed> */
    public function teacher(int $assessmentId, array $auth): array
    {
        $assessment = (new AssessmentService($this->pdo))->get($assessmentId);
        if (!$assessment || !(new AssessmentService($this->pdo))->canManage($auth, $assessment)) {
            return [];
        }
        return [
            'audience' => 'teacher',
            'assessment' => $assessment,
            'class' => (new AssessmentAnalyticsService($this->pdo))->classAnalysis($assessmentId),
            'questions' => (new AssessmentAnalyticsService($this->pdo))->questionAnalysis($assessmentId),
        ];
    }

    /** @return array<string,mixed> */
    public function admin(string $from, string $to, array $auth): array
    {
        if (($auth['role'] ?? '') !== 'admin') {
            return [];
        }
        return [
            'audience' => 'admin',
            'from' => $from,
            'to' => $to,
            'by_subject' => (new AssessmentAnalyticsService($this->pdo))->adminSummary($from, $to),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function calendar(array $auth, int $studentId = 0): array
    {
        $out = [];
        $svc = new AssessmentService($this->pdo);
        foreach ($svc->dashboard(['status' => 'published'], $auth) as $row) {
            $out[] = [
                'kind' => $row['assessment_type'],
                'title' => $row['title'],
                'date' => $row['start_at'] ?: $row['created_at'],
                'source' => 'assessment',
            ];
        }
        if ($studentId > 0) {
            try {
                $s = $this->pdo->prepare('SELECT e.unit_title title,e.exam_date date FROM student_exam_selections ses JOIN exams e ON e.id=ses.exam_id WHERE ses.student_id=? ORDER BY e.exam_date LIMIT 20');
                $s->execute([$studentId]);
                foreach ($s->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = ['kind' => 'official', 'title' => $r['title'], 'date' => $r['date'], 'source' => 'official_exam'];
                }
            } catch (\Throwable $e) {
            }
            try {
                $s = $this->pdo->prepare('SELECT title,due_date date FROM student_homework WHERE class_id IN (SELECT class_id FROM student_enrollments WHERE student_id=?) AND due_date>=CURDATE() ORDER BY due_date LIMIT 20');
                $s->execute([$studentId]);
                foreach ($s->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = ['kind' => 'homework', 'title' => $r['title'], 'date' => $r['date'], 'source' => 'homework'];
                }
            } catch (\Throwable $e) {
            }
        }
        usort($out, static fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function upcomingForStudent(int $studentId): array
    {
        return $this->calendar(['role' => 'student', 'user_id' => $studentId], $studentId);
    }
}
