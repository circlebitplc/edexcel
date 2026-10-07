<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AssessmentService
{
    public const TYPES = ['class_test','unit_test','mock','revision_test','topic_test','full_paper','quiz','past_paper','adaptive'];
    public const STATUSES = ['draft','review','approved','published','archived'];

    public function __construct(private PDO $pdo) {}

    public function canManage(array $auth, ?array $assessment = null): bool
    {
        $role = (string)($auth['role'] ?? '');
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'teacher') {
            return false;
        }
        if ($assessment === null) {
            return true;
        }
        $teacherId = (int)($auth['teacher_id'] ?? $_SESSION['teacher_id'] ?? 0);
        if ($teacherId > 0 && (int)($assessment['teacher_id'] ?? 0) === $teacherId) {
            return true;
        }
        $classId = (int)($assessment['class_id'] ?? 0);
        if ($classId < 1 || $teacherId < 1) {
            return false;
        }
        try {
            $s = $this->pdo->prepare('SELECT 1 FROM timetable WHERE teacher_id=? AND class_id=? AND deleted_at IS NULL LIMIT 1');
            $s->execute([$teacherId, $classId]);
            return (bool)$s->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function canViewStudent(array $auth, int $studentId): bool
    {
        return (new Student360Service($this->pdo))->canView(
            (int)($auth['user_id'] ?? $auth['parent_id'] ?? 0),
            (string)($auth['role'] ?? ''),
            $studentId
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, array $auth): int
    {
        if (!$this->canManage($auth)) {
            throw new RuntimeException('You cannot create assessments.');
        }
        $title = trim((string)($data['title'] ?? ''));
        $type = (string)($data['assessment_type'] ?? 'class_test');
        if ($title === '' || !in_array($type, self::TYPES, true)) {
            throw new RuntimeException('A title and valid assessment type are required.');
        }
        $blueprint = $this->normalizeBlueprint($data['blueprint'] ?? $data['blueprint_json'] ?? []);
        $this->pdo->prepare("
            INSERT INTO assessments(
                title,assessment_type,status,content_origin,qualification_label,subject_id,class_id,unit_label,
                teacher_id,official_exam_id,college_exam_id,duration_minutes,total_marks,pass_threshold,
                question_count,attempt_limit,randomize_questions,randomize_answers,adaptive,copy_controls,
                start_at,end_at,instructions,blueprint_json,generation_prompt,created_by
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            mb_substr($title, 0, 255),
            $type,
            'draft',
            in_array(($data['content_origin'] ?? 'teacher'), ['teacher','bank','generated_practice'], true) ? $data['content_origin'] : 'teacher',
            $data['qualification_label'] ?? null,
            ((int)($data['subject_id'] ?? 0)) ?: null,
            ((int)($data['class_id'] ?? 0)) ?: null,
            $data['unit_label'] ?? null,
            ((int)($auth['teacher_id'] ?? $_SESSION['teacher_id'] ?? 0)) ?: null,
            ((int)($data['official_exam_id'] ?? 0)) ?: null,
            ((int)($data['college_exam_id'] ?? 0)) ?: null,
            max(5, min(300, (int)($data['duration_minutes'] ?? 60))),
            (float)($data['total_marks'] ?? 0),
            max(0, min(100, (float)($data['pass_threshold'] ?? 40))),
            max(0, (int)($data['question_count'] ?? 0)),
            max(1, min(5, (int)($data['attempt_limit'] ?? 1))),
            empty($data['randomize_questions']) ? 0 : 1,
            empty($data['randomize_answers']) ? 0 : 1,
            empty($data['adaptive']) ? 0 : 1,
            empty($data['copy_controls']) ? 0 : 1,
            $this->dt($data['start_at'] ?? null),
            $this->dt($data['end_at'] ?? null),
            $data['instructions'] ?? 'Read each question carefully. Your answers are saved automatically when possible.',
            json_encode($blueprint, JSON_UNESCAPED_UNICODE),
            mb_substr((string)($data['generation_prompt'] ?? ''), 0, 500) ?: null,
            (int)($auth['user_id'] ?? 0) ?: null,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'assessment_created', 'assessments', $id, null, ['title' => $title, 'type' => $type]);
        }
        return $id;
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data, array $auth): void
    {
        $row = $this->get($id);
        if (!$row || !$this->canManage($auth, $row)) {
            throw new RuntimeException('Assessment access denied.');
        }
        if ($row['status'] === 'published' && empty($data['allow_published_edit'])) {
            throw new RuntimeException('Published assessments cannot be rewritten. Archive and create a new version.');
        }
        $blueprint = isset($data['blueprint']) || isset($data['blueprint_json'])
            ? $this->normalizeBlueprint($data['blueprint'] ?? $data['blueprint_json'])
            : json_decode((string)$row['blueprint_json'], true);
        $this->pdo->prepare("
            UPDATE assessments SET title=?,assessment_type=?,qualification_label=?,subject_id=?,class_id=?,unit_label=?,
                duration_minutes=?,total_marks=?,pass_threshold=?,question_count=?,attempt_limit=?,
                randomize_questions=?,randomize_answers=?,adaptive=?,copy_controls=?,start_at=?,end_at=?,
                instructions=?,blueprint_json=?,generation_prompt=?,updated_at=NOW()
            WHERE id=?
        ")->execute([
            mb_substr(trim((string)($data['title'] ?? $row['title'])), 0, 255),
            in_array(($data['assessment_type'] ?? $row['assessment_type']), self::TYPES, true) ? $data['assessment_type'] ?? $row['assessment_type'] : $row['assessment_type'],
            $data['qualification_label'] ?? $row['qualification_label'],
            ((int)($data['subject_id'] ?? $row['subject_id'])) ?: null,
            ((int)($data['class_id'] ?? $row['class_id'])) ?: null,
            $data['unit_label'] ?? $row['unit_label'],
            max(5, min(300, (int)($data['duration_minutes'] ?? $row['duration_minutes']))),
            (float)($data['total_marks'] ?? $row['total_marks']),
            max(0, min(100, (float)($data['pass_threshold'] ?? $row['pass_threshold']))),
            max(0, (int)($data['question_count'] ?? $row['question_count'])),
            max(1, min(5, (int)($data['attempt_limit'] ?? $row['attempt_limit']))),
            array_key_exists('randomize_questions', $data) ? (!empty($data['randomize_questions']) ? 1 : 0) : (int)$row['randomize_questions'],
            array_key_exists('randomize_answers', $data) ? (!empty($data['randomize_answers']) ? 1 : 0) : (int)$row['randomize_answers'],
            array_key_exists('adaptive', $data) ? (!empty($data['adaptive']) ? 1 : 0) : (int)$row['adaptive'],
            array_key_exists('copy_controls', $data) ? (!empty($data['copy_controls']) ? 1 : 0) : (int)$row['copy_controls'],
            $this->dt($data['start_at'] ?? $row['start_at']),
            $this->dt($data['end_at'] ?? $row['end_at']),
            $data['instructions'] ?? $row['instructions'],
            json_encode(is_array($blueprint) ? $blueprint : [], JSON_UNESCAPED_UNICODE),
            mb_substr((string)($data['generation_prompt'] ?? $row['generation_prompt'] ?? ''), 0, 500) ?: null,
            $id,
        ]);
    }

    public function transition(int $id, string $status, array $auth): void
    {
        $row = $this->get($id);
        if (!$row || !$this->canManage($auth, $row)) {
            throw new RuntimeException('Assessment access denied.');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid status.');
        }
        $from = (string)$row['status'];
        $allowed = [
            'draft' => ['review', 'archived'],
            'review' => ['draft', 'approved', 'archived'],
            'approved' => ['review', 'published', 'archived'],
            'published' => ['archived'],
            'archived' => ['draft'],
        ];
        if (!in_array($status, $allowed[$from] ?? [], true)) {
            throw new RuntimeException("Cannot move a {$from} assessment to {$status}.");
        }
        if ($status === 'published') {
            $count = $this->questionCount($id);
            if ($count < 1) {
                throw new RuntimeException('Publish requires at least one question.');
            }
            if ($row['content_origin'] === 'generated_practice' && $from !== 'approved') {
                throw new RuntimeException('Generated papers must be teacher-approved before publishing.');
            }
        }
        $this->pdo->prepare('UPDATE assessments SET status=?,approved_by=IF(?="approved" OR ?="published",?,approved_by),published_at=IF(?="published",NOW(),published_at),updated_at=NOW() WHERE id=?')
            ->execute([$status, $status, $status, (int)($auth['user_id'] ?? 0) ?: null, $status, $id]);
        $this->refreshTotals($id);
        if ($status === 'published') {
            $this->notifyAssigned($id);
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'assessment_status', 'assessments', $id, ['status' => $from], ['status' => $status]);
        }
    }

    /** @param array<string,mixed> $question */
    public function addQuestion(int $assessmentId, array $question, array $auth): int
    {
        $row = $this->get($assessmentId);
        if (!$row || !$this->canManage($auth, $row)) {
            throw new RuntimeException('Assessment access denied.');
        }
        if ($row['status'] === 'published') {
            throw new RuntimeException('Cannot add questions after publishing.');
        }
        $prompt = trim((string)($question['prompt'] ?? ''));
        $type = (string)($question['question_type'] ?? 'mcq');
        if ($prompt === '' || !in_array($type, ['mcq','true_false','numeric','short','essay'], true)) {
            throw new RuntimeException('Question prompt and type are required.');
        }
        $this->pdo->prepare("
            INSERT INTO assessment_questions(
                assessment_id,sort_order,question_type,topic_key,topic_label,difficulty,marks,prompt,
                choices_json,correct_index,accepted_answers_json,marking_guidance_json,source_bank_item_id,source_label,source_status
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $assessmentId,
            max(1, (int)($question['sort_order'] ?? ($this->questionCount($assessmentId) + 1))),
            $type,
            $question['topic_key'] ?? null,
            $question['topic_label'] ?? $question['topic_key'] ?? null,
            in_array(($question['difficulty'] ?? 'medium'), ['easy','medium','hard'], true) ? $question['difficulty'] : 'medium',
            max(0.5, (float)($question['marks'] ?? 1)),
            $prompt,
            isset($question['choices']) ? json_encode(array_values((array)$question['choices']), JSON_UNESCAPED_UNICODE) : ($question['choices_json'] ?? null),
            isset($question['correct_index']) ? (int)$question['correct_index'] : null,
            isset($question['accepted_answers']) ? json_encode(array_values((array)$question['accepted_answers']), JSON_UNESCAPED_UNICODE) : ($question['accepted_answers_json'] ?? null),
            isset($question['marking_guidance']) ? json_encode($question['marking_guidance'], JSON_UNESCAPED_UNICODE) : ($question['marking_guidance_json'] ?? null),
            ((int)($question['source_bank_item_id'] ?? 0)) ?: null,
            $question['source_label'] ?? 'teacher',
            in_array(($question['source_status'] ?? 'practice'), ['practice','teacher','official_reference'], true) ? $question['source_status'] : 'practice',
        ]);
        $qid = (int)$this->pdo->lastInsertId();
        $this->refreshTotals($assessmentId);
        return $qid;
    }

    /** @return array<string,mixed> */
    public function generateFromBlueprint(int $assessmentId, array $auth): array
    {
        $row = $this->get($assessmentId);
        if (!$row || !$this->canManage($auth, $row)) {
            throw new RuntimeException('Assessment access denied.');
        }
        $blueprint = $this->normalizeBlueprint($row['blueprint_json']);
        $pool = $this->questionPool((int)($row['subject_id'] ?? 0), (int)($auth['user_id'] ?? 0), ($auth['role'] ?? '') === 'admin');
        $selected = [];
        $used = [];
        foreach ($blueprint['topics'] as $topic) {
            $needMarks = (float)$topic['marks'];
            $got = 0.0;
            foreach ($pool as $item) {
                if ($got >= $needMarks) {
                    break;
                }
                $id = (int)$item['id'];
                if (isset($used[$id])) {
                    continue;
                }
                $topicOk = $this->topicMatches($item, (string)$topic['topic']);
                $diffOk = empty($blueprint['difficulty']) || ($item['difficulty'] ?? 'medium') === $blueprint['difficulty'] || $blueprint['difficulty'] === 'mixed';
                if (!$topicOk || !$diffOk) {
                    continue;
                }
                $used[$id] = true;
                $selected[] = $item + ['_topic' => $topic['topic']];
                $got += (float)($item['marks'] ?? 1);
            }
        }
        if ($selected === []) {
            foreach (array_slice($pool, 0, max(1, (int)$row['question_count'] ?: 8)) as $item) {
                $selected[] = $item + ['_topic' => $item['topic_label'] ?? 'General'];
            }
        }
        $this->pdo->prepare('DELETE FROM assessment_questions WHERE assessment_id=?')->execute([$assessmentId]);
        $order = 1;
        foreach ($selected as $item) {
            $this->addQuestion($assessmentId, [
                'sort_order' => $order++,
                'question_type' => $item['question_type'] ?? 'mcq',
                'topic_key' => AssessmentScoring::normalizeText((string)($item['_topic'] ?? $item['topic_label'] ?? 'general')),
                'topic_label' => $item['_topic'] ?? $item['topic_label'] ?? 'General',
                'difficulty' => $item['difficulty'] ?? 'medium',
                'marks' => $item['marks'] ?? 1,
                'prompt' => $item['prompt'],
                'choices' => is_string($item['choices_json'] ?? null) ? (json_decode((string)$item['choices_json'], true) ?: []) : ($item['choices'] ?? []),
                'correct_index' => $item['correct_index'] ?? null,
                'source_bank_item_id' => $item['id'] ?? null,
                'source_label' => 'question_bank',
                'source_status' => 'practice',
            ], $auth);
        }
        $this->pdo->prepare("UPDATE assessments SET content_origin='generated_practice',status='review',updated_at=NOW() WHERE id=?")->execute([$assessmentId]);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'assessment_generated', 'assessments', $assessmentId, null, ['questions' => count($selected)]);
        }
        return ['assessment_id' => $assessmentId, 'questions' => count($selected), 'status' => 'review', 'note' => 'Generated papers stay in review until a teacher approves them.'];
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT a.*,s.name subject_name,c.name class_name FROM assessments a LEFT JOIN subjects s ON s.id=a.subject_id LEFT JOIN student_classes c ON c.id=a.class_id WHERE a.id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return list<array<string,mixed>> */
    public function questions(int $assessmentId): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM assessment_questions WHERE assessment_id=? ORDER BY sort_order,id');
            $s->execute([$assessmentId]);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function dashboard(array $filters, array $auth): array
    {
        $where = ['1=1'];
        $params = [];
        if (($auth['role'] ?? '') === 'teacher') {
            $teacherId = (int)($auth['teacher_id'] ?? $_SESSION['teacher_id'] ?? 0);
            $where[] = '(a.teacher_id=? OR a.class_id IN (SELECT class_id FROM timetable WHERE teacher_id=? AND deleted_at IS NULL))';
            $params[] = $teacherId;
            $params[] = $teacherId;
        } elseif (($auth['role'] ?? '') === 'student') {
            $where[] = "a.status='published' AND (a.class_id IS NULL OR a.class_id IN (SELECT class_id FROM student_enrollments WHERE student_id=?))";
            $params[] = (int)$auth['user_id'];
        } elseif (($auth['role'] ?? '') !== 'admin') {
            return [];
        }
        foreach (['qualification_label','unit_label'] as $col) {
            if (!empty($filters[$col])) {
                $where[] = "a.{$col}=?";
                $params[] = $filters[$col];
            }
        }
        foreach (['subject_id','class_id','teacher_id'] as $col) {
            if ((int)($filters[$col] ?? 0) > 0) {
                $where[] = "a.{$col}=?";
                $params[] = (int)$filters[$col];
            }
        }
        if (!empty($filters['assessment_type']) && in_array($filters['assessment_type'], self::TYPES, true)) {
            $where[] = 'a.assessment_type=?';
            $params[] = $filters['assessment_type'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'a.status=?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
            $where[] = 'DATE(COALESCE(a.start_at,a.created_at))>=?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
            $where[] = 'DATE(COALESCE(a.start_at,a.created_at))<=?';
            $params[] = $filters['to'];
        }
        $sql = 'SELECT a.*,s.name subject_name,c.name class_name,
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.assessment_id=a.id AND aa.status IN ("submitted","marking","marked")) participants,
            (SELECT AVG(percent) FROM assessment_attempts aa WHERE aa.assessment_id=a.id AND aa.percent IS NOT NULL) average_percent,
            (SELECT MAX(percent) FROM assessment_attempts aa WHERE aa.assessment_id=a.id) highest_percent,
            (SELECT MIN(percent) FROM assessment_attempts aa WHERE aa.assessment_id=a.id AND aa.percent IS NOT NULL) lowest_percent,
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.assessment_id=a.id AND aa.status IN ("submitted","marking")) outstanding_marking
            FROM assessments a
            LEFT JOIN subjects s ON s.id=a.subject_id
            LEFT JOIN student_classes c ON c.id=a.class_id
            WHERE '.implode(' AND ', $where).'
            ORDER BY COALESCE(a.start_at,a.created_at) DESC,a.id DESC LIMIT 200';
        try {
            $s = $this->pdo->prepare($sql);
            $s->execute($params);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('assessment dashboard: '.$e->getMessage());
            return [];
        }
    }

    /** @return array{upcoming:int,active:int,completed:int,outstanding:int} */
    public function counters(array $auth): array
    {
        $rows = $this->dashboard([], $auth);
        $now = time();
        $out = ['upcoming' => 0, 'active' => 0, 'completed' => 0, 'outstanding' => 0];
        foreach ($rows as $r) {
            $start = $r['start_at'] ? strtotime((string)$r['start_at']) : null;
            $end = $r['end_at'] ? strtotime((string)$r['end_at']) : null;
            if (($r['status'] ?? '') === 'published' && $start && $start > $now) {
                $out['upcoming']++;
            } elseif (($r['status'] ?? '') === 'published' && (!$end || $end >= $now)) {
                $out['active']++;
            } else {
                $out['completed']++;
            }
            $out['outstanding'] += (int)($r['outstanding_marking'] ?? 0);
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function normalizeBlueprint(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $topics = [];
        $list = is_array($raw) ? ($raw['topics'] ?? $raw) : [];
        if (is_array($list)) {
            foreach ($list as $item) {
                if (is_string($item)) {
                    $topics[] = ['topic' => $item, 'marks' => 10];
                    continue;
                }
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string)($item['topic'] ?? $item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $topics[] = ['topic' => $name, 'marks' => max(1, (float)($item['marks'] ?? 10))];
            }
        }
        return [
            'qualification' => is_array($raw) ? (string)($raw['qualification'] ?? '') : '',
            'subject' => is_array($raw) ? (string)($raw['subject'] ?? '') : '',
            'unit' => is_array($raw) ? (string)($raw['unit'] ?? '') : '',
            'total_marks' => is_array($raw) ? (float)($raw['total_marks'] ?? array_sum(array_column($topics, 'marks'))) : array_sum(array_column($topics, 'marks')),
            'difficulty' => is_array($raw) && in_array(($raw['difficulty'] ?? 'mixed'), ['easy','medium','hard','mixed'], true) ? (string)($raw['difficulty'] ?? 'mixed') : 'mixed',
            'topics' => $topics,
        ];
    }

    private function refreshTotals(int $id): void
    {
        try {
            $s = $this->pdo->prepare('SELECT COUNT(*) q,COALESCE(SUM(marks),0) m FROM assessment_questions WHERE assessment_id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC) ?: ['q' => 0, 'm' => 0];
            $this->pdo->prepare('UPDATE assessments SET question_count=?,total_marks=? WHERE id=?')->execute([(int)$row['q'], (float)$row['m'], $id]);
        } catch (Throwable $e) {
        }
    }

    private function questionCount(int $id): int
    {
        try {
            $s = $this->pdo->prepare('SELECT COUNT(*) FROM assessment_questions WHERE assessment_id=?');
            $s->execute([$id]);
            return (int)$s->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** @return list<array<string,mixed>> */
    private function questionPool(int $subjectId, int $userId, bool $admin): array
    {
        $rows = [];
        try {
            $sql = 'SELECT i.id,i.question_type,i.prompt,i.choices_json,i.correct_index,i.marks,i.explanation,b.title bank_title
                    FROM online_question_bank_items i JOIN online_question_banks b ON b.id=i.bank_id';
            $p = [];
            if (!$admin && $userId > 0) {
                $sql .= ' WHERE b.owner_user_id=?';
                $p[] = $userId;
            }
            $sql .= ' ORDER BY i.id DESC LIMIT 400';
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $r['topic_label'] = $r['bank_title'] ?? 'Bank';
                $r['difficulty'] = 'medium';
                $rows[] = $r;
            }
        } catch (Throwable $e) {
        }
        return $rows;
    }

    private function topicMatches(array $item, string $topic): bool
    {
        $hay = AssessmentScoring::normalizeText(($item['topic_label'] ?? '').' '.($item['prompt'] ?? '').' '.($item['bank_title'] ?? ''));
        $needle = AssessmentScoring::normalizeText($topic);
        return $needle === '' || str_contains($hay, $needle);
    }

    private function notifyAssigned(int $id): void
    {
        $row = $this->get($id);
        if (!$row || !(int)($row['class_id'] ?? 0)) {
            return;
        }
        try {
            $s = $this->pdo->prepare('SELECT DISTINCT student_id FROM student_enrollments WHERE class_id=?');
            $s->execute([(int)$row['class_id']]);
            $notice = new NotificationCenterService($this->pdo);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) ?: [] as $sid) {
                $notice->create('student', 'exams', 'New assessment assigned', (string)$row['title'].' is now available.', BASE_URL.'student/assessment.php?id='.$id, (int)$sid, null, 'normal', ['assessment_id' => $id]);
            }
        } catch (Throwable $e) {
        }
    }

    private function dt(mixed $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') {
            return null;
        }
        $t = strtotime($v);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }
}
