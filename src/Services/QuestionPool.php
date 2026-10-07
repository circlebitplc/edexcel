<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Pure rules for question pools, randomised draws, and attempt scoring.
 * The questions themselves stay in online_lesson_questions.
 */
final class QuestionPool
{
    public const SCORING_RULES = ['latest', 'highest', 'average'];
    public const DIFFICULTIES = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];
    public const SUGGESTED_TAGS = ['CPU', 'Memory', 'Storage', 'Networking', 'Security'];
    public const TIME_GRACE_SECONDS = 30;

    /**
     * An activity keeps a per-attempt record of its questions when it draws from a pool or has a time limit.
     *
     * @param array<string,mixed> $activity
     */
    public static function usesSession(array $activity): bool
    {
        return (int)($activity['draw_count'] ?? 0) > 0 || (int)($activity['time_limit_minutes'] ?? 0) > 0;
    }

    /**
     * Question ids one student sees in one attempt, in display order.
     * The same student, activity, and attempt number always give the same result.
     *
     * @param list<int> $orderedIds question ids in teacher sort order
     * @return list<int>
     */
    public static function drawQuestionIds(array $orderedIds, int $drawCount, int $studentId, int $activityId, int $attemptNo, bool $shuffle): array
    {
        $orderedIds = array_values(array_map('intval', $orderedIds));
        $selected = $orderedIds;
        if ($drawCount > 0 && $drawCount < count($orderedIds)) {
            $byId = $orderedIds;
            sort($byId);
            $order = OnlineLessonService::permutation(count($byId), 'pool:' . $studentId . ':' . $activityId . ':' . $attemptNo);
            $picked = [];
            foreach (array_slice($order, 0, $drawCount) as $index) {
                $picked[$byId[$index]] = true;
            }
            $selected = array_values(array_filter($orderedIds, static fn (int $id): bool => isset($picked[$id])));
        }
        if ($shuffle && count($selected) > 1) {
            $order = OnlineLessonService::permutation(count($selected), 'qo:' . $studentId . ':' . $activityId . ':' . $attemptNo);
            $shuffled = [];
            foreach ($order as $index) {
                $shuffled[] = $selected[$index];
            }
            $selected = $shuffled;
        }
        return $selected;
    }

    /**
     * @return list<int>|null null when the attempt did not store its questions
     */
    public static function decodeIds(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $ids = [];
        foreach ($decoded as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Keeps only the questions listed, in the listed order.
     *
     * @param list<array<string,mixed>> $questions
     * @param list<int>|null $ids
     * @return list<array<string,mixed>>
     */
    public static function orderQuestions(array $questions, ?array $ids): array
    {
        if ($ids === null) {
            return $questions;
        }
        $byId = [];
        foreach ($questions as $question) {
            $byId[(int)$question['id']] = $question;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }
        return $out;
    }

    public static function normalizeRule(?string $rule): ?string
    {
        $rule = strtolower(trim((string)$rule));
        return in_array($rule, ['highest', 'average'], true) ? $rule : null;
    }

    public static function ruleLabel(?string $rule): string
    {
        return match (self::normalizeRule($rule)) {
            'highest' => 'Highest score',
            'average' => 'Average score',
            default => 'Latest score',
        };
    }

    /**
     * Picks the attempt that counts for results.
     * No rule means the latest attempt, which is the behaviour lessons had before scoring rules existed.
     * The average rule uses the latest attempt for question detail and adds avg_score / avg_max.
     *
     * @param list<array<string,mixed>> $attempts every attempt by one student on one item
     * @return array<string,mixed>|null
     */
    public static function chooseAttempt(array $attempts, ?string $rule): ?array
    {
        if ($attempts === []) {
            return null;
        }
        usort($attempts, static function (array $a, array $b): int {
            $cmp = (int)$a['attempt_no'] <=> (int)$b['attempt_no'];
            return $cmp !== 0 ? $cmp : ((int)$a['id'] <=> (int)$b['id']);
        });
        $latest = $attempts[count($attempts) - 1];
        $rule = self::normalizeRule($rule);
        if ($rule === null || count($attempts) === 1) {
            return $latest;
        }
        if ($rule === 'highest') {
            $best = null;
            $bestRatio = -1.0;
            foreach ($attempts as $attempt) {
                $max = (float)($attempt['max_score'] ?? 0);
                $ratio = $max > 0 ? (float)($attempt['score'] ?? 0) / $max : 0.0;
                if ($ratio >= $bestRatio) {
                    $bestRatio = $ratio;
                    $best = $attempt;
                }
            }
            return $best ?? $latest;
        }
        $sum = 0.0;
        $sumMax = 0.0;
        $n = 0;
        foreach ($attempts as $attempt) {
            if ($attempt['score'] === null || $attempt['max_score'] === null) {
                continue;
            }
            $sum += (float)$attempt['score'];
            $sumMax += (float)$attempt['max_score'];
            $n++;
        }
        if ($n > 0) {
            $latest['avg_score'] = round($sum / $n, 2);
            $latest['avg_max'] = round($sumMax / $n, 2);
            $latest['avg_count'] = $n;
        }
        return $latest;
    }

    /**
     * Marks shown for a pool activity in class-level totals. Each student's own maximum comes from their attempt.
     *
     * @param list<array<string,mixed>> $questions
     */
    public static function poolMax(array $questions, int $drawCount): float
    {
        if ($questions === []) {
            return 0.0;
        }
        $marks = array_map(static fn (array $q): float => max(0, (float)($q['marks'] ?? 0)), $questions);
        if ($drawCount < 1 || $drawCount >= count($marks)) {
            return array_sum($marks);
        }
        return round(array_sum($marks) / count($marks) * $drawCount, 2);
    }

    public static function normalizeTags(string $raw): ?string
    {
        $seen = [];
        foreach (preg_split('/[,;\n]+/', $raw) ?: [] as $part) {
            $tag = trim(preg_replace('/\s+/', ' ', $part) ?? '');
            if ($tag === '') {
                continue;
            }
            $tag = mb_substr($tag, 0, 40);
            $key = mb_strtolower($tag);
            if (!isset($seen[$key])) {
                $seen[$key] = $tag;
            }
        }
        if ($seen === []) {
            return null;
        }
        $joined = implode(',', array_values($seen));
        return mb_substr($joined, 0, 255);
    }

    /**
     * @return list<string>
     */
    public static function tagList(?string $stored): array
    {
        $normalized = self::normalizeTags((string)$stored);
        return $normalized === null ? [] : explode(',', $normalized);
    }

    public static function normalizeDifficulty(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }
        return array_key_exists($value, self::DIFFICULTIES) ? $value : mb_substr(trim($raw), 0, 40);
    }

    /**
     * @return array{limit:int,deadline:int,remaining:int,expired:bool}
     */
    public static function timeState(?string $startedAt, int $limitMinutes, ?int $now = null): array
    {
        $now = $now ?? time();
        if ($limitMinutes < 1 || $startedAt === null || $startedAt === '') {
            return ['limit' => 0, 'deadline' => 0, 'remaining' => 0, 'expired' => false];
        }
        $start = strtotime($startedAt) ?: $now;
        $deadline = $start + $limitMinutes * 60;
        return [
            'limit' => $limitMinutes,
            'deadline' => $deadline,
            'remaining' => max(0, $deadline - $now),
            'expired' => $now > $deadline,
        ];
    }

    public static function isOverTime(?string $startedAt, int $limitMinutes, ?int $now = null): bool
    {
        $state = self::timeState($startedAt, $limitMinutes, $now);
        return $state['limit'] > 0 && ($now ?? time()) > $state['deadline'] + self::TIME_GRACE_SECONDS;
    }
}
