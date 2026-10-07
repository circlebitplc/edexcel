<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Turns stored lesson questions, attempts, and answers into marks.
 * Unmarked essays and activities the student has not submitted are left out
 * of the percentage. They are not scored as zero.
 */
final class LessonResultBuilder
{
    public static function formatMark(float $value): string
    {
        $rounded = round($value, 2);
        if (abs($rounded - round($rounded)) < 0.001) {
            return (string)(int)round($rounded);
        }
        $text = number_format($rounded, 2, '.', '');
        return rtrim(rtrim($text, '0'), '.');
    }

    public static function formatPercent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }
        return self::formatMark($value) . '%';
    }

    public static function choiceLetter(?int $index): string
    {
        if ($index === null || $index < 0 || $index > 25) {
            return '—';
        }
        return chr(65 + $index);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $questions
     * @param list<array<string,mixed>> $students
     * @param list<array<string,mixed>> $attempts latest attempt per student and item
     * @param list<array<string,mixed>> $answers
     * @param list<array<string,mixed>> $states
     * @param array<string,int> $attemptCounts key "student:item"
     * @return array<string,mixed>
     */
    public static function analyse(
        array $items,
        array $questions,
        array $students,
        array $attempts,
        array $answers,
        array $states,
        array $attemptCounts,
        ?int $passPercent,
        array $submissions = []
    ): array {
        if ($passPercent !== null && ($passPercent < 1 || $passPercent > 100)) {
            $passPercent = null;
        }

        $questionsByActivity = [];
        foreach ($questions as $question) {
            if ((int)($question['ai_generated'] ?? 0) === 1) {
                continue;
            }
            $questionsByActivity[(int)$question['activity_id']][] = $question;
        }
        $attemptByStudentItem = [];
        foreach ($attempts as $attempt) {
            $attemptByStudentItem[(int)$attempt['student_id']][(int)$attempt['item_id']] = $attempt;
        }
        $answerByAttempt = [];
        foreach ($answers as $answer) {
            $answerByAttempt[(int)$answer['attempt_id']][(int)$answer['question_id']] = $answer;
        }
        $stateByStudentItem = [];
        foreach ($states as $state) {
            $stateByStudentItem[(int)$state['student_id']][(int)$state['item_id']] = $state;
        }

        $lessonMax = 0.0;
        $lessonMcqMax = 0.0;
        $lessonEssayMax = 0.0;
        $lessonShortMax = 0.0;
        $lessonExamMax = 0.0;
        $lessonAssignmentMax = 0.0;
        $schemes = [];
        foreach ($items as $item) {
            $activityId = (int)($item['activity_id'] ?? 0);
            $type = OnlineLessonService::normalizeItemType((string)($item['item_type'] ?? ''));
            $activityType = strtolower(trim((string)($item['activity_type'] ?? '')));
            if (OnlineLessonService::isSubmissionActivity($activityType)) {
                $max = max(0, (float)($item['max_marks'] ?? 0));
                $lessonMax += $max;
                $lessonAssignmentMax += $max;
                $schemes[(int)$item['id']] = [
                    'item' => $item,
                    'questions' => [],
                    'max' => $max,
                    'mcq_max' => 0.0,
                    'essay_max' => 0.0,
                    'short_max' => 0.0,
                    'exam_max' => 0.0,
                    'mcq_count' => 0,
                    'essay_count' => 0,
                    'academic' => $max > 0,
                    'submission' => true,
                ];
                continue;
            }
            $qs = $type === 'activity' ? ($questionsByActivity[$activityId] ?? []) : [];
            $mcqMax = 0.0;
            $essayMax = 0.0;
            $shortMax = 0.0;
            $examMax = 0.0;
            $mcqCount = 0;
            $essayCount = 0;
            foreach ($qs as $question) {
                $marks = max(0, (float)($question['marks'] ?? 0));
                $bucket = self::markBucket((string)($question['question_type'] ?? 'mcq'));
                if ($bucket === 'essay') {
                    $essayMax += $marks;
                    $essayCount++;
                } elseif ($bucket === 'short') {
                    $shortMax += $marks;
                } elseif ($bucket === 'exam') {
                    $examMax += $marks;
                } else {
                    $mcqMax += $marks;
                    $mcqCount++;
                }
            }
            $max = $mcqMax + $essayMax + $shortMax + $examMax;
            $drawCount = (int)($item['draw_count'] ?? 0);
            $pool = $drawCount > 0 && $drawCount < count($qs);
            if ($pool) {
                $max = QuestionPool::poolMax($qs, $drawCount);
                $ratio = ($mcqMax + $essayMax + $shortMax + $examMax) > 0 ? $max / ($mcqMax + $essayMax + $shortMax + $examMax) : 0;
                $mcqMax *= $ratio;
                $essayMax *= $ratio;
                $shortMax *= $ratio;
                $examMax *= $ratio;
            }
            $lessonMax += $max;
            $lessonMcqMax += $mcqMax;
            $lessonEssayMax += $essayMax;
            $lessonShortMax += $shortMax;
            $lessonExamMax += $examMax;
            $schemes[(int)$item['id']] = [
                'item' => $item,
                'questions' => $qs,
                'max' => $max,
                'mcq_max' => $mcqMax,
                'essay_max' => $essayMax,
                'short_max' => $shortMax,
                'exam_max' => $examMax,
                'mcq_count' => $mcqCount,
                'essay_count' => $essayCount,
                'academic' => $max > 0,
                'submission' => false,
                'pool' => $pool,
                'draw_count' => $pool ? $drawCount : 0,
            ];
        }

        $builtStudents = [];
        foreach ($students as $student) {
            $builtStudents[] = self::studentResult(
                $student,
                $schemes,
                $attemptByStudentItem[(int)$student['id']] ?? [],
                $answerByAttempt,
                $stateByStudentItem[(int)$student['id']] ?? [],
                $attemptCounts,
                $lessonMax,
                $passPercent,
                $submissions[(int)$student['id']] ?? []
            );
        }

        $activities = [];
        foreach ($items as $item) {
            $activities[] = self::activityResult(
                $schemes[(int)$item['id']],
                $builtStudents,
                count($students)
            );
        }

        return [
            'lesson_max' => $lessonMax,
            'mcq_max' => $lessonMcqMax,
            'essay_max' => $lessonEssayMax,
            'short_max' => $lessonShortMax,
            'exam_max' => $lessonExamMax,
            'assignment_max' => $lessonAssignmentMax,
            'pass_percent' => $passPercent,
            'summary' => self::classSummary($builtStudents, $lessonMax, $passPercent),
            'students' => $builtStudents,
            'activities' => $activities,
        ];
    }

    /**
     * @param array<string,mixed> $student
     * @param array<int,array<string,mixed>> $schemes
     * @param array<int,array<string,mixed>> $attempts
     * @param array<int,array<int,array<string,mixed>>> $answerByAttempt
     * @param array<int,array<string,mixed>> $states
     * @param array<string,int> $attemptCounts
     * @return array<string,mixed>
     */
    private static function studentResult(
        array $student,
        array $schemes,
        array $attempts,
        array $answerByAttempt,
        array $states,
        array $attemptCounts,
        float $lessonMax,
        ?int $passPercent,
        array $submissions = []
    ): array {
        $sid = (int)$student['id'];
        $obtained = 0.0;
        $counted = 0.0;
        $mcqGot = 0.0;
        $mcqCounted = 0.0;
        $essayGot = 0.0;
        $essayCounted = 0.0;
        $correct = 0;
        $incorrect = 0;
        $mcqAttempts = 0;
        $hasMcqSubmission = false;
        $essayPending = false;
        $essaySubmitted = false;
        $shortGot = 0.0;
        $shortCounted = 0.0;
        $shortPending = false;
        $shortSubmitted = false;
        $examGot = 0.0;
        $examCounted = 0.0;
        $examPending = false;
        $examSubmitted = false;
        $assignmentGot = 0.0;
        $assignmentCounted = 0.0;
        $assignmentPending = false;
        $assignmentSubmitted = false;
        $markable = 0;
        $submittedMarkable = 0;
        $activities = [];

        foreach ($schemes as $itemId => $scheme) {
            $item = $scheme['item'];
            $state = $states[$itemId] ?? null;
            $attempt = $attempts[$itemId] ?? null;
            $attemptsN = (int)($attemptCounts[$sid . ':' . $itemId] ?? 0);
            $watchPercent = null;
            if ($state && (int)($state['video_duration_seconds'] ?? 0) > 0 && (int)($state['video_seconds'] ?? 0) > 0) {
                $watchPercent = (int)min(100, round((int)$state['video_seconds'] / (int)$state['video_duration_seconds'] * 100));
            }
            $row = [
                'item_id' => $itemId,
                'title' => (string)($item['title'] ?? ''),
                'kind' => OnlineLessonService::itemKindLabel($item),
                'type' => OnlineLessonService::normalizeItemType((string)($item['item_type'] ?? '')),
                'activity_type' => strtolower(trim((string)($item['activity_type'] ?? ''))),
                'academic' => (bool)$scheme['academic'],
                'max' => (float)$scheme['max'],
                'obtained' => null,
                'counted_max' => null,
                'percent' => null,
                'status' => ((string)($state['status'] ?? '') === 'completed') ? 'completed' : 'not_completed',
                'attempts' => $attemptsN,
                'active_seconds' => (int)($state['active_seconds'] ?? 0),
                'opens' => (int)($state['open_count'] ?? 0),
                'mcq_correct' => 0,
                'mcq_incorrect' => 0,
                'mcq_questions' => (int)$scheme['mcq_count'],
                'watch_percent' => $watchPercent,
                'questions' => [],
            ];
            if (!$scheme['academic']) {
                $activities[] = $row;
                continue;
            }
            if (!empty($scheme['submission'])) {
                $markable++;
                $sub = $submissions[$itemId] ?? null;
                $subStatus = (string)($sub['status'] ?? '');
                if (!$sub || $subStatus === 'resubmit') {
                    $row['status'] = $sub ? 'resubmit' : 'not_submitted';
                    $activities[] = $row;
                    continue;
                }
                $assignmentSubmitted = true;
                $submittedMarkable++;
                $awardedRaw = $sub['marks_awarded'] ?? null;
                if ($awardedRaw === null || $awardedRaw === '' || $subStatus === 'submitted') {
                    $row['status'] = 'pending';
                    $assignmentPending = true;
                    $activities[] = $row;
                    continue;
                }
                $awarded = (float)$awardedRaw;
                $itemMax = (float)$scheme['max'];
                $assignmentGot += $awarded;
                $assignmentCounted += $itemMax;
                $obtained += $awarded;
                $counted += $itemMax;
                $row['obtained'] = $awarded;
                $row['counted_max'] = $itemMax;
                $row['percent'] = $itemMax > 0 ? (100 * $awarded / $itemMax) : null;
                $row['status'] = 'marked';
                $row['feedback'] = (string)($sub['teacher_comment'] ?? '');
                $activities[] = $row;
                continue;
            }
            $markable++;
            if (!$attempt) {
                $row['status'] = 'not_submitted';
                $activities[] = $row;
                continue;
            }
            $submittedMarkable++;
            $answers = $answerByAttempt[(int)$attempt['id']] ?? [];
            $itemGot = 0.0;
            $itemCounted = 0.0;
            $itemPending = false;
            $itemHasMcq = false;
            $n = 0;
            $shownQuestions = QuestionPool::orderQuestions(
                $scheme['questions'],
                QuestionPool::decodeIds(isset($attempt['question_ids_json']) ? (string)$attempt['question_ids_json'] : null)
            );
            if (!empty($scheme['pool'])) {
                $ownMax = 0.0;
                foreach ($shownQuestions as $shown) {
                    $ownMax += max(0, (float)($shown['marks'] ?? 0));
                }
                $lessonMax += $ownMax - (float)$scheme['max'];
                $row['max'] = $ownMax;
            }
            foreach ($shownQuestions as $question) {
                $n++;
                $qid = (int)$question['id'];
                $qMarks = max(0, (float)($question['marks'] ?? 0));
                $answer = $answers[$qid] ?? null;
                $bucket = self::markBucket((string)($question['question_type'] ?? 'mcq'));
                $manual = $bucket !== 'mcq';
                $pending = $manual && ($answer === null || $answer['marks_awarded'] === null || $answer['marks_awarded'] === '');
                $awarded = null;
                $isCorrect = null;
                if (!$pending && $answer !== null) {
                    if ($answer['marks_awarded'] !== null && $answer['marks_awarded'] !== '') {
                        $awarded = (float)$answer['marks_awarded'];
                    } elseif (!$manual) {
                        $awarded = ((int)($answer['is_correct'] ?? 0) === 1) ? $qMarks : 0.0;
                    }
                }
                if ($manual) {
                    if ($bucket === 'essay') {
                        $essaySubmitted = true;
                    } elseif ($bucket === 'short') {
                        $shortSubmitted = true;
                    } else {
                        $examSubmitted = true;
                    }
                    if ($pending) {
                        $itemPending = true;
                        if ($bucket === 'essay') {
                            $essayPending = true;
                        } elseif ($bucket === 'short') {
                            $shortPending = true;
                        } else {
                            $examPending = true;
                        }
                    } elseif ($awarded !== null) {
                        $itemGot += $awarded;
                        $itemCounted += $qMarks;
                        if ($bucket === 'essay') {
                            $essayGot += $awarded;
                            $essayCounted += $qMarks;
                        } elseif ($bucket === 'short') {
                            $shortGot += $awarded;
                            $shortCounted += $qMarks;
                        } else {
                            $examGot += $awarded;
                            $examCounted += $qMarks;
                        }
                    }
                } elseif ($answer !== null && $awarded !== null) {
                    $hasMcqSubmission = true;
                    $itemHasMcq = true;
                    $itemGot += $awarded;
                    $itemCounted += $qMarks;
                    $mcqGot += $awarded;
                    $mcqCounted += $qMarks;
                    $isCorrect = ((int)($answer['is_correct'] ?? 0) === 1) || ($qMarks > 0 && $awarded >= $qMarks);
                    if ($isCorrect) {
                        $correct++;
                        $row['mcq_correct']++;
                    } else {
                        $incorrect++;
                        $row['mcq_incorrect']++;
                    }
                }
                $row['questions'][] = [
                    'n' => $n,
                    'id' => $qid,
                    'prompt' => (string)($question['prompt'] ?? ''),
                    'type' => $bucket,
                    'max' => $qMarks,
                    'awarded' => $awarded,
                    'pending' => $pending,
                    'correct' => $manual ? null : $isCorrect,
                    'choice' => $answer !== null ? self::choiceLetter($answer['choice_index'] === null ? null : (int)$answer['choice_index']) : '—',
                ];
            }
            if ($itemHasMcq) {
                $mcqAttempts += $attemptsN;
            }
            if (!$itemPending && isset($attempt['avg_score'], $attempt['avg_max']) && (float)$attempt['avg_max'] > 0) {
                $itemGot = (float)$attempt['avg_score'];
                $itemCounted = (float)$attempt['avg_max'];
                $row['scoring'] = 'average of ' . (int)($attempt['avg_count'] ?? 0) . ' attempts';
            }
            $row['obtained'] = $itemCounted > 0 ? $itemGot : null;
            $row['counted_max'] = $itemCounted > 0 ? $itemCounted : null;
            $row['percent'] = $itemCounted > 0 ? (100 * $itemGot / $itemCounted) : null;
            $row['status'] = $itemPending ? 'pending' : 'marked';
            $obtained += $itemGot;
            $counted += $itemCounted;
            $activities[] = $row;
        }

        $percent = $counted > 0 ? (100 * $obtained / $counted) : null;
        $allSubmitted = $markable > 0 && $submittedMarkable === $markable;
        if ($markable === 0) {
            $marking = 'none';
        } elseif ($essayPending || $shortPending || $examPending || $assignmentPending) {
            $marking = 'pending';
        } elseif (!$allSubmitted) {
            $marking = $submittedMarkable > 0 ? 'partial' : 'none';
        } else {
            $marking = 'final';
        }
        $full = $marking === 'final' && $lessonMax > 0 && abs($counted - $lessonMax) < 0.001;
        $outcome = null;
        if ($passPercent !== null) {
            if ($full && $percent !== null) {
                $outcome = $percent >= $passPercent ? 'pass' : 'fail';
            } elseif ($markable > 0 && ($marking === 'pending' || $marking === 'partial' || $submittedMarkable > 0)) {
                $outcome = 'pending';
            }
        }

        return [
            'id' => $sid,
            'name' => (string)($student['name'] ?? ''),
            'status' => (string)($student['status'] ?? 'not_started'),
            'progress' => (int)($student['percent'] ?? 0),
            'items_done' => (int)($student['completed_count'] ?? 0),
            'items_total' => (int)($student['total'] ?? 0),
            'active_seconds' => (int)($student['active_seconds'] ?? 0),
            'last_seen_at' => (string)($student['last_seen_at'] ?? ''),
            'obtained' => $counted > 0 ? $obtained : null,
            'counted_max' => $counted > 0 ? $counted : null,
            'lesson_max' => $lessonMax,
            'percent' => $percent,
            'full' => $full,
            'mcq_obtained' => $mcqCounted > 0 ? $mcqGot : null,
            'mcq_counted_max' => $mcqCounted > 0 ? $mcqCounted : null,
            'mcq_correct' => $correct,
            'mcq_incorrect' => $incorrect,
            'mcq_attempts' => $mcqAttempts,
            'has_mcq' => $hasMcqSubmission,
            'essay_obtained' => $essayCounted > 0 ? $essayGot : null,
            'essay_counted_max' => $essayCounted > 0 ? $essayCounted : null,
            'essay_pending' => $essayPending,
            'essay_submitted' => $essaySubmitted,
            'short_obtained' => $shortCounted > 0 ? $shortGot : null,
            'short_counted_max' => $shortCounted > 0 ? $shortCounted : null,
            'short_pending' => $shortPending,
            'short_submitted' => $shortSubmitted,
            'exam_obtained' => $examCounted > 0 ? $examGot : null,
            'exam_counted_max' => $examCounted > 0 ? $examCounted : null,
            'exam_pending' => $examPending,
            'exam_submitted' => $examSubmitted,
            'assignment_obtained' => $assignmentCounted > 0 ? $assignmentGot : null,
            'assignment_counted_max' => $assignmentCounted > 0 ? $assignmentCounted : null,
            'assignment_pending' => $assignmentPending,
            'assignment_submitted' => $assignmentSubmitted,
            'marking' => $marking,
            'outcome' => $outcome,
            'activities' => $activities,
        ];
    }

    /**
     * @param array<string,mixed> $scheme
     * @param list<array<string,mixed>> $students
     * @return array<string,mixed>
     */
    private static function activityResult(array $scheme, array $students, int $enrolled): array
    {
        $item = $scheme['item'];
        $itemId = (int)$item['id'];
        $scores = [];
        $correct = 0;
        $incorrect = 0;
        $attempted = 0;
        $essaySubmitted = 0;
        $essayMarked = 0;
        $essayPending = 0;
        $essayGot = 0.0;
        $essayCounted = 0.0;
        $started = 0;
        $completed = 0;
        $opens = 0;
        $timeSum = 0;
        $timed = 0;
        $watchSum = 0;
        $watchN = 0;
        $notCompleted = 0;
        $questionStats = [];
        foreach ($scheme['questions'] as $index => $question) {
            if (self::markBucket((string)($question['question_type'] ?? 'mcq')) !== 'mcq') {
                continue;
            }
            $choices = $question['choices'] ?? [];
            if (!is_array($choices)) {
                $choices = [];
            }
            $counts = [];
            foreach (array_values($choices) as $choiceIndex => $label) {
                $counts[$choiceIndex] = ['letter' => self::choiceLetter((int)$choiceIndex), 'label' => (string)$label, 'count' => 0];
            }
            $questionStats[(int)$question['id']] = [
                'n' => $index + 1,
                'id' => (int)$question['id'],
                'prompt' => (string)($question['prompt'] ?? ''),
                'max' => max(0, (float)($question['marks'] ?? 0)),
                'correct_letter' => self::choiceLetter($question['correct_index'] === null ? null : (int)$question['correct_index']),
                'correct_index' => $question['correct_index'] === null ? null : (int)$question['correct_index'],
                'choices' => $counts,
                'answered' => 0,
                'correct' => 0,
                'awarded_sum' => 0.0,
            ];
        }
        $studentRows = [];
        foreach ($students as $student) {
            $activity = null;
            foreach ($student['activities'] as $candidate) {
                if ((int)$candidate['item_id'] === $itemId) {
                    $activity = $candidate;
                    break;
                }
            }
            if ($activity === null) {
                continue;
            }
            if ((int)$activity['opens'] > 0 || $activity['status'] !== 'not_completed') {
                $started++;
            }
            if ($activity['status'] === 'completed' || $activity['status'] === 'marked') {
                $completed++;
            } else {
                $notCompleted++;
            }
            $opens += (int)$activity['opens'];
            if ((int)$activity['active_seconds'] > 0) {
                $timeSum += (int)$activity['active_seconds'];
                $timed++;
            }
            if ($activity['watch_percent'] !== null) {
                $watchSum += (int)$activity['watch_percent'];
                $watchN++;
            }
            if (!$scheme['academic']) {
                continue;
            }
            if ($activity['status'] === 'not_submitted') {
                $studentRows[] = [
                    'id' => (int)$student['id'],
                    'name' => (string)$student['name'],
                    'status' => 'not_submitted',
                    'obtained' => null,
                    'max' => (float)$scheme['max'],
                    'percent' => null,
                    'attempts' => 0,
                ];
                continue;
            }
            $attempted++;
            $mcqScore = 0.0;
            $mcqAnswered = false;
            foreach ($activity['questions'] as $question) {
                if (($question['type'] ?? '') === 'mcq' && $question['awarded'] !== null) {
                    $mcqScore += (float)$question['awarded'];
                    $mcqAnswered = true;
                }
            }
            if ($mcqAnswered) {
                $scores[] = $mcqScore;
            }
            $correct += (int)$activity['mcq_correct'];
            $incorrect += (int)$activity['mcq_incorrect'];
            foreach ($activity['questions'] as $question) {
                if (($question['type'] ?? '') !== 'mcq' || !isset($questionStats[(int)$question['id']])) {
                    continue;
                }
                $stat = &$questionStats[(int)$question['id']];
                if ($question['correct'] === null) {
                    continue;
                }
                $stat['answered']++;
                if ($question['awarded'] !== null) {
                    $stat['awarded_sum'] += (float)$question['awarded'];
                }
                if ($question['correct']) {
                    $stat['correct']++;
                }
                $letter = (string)$question['choice'];
                foreach ($stat['choices'] as $choiceIndex => $choice) {
                    if ($choice['letter'] === $letter) {
                        $stat['choices'][$choiceIndex]['count']++;
                    }
                }
                unset($stat);
            }
            $pendingEssay = false;
            $markedEssay = false;
            foreach ($activity['questions'] as $question) {
                if (($question['type'] ?? '') !== 'essay') {
                    continue;
                }
                if (!empty($question['pending'])) {
                    $pendingEssay = true;
                } elseif ($question['awarded'] !== null) {
                    $markedEssay = true;
                    $essayGot += (float)$question['awarded'];
                    $essayCounted += (float)$question['max'];
                }
            }
            if ($scheme['essay_count'] > 0 && $activity['status'] !== 'not_submitted') {
                $essaySubmitted++;
                if ($pendingEssay) {
                    $essayPending++;
                }
                if ($markedEssay && !$pendingEssay) {
                    $essayMarked++;
                } elseif ($markedEssay && $pendingEssay) {
                    $essayMarked++;
                }
            }
            $studentRows[] = [
                'id' => (int)$student['id'],
                'name' => (string)$student['name'],
                'status' => (string)$activity['status'],
                'obtained' => $activity['obtained'],
                'max' => $activity['counted_max'] ?? (float)$scheme['max'],
                'percent' => $activity['percent'],
                'attempts' => (int)$activity['attempts'],
            ];
        }

        $questionRows = array_values($questionStats);
        $rates = [];
        foreach ($questionRows as &$question) {
            $question['percent'] = $question['answered'] > 0 ? (float)(100 * $question['correct'] / $question['answered']) : null;
            $question['average_marks'] = $question['answered'] > 0 ? ((float)$question['awarded_sum'] / $question['answered']) : null;
            if ($question['percent'] !== null) {
                $rates[] = $question['percent'];
            }
        }
        unset($question);
        $meanRate = $rates !== [] ? array_sum($rates) / count($rates) : null;
        $lower = [];
        if ($meanRate !== null && count($rates) > 1) {
            foreach ($questionRows as $question) {
                if ($question['percent'] !== null && $question['percent'] < $meanRate) {
                    $lower[] = $question;
                }
            }
        }
        $distribution = self::distribution($scores, (float)$scheme['mcq_max'] > 0 ? (float)$scheme['mcq_max'] : (float)$scheme['max']);

        return [
            'item_id' => $itemId,
            'title' => (string)($item['title'] ?? ''),
            'kind' => OnlineLessonService::itemKindLabel($item),
            'type' => OnlineLessonService::normalizeItemType((string)($item['item_type'] ?? '')),
            'activity_type' => strtolower(trim((string)($item['activity_type'] ?? ''))),
            'academic' => (bool)$scheme['academic'],
            'max' => (float)$scheme['max'],
            'mcq_max' => (float)$scheme['mcq_max'],
            'essay_max' => (float)$scheme['essay_max'],
            'mcq_count' => (int)$scheme['mcq_count'],
            'essay_count' => (int)$scheme['essay_count'],
            'enrolled' => $enrolled,
            'attempted' => $attempted,
            'started' => $started,
            'completed' => $completed,
            'not_completed' => $notCompleted,
            'opens' => $opens,
            'avg_seconds' => $timed > 0 ? (int)round($timeSum / $timed) : 0,
            'avg_watch_percent' => $watchN > 0 ? (int)round($watchSum / $watchN) : null,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'accuracy' => ($correct + $incorrect) > 0 ? (100 * $correct / ($correct + $incorrect)) : null,
            'average' => $scores !== [] ? array_sum($scores) / count($scores) : null,
            'highest' => $scores !== [] ? max($scores) : null,
            'lowest' => $scores !== [] ? min($scores) : null,
            'essay_submitted' => $essaySubmitted,
            'essay_marked' => $essayMarked,
            'essay_pending' => $essayPending,
            'essay_average' => $essayCounted > 0 ? (100 * $essayGot / $essayCounted) : null,
            'essay_average_marks' => $essayMarked > 0 ? ($essayGot / $essayMarked) : null,
            'questions' => $questionRows,
            'lower' => $lower,
            'distribution' => $distribution,
            'students' => $studentRows,
        ];
    }

    /**
     * @param list<float> $scores
     * @return list<array{label:string,count:int,width:int}>
     */
    private static function distribution(array $scores, float $max): array
    {
        $bands = [
            ['min' => 90, 'label' => '', 'count' => 0],
            ['min' => 75, 'label' => '', 'count' => 0],
            ['min' => 50, 'label' => '', 'count' => 0],
            ['min' => 0, 'label' => '', 'count' => 0],
        ];
        if ($max <= 0) {
            return [];
        }
        $bands[0]['label'] = self::formatMark($max * 0.9) . '–' . self::formatMark($max);
        $bands[1]['label'] = self::formatMark($max * 0.75) . '–' . self::formatMark($max * 0.89);
        $bands[2]['label'] = self::formatMark($max * 0.5) . '–' . self::formatMark($max * 0.74);
        $bands[3]['label'] = '0–' . self::formatMark($max * 0.49);
        foreach ($scores as $score) {
            $pct = 100 * $score / $max;
            if ($pct >= 90) {
                $bands[0]['count']++;
            } elseif ($pct >= 75) {
                $bands[1]['count']++;
            } elseif ($pct >= 50) {
                $bands[2]['count']++;
            } else {
                $bands[3]['count']++;
            }
        }
        $peak = max(1, ...array_column($bands, 'count'));
        $rows = [];
        foreach ($bands as $band) {
            $rows[] = [
                'label' => $band['label'],
                'count' => $band['count'],
                'width' => (int)round(100 * $band['count'] / $peak),
            ];
        }
        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $students
     * @return array<string,mixed>
     */
    private static function markBucket(string $type): string
    {
        return match (strtolower(trim($type))) {
            'essay' => 'essay',
            'short' => 'short',
            'exam' => 'exam',
            default => 'mcq',
        };
    }

    private static function classSummary(array $students, float $lessonMax, ?int $passPercent): array
    {
        $withMarks = 0;
        $pendingStudents = 0;
        $fullObtained = [];
        $fullPercent = [];
        $pass = 0;
        $fail = 0;
        $mcqGot = 0.0;
        $mcqMax = 0.0;
        $essayGot = 0.0;
        $essayMax = 0.0;
        $auto = 0;
        $essaySubmitted = 0;
        $essayMarkedStudents = 0;
        $essayPendingStudents = 0;
        $shortGot = 0.0;
        $shortMax = 0.0;
        $examGot = 0.0;
        $examMax = 0.0;
        $assignmentGot = 0.0;
        $assignmentMax = 0.0;
        $assignmentPending = 0;
        $assignmentSubmitted = 0;
        foreach ($students as $student) {
            if ($student['counted_max'] !== null && (float)$student['counted_max'] > 0) {
                $withMarks++;
            }
            if (!empty($student['essay_pending']) || !empty($student['short_pending']) || !empty($student['exam_pending']) || !empty($student['assignment_pending'])) {
                $pendingStudents++;
            }
            if (!empty($student['essay_pending'])) {
                $essayPendingStudents++;
            }
            if (!empty($student['has_mcq'])) {
                $auto++;
            }
            if (!empty($student['essay_submitted'])) {
                $essaySubmitted++;
            }
            if ($student['essay_counted_max'] !== null) {
                $essayMarkedStudents++;
                $essayGot += (float)$student['essay_obtained'];
                $essayMax += (float)$student['essay_counted_max'];
            }
            if ($student['mcq_counted_max'] !== null) {
                $mcqGot += (float)$student['mcq_obtained'];
                $mcqMax += (float)$student['mcq_counted_max'];
            }
            if (($student['short_counted_max'] ?? null) !== null) {
                $shortGot += (float)$student['short_obtained'];
                $shortMax += (float)$student['short_counted_max'];
            }
            if (($student['exam_counted_max'] ?? null) !== null) {
                $examGot += (float)$student['exam_obtained'];
                $examMax += (float)$student['exam_counted_max'];
            }
            if (($student['assignment_counted_max'] ?? null) !== null) {
                $assignmentGot += (float)$student['assignment_obtained'];
                $assignmentMax += (float)$student['assignment_counted_max'];
            }
            if (!empty($student['assignment_pending'])) {
                $assignmentPending++;
            }
            if (!empty($student['assignment_submitted'])) {
                $assignmentSubmitted++;
            }
            if (!empty($student['full'])) {
                $fullObtained[] = (float)$student['obtained'];
                $fullPercent[] = (float)$student['percent'];
                if (($student['outcome'] ?? '') === 'pass') {
                    $pass++;
                } elseif (($student['outcome'] ?? '') === 'fail') {
                    $fail++;
                }
            }
        }
        $finalCount = count($fullObtained);
        $averageObtained = $finalCount > 0 ? array_sum($fullObtained) / $finalCount : null;
        $averagePercent = ($finalCount > 0 && $lessonMax > 0) ? (100 * array_sum($fullObtained) / ($finalCount * $lessonMax)) : null;
        sort($fullPercent);
        $median = null;
        if ($fullPercent !== []) {
            $mid = intdiv($finalCount, 2);
            $median = $finalCount % 2 === 1 ? $fullPercent[$mid] : (($fullPercent[$mid - 1] + $fullPercent[$mid]) / 2);
        }
        return [
            'students' => count($students),
            'with_marks' => $withMarks,
            'pending_marking' => $pendingStudents,
            'final_count' => $finalCount,
            'average_obtained' => $averageObtained,
            'average_percent' => $averagePercent,
            'median_percent' => $median,
            'highest_obtained' => $fullObtained !== [] ? max($fullObtained) : null,
            'lowest_obtained' => $fullObtained !== [] ? min($fullObtained) : null,
            'highest_percent' => $fullPercent !== [] ? max($fullPercent) : null,
            'lowest_percent' => $fullPercent !== [] ? min($fullPercent) : null,
            'pass_count' => $pass,
            'fail_count' => $fail,
            'pass_rate' => ($passPercent !== null && $finalCount > 0) ? (float)(100 * $pass / $finalCount) : null,
            'mcq_average_percent' => $mcqMax > 0 ? (100 * $mcqGot / $mcqMax) : null,
            'essay_average_percent' => $essayMax > 0 ? (100 * $essayGot / $essayMax) : null,
            'auto_marked_students' => $auto,
            'essay_submitted_students' => $essaySubmitted,
            'essay_marked_students' => $essayMarkedStudents,
            'essay_pending_students' => $essayPendingStudents,
            'short_average_percent' => $shortMax > 0 ? (100 * $shortGot / $shortMax) : null,
            'exam_average_percent' => $examMax > 0 ? (100 * $examGot / $examMax) : null,
            'assignment_average_percent' => $assignmentMax > 0 ? (100 * $assignmentGot / $assignmentMax) : null,
            'assignment_pending_students' => $assignmentPending,
            'assignment_submitted_students' => $assignmentSubmitted,
        ];
    }
}
