<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Lesson content history. A version is a snapshot of the lesson design only:
 * settings, plan, sections, objectives, items, activities, questions and criteria.
 * It never contains progress, attempts, answers, marks or submissions.
 */
final class LessonVersionService
{
    public const LESSON_FIELDS = [
        'title', 'intro', 'sequential', 'min_watch_percent', 'available_after_class', 'close_after_days', 'pass_percent',
        'plan_objectives', 'plan_topics', 'plan_minutes', 'plan_difficulty', 'plan_prerequisites', 'plan_outcomes',
        'plan_materials', 'plan_homework', 'plan_assessment',
    ];
    public const SECTION_FIELDS = ['title', 'sort_order', 'estimated_minutes'];
    public const OBJECTIVE_FIELDS = ['body', 'sort_order'];
    public const ITEM_FIELDS = [
        'item_type', 'title', 'sort_order', 'video_asset_id', 'body', 'required', 'link_url', 'open_new_tab',
        'section_id', 'estimated_minutes', 'resource_id',
    ];
    public const ACTIVITY_FIELDS = [
        'activity_type', 'title', 'instructions', 'pass_percent', 'max_attempts', 'show_correct', 'shuffle_choices',
        'shuffle_questions', 'due_at', 'max_marks', 'allow_text', 'allow_file', 'draw_count', 'scoring_rule',
        'time_limit_minutes', 'show_score', 'show_explanation',
    ];
    public const QUESTION_FIELDS = [
        'question_type', 'sort_order', 'prompt', 'choices_json', 'correct_index', 'marks', 'explanation', 'topic',
        'difficulty', 'exam_ref', 'expected_answer', 'tags', 'ai_generated',
    ];
    public const REASONS = [
        'saved' => 'Saved by teacher',
        'published' => 'Published',
        'before_restore' => 'Before restore',
        'restored' => 'Restored',
        'copied' => 'Copied from another class',
        'ai_applied' => 'AI draft applied',
    ];

    /** @var array<string,list<string>> */
    private array $columns = [];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lessons WHERE id = ? LIMIT 1');
        $stmt->execute([$lessonId]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lesson) {
            throw new RuntimeException('That lesson was not found.');
        }
        $snap = [
            'v' => 2,
            'lesson' => self::pick($lesson, self::LESSON_FIELDS),
            'sections' => [],
            'objectives' => [],
            'items' => [],
            'objective_links' => [],
        ];
        foreach ($this->rows('SELECT * FROM online_lesson_sections WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC', [$lessonId]) as $row) {
            $snap['sections'][] = ['id' => (int)$row['id']] + self::pick($row, self::SECTION_FIELDS);
        }
        $objectiveIds = [];
        foreach ($this->rows('SELECT * FROM online_lesson_objectives WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC', [$lessonId]) as $row) {
            $snap['objectives'][] = ['id' => (int)$row['id']] + self::pick($row, self::OBJECTIVE_FIELDS);
            $objectiveIds[] = (int)$row['id'];
        }
        $activities = [];
        foreach ($this->rows('SELECT * FROM online_lesson_activities WHERE lesson_id = ?', [$lessonId]) as $row) {
            $activities[(int)$row['id']] = ['id' => (int)$row['id']] + self::pick($row, self::ACTIVITY_FIELDS) + ['questions' => []];
        }
        $questionRows = $this->rows('
            SELECT q.* FROM online_lesson_questions q
            JOIN online_lesson_activities a ON a.id = q.activity_id
            WHERE a.lesson_id = ?
            ORDER BY q.sort_order ASC, q.id ASC
        ', [$lessonId]);
        $criteria = [];
        if ($questionRows !== []) {
            $ids = array_map(static fn (array $q): int => (int)$q['id'], $questionRows);
            foreach ($this->rows('SELECT * FROM online_lesson_criteria WHERE question_id IN (' . self::marks($ids) . ') ORDER BY sort_order ASC, id ASC', $ids) as $c) {
                $criteria[(int)$c['question_id']][] = [
                    'label' => (string)$c['label'],
                    'marks' => (string)$c['marks'],
                    'sort_order' => (int)$c['sort_order'],
                ];
            }
        }
        foreach ($questionRows as $q) {
            $aid = (int)$q['activity_id'];
            if (!isset($activities[$aid])) {
                continue;
            }
            $activities[$aid]['questions'][] = ['id' => (int)$q['id']] + self::pick($q, self::QUESTION_FIELDS)
                + ['criteria' => $criteria[(int)$q['id']] ?? []];
        }
        foreach ($this->rows('SELECT * FROM online_lesson_items WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC', [$lessonId]) as $row) {
            $item = ['id' => (int)$row['id']] + self::pick($row, self::ITEM_FIELDS);
            $aid = (int)($row['activity_id'] ?? 0);
            $item['activity'] = $aid > 0 && isset($activities[$aid]) ? $activities[$aid] : null;
            $snap['items'][] = $item;
        }
        if ($objectiveIds !== []) {
            foreach ($this->rows('SELECT objective_id, question_id, item_id FROM online_lesson_objective_links WHERE objective_id IN (' . self::marks($objectiveIds) . ') ORDER BY id ASC', $objectiveIds) as $link) {
                $snap['objective_links'][] = [
                    'objective_id' => (int)$link['objective_id'],
                    'question_id' => (int)$link['question_id'],
                    'item_id' => (int)$link['item_id'],
                ];
            }
        }
        return $snap;
    }

    public static function hash(array $snapshot): string
    {
        return hash('sha256', (string)json_encode($snapshot, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Saves a version when the content changed since the last one. Returns the latest version id.
     */
    public function createVersion(int $lessonId, int $userId, string $reason = 'saved', ?int $restoredFrom = null, bool $force = false): int
    {
        $reason = isset(self::REASONS[$reason]) ? $reason : 'saved';
        $snapshot = $this->snapshot($lessonId);
        $hash = self::hash($snapshot);
        $latest = $this->pdo->prepare('SELECT id, version_no, snapshot_hash FROM online_lesson_versions WHERE lesson_id = ? ORDER BY version_no DESC LIMIT 1');
        $latest->execute([$lessonId]);
        $last = $latest->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$force && $last && (string)$last['snapshot_hash'] === $hash) {
            return (int)$last['id'];
        }
        $next = $last ? (int)$last['version_no'] + 1 : 1;
        for ($try = 0; $try < 3; $try++) {
            try {
                $this->pdo->prepare('
                    INSERT INTO online_lesson_versions (lesson_id, version_no, snapshot_json, snapshot_hash, reason, restored_from, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([
                    $lessonId,
                    $next,
                    json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                    $hash,
                    $reason,
                    $restoredFrom,
                    $userId > 0 ? $userId : null,
                    date('Y-m-d H:i:s'),
                ]);
                return (int)$this->pdo->lastInsertId();
            } catch (\PDOException $e) {
                $next++;
            }
        }
        throw new RuntimeException('Could not save the lesson version. Try again.');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function versions(int $lessonId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('
            SELECT v.id, v.version_no, v.reason, v.restored_from, v.created_by, v.created_at, u.username AS created_by_name
            FROM online_lesson_versions v
            LEFT JOIN users u ON u.id = v.created_by
            WHERE v.lesson_id = ?
            ORDER BY v.version_no DESC
            LIMIT ' . max(1, min(200, $limit)));
        try {
            $stmt->execute([$lessonId]);
        } catch (\PDOException $e) {
            $stmt = $this->pdo->prepare('
                SELECT id, version_no, reason, restored_from, created_by, created_at
                FROM online_lesson_versions WHERE lesson_id = ? ORDER BY version_no DESC LIMIT ' . max(1, min(200, $limit)));
            $stmt->execute([$lessonId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>
     */
    public function version(int $lessonId, int $versionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_versions WHERE id = ? AND lesson_id = ? LIMIT 1');
        $stmt->execute([$versionId, $lessonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('That version was not found for this lesson.');
        }
        $snap = json_decode((string)$row['snapshot_json'], true);
        if (!is_array($snap)) {
            throw new RuntimeException('That version could not be read.');
        }
        $row['snapshot'] = $snap;
        return $row;
    }

    /**
     * Differences between two snapshots. Content only; no student data is involved.
     *
     * @return array<string,list<mixed>|array<string,float>>
     */
    public static function compare(array $old, array $new): array
    {
        $diff = [
            'settings' => [],
            'objectives_added' => [],
            'objectives_removed' => [],
            'sections_added' => [],
            'sections_removed' => [],
            'items_added' => [],
            'items_removed' => [],
            'items_changed' => [],
            'questions_added' => [],
            'questions_removed' => [],
            'questions_changed' => [],
            'marks' => ['old' => self::totalMarks($old), 'new' => self::totalMarks($new)],
        ];
        foreach (self::LESSON_FIELDS as $field) {
            $a = self::norm($old['lesson'][$field] ?? null);
            $b = self::norm($new['lesson'][$field] ?? null);
            if ($a !== $b) {
                $diff['settings'][] = ['field' => $field, 'old' => $a, 'new' => $b];
            }
        }
        $oldObjectives = array_map(static fn ($o) => trim((string)($o['body'] ?? '')), $old['objectives'] ?? []);
        $newObjectives = array_map(static fn ($o) => trim((string)($o['body'] ?? '')), $new['objectives'] ?? []);
        $diff['objectives_added'] = array_values(array_diff($newObjectives, $oldObjectives));
        $diff['objectives_removed'] = array_values(array_diff($oldObjectives, $newObjectives));
        $oldSections = array_map(static fn ($s) => trim((string)($s['title'] ?? '')), $old['sections'] ?? []);
        $newSections = array_map(static fn ($s) => trim((string)($s['title'] ?? '')), $new['sections'] ?? []);
        $diff['sections_added'] = array_values(array_diff($newSections, $oldSections));
        $diff['sections_removed'] = array_values(array_diff($oldSections, $newSections));

        $oldItems = self::itemsByKey($old);
        $newItems = self::itemsByKey($new);
        foreach ($newItems as $key => $item) {
            if (!isset($oldItems[$key])) {
                $diff['items_added'][] = (string)($item['title'] ?? '');
                continue;
            }
            $changes = [];
            foreach (self::ITEM_FIELDS as $field) {
                if (in_array($field, ['sort_order', 'section_id'], true)) {
                    continue;
                }
                if (self::norm($oldItems[$key][$field] ?? null) !== self::norm($item[$field] ?? null)) {
                    $changes[] = $field;
                }
            }
            foreach (self::ACTIVITY_FIELDS as $field) {
                if (self::norm($oldItems[$key]['activity'][$field] ?? null) !== self::norm($item['activity'][$field] ?? null)) {
                    $changes[] = 'activity ' . $field;
                }
            }
            if ($changes !== []) {
                $diff['items_changed'][] = ['title' => (string)($item['title'] ?? ''), 'fields' => $changes];
            }
        }
        foreach ($oldItems as $key => $item) {
            if (!isset($newItems[$key])) {
                $diff['items_removed'][] = (string)($item['title'] ?? '');
            }
        }

        $oldQuestions = self::questionsByKey($old);
        $newQuestions = self::questionsByKey($new);
        foreach ($newQuestions as $key => $q) {
            if (!isset($oldQuestions[$key])) {
                $diff['questions_added'][] = self::short((string)($q['prompt'] ?? ''));
                continue;
            }
            $changes = [];
            foreach (self::QUESTION_FIELDS as $field) {
                if ($field === 'sort_order') {
                    continue;
                }
                if (self::norm($oldQuestions[$key][$field] ?? null) !== self::norm($q[$field] ?? null)) {
                    $changes[] = $field;
                }
            }
            if (json_encode($oldQuestions[$key]['criteria'] ?? []) !== json_encode($q['criteria'] ?? [])) {
                $changes[] = 'criteria';
            }
            if ($changes !== []) {
                $diff['questions_changed'][] = ['prompt' => self::short((string)($q['prompt'] ?? '')), 'fields' => $changes];
            }
        }
        foreach ($oldQuestions as $key => $q) {
            if (!isset($newQuestions[$key])) {
                $diff['questions_removed'][] = self::short((string)($q['prompt'] ?? ''));
            }
        }
        return $diff;
    }

    public static function isEmptyDiff(array $diff): bool
    {
        foreach ($diff as $key => $value) {
            if ($key === 'marks') {
                if (abs((float)$value['old'] - (float)$value['new']) > 0.0001) {
                    return false;
                }
                continue;
            }
            if ($value !== []) {
                return false;
            }
        }
        return true;
    }

    /**
     * Restores a version by writing its content back and saving the result as a new version.
     * Items and questions that students already used are never deleted; they are kept and reported.
     *
     * @return array{version_id:int,kept:list<string>}
     */
    public function restore(int $lessonId, int $versionId, int $userId): array
    {
        $version = $this->version($lessonId, $versionId);
        $snap = $version['snapshot'];
        $this->createVersion($lessonId, $userId, 'before_restore');
        $kept = [];
        $this->pdo->beginTransaction();
        try {
            $this->updateRow('online_lessons', $lessonId, self::pick($snap['lesson'] ?? [], self::LESSON_FIELDS));
            $sectionMap = $this->reconcileFlat('online_lesson_sections', $lessonId, $snap['sections'] ?? [], self::SECTION_FIELDS);
            $objectiveMap = $this->reconcileFlat('online_lesson_objectives', $lessonId, $snap['objectives'] ?? [], self::OBJECTIVE_FIELDS);

            $current = [];
            foreach ($this->rows('SELECT * FROM online_lesson_items WHERE lesson_id = ?', [$lessonId]) as $row) {
                $current[(int)$row['id']] = $row;
            }
            $itemMap = [];
            $questionMap = [];
            foreach (($snap['items'] ?? []) as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $existing = $current[(int)($s['id'] ?? 0)] ?? null;
                if ($existing && (string)$existing['item_type'] !== (string)($s['item_type'] ?? '')) {
                    $existing = null;
                }
                $fields = self::pick($s, self::ITEM_FIELDS);
                $fields['section_id'] = isset($sectionMap[(int)($s['section_id'] ?? 0)]) ? $sectionMap[(int)$s['section_id']] : null;
                if (is_array($s['activity'] ?? null)) {
                    $fields['activity_id'] = $this->restoreActivity($lessonId, $existing ? (int)($existing['activity_id'] ?? 0) : 0, $s['activity'], $questionMap, $kept);
                }
                if ($existing) {
                    $this->updateRow('online_lesson_items', (int)$existing['id'], $fields);
                    $itemMap[(int)$s['id']] = (int)$existing['id'];
                } else {
                    $itemMap[(int)($s['id'] ?? 0)] = $this->insertRow('online_lesson_items', ['lesson_id' => $lessonId] + $fields);
                }
            }
            $keepIds = array_flip(array_values($itemMap));
            foreach ($current as $id => $row) {
                if (isset($keepIds[$id])) {
                    continue;
                }
                if ($this->itemHasStudentWork($id)) {
                    $kept[] = (string)$row['title'] . ' (students already used it)';
                    continue;
                }
                $this->deleteItemContent($row);
            }
            $this->relinkObjectives(array_values($objectiveMap), $snap['objective_links'] ?? [], $objectiveMap, $questionMap, $itemMap);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $newId = $this->createVersion($lessonId, $userId, 'restored', $versionId, true);
        return ['version_id' => $newId, 'kept' => $kept];
    }

    /**
     * Copies the design of a lesson into another class's lesson and leaves the target as a DRAFT.
     * The target's own recording clips fill the video positions in order. The source recording,
     * progress, attempts, answers, marks and submissions are never copied.
     *
     * @param list<int> $targetAssetIds clips of the target class recording, in order
     * @return int number of items created
     */
    public function copyInto(int $sourceLessonId, int $targetLessonId, int $userId, array $targetAssetIds = []): int
    {
        if ($sourceLessonId === $targetLessonId) {
            throw new RuntimeException('Choose a different class to copy into.');
        }
        $targetItems = $this->rows('SELECT * FROM online_lesson_items WHERE lesson_id = ?', [$targetLessonId]);
        foreach ($targetItems as $row) {
            if ($this->itemHasStudentWork((int)$row['id'])) {
                throw new RuntimeException('That class already has student progress. Copy to a date nobody has started.');
            }
        }
        $snap = $this->snapshot($sourceLessonId);
        if ($snap['items'] === []) {
            throw new RuntimeException('This lesson has nothing to copy yet.');
        }
        $created = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($targetItems as $row) {
                $this->deleteItemContent($row);
            }
            $this->pdo->prepare('DELETE FROM online_lesson_sections WHERE lesson_id = ?')->execute([$targetLessonId]);
            foreach ($this->rows('SELECT id FROM online_lesson_objectives WHERE lesson_id = ?', [$targetLessonId]) as $o) {
                $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE objective_id = ?')->execute([(int)$o['id']]);
            }
            $this->pdo->prepare('DELETE FROM online_lesson_objectives WHERE lesson_id = ?')->execute([$targetLessonId]);

            $copied = self::pick($snap['lesson'], self::LESSON_FIELDS);
            unset($copied['title']);
            $this->updateRow('online_lessons', $targetLessonId, $copied + ['published' => 0]);
            $sectionMap = [];
            foreach ($snap['sections'] as $s) {
                $sectionMap[(int)$s['id']] = $this->insertRow('online_lesson_sections', ['lesson_id' => $targetLessonId] + self::pick($s, self::SECTION_FIELDS));
            }
            $objectiveMap = [];
            foreach ($snap['objectives'] as $o) {
                $objectiveMap[(int)$o['id']] = $this->insertRow('online_lesson_objectives', ['lesson_id' => $targetLessonId] + self::pick($o, self::OBJECTIVE_FIELDS));
            }
            $itemMap = [];
            $questionMap = [];
            $ignored = [];
            $assets = array_values(array_map('intval', $targetAssetIds));
            $videoIndex = 0;
            $sort = 0;
            foreach ($snap['items'] as $s) {
                $sort++;
                $fields = self::pick($s, self::ITEM_FIELDS);
                $fields['sort_order'] = $sort;
                $fields['section_id'] = $sectionMap[(int)($s['section_id'] ?? 0)] ?? null;
                if ((string)($s['item_type'] ?? '') === 'video') {
                    $assetId = $assets[$videoIndex] ?? 0;
                    $videoIndex++;
                    if ($assetId < 1) {
                        continue;
                    }
                    $fields['video_asset_id'] = $assetId;
                } else {
                    $fields['video_asset_id'] = null;
                }
                if (is_array($s['activity'] ?? null)) {
                    $fields['activity_id'] = $this->restoreActivity($targetLessonId, 0, $s['activity'], $questionMap, $ignored);
                }
                $itemMap[(int)$s['id']] = $this->insertRow('online_lesson_items', ['lesson_id' => $targetLessonId] + $fields);
                $created++;
            }
            while ($videoIndex < count($assets)) {
                $sort++;
                $this->insertRow('online_lesson_items', [
                    'lesson_id' => $targetLessonId,
                    'item_type' => 'video',
                    'title' => 'Clip ' . ($videoIndex + 1),
                    'sort_order' => $sort,
                    'video_asset_id' => $assets[$videoIndex],
                    'required' => 1,
                ]);
                $videoIndex++;
            }
            $this->relinkObjectives([], $snap['objective_links'], $objectiveMap, $questionMap, $itemMap);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $this->createVersion($targetLessonId, $userId, 'copied', null, true);
        return $created;
    }

    public function itemHasStudentWork(int $itemId): bool
    {
        foreach (['online_lesson_attempts', 'online_lesson_submissions', 'online_lesson_item_state'] as $table) {
            if ($this->scalar('SELECT 1 FROM ' . $table . ' WHERE item_id = ? LIMIT 1', [$itemId])) {
                return true;
            }
        }
        return false;
    }

    public function questionHasAnswers(int $questionId): bool
    {
        return (bool)$this->scalar('SELECT 1 FROM online_lesson_answers WHERE question_id = ? LIMIT 1', [$questionId]);
    }

    /**
     * @param array<int,int> $questionMap snapshot question id => live id (filled in)
     * @param list<string> $kept
     */
    private function restoreActivity(int $lessonId, int $currentActivityId, array $s, array &$questionMap, array &$kept): int
    {
        $fields = self::pick($s, self::ACTIVITY_FIELDS);
        $activityId = 0;
        if ($currentActivityId > 0 && $this->scalar('SELECT id FROM online_lesson_activities WHERE id = ? AND lesson_id = ?', [$currentActivityId, $lessonId])) {
            $activityId = $currentActivityId;
            $this->updateRow('online_lesson_activities', $activityId, $fields);
        } else {
            $activityId = $this->insertRow('online_lesson_activities', ['lesson_id' => $lessonId] + $fields);
        }
        $current = [];
        foreach ($this->rows('SELECT id, prompt FROM online_lesson_questions WHERE activity_id = ?', [$activityId]) as $row) {
            $current[(int)$row['id']] = $row;
        }
        $keep = [];
        foreach (($s['questions'] ?? []) as $q) {
            if (!is_array($q)) {
                continue;
            }
            $qFields = self::pick($q, self::QUESTION_FIELDS);
            $sid = (int)($q['id'] ?? 0);
            if ($sid > 0 && isset($current[$sid])) {
                $this->updateRow('online_lesson_questions', $sid, $qFields);
                $liveId = $sid;
            } else {
                $liveId = $this->insertRow('online_lesson_questions', ['activity_id' => $activityId] + $qFields);
            }
            $keep[$liveId] = true;
            $questionMap[$sid] = $liveId;
            $this->pdo->prepare('DELETE FROM online_lesson_criteria WHERE question_id = ?')->execute([$liveId]);
            foreach (($q['criteria'] ?? []) as $c) {
                $this->insertRow('online_lesson_criteria', [
                    'question_id' => $liveId,
                    'label' => mb_substr((string)($c['label'] ?? ''), 0, 200),
                    'marks' => (float)($c['marks'] ?? 1),
                    'sort_order' => (int)($c['sort_order'] ?? 0),
                ]);
            }
        }
        foreach ($current as $id => $row) {
            if (isset($keep[$id])) {
                continue;
            }
            if ($this->questionHasAnswers($id)) {
                $kept[] = 'Question “' . self::short((string)$row['prompt']) . '” (students already answered it)';
                continue;
            }
            $this->pdo->prepare('DELETE FROM online_lesson_criteria WHERE question_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE question_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM online_lesson_questions WHERE id = ?')->execute([$id]);
        }
        return $activityId;
    }

    /**
     * @param list<array<string,mixed>> $snapRows
     * @param list<string> $fields
     * @return array<int,int> snapshot id => live id
     */
    private function reconcileFlat(string $table, int $lessonId, array $snapRows, array $fields): array
    {
        $current = [];
        foreach ($this->rows('SELECT id FROM ' . $table . ' WHERE lesson_id = ?', [$lessonId]) as $row) {
            $current[(int)$row['id']] = true;
        }
        $map = [];
        foreach ($snapRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sid = (int)($row['id'] ?? 0);
            $values = self::pick($row, $fields);
            if ($sid > 0 && isset($current[$sid])) {
                $this->updateRow($table, $sid, $values);
                $map[$sid] = $sid;
            } else {
                $map[$sid] = $this->insertRow($table, ['lesson_id' => $lessonId] + $values);
            }
        }
        $live = array_flip(array_values($map));
        foreach (array_keys($current) as $id) {
            if (isset($live[$id])) {
                continue;
            }
            if ($table === 'online_lesson_sections') {
                $this->pdo->prepare('UPDATE online_lesson_items SET section_id = NULL WHERE lesson_id = ? AND section_id = ?')->execute([$lessonId, $id]);
            } else {
                $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE objective_id = ?')->execute([$id]);
            }
            $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE id = ? AND lesson_id = ?')->execute([$id, $lessonId]);
        }
        return $map;
    }

    /**
     * @param list<int> $liveObjectiveIds links for these objectives are replaced
     */
    private function relinkObjectives(array $liveObjectiveIds, array $links, array $objectiveMap, array $questionMap, array $itemMap): void
    {
        if ($liveObjectiveIds !== []) {
            $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE objective_id IN (' . self::marks($liveObjectiveIds) . ')')
                ->execute(array_values($liveObjectiveIds));
        }
        $seen = [];
        foreach ($links as $link) {
            $objective = $objectiveMap[(int)($link['objective_id'] ?? 0)] ?? 0;
            $question = (int)($link['question_id'] ?? 0) > 0 ? ($questionMap[(int)$link['question_id']] ?? 0) : 0;
            $item = (int)($link['item_id'] ?? 0) > 0 ? ($itemMap[(int)$link['item_id']] ?? 0) : 0;
            if ($objective < 1 || ($question < 1 && $item < 1)) {
                continue;
            }
            $key = $objective . ':' . $question . ':' . $item;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->insertRow('online_lesson_objective_links', ['objective_id' => $objective, 'question_id' => $question, 'item_id' => $item]);
        }
    }

    private function deleteItemContent(array $item): void
    {
        $activityId = (int)($item['activity_id'] ?? 0);
        $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE item_id = ?')->execute([(int)$item['id']]);
        $this->pdo->prepare('DELETE FROM online_lesson_items WHERE id = ?')->execute([(int)$item['id']]);
        if ($activityId > 0 && !$this->scalar('SELECT 1 FROM online_lesson_items WHERE activity_id = ? LIMIT 1', [$activityId])) {
            foreach ($this->rows('SELECT id FROM online_lesson_questions WHERE activity_id = ?', [$activityId]) as $q) {
                if ($this->questionHasAnswers((int)$q['id'])) {
                    return;
                }
            }
            $ids = array_map(static fn (array $q): int => (int)$q['id'], $this->rows('SELECT id FROM online_lesson_questions WHERE activity_id = ?', [$activityId]));
            if ($ids !== []) {
                $this->pdo->prepare('DELETE FROM online_lesson_criteria WHERE question_id IN (' . self::marks($ids) . ')')->execute($ids);
                $this->pdo->prepare('DELETE FROM online_lesson_objective_links WHERE question_id IN (' . self::marks($ids) . ')')->execute($ids);
            }
            $this->pdo->prepare('DELETE FROM online_lesson_questions WHERE activity_id = ?')->execute([$activityId]);
            $this->pdo->prepare('DELETE FROM online_lesson_activities WHERE id = ?')->execute([$activityId]);
        }
    }

    /**
     * @param array<string,mixed> $values
     */
    private function updateRow(string $table, int $id, array $values): void
    {
        $values = $this->onlyColumns($table, $values);
        if ($values === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = ?', array_keys($values)));
        $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $sets . ' WHERE id = ?')->execute([...array_values($values), $id]);
    }

    /**
     * @param array<string,mixed> $values
     */
    private function insertRow(string $table, array $values): int
    {
        $values = $this->onlyColumns($table, $values);
        $cols = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', array_keys($values)));
        $this->pdo->prepare('INSERT INTO ' . $table . ' (' . $cols . ') VALUES (' . self::marks(array_values($values)) . ')')
            ->execute(array_values($values));
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function onlyColumns(string $table, array $values): array
    {
        if (!isset($this->columns[$table])) {
            $cols = [];
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cols[] = (string)$row['name'];
                }
            } else {
                foreach ($this->pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cols[] = (string)$row['Field'];
                }
            }
            $this->columns[$table] = $cols;
        }
        return array_intersect_key($values, array_flip($this->columns[$table]));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function scalar(string $sql, array $params): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $fields
     * @return array<string,mixed>
     */
    private static function pick(array $row, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $out[$field] = $row[$field] === null ? null : (is_bool($row[$field]) ? (int)$row[$field] : (string)$row[$field]);
            }
        }
        return $out;
    }

    private static function marks(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }

    private static function norm(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = trim((string)$value);
        if (is_numeric($text)) {
            return (string)(0 + $text);
        }
        return $text;
    }

    private static function short(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        return mb_strlen($text) > 80 ? mb_substr($text, 0, 79) . '…' : $text;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function itemsByKey(array $snap): array
    {
        $out = [];
        foreach (($snap['items'] ?? []) as $item) {
            $key = (string)($item['id'] ?? '');
            $out[$key !== '' ? 'i' . $key : 't' . ($item['title'] ?? '')] = $item;
        }
        return $out;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function questionsByKey(array $snap): array
    {
        $out = [];
        foreach (($snap['items'] ?? []) as $item) {
            foreach (($item['activity']['questions'] ?? []) as $q) {
                $out['q' . (int)($q['id'] ?? 0)] = $q;
            }
        }
        return $out;
    }

    private static function totalMarks(array $snap): float
    {
        $sum = 0.0;
        foreach (($snap['items'] ?? []) as $item) {
            foreach (($item['activity']['questions'] ?? []) as $q) {
                $sum += (float)($q['marks'] ?? 0);
            }
        }
        return round($sum, 2);
    }
}
