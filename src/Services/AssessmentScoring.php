<?php
declare(strict_types=1);

namespace Edexcel\Services;

final class AssessmentScoring
{
    public static function autoMark(
        string $type,
        mixed $given,
        mixed $correctIndex,
        array $accepted = [],
        float $marks = 1.0,
        float $tolerance = 0.01
    ): ?array {
        $type = strtolower($type);
        if (in_array($type, ['mcq', 'true_false'], true)) {
            if ($given === null || $given === '' || $correctIndex === null) {
                return ['correct' => false, 'marks' => 0.0, 'automatic' => true];
            }
            $ok = (int)$given === (int)$correctIndex;
            return ['correct' => $ok, 'marks' => $ok ? $marks : 0.0, 'automatic' => true];
        }
        if ($type === 'numeric') {
            if ($given === null || $given === '') {
                return ['correct' => false, 'marks' => 0.0, 'automatic' => true];
            }
            $value = (float)$given;
            foreach ($accepted as $accept) {
                if (is_numeric($accept) && abs($value - (float)$accept) <= $tolerance) {
                    return ['correct' => true, 'marks' => $marks, 'automatic' => true];
                }
            }
            if (is_numeric($correctIndex) && abs($value - (float)$correctIndex) <= $tolerance) {
                return ['correct' => true, 'marks' => $marks, 'automatic' => true];
            }
            return ['correct' => false, 'marks' => 0.0, 'automatic' => true];
        }
        if ($type === 'short' && $accepted !== []) {
            $norm = self::normalizeText((string)$given);
            foreach ($accepted as $accept) {
                if ($norm !== '' && $norm === self::normalizeText((string)$accept)) {
                    return ['correct' => true, 'marks' => $marks, 'automatic' => true];
                }
            }
            return ['correct' => false, 'marks' => 0.0, 'automatic' => true];
        }
        return null;
    }

    public static function nextAdaptiveDifficulty(string $current, bool $correct): string
    {
        $current = in_array($current, ['easy', 'medium', 'hard'], true) ? $current : 'medium';
        if ($correct) {
            return $current === 'easy' ? 'medium' : 'hard';
        }
        return $current === 'hard' ? 'medium' : 'easy';
    }

    public static function observedDifficulty(?float $successRate): string
    {
        if ($successRate === null) {
            return 'medium';
        }
        if ($successRate >= 70) {
            return 'easy';
        }
        if ($successRate <= 40) {
            return 'hard';
        }
        return 'medium';
    }

    public static function percent(?float $score, ?float $max): ?float
    {
        if ($score === null || $max === null || $max <= 0) {
            return null;
        }
        return round(max(0, min(100, $score / $max * 100)), 1);
    }

    public static function passed(?float $percent, float $threshold = 40.0): ?bool
    {
        return $percent === null ? null : $percent >= $threshold;
    }

    public static function trend(array $percents): string
    {
        $vals = array_values(array_filter($percents, static fn($v) => is_numeric($v)));
        if (count($vals) < 2) {
            return 'insufficient';
        }
        $first = (float)$vals[0];
        $last = (float)$vals[count($vals) - 1];
        $delta = $last - $first;
        if ($delta >= 8) {
            return 'improving';
        }
        if ($delta <= -8) {
            return 'declining';
        }
        $spread = max($vals) - min($vals);
        return $spread <= 8 ? 'consistent' : 'mixed';
    }

    public static function readiness(array $parts): ?float
    {
        $vals = [];
        foreach ($parts as $v) {
            if ($v !== null && is_numeric($v)) {
                $vals[] = max(0.0, min(100.0, (float)$v));
            }
        }
        if ($vals === []) {
            return null;
        }
        return round(array_sum($vals) / count($vals), 1);
    }

    public static function predictiveWording(string $trend, array $weakTopics): string
    {
        $topics = array_slice(array_values(array_filter($weakTopics)), 0, 3);
        $focus = $topics ? implode(', ', $topics) : 'recent weak topics';
        return match ($trend) {
            'improving' => 'Current performance indicates improvement. Additional support in '.$focus.' may still help.',
            'declining' => 'Current performance indicates the student may benefit from additional support in '.$focus.'.',
            'consistent' => 'Performance is stable. Targeted practice in '.$focus.' may help the next assessment.',
            default => 'There is not enough comparable assessment history to describe a trend. Review '.$focus.'.',
        };
    }

    public static function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return $text;
    }

    public static function shuffleSeeded(array $items, int $seed): array
    {
        if ($items === []) {
            return [];
        }
        mt_srand($seed);
        $keys = array_keys($items);
        shuffle($keys);
        $out = [];
        foreach ($keys as $k) {
            $out[] = $items[$k];
        }
        mt_srand();
        return $out;
    }
}
