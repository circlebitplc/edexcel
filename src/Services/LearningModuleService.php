<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Objectives, mark schemes, assignments, resources, and teacher lists
 * for an existing video lesson. Progress and marks stay on the lesson tables.
 */
final class LearningModuleService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param list<array<string,mixed>> $criteria
     * @param list<int> $selectedIds
     */
    public static function criteriaAward(array $criteria, array $selectedIds): float
    {
        $picked = array_fill_keys(array_map('intval', $selectedIds), true);
        $sum = 0.0;
        foreach ($criteria as $row) {
            if (isset($picked[(int)($row['id'] ?? 0)])) {
                $sum += max(0, (float)($row['marks'] ?? 0));
            }
        }
        return round($sum, 2);
    }

    public static function cappedAward(float $marks, float $maximum): float
    {
        return max(0, min(max(0, $maximum), round($marks, 2)));
    }

    /**
     * @param array<string,mixed> $question
     * @return array<string,mixed>
     */
    public static function independentQuestionCopy(array $question): array
    {
        return [
            'question_type' => (string)($question['question_type'] ?? 'mcq'),
            'prompt' => (string)($question['prompt'] ?? ''),
            'choices' => array_values($question['choices'] ?? []),
            'correct_index' => $question['correct_index'] === null || $question['correct_index'] === '' ? null : (int)$question['correct_index'],
            'marks' => (float)($question['marks'] ?? 1),
            'explanation' => (string)($question['explanation'] ?? ''),
            'topic' => (string)($question['topic'] ?? ''),
            'difficulty' => (string)($question['difficulty'] ?? ''),
            'exam_ref' => (string)($question['exam_ref'] ?? ''),
            'expected_answer' => (string)($question['expected_answer'] ?? ''),
        ];
    }

    public static function normalizeDueAt(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        throw new RuntimeException('Enter a valid due date.');
    }

    public static function submissionLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'marked' => 'Marked',
            'returned' => 'Returned',
            'resubmit' => 'Resubmission requested',
            default => 'Not submitted',
        };
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function objectives(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_objectives WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addObjective(int $lessonId, string $body): int
    {
        $body = mb_substr(trim($body), 0, 300);
        if ($body === '') {
            throw new RuntimeException('Enter an objective.');
        }
        $sort = count($this->objectives($lessonId)) + 1;
        $this->pdo->prepare('INSERT INTO online_lesson_objectives (lesson_id, body, sort_order) VALUES (?, ?, ?)')
            ->execute([$lessonId, $body, $sort]);
        $this->syncObjectiveText($lessonId);
        return (int)$this->pdo->lastInsertId();
    }

    public function renameObjective(int $lessonId, int $objectiveId, string $body): void
    {
        $body = mb_substr(trim($body), 0, 300);
        if ($body === '') {
            throw new RuntimeException('Enter an objective.');
        }
        $this->pdo->prepare('UPDATE online_lesson_objectives SET body = ? WHERE id = ? AND lesson_id = ?')
            ->execute([$body, $objectiveId, $lessonId]);
        $this->syncObjectiveText($lessonId);
    }

    public function deleteObjective(int $lessonId, int $objectiveId): void
    {
        $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE objective_id = ?')->execute([$objectiveId]);
        $this->pdo->prepare('DELETE FROM online_lesson_objectives WHERE id = ? AND lesson_id = ?')->execute([$objectiveId, $lessonId]);
        $this->syncObjectiveText($lessonId);
    }

    public function moveObjective(int $lessonId, int $objectiveId, string $direction): void
    {
        $rows = $this->objectives($lessonId);
        $index = null;
        foreach ($rows as $i => $row) {
            if ((int)$row['id'] === $objectiveId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($rows[$swap])) {
            return;
        }
        $this->pdo->prepare('UPDATE online_lesson_objectives SET sort_order = ? WHERE id = ?')->execute([(int)$rows[$swap]['sort_order'], $objectiveId]);
        $this->pdo->prepare('UPDATE online_lesson_objectives SET sort_order = ? WHERE id = ?')->execute([(int)$rows[$index]['sort_order'], (int)$rows[$swap]['id']]);
    }

    /**
     * @param list<int> $objectiveIds
     */
    public function setQuestionObjectives(int $lessonId, int $questionId, array $objectiveIds): void
    {
        $allowed = [];
        foreach ($this->objectives($lessonId) as $row) {
            $allowed[(int)$row['id']] = true;
        }
        $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE question_id = ?')->execute([$questionId]);
        $ins = $this->pdo->prepare('INSERT INTO online_lesson_objective_links (objective_id, question_id, item_id) VALUES (?, ?, 0)');
        foreach (array_unique(array_map('intval', $objectiveIds)) as $id) {
            if ($id > 0 && isset($allowed[$id])) {
                $ins->execute([$id, $questionId]);
            }
        }
    }

    /**
     * @return array<int,list<int>>
     */
    public function objectiveIdsByQuestion(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT l.question_id, l.objective_id
            FROM online_lesson_objective_links l
            JOIN online_lesson_objectives o ON o.id = l.objective_id
            WHERE o.lesson_id = ? AND l.question_id > 0
        ');
        $stmt->execute([$lessonId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['question_id']][] = (int)$row['objective_id'];
        }
        return $map;
    }

    /**
     * @param list<array<string,mixed>> $students
     * @return list<array<string,mixed>>
     */
    public static function objectivePerformance(array $objectives, array $linksByQuestion, array $students): array
    {
        $out = [];
        foreach ($objectives as $objective) {
            $id = (int)$objective['id'];
            $got = 0.0;
            $max = 0.0;
            foreach ($students as $student) {
                foreach (($student['activities'] ?? []) as $activity) {
                    foreach (($activity['questions'] ?? []) as $question) {
                        $linked = $linksByQuestion[(int)($question['id'] ?? 0)] ?? [];
                        if (!in_array($id, $linked, true) || !empty($question['pending']) || $question['awarded'] === null) {
                            continue;
                        }
                        $got += (float)$question['awarded'];
                        $max += (float)$question['max'];
                    }
                }
            }
            $out[] = [
                'id' => $id,
                'body' => (string)($objective['body'] ?? ''),
                'awarded' => $max > 0 ? $got : null,
                'max' => $max > 0 ? $max : null,
                'percent' => $max > 0 ? (100 * $got / $max) : null,
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function criteriaForQuestion(int $questionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_criteria WHERE question_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addCriterion(int $questionId, string $label, float $marks): int
    {
        $label = mb_substr(trim($label), 0, 200);
        if ($label === '') {
            throw new RuntimeException('Enter a mark-scheme criterion.');
        }
        if ($marks <= 0) {
            throw new RuntimeException('Enter marks for this criterion.');
        }
        $sort = count($this->criteriaForQuestion($questionId)) + 1;
        $this->pdo->prepare('INSERT INTO online_lesson_criteria (question_id, label, marks, sort_order) VALUES (?, ?, ?, ?)')
            ->execute([$questionId, $label, round($marks, 2), $sort]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteCriterion(int $questionId, int $criterionId): void
    {
        $this->pdo->prepare('DELETE FROM online_lesson_criteria WHERE id = ? AND question_id = ?')->execute([$criterionId, $questionId]);
    }

    public function copyQuestionExtras(int $fromQuestionId, int $toQuestionId): void
    {
        foreach ($this->criteriaForQuestion($fromQuestionId) as $row) {
            $this->addCriterion($toQuestionId, (string)$row['label'], (float)$row['marks']);
        }
        $links = $this->pdo->prepare('SELECT objective_id FROM online_lesson_objective_links WHERE question_id = ?');
        $links->execute([$fromQuestionId]);
        $ins = $this->pdo->prepare('INSERT IGNORE INTO online_lesson_objective_links (objective_id, question_id, item_id) VALUES (?, ?, 0)');
        foreach ($links->fetchAll(PDO::FETCH_COLUMN) ?: [] as $objectiveId) {
            $ins->execute([(int)$objectiveId, $toQuestionId]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function submission(int $studentId, int $itemId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_submissions WHERE student_id = ? AND item_id = ? LIMIT 1');
        $stmt->execute([$studentId, $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $activity
     * @param array<string,mixed>|null $file
     */
    public function submitWork(int $studentId, int $itemId, array $activity, string $text, ?array $file): void
    {
        $allowText = (int)($activity['allow_text'] ?? 1) === 1;
        $allowFile = (int)($activity['allow_file'] ?? 1) === 1;
        $text = trim($text);
        if (!$allowText) {
            $text = '';
        }
        if ($text !== '' && mb_strlen($text) > 20000) {
            throw new RuntimeException('The written answer is too long.');
        }
        $stored = null;
        $original = null;
        $mime = null;
        if ($allowFile && $file && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $storedFile = self::storeUpload($file, 'assignments/' . $itemId);
            $stored = $storedFile['key'];
            $original = $storedFile['name'];
            $mime = $storedFile['mime'];
        }
        if ($text === '' && $stored === null) {
            throw new RuntimeException('Submit written work or an allowed file.');
        }
        $existing = $this->submission($studentId, $itemId);
        if ($existing && (string)$existing['status'] !== 'resubmit') {
            throw new RuntimeException('This work has already been submitted.');
        }
        if ($existing) {
            if ($stored === null) {
                $stored = (string)($existing['file_key'] ?? '');
                $original = (string)($existing['file_name'] ?? '');
                $mime = (string)($existing['mime'] ?? '');
            } elseif (!empty($existing['file_key'])) {
                $this->deletePrivate((string)$existing['file_key']);
            }
            $this->pdo->prepare('
                UPDATE online_lesson_submissions
                SET status = \'submitted\', body_text = ?, file_key = ?, file_name = ?, mime = ?,
                    marks_awarded = NULL, teacher_comment = NULL, submitted_at = NOW()
                WHERE id = ? AND student_id = ?
            ')->execute([
                $text !== '' ? $text : null,
                $stored !== '' ? $stored : null,
                $original !== '' ? $original : null,
                $mime !== '' ? $mime : null,
                (int)$existing['id'],
                $studentId,
            ]);
            return;
        }
        $this->pdo->prepare('
            INSERT INTO online_lesson_submissions
                (student_id, item_id, status, body_text, file_key, file_name, mime, submitted_at)
            VALUES (?, ?, \'submitted\', ?, ?, ?, ?, NOW())
        ')->execute([$studentId, $itemId, $text !== '' ? $text : null, $stored, $original, $mime]);
    }

    public function reviewSubmission(int $lessonId, int $submissionId, string $action, ?float $marks, string $comment, float $maximum): void
    {
        $stmt = $this->pdo->prepare('
            SELECT s.*
            FROM online_lesson_submissions s
            JOIN online_lesson_items i ON i.id = s.item_id
            WHERE s.id = ? AND i.lesson_id = ?
            LIMIT 1
        ');
        $stmt->execute([$submissionId, $lessonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('That submission was not found.');
        }
        $comment = trim($comment);
        if ($action === 'resubmit') {
            $this->pdo->prepare('
                UPDATE online_lesson_submissions
                SET status = \'resubmit\', marks_awarded = NULL, teacher_comment = ?
                WHERE id = ?
            ')->execute([$comment !== '' ? $comment : null, $submissionId]);
            $this->pdo->prepare("
                UPDATE online_lesson_item_state
                SET status = 'incomplete', completed_at = NULL
                WHERE student_id = ? AND item_id = ?
            ")->execute([(int)$row['student_id'], (int)$row['item_id']]);
            return;
        }
        if ($marks === null) {
            throw new RuntimeException('Enter the marks.');
        }
        $awarded = self::cappedAward($marks, $maximum);
        $status = $action === 'return' ? 'returned' : 'marked';
        $this->pdo->prepare('
            UPDATE online_lesson_submissions
            SET status = ?, marks_awarded = ?, teacher_comment = ?
            WHERE id = ?
        ')->execute([$status, $awarded, $comment !== '' ? $comment : null, $submissionId]);
    }

    /**
     * @param list<int> $itemIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    public function submissionsByStudentItem(array $itemIds, ?int $onlyStudentId = null): array
    {
        if ($itemIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $params = $itemIds;
        $studentSql = '';
        if ($onlyStudentId !== null) {
            $studentSql = ' AND student_id = ?';
            $params[] = $onlyStudentId;
        }
        $stmt = $this->pdo->prepare("
            SELECT student_id, item_id, status, marks_awarded, teacher_comment
            FROM online_lesson_submissions
            WHERE item_id IN ($placeholders)$studentSql
        ");
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['student_id']][(int)$row['item_id']] = $row;
        }
        return $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function lessonSubmissions(int $lessonId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, i.title AS item_title, a.max_marks, a.activity_type, a.due_at,
                   COALESCE(NULLIF(sp.full_name,''), u.username) AS student_name
            FROM online_lesson_submissions s
            JOIN online_lesson_items i ON i.id = s.item_id
            JOIN online_lesson_activities a ON a.id = i.activity_id
            LEFT JOIN users u ON u.id = s.student_id
            LEFT JOIN student_profiles sp ON sp.user_id = s.student_id
            WHERE i.lesson_id = ?
            ORDER BY s.submitted_at ASC, s.id ASC
        ");
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{key:string,name:string,mime:string}
     */
    public static function storeUpload(array $file, string $folder): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The file could not be uploaded.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > 8 * 1024 * 1024) {
            throw new RuntimeException('Upload a file of 8 MB or smaller.');
        }
        $original = basename((string)($file['name'] ?? 'file'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        if (!isset($allowed[$ext])) {
            throw new RuntimeException('That file type is not allowed.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = '';
        if (function_exists('finfo_open') && is_file($tmp)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }
        if ($mime !== '' && $mime !== $allowed[$ext] && !($ext === 'txt' && $mime === 'text/plain')) {
            $compatible = [
                'doc' => ['application/msword', 'application/octet-stream'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
                'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
                'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
            ];
            if (!in_array($mime, $compatible[$ext] ?? [$allowed[$ext]], true)) {
                throw new RuntimeException('That file type is not allowed.');
            }
        }
        if ($mime === '') {
            $mime = $allowed[$ext];
        }
        $root = online_lesson_private_root();
        $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('The file could not be stored.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('The file could not be stored.');
        }
        return ['key' => $folder . '/' . $name, 'name' => mb_substr($original, 0, 200), 'mime' => $mime];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function searchBankItems(int $userId, bool $isAdmin, array $filters, int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $where = $isAdmin ? '1=1' : 'b.owner_user_id = ?';
        $params = $isAdmin ? [] : [$userId];
        $subject = trim((string)($filters['subject'] ?? ''));
        $topic = trim((string)($filters['topic'] ?? ''));
        $type = strtolower(trim((string)($filters['type'] ?? '')));
        $difficulty = trim((string)($filters['difficulty'] ?? ''));
        $marks = trim((string)($filters['marks'] ?? ''));
        if ($subject !== '') {
            $where .= ' AND b.subject = ?';
            $params[] = mb_substr($subject, 0, 120);
        }
        if ($topic !== '') {
            $where .= ' AND i.topic = ?';
            $params[] = mb_substr($topic, 0, 120);
        }
        if (in_array($type, ['mcq', 'essay', 'short', 'exam'], true)) {
            $where .= ' AND i.question_type = ?';
            $params[] = $type;
        }
        if ($difficulty !== '') {
            $where .= ' AND i.difficulty = ?';
            $params[] = mb_substr($difficulty, 0, 40);
        }
        $tag = trim((string)($filters['tag'] ?? ''));
        if ($tag !== '') {
            $tag = mb_substr(str_replace(['%', '_', ','], '', $tag), 0, 40);
            $where .= ' AND (i.tags = ? OR i.tags LIKE ? OR i.tags LIKE ? OR i.tags LIKE ?)';
            array_push($params, $tag, $tag . ',%', '%,' . $tag, '%,' . $tag . ',%');
        }
        if ($marks !== '' && is_numeric($marks)) {
            $where .= ' AND i.marks = ?';
            $params[] = round((float)$marks, 2);
        }
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM online_question_bank_items i JOIN online_question_banks b ON b.id = i.bank_id WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $sql = "
            SELECT i.*, b.title AS bank_title, b.subject
            FROM online_question_bank_items i
            JOIN online_question_banks b ON b.id = i.bank_id
            WHERE $where
            ORDER BY i.id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)(($page - 1) * $perPage);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,drafts:int,published:int,needs_marking:int}
     */
    public function teacherModules(int $teacherId, bool $isAdmin, array $filters, int $page): array
    {
        $scope = $isAdmin ? '1=1' : '(tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
        $scopeParams = $isAdmin ? [] : [$teacherId, $teacherId];
        $where = $scope . ' AND tt.deleted_at IS NULL';
        $params = $scopeParams;
        $status = (string)($filters['status'] ?? '');
        $nowSql = date('Y-m-d H:i:s');
        if ($status === 'draft') {
            $where .= ' AND ol.published = 0 AND ol.archived = 0';
        } elseif ($status === 'scheduled') {
            $where .= ' AND ol.published = 1 AND ol.archived = 0 AND ol.publish_at > ?';
            $params[] = $nowSql;
        } elseif ($status === 'published') {
            $where .= ' AND ol.published = 1 AND ol.archived = 0 AND (ol.publish_at IS NULL OR ol.publish_at <= ?)';
            $params[] = $nowSql;
        } elseif ($status === 'archived') {
            $where .= ' AND ol.archived = 1';
        }
        $subject = trim((string)($filters['subject'] ?? ''));
        if ($subject !== '') {
            $where .= ' AND s.name LIKE ?';
            $params[] = '%' . mb_substr($subject, 0, 80) . '%';
        }
        $topic = trim((string)($filters['topic'] ?? ''));
        if ($topic !== '') {
            $where .= ' AND ol.plan_topics LIKE ?';
            $params[] = '%' . mb_substr($topic, 0, 80) . '%';
        }
        $from = trim((string)($filters['from'] ?? ''));
        $to = trim((string)($filters['to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where .= ' AND tt.date >= ?';
            $params[] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where .= ' AND tt.date <= ?';
            $params[] = $to;
        }
        $count = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            WHERE $where
        ");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $page = max(1, $page);
        $stmt = $this->pdo->prepare("
            SELECT ol.id, ol.title, ol.published, ol.archived, ol.updated_at, ol.plan_topics, ol.timetable_id,
                   ol.publish_at, ol.unpublish_at, ol.available_after_class, ol.close_after_days,
                   tt.date, tt.start_time, tt.end_time, s.name AS subject_name, c.name AS class_name
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            WHERE $where
            ORDER BY ol.updated_at DESC, ol.id DESC
            LIMIT 20 OFFSET " . (int)(($page - 1) * 20) . "
        ");
        $stmt->execute($params);
        $counts = $this->pdo->prepare("
            SELECT
                SUM(CASE WHEN ol.published = 0 AND ol.archived = 0 THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN ol.published = 1 AND ol.archived = 0 AND ol.publish_at > ? THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN ol.published = 1 AND ol.archived = 0 AND (ol.publish_at IS NULL OR ol.publish_at <= ?) THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN ol.archived = 1 THEN 1 ELSE 0 END) AS archived
            FROM online_lessons ol
            JOIN timetable tt ON tt.id = ol.timetable_id
            WHERE $scope AND tt.deleted_at IS NULL
        ");
        $counts->execute(array_merge([$nowSql, $nowSql], $scopeParams));
        $countRow = $counts->fetch(PDO::FETCH_ASSOC) ?: [];
        $marking = $this->needsMarkingCount($teacherId, $isAdmin);
        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'drafts' => (int)($countRow['drafts'] ?? 0),
            'scheduled' => (int)($countRow['scheduled'] ?? 0),
            'published' => (int)($countRow['published'] ?? 0),
            'archived' => (int)($countRow['archived'] ?? 0),
            'needs_marking' => $marking,
        ];
    }

    /**
     * Planning and recent student activity for the teacher dashboard.
     *
     * @return array{unplanned:int,upcoming:int,attempts_7d:int,submissions_7d:int,active_students_7d:int}
     */
    public function teacherActivity(int $teacherId, bool $isAdmin, ?int $now = null): array
    {
        $now = $now ?? time();
        $scope = $isAdmin ? '1=1' : '(tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
        $scopeParams = $isAdmin ? [] : [$teacherId, $teacherId];
        $today = date('Y-m-d', $now);
        $horizon = date('Y-m-d', $now + 14 * 86400);
        $since = date('Y-m-d H:i:s', $now - 7 * 86400);
        $upcoming = $this->pdo->prepare("
            SELECT tt.delivery_mode, ol.id AS lesson_id
            FROM timetable tt
            LEFT JOIN online_lessons ol ON ol.timetable_id = tt.id
            WHERE $scope AND tt.deleted_at IS NULL AND tt.date BETWEEN ? AND ?
              AND (tt.lesson_status IS NULL OR tt.lesson_status IN ('scheduled','substituted'))
        ");
        $upcoming->execute(array_merge($scopeParams, [$today, $horizon]));
        $total = 0;
        $unplanned = 0;
        foreach ($upcoming->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!OnlineLessonService::supportsDeliveryMode((string)($row['delivery_mode'] ?? 'physical'))) {
                continue;
            }
            $total++;
            if (empty($row['lesson_id'])) {
                $unplanned++;
            }
        }
        $attempts = $this->pdo->prepare("
            SELECT COUNT(*) AS n, COUNT(DISTINCT at.student_id) AS students
            FROM online_lesson_attempts at
            JOIN online_lesson_items i ON i.id = at.item_id
            JOIN online_lessons ol ON ol.id = i.lesson_id
            JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
            WHERE $scope AND at.submitted_at >= ?
        ");
        $attempts->execute(array_merge($scopeParams, [$since]));
        $attemptRow = $attempts->fetch(PDO::FETCH_ASSOC) ?: [];
        $subs = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM online_lesson_submissions s
            JOIN online_lesson_items i ON i.id = s.item_id
            JOIN online_lessons ol ON ol.id = i.lesson_id
            JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
            WHERE $scope AND s.submitted_at >= ?
        ");
        $subs->execute(array_merge($scopeParams, [$since]));
        return [
            'unplanned' => $unplanned,
            'upcoming' => $total,
            'attempts_7d' => (int)($attemptRow['n'] ?? 0),
            'submissions_7d' => (int)$subs->fetchColumn(),
            'active_students_7d' => (int)($attemptRow['students'] ?? 0),
        ];
    }

    public function needsMarkingCount(int $teacherId, bool $isAdmin): int
    {
        $scope = $isAdmin ? '1=1' : '(tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
        $params = $isAdmin ? [] : [$teacherId, $teacherId];
        $written = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM online_lesson_answers an
            JOIN online_lesson_questions q ON q.id = an.question_id
            JOIN online_lesson_attempts at ON at.id = an.attempt_id
            JOIN online_lesson_items i ON i.id = at.item_id
            JOIN online_lessons ol ON ol.id = i.lesson_id
            JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
            WHERE $scope
              AND q.question_type IN ('essay','short','exam')
              AND an.marks_awarded IS NULL
              AND an.essay_text IS NOT NULL
        ");
        $written->execute($params);
        $files = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM online_lesson_submissions s
            JOIN online_lesson_items i ON i.id = s.item_id
            JOIN online_lessons ol ON ol.id = i.lesson_id
            JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
            WHERE $scope AND s.status = 'submitted'
        ");
        $files->execute($params);
        return (int)$written->fetchColumn() + (int)$files->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function markingQueue(int $teacherId, bool $isAdmin, int $page, array $filters = [], int $perPage = 30): array
    {
        $scope = $isAdmin ? '1=1' : '(tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
        $params = $isAdmin ? [] : [$teacherId, $teacherId];
        $perPage = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        [$outerWhere, $outerParams] = self::markingQueueFilters($filters);
        $newest = ($filters['order'] ?? '') === 'newest';
        $order = $newest ? 'DESC' : 'ASC';
        $limit = $offset + $perPage;
        // Separate queries: a UNION of these tables fails on production MariaDB with mixed column collations.
        $written = "
            SELECT * FROM (
                SELECT at.student_id, COALESCE(NULLIF(sp.full_name,''), u.username) AS student_name,
                       c.name AS class_name, ol.title AS lesson_title, i.title AS activity_title,
                       at.submitted_at, q.marks AS max_marks, 'pending' AS status, ol.timetable_id, 'written' AS kind,
                       tt.class_id, tt.date AS lesson_date, at.id AS attempt_id, q.id AS question_id, 0 AS submission_id
                FROM online_lesson_answers an
                JOIN online_lesson_questions q ON q.id = an.question_id
                JOIN online_lesson_attempts at ON at.id = an.attempt_id
                JOIN online_lesson_items i ON i.id = at.item_id
                JOIN online_lessons ol ON ol.id = i.lesson_id
                JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
                JOIN student_classes c ON c.id = tt.class_id
                LEFT JOIN users u ON u.id = at.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = at.student_id
                WHERE $scope AND q.question_type IN ('essay','short','exam') AND an.marks_awarded IS NULL AND an.essay_text IS NOT NULL
            ) queue
            WHERE $outerWhere
            ORDER BY submitted_at $order, attempt_id $order, question_id ASC
            LIMIT $limit
        ";
        $files = "
            SELECT * FROM (
                SELECT s.student_id, COALESCE(NULLIF(sp.full_name,''), u.username) AS student_name,
                       c.name AS class_name, ol.title AS lesson_title, i.title AS activity_title,
                       s.submitted_at, a.max_marks, s.status, ol.timetable_id, 'submission' AS kind,
                       tt.class_id, tt.date AS lesson_date, 0 AS attempt_id, 0 AS question_id, s.id AS submission_id
                FROM online_lesson_submissions s
                JOIN online_lesson_items i ON i.id = s.item_id
                JOIN online_lesson_activities a ON a.id = i.activity_id
                JOIN online_lessons ol ON ol.id = i.lesson_id
                JOIN timetable tt ON tt.id = ol.timetable_id AND tt.deleted_at IS NULL
                JOIN student_classes c ON c.id = tt.class_id
                LEFT JOIN users u ON u.id = s.student_id
                LEFT JOIN student_profiles sp ON sp.user_id = s.student_id
                WHERE $scope AND s.status IN ('submitted','resubmit')
            ) queue
            WHERE $outerWhere
            ORDER BY submitted_at $order, submission_id $order
            LIMIT $limit
        ";
        $rows = [];
        foreach ([$written, $files] as $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $outerParams));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rows[] = $row;
            }
        }
        return array_slice(self::sortMarkingQueue($rows, $newest), $offset, $perPage);
    }

    /**
     * Same order as the queue SQL: submitted time, then attempt, question and submission ids.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function sortMarkingQueue(array $rows, bool $newest = false): array
    {
        $dir = $newest ? -1 : 1;
        usort($rows, static function (array $a, array $b) use ($dir): int {
            return $dir * strcmp((string)($a['submitted_at'] ?? ''), (string)($b['submitted_at'] ?? ''))
                ?: $dir * ((int)$a['attempt_id'] <=> (int)$b['attempt_id'])
                ?: ((int)$a['question_id'] <=> (int)$b['question_id'])
                ?: $dir * ((int)$a['submission_id'] <=> (int)$b['submission_id']);
        });
        return $rows;
    }

    /**
     * Only items that are actually waiting: resubmission requests are with the student, not the teacher.
     */
    public function nextUnmarked(int $teacherId, bool $isAdmin, array $filters = []): ?array
    {
        $filters['order'] = 'oldest';
        if (!in_array((string)($filters['status'] ?? ''), ['pending', 'submitted'], true)) {
            $filters['status'] = 'waiting';
        }
        return $this->markingQueue($teacherId, $isAdmin, 1, $filters, 1)[0] ?? null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    public static function markingQueueFilters(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if ((int)($filters['class_id'] ?? 0) > 0) {
            $where[] = 'class_id = ?';
            $params[] = (int)$filters['class_id'];
        }
        if ((int)($filters['lesson'] ?? 0) > 0) {
            $where[] = 'timetable_id = ?';
            $params[] = (int)$filters['lesson'];
        }
        $kind = (string)($filters['kind'] ?? '');
        if (in_array($kind, ['written', 'submission'], true)) {
            $where[] = 'kind = ?';
            $params[] = $kind;
        }
        $status = (string)($filters['status'] ?? '');
        if (in_array($status, ['pending', 'submitted', 'resubmit'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        } elseif ($status === 'waiting') {
            $where[] = "status IN ('pending','submitted')";
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            $value = (string)($filters[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $where[] = "lesson_date $op ?";
                $params[] = $value;
            }
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function resourcesFor(int $userId, bool $isAdmin, string $search, bool $archived): array
    {
        $where = $isAdmin ? '1=1' : 'owner_user_id = ?';
        $params = $isAdmin ? [] : [$userId];
        $where .= $archived ? ' AND archived = 1' : ' AND archived = 0';
        $search = trim($search);
        if ($search !== '') {
            $where .= ' AND title LIKE ?';
            $params[] = '%' . mb_substr($search, 0, 80) . '%';
        }
        $stmt = $this->pdo->prepare("SELECT * FROM online_lesson_resources WHERE $where ORDER BY created_at DESC LIMIT 40");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveUrlResource(int $userId, string $title, string $type, string $url): int
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a resource title.');
        }
        if (!in_array($type, ['video', 'pdf', 'ppt', 'image', 'document', 'url'], true)) {
            $type = 'url';
        }
        $url = OnlineLessonService::normalizeExternalUrl($url);
        $this->pdo->prepare('
            INSERT INTO online_lesson_resources (owner_user_id, title, resource_type, url)
            VALUES (?, ?, ?, ?)
        ')->execute([$userId, $title, $type, $url]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $file
     */
    public function saveFileResource(int $userId, string $title, string $type, array $file): int
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = mb_substr(basename((string)($file['name'] ?? 'Resource')), 0, 200);
        }
        if (!in_array($type, ['pdf', 'ppt', 'image', 'document'], true)) {
            $type = 'document';
        }
        $stored = self::storeUpload($file, 'resources/' . $userId);
        $this->pdo->prepare('
            INSERT INTO online_lesson_resources (owner_user_id, title, resource_type, file_key, file_name, mime)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([$userId, $title, $type, $stored['key'], $stored['name'], $stored['mime']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function archiveResource(int $resourceId, int $userId, bool $isAdmin, bool $archived): void
    {
        $resource = $this->resource($resourceId);
        if (!$resource || (!$isAdmin && (int)$resource['owner_user_id'] !== $userId)) {
            throw new RuntimeException('That resource was not found.');
        }
        $this->pdo->prepare('UPDATE online_lesson_resources SET archived = ? WHERE id = ?')->execute([$archived ? 1 : 0, $resourceId]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function resource(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_resources WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<int>
     */
    public function archivedResourceIds(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.resource_id
            FROM online_lesson_items i
            JOIN online_lesson_resources r ON r.id = i.resource_id
            WHERE i.lesson_id = ? AND r.archived = 1
        ');
        $stmt->execute([$lessonId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function syncObjectiveText(int $lessonId): void
    {
        $lines = [];
        foreach ($this->objectives($lessonId) as $row) {
            $lines[] = (string)$row['body'];
        }
        $this->pdo->prepare('UPDATE online_lessons SET plan_objectives = ? WHERE id = ?')
            ->execute([$lines === [] ? null : implode("\n", $lines), $lessonId]);
    }

    private function deletePrivate(string $key): void
    {
        try {
            $path = online_lesson_private_path($key);
            if (is_file($path)) {
                unlink($path);
            }
        } catch (\Throwable $e) {
        }
    }
}
