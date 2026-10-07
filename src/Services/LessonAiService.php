<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * AI-assisted lesson planning and question drafting.
 * Output is stored as a draft for the teacher to review. Nothing is published automatically,
 * and applied questions stay flagged "AI generated" (hidden from students) until the teacher saves them.
 */
final class LessonAiService
{
    private const SYSTEM_RULES = 'You help a school teacher draft lesson material. '
        . 'Write original content only. Never say or imply that a question is an official exam, past-paper, or exam-board question, '
        . 'and never cite paper codes, years, or exam boards. Keep language clear for secondary-school students. '
        . 'Reply with JSON only, no commentary.';

    /** @var callable(string,string,int):?string */
    private $completer;

    public function __construct(private PDO $pdo, ?callable $completer = null)
    {
        $this->completer = $completer ?? static function (string $system, string $user, int $maxTokens) use ($pdo): ?string {
            return (new WhatsAppAiService($pdo))->completeText($system, $user, [], $maxTokens);
        };
    }

    public function available(): bool
    {
        try {
            return (new WhatsAppAiService($this->pdo))->providerName() !== 'none';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array{topic:string,objectives?:string,minutes?:int,level?:string} $input
     */
    public function draftPlan(int $lessonId, int $userId, array $input): int
    {
        $topic = mb_substr(trim((string)($input['topic'] ?? '')), 0, 200);
        if ($topic === '') {
            throw new RuntimeException('Enter the lesson topic.');
        }
        $minutes = max(10, min(240, (int)($input['minutes'] ?? 60)));
        $objectives = mb_substr(trim((string)($input['objectives'] ?? '')), 0, 1500);
        $level = mb_substr(trim((string)($input['level'] ?? '')), 0, 80);
        $prompt = "Plan a {$minutes}-minute lesson on: {$topic}.\n"
            . ($level !== '' ? "Level: {$level}.\n" : '')
            . ($objectives !== '' ? "Teacher's objectives:\n{$objectives}\n" : '')
            . "Return JSON: {\"objectives\":[string], \"sections\":[{\"title\":string,\"minutes\":int,\"items\":[{\"type\":\"page\",\"title\":string,\"body\":string} | "
            . "{\"type\":\"mcq\",\"title\":string,\"questions\":[{\"prompt\":string,\"choices\":[4 strings],\"correct_index\":0-3,\"explanation\":string,\"difficulty\":\"easy|medium|hard\"}]}]}]}. "
            . 'Use 3 to 5 sections, section minutes must add up to about ' . $minutes . '. At most 5 questions per quiz.';
        $raw = $this->complete($prompt, 1800);
        $plan = self::normalizePlan(self::decodeJson($raw));
        if ($plan['sections'] === []) {
            throw new RuntimeException('The AI reply could not be used. Try again or change the topic.');
        }
        return $this->storeDraft($lessonId, 0, $userId, 'plan', ['topic' => $topic, 'minutes' => $minutes, 'objectives' => $objectives, 'level' => $level], $plan);
    }

    /**
     * @param array{topic:string,count?:int,difficulty?:string,type?:string} $input
     */
    public function draftQuestions(int $lessonId, int $activityId, int $userId, array $input): int
    {
        $topic = mb_substr(trim((string)($input['topic'] ?? '')), 0, 200);
        if ($topic === '') {
            throw new RuntimeException('Enter what the questions should cover.');
        }
        $count = max(1, min(10, (int)($input['count'] ?? 5)));
        $difficulty = QuestionPool::normalizeDifficulty((string)($input['difficulty'] ?? '')) ?? '';
        $type = in_array((string)($input['type'] ?? 'mcq'), ['mcq', 'short'], true) ? (string)$input['type'] : 'mcq';
        $prompt = "Write {$count} " . ($type === 'mcq' ? 'multiple-choice' : 'short-answer') . " questions on: {$topic}.\n"
            . ($difficulty !== '' ? "Difficulty: {$difficulty}.\n" : '')
            . ($type === 'mcq'
                ? 'Return JSON: {"questions":[{"prompt":string,"choices":[4 strings],"correct_index":0-3,"explanation":string,"difficulty":"easy|medium|hard"}]}'
                : 'Return JSON: {"questions":[{"prompt":string,"expected_answer":string,"marks":1-6,"difficulty":"easy|medium|hard"}]}');
        $raw = $this->complete($prompt, 1500);
        $decoded = self::decodeJson($raw);
        $questions = self::normalizeQuestions(is_array($decoded['questions'] ?? null) ? $decoded['questions'] : [], $type);
        if ($questions === []) {
            throw new RuntimeException('The AI reply could not be used. Try again.');
        }
        return $this->storeDraft($lessonId, $activityId, $userId, 'questions', ['topic' => $topic, 'count' => $count, 'difficulty' => $difficulty, 'type' => $type], ['questions' => $questions]);
    }

    /**
     * @return array<string,mixed>
     */
    public function draft(int $draftId, int $lessonId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_ai_drafts WHERE id = ? AND lesson_id = ? LIMIT 1');
        $stmt->execute([$draftId, $lessonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('That AI draft was not found.');
        }
        $row['output'] = json_decode((string)$row['output_json'], true) ?: [];
        $row['input'] = json_decode((string)$row['input_json'], true) ?: [];
        return $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function drafts(int $lessonId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, activity_id, kind, status, input_json, created_at, user_id
            FROM online_lesson_ai_drafts WHERE lesson_id = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function discard(int $draftId, int $lessonId): void
    {
        $this->pdo->prepare("UPDATE online_lesson_ai_drafts SET status = 'discarded' WHERE id = ? AND lesson_id = ? AND status = 'draft'")
            ->execute([$draftId, $lessonId]);
    }

    /**
     * Creates sections, pages, and quizzes from a plan draft. The lesson must still be a draft with no pages or quizzes.
     */
    public function applyPlan(int $draftId, int $lessonId): int
    {
        $draft = $this->draft($draftId, $lessonId);
        if ((string)$draft['kind'] !== 'plan' || (string)$draft['status'] !== 'draft') {
            throw new RuntimeException('That draft cannot be applied.');
        }
        $lessons = new OnlineLessonService($this->pdo);
        $lesson = $lessons->find($lessonId);
        if (!$lesson) {
            throw new RuntimeException('That lesson was not found.');
        }
        if ((int)$lesson['published'] === 1) {
            throw new RuntimeException('AI drafts are only applied to unpublished lessons. Unpublish the lesson first.');
        }
        foreach ($lessons->items($lessonId) as $item) {
            if (OnlineLessonService::normalizeItemType((string)$item['item_type']) !== 'video') {
                throw new RuntimeException('This lesson already has pages or activities. Apply the AI plan to an empty lesson.');
            }
        }
        $plan = self::normalizePlan($draft['output']);
        $authoring = new LessonAuthoringService($this->pdo, $lessons);
        $modules = new LearningModuleService($this->pdo);
        $created = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($plan['objectives'] as $objective) {
                $modules->addObjective($lessonId, $objective);
            }
            foreach ($plan['sections'] as $section) {
                $sectionId = $authoring->addSection($lessonId, $section['title'], $section['minutes']);
                foreach ($section['items'] as $item) {
                    if ($item['type'] === 'page') {
                        $row = $lessons->addPageAfter($lessonId, 0, $item['title'], $item['body']);
                    } else {
                        $row = $lessons->addActivityAfter($lessonId, 0, 'mcq', $item['title']);
                        foreach ($item['questions'] as $q) {
                            $qid = $lessons->addQuestion((int)$row['activity_id'], 'mcq', $q['prompt'], $q['choices'], $q['correct_index'], 1.0, $q['explanation'], '', $q['difficulty']);
                            $lessons->markQuestionAiGenerated($qid, true);
                        }
                    }
                    $authoring->assignItem($lessonId, (int)$row['id'], $sectionId);
                    $created++;
                }
            }
            $this->pdo->prepare("UPDATE online_lesson_ai_drafts SET status = 'applied' WHERE id = ?")->execute([$draftId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $created;
    }

    /**
     * Adds the chosen draft questions to the activity, flagged as AI generated until the teacher reviews each one.
     *
     * @param list<int> $indexes positions in the draft to add
     */
    public function applyQuestions(int $draftId, int $lessonId, array $indexes): int
    {
        $draft = $this->draft($draftId, $lessonId);
        if ((string)$draft['kind'] !== 'questions' || (string)$draft['status'] !== 'draft') {
            throw new RuntimeException('That draft cannot be applied.');
        }
        $activityId = (int)$draft['activity_id'];
        $lessons = new OnlineLessonService($this->pdo);
        $activity = $lessons->activity($activityId);
        if (!$activity || (int)$activity['lesson_id'] !== $lessonId) {
            throw new RuntimeException('That activity is no longer in this lesson.');
        }
        $type = (string)($draft['input']['type'] ?? 'mcq');
        $questions = self::normalizeQuestions($draft['output']['questions'] ?? [], $type);
        $wanted = array_fill_keys(array_map('intval', $indexes), true);
        $added = 0;
        foreach ($questions as $i => $q) {
            if (!isset($wanted[$i])) {
                continue;
            }
            if ($type === 'mcq') {
                $qid = $lessons->addQuestion($activityId, 'mcq', $q['prompt'], $q['choices'], $q['correct_index'], 1.0, $q['explanation'], '', $q['difficulty']);
            } else {
                $qid = $lessons->addQuestion($activityId, 'short', $q['prompt'], [], null, $q['marks'], '', '', $q['difficulty'], '', $q['expected_answer']);
            }
            $lessons->markQuestionAiGenerated($qid, true);
            $added++;
        }
        if ($added < 1) {
            throw new RuntimeException('Tick at least one question to add.');
        }
        $this->pdo->prepare("UPDATE online_lesson_ai_drafts SET status = 'applied' WHERE id = ?")->execute([$draftId]);
        return $added;
    }

    /**
     * @return array<string,mixed>
     */
    public static function decodeJson(?string $raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            }
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{objectives:list<string>,sections:list<array{title:string,minutes:?int,items:list<array<string,mixed>>}>}
     */
    public static function normalizePlan(array $plan): array
    {
        $objectives = [];
        foreach (array_slice(is_array($plan['objectives'] ?? null) ? $plan['objectives'] : [], 0, 8) as $objective) {
            $text = self::clean((string)(is_scalar($objective) ? $objective : ''), 300);
            if ($text !== '') {
                $objectives[] = $text;
            }
        }
        $sections = [];
        foreach (array_slice(is_array($plan['sections'] ?? null) ? $plan['sections'] : [], 0, 8) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $items = [];
            foreach (array_slice(is_array($section['items'] ?? null) ? $section['items'] : [], 0, 6) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = (string)($item['type'] ?? 'page') === 'mcq' ? 'mcq' : 'page';
                $title = self::clean((string)($item['title'] ?? ''), 200) ?: ($type === 'mcq' ? 'Check understanding' : 'Notes');
                if ($type === 'page') {
                    $items[] = ['type' => 'page', 'title' => $title, 'body' => self::clean((string)($item['body'] ?? ''), 8000)];
                    continue;
                }
                $questions = self::normalizeQuestions(is_array($item['questions'] ?? null) ? array_slice($item['questions'], 0, 5) : [], 'mcq');
                if ($questions !== []) {
                    $items[] = ['type' => 'mcq', 'title' => $title, 'questions' => $questions];
                }
            }
            $title = self::clean((string)($section['title'] ?? ''), 200);
            if ($title === '' && $items === []) {
                continue;
            }
            $minutes = isset($section['minutes']) && is_numeric($section['minutes']) ? max(1, min(240, (int)$section['minutes'])) : null;
            $sections[] = ['title' => $title !== '' ? $title : 'Section', 'minutes' => $minutes, 'items' => $items];
        }
        return ['objectives' => $objectives, 'sections' => $sections];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function normalizeQuestions(array $rows, string $type): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $prompt = self::clean((string)($row['prompt'] ?? ''), 2000);
            if ($prompt === '' || preg_match('/\b(past[\s-]?paper|official (exam|question)|exam board|mark scheme from)\b/i', $prompt)) {
                continue;
            }
            $difficulty = QuestionPool::normalizeDifficulty((string)($row['difficulty'] ?? '')) ?? '';
            if (!isset(QuestionPool::DIFFICULTIES[$difficulty])) {
                $difficulty = '';
            }
            if ($type === 'mcq') {
                $choices = [];
                foreach (array_slice(is_array($row['choices'] ?? null) ? $row['choices'] : [], 0, 6) as $choice) {
                    $text = self::clean((string)(is_scalar($choice) ? $choice : ''), 500);
                    if ($text !== '') {
                        $choices[] = $text;
                    }
                }
                $correct = isset($row['correct_index']) && is_numeric($row['correct_index']) ? (int)$row['correct_index'] : -1;
                if (count($choices) < 2 || $correct < 0 || $correct >= count($choices)) {
                    continue;
                }
                $out[] = [
                    'prompt' => $prompt,
                    'choices' => $choices,
                    'correct_index' => $correct,
                    'explanation' => self::clean((string)($row['explanation'] ?? ''), 2000),
                    'difficulty' => $difficulty,
                ];
                continue;
            }
            $out[] = [
                'prompt' => $prompt,
                'expected_answer' => self::clean((string)($row['expected_answer'] ?? ''), 4000),
                'marks' => max(1.0, min(20.0, (float)($row['marks'] ?? 2))),
                'difficulty' => $difficulty,
            ];
        }
        return $out;
    }

    private function complete(string $prompt, int $maxTokens): string
    {
        $text = ($this->completer)(self::SYSTEM_RULES, $prompt, $maxTokens);
        if ($text === null || trim($text) === '') {
            throw new RuntimeException('The AI service is not available right now. Try again later.');
        }
        return $text;
    }

    private function storeDraft(int $lessonId, int $activityId, int $userId, string $kind, array $input, array $output): int
    {
        $this->pdo->prepare('
            INSERT INTO online_lesson_ai_drafts (lesson_id, activity_id, question_id, user_id, kind, input_json, output_json, status, created_at)
            VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $lessonId,
            $activityId,
            $userId,
            $kind,
            json_encode($input, JSON_UNESCAPED_UNICODE),
            json_encode($output, JSON_UNESCAPED_UNICODE),
            'draft',
            date('Y-m-d H:i:s'),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private static function clean(string $text, int $limit): string
    {
        $text = trim(strip_tags($text));
        return mb_substr($text, 0, $limit);
    }
}
