<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Lesson plan, sections, and publish checks for an existing video lesson.
 * Items stay in online_lesson_items. A section is only a grouping.
 */
final class LessonAuthoringService
{
    public function __construct(private PDO $pdo, private OnlineLessonService $lessons)
    {
    }

    /**
     * @param array<string,mixed> $fields
     */
    public function savePlan(int $lessonId, array $fields): void
    {
        $minutes = trim((string)($fields['plan_minutes'] ?? ''));
        $this->pdo->prepare('
            UPDATE online_lessons
            SET plan_objectives = ?, plan_topics = ?, plan_minutes = ?, plan_difficulty = ?,
                plan_prerequisites = ?, plan_outcomes = ?, plan_materials = ?, plan_homework = ?, plan_assessment = ?
            WHERE id = ?
        ')->execute([
            self::blank($fields['plan_objectives'] ?? ''),
            self::blank($fields['plan_topics'] ?? ''),
            $minutes === '' ? null : max(1, min(600, (int)$minutes)),
            self::blank($fields['plan_difficulty'] ?? '', 40),
            self::blank($fields['plan_prerequisites'] ?? ''),
            self::blank($fields['plan_outcomes'] ?? ''),
            self::blank($fields['plan_materials'] ?? ''),
            self::blank($fields['plan_homework'] ?? ''),
            self::blank($fields['plan_assessment'] ?? ''),
            $lessonId,
        ]);
    }

    public function setArchived(int $lessonId, bool $archived): void
    {
        $this->pdo->prepare('UPDATE online_lessons SET archived = ? WHERE id = ?')->execute([$archived ? 1 : 0, $lessonId]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sections(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_sections WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addSection(int $lessonId, string $title, ?int $minutes = null): int
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = 'Section';
        }
        $sort = count($this->sections($lessonId)) + 1;
        $this->pdo->prepare('
            INSERT INTO online_lesson_sections (lesson_id, title, sort_order, estimated_minutes)
            VALUES (?, ?, ?, ?)
        ')->execute([$lessonId, $title, $sort, $minutes]);
        return (int)$this->pdo->lastInsertId();
    }

    public function renameSection(int $lessonId, int $sectionId, string $title, ?int $minutes): void
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a section title.');
        }
        $this->pdo->prepare('
            UPDATE online_lesson_sections SET title = ?, estimated_minutes = ? WHERE id = ? AND lesson_id = ?
        ')->execute([$title, $minutes, $sectionId, $lessonId]);
    }

    public function deleteSection(int $lessonId, int $sectionId): void
    {
        $this->pdo->prepare('UPDATE online_lesson_items SET section_id = NULL WHERE lesson_id = ? AND section_id = ?')
            ->execute([$lessonId, $sectionId]);
        $this->pdo->prepare('DELETE FROM online_lesson_sections WHERE id = ? AND lesson_id = ?')->execute([$sectionId, $lessonId]);
    }

    public function moveSection(int $lessonId, int $sectionId, string $direction): void
    {
        $sections = $this->sections($lessonId);
        $index = null;
        foreach ($sections as $i => $section) {
            if ((int)$section['id'] === $sectionId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($sections[$swap])) {
            return;
        }
        $a = (int)$sections[$index]['sort_order'];
        $b = (int)$sections[$swap]['sort_order'];
        $this->pdo->prepare('UPDATE online_lesson_sections SET sort_order = ? WHERE id = ?')->execute([$b, $sectionId]);
        $this->pdo->prepare('UPDATE online_lesson_sections SET sort_order = ? WHERE id = ?')->execute([$a, (int)$sections[$swap]['id']]);
    }

    public function assignItem(int $lessonId, int $itemId, ?int $sectionId): void
    {
        if ($sectionId !== null && $sectionId > 0) {
            $check = $this->pdo->prepare('SELECT id FROM online_lesson_sections WHERE id = ? AND lesson_id = ?');
            $check->execute([$sectionId, $lessonId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('That section is not part of this lesson.');
            }
        } else {
            $sectionId = null;
        }
        $this->pdo->prepare('UPDATE online_lesson_items SET section_id = ? WHERE id = ? AND lesson_id = ?')
            ->execute([$sectionId, $itemId, $lessonId]);
    }

    /**
     * Creates a draft structure from the saved objectives. Does not publish.
     * Refuses when the lesson already has notes, questions, or links.
     */
    public function generateDraft(int $lessonId): int
    {
        $lesson = $this->lessons->find($lessonId);
        if (!$lesson) {
            throw new RuntimeException('That lesson was not found.');
        }
        foreach ($this->lessons->items($lessonId) as $item) {
            $type = OnlineLessonService::normalizeItemType((string)$item['item_type']);
            if ($type !== 'video') {
                throw new RuntimeException('This lesson already has pages or activities. The plan will not replace them.');
            }
        }
        $lines = self::lines((string)($lesson['plan_objectives'] ?? ''));
        if ($lines === []) {
            $lines = ['Lesson notes'];
        }
        $created = 0;
        $intro = $this->addSection($lessonId, 'Introduction', 5);
        $page = $this->lessons->addPageAfter($lessonId, 0, 'Learning objectives', implode("\n", $lines));
        $this->assignItem($lessonId, (int)$page['id'], $intro);
        $created++;
        foreach ($lines as $line) {
            $section = $this->addSection($lessonId, mb_substr($line, 0, 200), 15);
            $note = $this->lessons->addPageAfter($lessonId, 0, mb_substr($line, 0, 200), 'Add the explanation, example, and any class notes for this objective.');
            $this->assignItem($lessonId, (int)$note['id'], $section);
            $created++;
        }
        $assess = $this->addSection($lessonId, 'Assessment', 20);
        $quiz = $this->lessons->addActivityAfter($lessonId, 0, 'mcq', 'Check understanding');
        $this->assignItem($lessonId, (int)$quiz['id'], $assess);
        $created++;
        return $created;
    }

    /**
     * @param array<string,mixed> $lesson
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $questions
     * @param list<array<string,mixed>> $sections
     * @param list<int> $archivedResourceIds
     * @return list<string>
     */
    public static function issues(array $lesson, array $items, array $questions, array $sections = [], array $archivedResourceIds = []): array
    {
        $messages = [];
        foreach (self::issueRows($lesson, $items, $questions, $sections, $archivedResourceIds) as $row) {
            $messages[] = (string)$row['message'];
        }
        return $messages;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $questions
     * @param list<array<string,mixed>> $sections
     * @param list<int> $archivedResourceIds
     * @return list<array{message:string,activity_id:int,item_id:int}>
     */
    public static function issueRows(array $lesson, array $items, array $questions, array $sections = [], array $archivedResourceIds = []): array
    {
        $issues = [];
        $add = static function (string $message, int $activityId = 0, int $itemId = 0) use (&$issues): void {
            $issues[] = ['message' => $message, 'activity_id' => $activityId, 'item_id' => $itemId];
        };
        if (trim((string)($lesson['title'] ?? '')) === '') {
            $add('The lesson has no title.');
        }
        if (!empty($lesson['archived'])) {
            $add('This lesson is archived. Restore it before publishing.');
        }
        if ($items === []) {
            $add('The lesson has no activities yet.');
        }
        $byActivity = [];
        foreach ($questions as $question) {
            $byActivity[(int)$question['activity_id']][] = $question;
        }
        $archived = array_fill_keys(array_map('intval', $archivedResourceIds), true);
        $usedSections = [];
        foreach ($items as $item) {
            $title = trim((string)($item['title'] ?? '')) ?: 'Untitled item';
            $type = OnlineLessonService::normalizeItemType((string)($item['item_type'] ?? ''));
            $itemId = (int)($item['id'] ?? 0);
            $sectionId = (int)($item['section_id'] ?? 0);
            if ($sectionId > 0) {
                $usedSections[$sectionId] = true;
            }
            if ($type === 'page' && trim((string)($item['body'] ?? '')) === '') {
                $add($title . ' is an empty text page.', 0, $itemId);
            }
            if ($type === 'external_link') {
                try {
                    OnlineLessonService::normalizeExternalUrl((string)($item['link_url'] ?? ''));
                } catch (\Throwable $e) {
                    $add($title . ' has an invalid link.', 0, $itemId);
                }
            }
            if ($type === 'resource') {
                $resourceId = (int)($item['resource_id'] ?? 0);
                if ($resourceId < 1) {
                    $add($title . ' has no resource.', 0, $itemId);
                } elseif (isset($archived[$resourceId])) {
                    $add($title . ' uses an archived resource.', 0, $itemId);
                }
            }
            if ($type !== 'activity') {
                continue;
            }
            $activityId = (int)($item['activity_id'] ?? 0);
            $activityType = strtolower(trim((string)($item['activity_type'] ?? '')));
            if (OnlineLessonService::isSubmissionActivity($activityType)) {
                if (trim((string)($item['activity_instructions'] ?? '')) === '') {
                    $add($title . ' has no instructions.', $activityId, $itemId);
                }
                if ((float)($item['max_marks'] ?? 0) <= 0) {
                    $add($title . ' has no maximum marks.', $activityId, $itemId);
                }
                if ((int)($item['allow_text'] ?? 1) !== 1 && (int)($item['allow_file'] ?? 1) !== 1) {
                    $add($title . ' does not allow a submission.', $activityId, $itemId);
                }
                continue;
            }
            $rows = $byActivity[$activityId] ?? [];
            if ($rows === []) {
                $add($title . ' has no questions.', $activityId, $itemId);
                continue;
            }
            foreach ($rows as $question) {
                $kind = (string)($question['question_type'] ?? 'mcq');
                $marks = (float)($question['marks'] ?? 0);
                if ($marks <= 0) {
                    $add($title . ' has a question with no marks.', $activityId, $itemId);
                }
                if (!OnlineLessonService::isManualQuestionType($kind) && ($question['correct_index'] === null || $question['correct_index'] === '')) {
                    $add($title . ' has an MCQ with no correct answer.', $activityId, $itemId);
                }
            }
        }
        foreach ($sections as $section) {
            $sectionId = (int)($section['id'] ?? 0);
            if ($sectionId > 0 && !isset($usedSections[$sectionId])) {
                $add(trim((string)($section['title'] ?? 'Section')) . ' is an empty section.');
            }
        }
        return $issues;
    }

    /**
     * @param list<array<string,mixed>> $sections
     * @param list<array<string,mixed>> $items
     * @return array{estimated:?int,planned:?int,state:string,message:string}
     */
    public static function durationStatus(?int $planned, array $sections, array $items = []): array
    {
        $sum = 0;
        $any = false;
        foreach ($items as $item) {
            if (($item['estimated_minutes'] ?? null) === null || $item['estimated_minutes'] === '') {
                continue;
            }
            $any = true;
            $sum += (int)$item['estimated_minutes'];
        }
        if (!$any) {
            foreach ($sections as $section) {
                if (($section['estimated_minutes'] ?? null) === null || $section['estimated_minutes'] === '') {
                    continue;
                }
                $any = true;
                $sum += (int)$section['estimated_minutes'];
            }
        }
        if ($planned === null || $planned < 1 || !$any) {
            return ['estimated' => $any ? $sum : null, 'planned' => $planned, 'state' => 'unknown', 'message' => ''];
        }
        if ($sum <= $planned) {
            return [
                'estimated' => $sum,
                'planned' => $planned,
                'state' => 'fits',
                'message' => 'Estimated duration is ' . $sum . ' minutes. Planned duration is ' . $planned . ' minutes. The lesson fits the planned time.',
            ];
        }
        return [
            'estimated' => $sum,
            'planned' => $planned,
            'state' => 'over',
            'message' => 'Estimated duration exceeds the planned duration by ' . ($sum - $planned) . ' minutes.',
        ];
    }

    /**
     * @param list<array<string,mixed>> $sections
     */
    public static function durationWarning(?int $planned, array $sections): ?string
    {
        if ($planned === null || $planned < 1) {
            return null;
        }
        $sum = 0;
        $any = false;
        foreach ($sections as $section) {
            if ($section['estimated_minutes'] === null || $section['estimated_minutes'] === '') {
                continue;
            }
            $any = true;
            $sum += (int)$section['estimated_minutes'];
        }
        if (!$any || $sum <= $planned) {
            return null;
        }
        return 'Estimated lesson duration exceeds the planned ' . $planned . ' minutes by ' . ($sum - $planned) . ' minutes.';
    }

    public function questionRows(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT q.activity_id, q.question_type, q.correct_index, q.marks
            FROM online_lesson_questions q
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ?
        ');
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function marksTotal(int $lessonId): float
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(SUM(q.marks), 0)
            FROM online_lesson_questions q
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ?
        ');
        $stmt->execute([$lessonId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * A template is a copy of the plan, sections, pages, links, and questions.
     * It does not include recordings, student progress, attempts, or marks.
     *
     * @param array<string,mixed> $lesson
     * @param list<array<string,mixed>> $sections
     * @param list<array<string,mixed>> $items
     * @param array<int,list<array<string,mixed>>> $questionsByActivity
     * @return array<string,mixed>
     */
    public static function snapshot(array $lesson, array $sections, array $items, array $questionsByActivity): array
    {
        $sectionItems = [];
        $loose = [];
        foreach ($items as $item) {
            $packed = self::packItem($item, $questionsByActivity[(int)($item['activity_id'] ?? 0)] ?? []);
            if ($packed === null) {
                continue;
            }
            $sectionId = (int)($item['section_id'] ?? 0);
            if ($sectionId > 0) {
                $sectionItems[$sectionId][] = $packed;
            } else {
                $loose[] = $packed;
            }
        }
        $packedSections = [];
        foreach ($sections as $section) {
            $packedSections[] = [
                'title' => (string)$section['title'],
                'minutes' => $section['estimated_minutes'] === null || $section['estimated_minutes'] === '' ? null : (int)$section['estimated_minutes'],
                'items' => $sectionItems[(int)$section['id']] ?? [],
            ];
        }
        return [
            'plan' => [
                'objectives' => (string)($lesson['plan_objectives'] ?? ''),
                'topics' => (string)($lesson['plan_topics'] ?? ''),
                'minutes' => $lesson['plan_minutes'] ?? null,
                'difficulty' => (string)($lesson['plan_difficulty'] ?? ''),
                'prerequisites' => (string)($lesson['plan_prerequisites'] ?? ''),
                'outcomes' => (string)($lesson['plan_outcomes'] ?? ''),
                'materials' => (string)($lesson['plan_materials'] ?? ''),
                'homework' => (string)($lesson['plan_homework'] ?? ''),
                'assessment' => (string)($lesson['plan_assessment'] ?? ''),
            ],
            'sections' => $packedSections,
            'items' => $loose,
        ];
    }

    public function saveTemplate(int $lessonId, int $userId, string $title): int
    {
        $lesson = $this->lessons->find($lessonId);
        if (!$lesson) {
            throw new RuntimeException('That lesson was not found.');
        }
        $title = mb_substr(trim($title), 0, 200);
        if ($title === '') {
            $title = mb_substr(trim((string)$lesson['title']), 0, 200);
        }
        if ($title === '') {
            throw new RuntimeException('Enter a template name.');
        }
        $questions = [];
        $stmt = $this->pdo->prepare('
            SELECT q.*
            FROM online_lesson_questions q
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ?
            ORDER BY q.sort_order ASC, q.id ASC
        ');
        $stmt->execute([$lessonId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $decoded = json_decode((string)($row['choices_json'] ?? ''), true);
            $row['choices'] = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
            $questions[(int)$row['activity_id']][] = $row;
        }
        $snapshot = self::snapshot($lesson, $this->sections($lessonId), $this->lessons->items($lessonId), $questions);
        $this->pdo->prepare('
            INSERT INTO online_lesson_templates (owner_user_id, title, structure_json)
            VALUES (?, ?, ?)
        ')->execute([$userId, $title, json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function templatesFor(int $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            $stmt = $this->pdo->query('SELECT id, owner_user_id, title, created_at FROM online_lesson_templates ORDER BY created_at DESC LIMIT 100');
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $stmt = $this->pdo->prepare('SELECT id, owner_user_id, title, created_at FROM online_lesson_templates WHERE owner_user_id = ? ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function applyTemplate(int $lessonId, int $templateId, int $userId, bool $isAdmin): int
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_templates WHERE id = ? LIMIT 1');
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template || (!$isAdmin && (int)$template['owner_user_id'] !== $userId)) {
            throw new RuntimeException('That template was not found.');
        }
        foreach ($this->lessons->items($lessonId) as $item) {
            if (OnlineLessonService::normalizeItemType((string)$item['item_type']) !== 'video') {
                throw new RuntimeException('This lesson already has pages or activities. Apply a template to an empty lesson.');
            }
        }
        $structure = json_decode((string)$template['structure_json'], true);
        if (!is_array($structure)) {
            throw new RuntimeException('That template could not be read.');
        }
        $plan = is_array($structure['plan'] ?? null) ? $structure['plan'] : [];
        $this->pdo->beginTransaction();
        try {
        $this->savePlan($lessonId, [
            'plan_objectives' => $plan['objectives'] ?? '',
            'plan_topics' => $plan['topics'] ?? '',
            'plan_minutes' => $plan['minutes'] ?? '',
            'plan_difficulty' => $plan['difficulty'] ?? '',
            'plan_prerequisites' => $plan['prerequisites'] ?? '',
            'plan_outcomes' => $plan['outcomes'] ?? '',
            'plan_materials' => $plan['materials'] ?? '',
            'plan_homework' => $plan['homework'] ?? '',
            'plan_assessment' => $plan['assessment'] ?? '',
        ]);
        $created = 0;
        foreach (($structure['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionId = $this->addSection($lessonId, (string)($section['title'] ?? 'Section'), isset($section['minutes']) ? (int)$section['minutes'] : null);
            foreach (($section['items'] ?? []) as $item) {
                if (is_array($item) && $this->materialiseItem($lessonId, $item, $sectionId)) {
                    $created++;
                }
            }
        }
        foreach (($structure['items'] ?? []) as $item) {
            if (is_array($item) && $this->materialiseItem($lessonId, $item, null)) {
                $created++;
            }
        }
        $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $created;
    }

    public function duplicateSection(int $lessonId, int $sectionId): int
    {
        $sections = $this->sections($lessonId);
        $source = null;
        foreach ($sections as $section) {
            if ((int)$section['id'] === $sectionId) {
                $source = $section;
                break;
            }
        }
        if (!$source) {
            throw new RuntimeException('That section was not found.');
        }
        $copyId = $this->addSection($lessonId, 'Copy of ' . (string)$source['title'], $source['estimated_minutes'] === null ? null : (int)$source['estimated_minutes']);
        $created = 0;
        foreach ($this->lessons->items($lessonId) as $item) {
            if ((int)($item['section_id'] ?? 0) !== $sectionId) {
                continue;
            }
            $newId = $this->cloneItem($lessonId, $item);
            if ($newId > 0) {
                $this->assignItem($lessonId, $newId, $copyId);
                $created++;
            }
        }
        return $created;
    }

    public function duplicateItem(int $lessonId, int $itemId): int
    {
        $item = $this->lessons->item($itemId);
        if (!$item || (int)$item['lesson_id'] !== $lessonId) {
            throw new RuntimeException('That activity was not found.');
        }
        $newId = $this->cloneItem($lessonId, $item);
        if ($newId < 1) {
            throw new RuntimeException('Video clips stay with the class recording and are not duplicated here.');
        }
        $sectionId = (int)($item['section_id'] ?? 0);
        if ($sectionId > 0) {
            $this->assignItem($lessonId, $newId, $sectionId);
        }
        return $newId;
    }

    /**
     * @param array<string,mixed> $item
     * @param list<array<string,mixed>> $questions
     * @return array<string,mixed>|null
     */
    private static function packItem(array $item, array $questions): ?array
    {
        $type = OnlineLessonService::normalizeItemType((string)($item['item_type'] ?? ''));
        if ($type === 'video') {
            return null;
        }
        $packed = [
            'type' => $type,
            'title' => (string)($item['title'] ?? ''),
            'required' => (int)($item['required'] ?? 1) === 1,
        ];
        if ($type === 'page') {
            $packed['body'] = (string)($item['body'] ?? '');
        } elseif ($type === 'external_link') {
            $packed['url'] = (string)($item['link_url'] ?? '');
            $packed['new_tab'] = (int)($item['open_new_tab'] ?? 1) === 1;
        } elseif ($type === 'activity') {
            $packed['activity_type'] = (string)($item['activity_type'] ?? 'mcq');
            $packed['questions'] = [];
            foreach ($questions as $question) {
                $packed['questions'][] = [
                    'type' => (string)($question['question_type'] ?? 'mcq'),
                    'prompt' => (string)($question['prompt'] ?? ''),
                    'choices' => array_values($question['choices'] ?? []),
                    'correct_index' => $question['correct_index'] === null ? null : (int)$question['correct_index'],
                    'marks' => (float)($question['marks'] ?? 1),
                    'explanation' => (string)($question['explanation'] ?? ''),
                    'topic' => (string)($question['topic'] ?? ''),
                    'difficulty' => (string)($question['difficulty'] ?? ''),
                    'exam_ref' => (string)($question['exam_ref'] ?? ''),
                    'expected_answer' => (string)($question['expected_answer'] ?? ''),
                ];
            }
        }
        return $packed;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function materialiseItem(int $lessonId, array $item, ?int $sectionId): bool
    {
        $type = (string)($item['type'] ?? '');
        $created = null;
        if ($type === 'page') {
            $created = $this->lessons->addPageAfter($lessonId, 0, (string)($item['title'] ?? 'Notes'), (string)($item['body'] ?? ''));
        } elseif ($type === 'external_link') {
            $created = $this->lessons->saveExternalLink($lessonId, 0, 0, (string)($item['title'] ?? 'Link'), (string)($item['url'] ?? ''), !empty($item['new_tab']), !isset($item['required']) || !empty($item['required']));
        } elseif ($type === 'activity') {
            $created = $this->lessons->addActivityAfter($lessonId, 0, (string)($item['activity_type'] ?? 'mcq'), (string)($item['title'] ?? 'Questions'));
            $activityId = (int)($created['activity_id'] ?? 0);
            foreach (($item['questions'] ?? []) as $question) {
                if (!is_array($question) || $activityId < 1) {
                    continue;
                }
                $this->lessons->addQuestion(
                    $activityId,
                    (string)($question['type'] ?? 'mcq'),
                    (string)($question['prompt'] ?? ''),
                    array_values($question['choices'] ?? []),
                    isset($question['correct_index']) && $question['correct_index'] !== null ? (int)$question['correct_index'] : null,
                    (float)($question['marks'] ?? 1),
                    (string)($question['explanation'] ?? ''),
                    (string)($question['topic'] ?? ''),
                    (string)($question['difficulty'] ?? ''),
                    (string)($question['exam_ref'] ?? ''),
                    (string)($question['expected_answer'] ?? '')
                );
            }
        }
        if (!$created) {
            return false;
        }
        if ($sectionId !== null) {
            $this->assignItem($lessonId, (int)$created['id'], $sectionId);
        }
        return true;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function cloneItem(int $lessonId, array $item): int
    {
        $type = OnlineLessonService::normalizeItemType((string)$item['item_type']);
        if ($type === 'video') {
            return 0;
        }
        $questions = [];
        if ($type === 'activity' && (int)($item['activity_id'] ?? 0) > 0) {
            foreach ($this->lessons->questions((int)$item['activity_id']) as $question) {
                $questions[] = $question;
            }
        }
        $packed = self::packItem($item, $questions);
        if ($packed === null) {
            return 0;
        }
        $packed['title'] = 'Copy of ' . (string)$packed['title'];
        $before = array_column($this->lessons->items($lessonId), 'id');
        if (!$this->materialiseItem($lessonId, $packed, null)) {
            return 0;
        }
        foreach ($this->lessons->items($lessonId) as $candidate) {
            if (!in_array((int)$candidate['id'], $before, true)) {
                return (int)$candidate['id'];
            }
        }
        return 0;
    }

    private static function blank(mixed $value, int $limit = 5000): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $rows[] = $line;
            }
        }
        return $rows;
    }
}
