<?php
declare(strict_types=1);

use Edexcel\Services\CourseService;
use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonAccessService;
use Edexcel\Services\LessonAiService;
use Edexcel\Services\LessonInsightService;
use Edexcel\Services\LessonPlannerService;
use Edexcel\Services\LessonVersionService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\QuestionPool;
use PHPUnit\Framework\TestCase;

/**
 * Pure rules behind Phase 3: pools, scoring rules, prerequisites, versions, coverage, planning, AI output and insights.
 */
final class LearningModulePhase3Test extends TestCase
{
    public function testPoolDrawsNOfMAndIsReproducible(): void
    {
        $ids = range(101, 120);
        $a = QuestionPool::drawQuestionIds($ids, 5, 7, 3, 1, false);
        $b = QuestionPool::drawQuestionIds($ids, 5, 7, 3, 1, false);
        $this->assertCount(5, $a);
        $this->assertSame($a, $b, 'same student, activity and attempt must see the same questions');
        $this->assertSame(array_values(array_intersect($ids, $a)), $a, 'without shuffle the teacher order is kept');
        $this->assertCount(5, array_unique($a));
        foreach ($a as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function testPoolDrawDiffersBetweenStudentsAndAttempts(): void
    {
        $ids = range(1, 40);
        $draws = [];
        foreach ([[1, 1], [2, 1], [1, 2], [3, 1]] as [$student, $attempt]) {
            $draws[] = implode(',', QuestionPool::drawQuestionIds($ids, 6, $student, 9, $attempt, false));
        }
        $this->assertGreaterThan(1, count(array_unique($draws)));
    }

    public function testDrawIgnoresQuestionOrderChangesBySortingIds(): void
    {
        $ids = [5, 3, 9, 1, 7, 2];
        $shuffledTeacherOrder = [9, 1, 5, 7, 2, 3];
        $a = QuestionPool::drawQuestionIds($ids, 3, 4, 2, 1, false);
        $b = QuestionPool::drawQuestionIds($shuffledTeacherOrder, 3, 4, 2, 1, false);
        sort($a);
        sort($b);
        $this->assertSame($a, $b, 'reordering questions must not change which ones a student drew');
    }

    public function testDrawAllWhenCountIsZeroOrLarger(): void
    {
        $ids = [1, 2, 3];
        $this->assertSame($ids, QuestionPool::drawQuestionIds($ids, 0, 1, 1, 1, false));
        $this->assertSame($ids, QuestionPool::drawQuestionIds($ids, 10, 1, 1, 1, false));
        $shuffled = QuestionPool::drawQuestionIds($ids, 0, 1, 1, 1, true);
        sort($shuffled);
        $this->assertSame($ids, $shuffled);
    }

    public function testStoredIdsRebuildTheSameQuestionSet(): void
    {
        $questions = [['id' => 1, 'p' => 'a'], ['id' => 2, 'p' => 'b'], ['id' => 3, 'p' => 'c']];
        $ids = QuestionPool::decodeIds(json_encode([3, 1]));
        $ordered = QuestionPool::orderQuestions($questions, $ids);
        $this->assertSame([3, 1], array_column($ordered, 'id'));
        $this->assertNull(QuestionPool::decodeIds(null));
        $this->assertSame($questions, QuestionPool::orderQuestions($questions, null), 'older attempts without stored ids keep every question');
    }

    public function testScoringRuleDefaultsToLatest(): void
    {
        $attempts = [
            ['id' => 1, 'attempt_no' => 1, 'score' => 9, 'max_score' => 10],
            ['id' => 2, 'attempt_no' => 2, 'score' => 4, 'max_score' => 10],
        ];
        $this->assertSame(2, QuestionPool::chooseAttempt($attempts, null)['id']);
        $this->assertSame(2, QuestionPool::chooseAttempt($attempts, 'latest')['id']);
        $this->assertSame(1, QuestionPool::chooseAttempt($attempts, 'highest')['id']);
        $avg = QuestionPool::chooseAttempt($attempts, 'average');
        $this->assertSame(2, $avg['id']);
        $this->assertEqualsWithDelta(6.5, $avg['avg_score'], 0.001);
        $this->assertNull(QuestionPool::chooseAttempt([], 'highest'));
    }

    public function testHighestUsesPercentageWhenPoolMaximumsDiffer(): void
    {
        $attempts = [
            ['id' => 1, 'attempt_no' => 1, 'score' => 6, 'max_score' => 10],
            ['id' => 2, 'attempt_no' => 2, 'score' => 5, 'max_score' => 6],
        ];
        $this->assertSame(2, QuestionPool::chooseAttempt($attempts, 'highest')['id']);
    }

    public function testPoolMaxTagsDifficultyAndTimer(): void
    {
        $qs = [['marks' => 1], ['marks' => 2], ['marks' => 3], ['marks' => 2]];
        $this->assertEqualsWithDelta(8.0, QuestionPool::poolMax($qs, 0), 0.001);
        $this->assertEqualsWithDelta(4.0, QuestionPool::poolMax($qs, 2), 0.001);
        $this->assertSame('CPU,Memory', QuestionPool::normalizeTags(' CPU, Memory ;cpu, memory'), 'duplicates are removed, first spelling kept');
        $this->assertSame(['CPU', 'Memory'], QuestionPool::tagList('CPU,Memory'));
        $this->assertSame('hard', QuestionPool::normalizeDifficulty('Hard'));
        $this->assertNull(QuestionPool::normalizeDifficulty(''));
        $start = '2026-01-01 10:00:00';
        $t = strtotime($start);
        $this->assertSame(300, QuestionPool::timeState($start, 5, $t)['remaining']);
        $this->assertFalse(QuestionPool::isOverTime($start, 5, $t + 300 + 10), 'a short grace period absorbs network delay');
        $this->assertTrue(QuestionPool::isOverTime($start, 5, $t + 300 + QuestionPool::TIME_GRACE_SECONDS + 1));
        $this->assertTrue(QuestionPool::usesSession(['draw_count' => 3]));
        $this->assertTrue(QuestionPool::usesSession(['time_limit_minutes' => 10]));
        $this->assertFalse(QuestionPool::usesSession([]));
    }

    public function testCircularPrerequisitesAreDetected(): void
    {
        $edges = [2 => [1], 3 => [2]];
        $this->assertTrue(LessonAccessService::wouldCreateCycle($edges, 1, 3), '1 requires 3 while 3 → 2 → 1');
        $this->assertTrue(LessonAccessService::wouldCreateCycle($edges, 4, 4));
        $this->assertFalse(LessonAccessService::wouldCreateCycle($edges, 4, 3));
        $this->assertFalse(LessonAccessService::wouldCreateCycle($edges, 3, 1));
    }

    public function testScheduledAndClosedStates(): void
    {
        $now = strtotime('2026-05-01 12:00:00');
        $this->assertSame('draft', OnlineLessonService::publicationState(['published' => 0], $now));
        $this->assertSame('scheduled', OnlineLessonService::publicationState(['published' => 1, 'publish_at' => '2026-05-02 08:00:00'], $now));
        $this->assertSame('published', OnlineLessonService::publicationState(['published' => 1, 'publish_at' => '2026-04-30 08:00:00'], $now));
        $this->assertSame('closed', OnlineLessonService::publicationState(['published' => 1, 'unpublish_at' => '2026-04-30 08:00:00'], $now));
        $this->assertSame('archived', OnlineLessonService::publicationState(['published' => 1, 'archived' => 1], $now));
        $this->assertFalse(OnlineLessonService::isReleased(['published' => 1, 'publish_at' => '2026-05-02 08:00:00'], $now));
        $window = OnlineLessonService::availabilityWindow(['unpublish_at' => '2026-04-30 08:00:00'], ['date' => '2026-04-01', 'end_time' => '10:00:00'], $now);
        $this->assertFalse($window['open'], 'an unpublish time closes the lesson for students');
    }

    public function testVersionCompareReportsContentChangesOnly(): void
    {
        $old = [
            'lesson' => ['title' => 'CPU', 'pass_percent' => '50'],
            'objectives' => [['id' => 1, 'body' => 'Explain fetch']],
            'sections' => [['id' => 1, 'title' => 'Starter']],
            'items' => [
                ['id' => 10, 'title' => 'Quiz', 'item_type' => 'activity', 'activity' => ['draw_count' => null, 'questions' => [
                    ['id' => 100, 'prompt' => 'What is ALU?', 'marks' => '1', 'criteria' => []],
                ]]],
            ],
        ];
        $new = $old;
        $new['lesson']['title'] = 'CPU basics';
        $new['items'][0]['activity']['draw_count'] = '3';
        $new['items'][0]['activity']['questions'][0]['marks'] = '2';
        $new['items'][0]['activity']['questions'][] = ['id' => 101, 'prompt' => 'What is CU?', 'marks' => '1', 'criteria' => []];
        $new['objectives'][] = ['id' => 2, 'body' => 'Name registers'];
        $diff = LessonVersionService::compare($old, $new);
        $this->assertSame('title', $diff['settings'][0]['field']);
        $this->assertSame(['Name registers'], $diff['objectives_added']);
        $this->assertSame(['What is CU?'], $diff['questions_added']);
        $this->assertSame(['marks'], $diff['questions_changed'][0]['fields']);
        $this->assertContains('activity draw_count', $diff['items_changed'][0]['fields']);
        $this->assertEqualsWithDelta(1.0, $diff['marks']['old'], 0.001);
        $this->assertEqualsWithDelta(3.0, $diff['marks']['new'], 0.001);
        $this->assertFalse(LessonVersionService::isEmptyDiff($diff));
        $this->assertTrue(LessonVersionService::isEmptyDiff(LessonVersionService::compare($old, $old)));
    }

    public function testVersionFieldsNeverIncludeStudentData(): void
    {
        $all = array_merge(
            LessonVersionService::LESSON_FIELDS,
            LessonVersionService::ITEM_FIELDS,
            LessonVersionService::ACTIVITY_FIELDS,
            LessonVersionService::QUESTION_FIELDS
        );
        foreach (['student_id', 'score', 'marks_awarded', 'essay_text', 'body_text', 'status', 'submitted_at', 'choice_index'] as $forbidden) {
            $this->assertNotContains($forbidden, $all);
        }
    }

    public function testTopicCoverageAndWeightedProgress(): void
    {
        $today = '2026-05-10';
        $lessons = [
            ['released' => true, 'date' => '2026-05-01', 'avg_completion' => 80],
            ['released' => true, 'date' => '2026-05-20', 'avg_completion' => 90],
            ['released' => false, 'date' => '2026-05-02', 'avg_completion' => 100],
        ];
        $this->assertSame('in_progress', CourseService::topicStatus($lessons, 2, 0, $today)['status']);
        $this->assertSame('covered', CourseService::topicStatus($lessons, 1, 0, $today)['status']);
        $this->assertSame('in_progress', CourseService::topicStatus($lessons, 1, 90, $today)['status'], 'minimum completion is enforced');
        $this->assertSame('not_started', CourseService::topicStatus([], 1, 0, $today)['status']);
        $this->assertSame(75, CourseService::weightedProgress([['completed' => 5, 'total' => 5], ['completed' => 1, 'total' => 3]]));
        $this->assertSame(0, CourseService::weightedProgress([]));
    }

    public function testPlannerRangesTimelineAndPlannedMinutes(): void
    {
        $week = LessonPlannerService::range('week', '2026-09-30');
        $this->assertSame('2026-09-28', $week['from']);
        $this->assertSame('2026-10-04', $week['to']);
        $month = LessonPlannerService::range('month', '2026-02-10');
        $this->assertSame(['2026-02-01', '2026-02-28'], [$month['from'], $month['to']]);
        $term = LessonPlannerService::range('term', '2026-09-30');
        $this->assertSame('2026-12-27', $term['to']);
        $this->assertSame(90, LessonPlannerService::plannedMinutes([], ['start_time' => '08:00:00', 'end_time' => '09:30:00']));
        $this->assertSame(45, LessonPlannerService::plannedMinutes(['plan_minutes' => 45], ['start_time' => '08:00:00', 'end_time' => '09:30:00']));

        $timeline = LessonPlannerService::timeline([
            ['id' => 1, 'title' => 'Intro', 'item_type' => 'page', 'estimated_minutes' => 10],
            ['id' => 2, 'title' => 'Quiz', 'item_type' => 'activity', 'estimated_minutes' => 25],
            ['id' => 3, 'title' => 'Link', 'item_type' => 'external_link', 'estimated_minutes' => null],
            ['id' => 4, 'title' => 'Essay', 'item_type' => 'activity', 'estimated_minutes' => 20],
        ], [], 40);
        $this->assertSame(55, $timeline['total']);
        $this->assertSame(15, $timeline['over']);
        $this->assertSame(1, $timeline['unestimated']);
        $this->assertSame([false, false, false, true], array_column($timeline['rows'], 'over'));
        $this->assertSame(35, $timeline['rows'][3]['start']);

        $unestimated = LessonPlannerService::timeline([
            ['id' => 1, 'title' => 'Intro', 'item_type' => 'page', 'estimated_minutes' => null],
            ['id' => 2, 'title' => 'Quiz', 'item_type' => 'activity', 'estimated_minutes' => null],
        ], [], 40);
        $this->assertCount(2, $unestimated['rows']);
        $this->assertSame(2, $unestimated['unestimated']);
        $this->assertSame(0, $unestimated['total']);
    }

    public function testMarkingQueueMergedRowsSortAndPage(): void
    {
        $rows = [
            ['submitted_at' => '2026-09-28 10:00:00', 'attempt_id' => 0, 'question_id' => 0, 'submission_id' => 7],
            ['submitted_at' => '2026-09-27 09:00:00', 'attempt_id' => 3, 'question_id' => 12, 'submission_id' => 0],
            ['submitted_at' => '2026-09-28 10:00:00', 'attempt_id' => 4, 'question_id' => 2, 'submission_id' => 0],
            ['submitted_at' => '2026-09-26 08:00:00', 'attempt_id' => 0, 'question_id' => 0, 'submission_id' => 5],
        ];
        $oldest = LearningModuleService::sortMarkingQueue($rows);
        $this->assertSame(['2026-09-26 08:00:00', '2026-09-27 09:00:00'], array_column(array_slice($oldest, 0, 2), 'submitted_at'));
        $this->assertSame([0, 4], array_column(array_slice($oldest, 2), 'attempt_id'));
        $newest = LearningModuleService::sortMarkingQueue($rows, true);
        $this->assertSame([4, 0], array_column(array_slice($newest, 0, 2), 'attempt_id'));
        $this->assertSame(5, $newest[3]['submission_id']);
    }

    public function testAiOutputIsNormalisedAndNeverClaimsOfficialQuestions(): void
    {
        $raw = "```json\n{\"questions\":[{\"prompt\":\"From the 2019 past paper: what is RAM?\",\"choices\":[\"a\",\"b\"],\"correct_index\":0},"
            . "{\"prompt\":\"What does the ALU do?\",\"choices\":[\"Arithmetic\",\"Storage\",\"\"],\"correct_index\":0,\"difficulty\":\"Easy\"},"
            . "{\"prompt\":\"Bad index\",\"choices\":[\"a\",\"b\"],\"correct_index\":5}]}\n```";
        $decoded = LessonAiService::decodeJson($raw);
        $questions = LessonAiService::normalizeQuestions($decoded['questions'], 'mcq');
        $this->assertCount(1, $questions);
        $this->assertSame('What does the ALU do?', $questions[0]['prompt']);
        $this->assertSame(['Arithmetic', 'Storage'], $questions[0]['choices']);
        $this->assertSame('easy', $questions[0]['difficulty']);

        $plan = LessonAiService::normalizePlan(['objectives' => ['<b>Explain</b> the CPU'], 'sections' => [
            ['title' => 'Starter', 'minutes' => 999, 'items' => [['type' => 'page', 'title' => 'Notes', 'body' => '<script>x</script>Hello']]],
        ]]);
        $this->assertSame(['Explain the CPU'], $plan['objectives']);
        $this->assertSame(240, $plan['sections'][0]['minutes']);
        $this->assertStringNotContainsString('<script>', $plan['sections'][0]['items'][0]['body']);
    }

    public function testQualityIssuesCarrySeverityAndFix(): void
    {
        $issues = LessonInsightService::qualityIssues([
            'lesson' => ['title' => 'CPU', 'archived' => 0, 'plan_minutes' => 20],
            'items' => [['id' => 5, 'title' => 'Quiz', 'item_type' => 'activity', 'activity_id' => 9, 'body' => null, 'link_url' => null, 'estimated_minutes' => 30]],
            'questions' => [
                ['id' => 1, 'activity_id' => 9, 'question_type' => 'mcq', 'correct_index' => 0, 'marks' => 1, 'explanation' => '', 'difficulty' => '', 'ai_generated' => 1],
                ['id' => 2, 'activity_id' => 9, 'question_type' => 'essay', 'correct_index' => null, 'marks' => 4, 'expected_answer' => '', 'difficulty' => ''],
            ],
            'activities' => [9 => ['draw_count' => 5, 'time_limit_minutes' => 10, 'max_attempts' => 0]],
            'objectives' => [],
        ]);
        $bySeverity = [];
        foreach ($issues as $issue) {
            $this->assertNotSame('', $issue['fix']);
            $bySeverity[$issue['severity']][] = $issue['message'];
        }
        $high = implode(' ', $bySeverity['high'] ?? []);
        $this->assertStringContainsString('draws 5 questions but only has 2', $high);
        $this->assertStringContainsString('AI-generated question', $high);
        $this->assertStringContainsString('no expected answer', implode(' ', $bySeverity['medium'] ?? []));
        $this->assertStringContainsString('no learning objectives', implode(' ', $bySeverity['medium'] ?? []));
        $this->assertStringContainsString('exceeds the planned duration', implode(' ', $bySeverity['medium'] ?? []));
        $this->assertStringContainsString('unlimited attempts', implode(' ', $bySeverity['low'] ?? []));
        $this->assertSame('high', $issues[0]['severity'], 'issues are sorted by severity');
        $this->assertFalse(LessonInsightService::isReady($issues));
    }

    public function testInsightsNeedEnoughStudentsAndStateTheNumbers(): void
    {
        $items = [['id' => 1, 'title' => 'Video', 'estimated_minutes' => 5, 'activity_id' => null], ['id' => 2, 'title' => 'Quiz', 'estimated_minutes' => null, 'activity_id' => 7]];
        $states = [1 => ['opened' => 10, 'completed' => 4, 'avg_seconds' => 1200], 2 => ['opened' => 2, 'completed' => 0, 'avg_seconds' => 0]];
        $out = LessonInsightService::buildInsights($items, $states, [1 => 3], [['id' => 3, 'prompt' => 'What is cache?', 'activity_id' => 7, 'answered' => 10, 'correct' => 3]]);
        $text = implode(' | ', array_column($out, 'message'));
        $this->assertStringContainsString('60% of students who opened “Video” did not complete it (6 of 10)', $text);
        $this->assertStringContainsString('about 20 minutes on “Video”; the estimate is 5 minutes', $text);
        $this->assertStringContainsString('3 students who have not finished the lesson stopped at “Video”', $text);
        $this->assertStringContainsString('Only 30% of answers', $text);
        $this->assertStringNotContainsString('Quiz”', $text, 'fewer than three students gives no insight');
    }

    public function testAdvancedAnalyticsBuilders(): void
    {
        $items = [
            ['id' => 1, 'title' => 'Quiz', 'item_type' => 'activity', 'activity_type' => 'mcq'],
            ['id' => 2, 'title' => 'Worksheet', 'item_type' => 'resource'],
            ['id' => 3, 'title' => 'Homework', 'item_type' => 'activity', 'activity_type' => 'homework', 'due_at' => '2026-05-05 18:00:00'],
        ];
        $cmp = LessonInsightService::attemptComparison($items, [
            ['student_id' => 1, 'item_id' => 1, 'attempt_no' => 1, 'score' => 2, 'max_score' => 10],
            ['student_id' => 1, 'item_id' => 1, 'attempt_no' => 2, 'score' => 8, 'max_score' => 10],
            ['student_id' => 2, 'item_id' => 1, 'attempt_no' => 1, 'score' => 6, 'max_score' => 10],
        ]);
        $this->assertSame(['students' => 2, 'retried' => 1, 'first' => 40, 'latest' => 70, 'best' => 70], array_intersect_key($cmp[0], array_flip(['students', 'retried', 'first', 'latest', 'best'])));

        $pool = LessonInsightService::poolUsage(
            [['id' => 10, 'activity_id' => 4, 'prompt' => 'A'], ['id' => 11, 'activity_id' => 4, 'prompt' => 'B']],
            [['question_ids_json' => '[10]'], ['question_ids_json' => '[10,11]']],
            [['question_id' => 10, 'is_correct' => 1], ['question_id' => 10, 'is_correct' => 0], ['question_id' => 11, 'is_correct' => 1]]
        );
        $this->assertSame([2, 1], array_column($pool, 'shown'));
        $this->assertSame([50, 100], array_column($pool, 'correct_percent'));

        $res = LessonInsightService::resourceUsage($items, [['item_id' => 2, 'student_id' => 1, 'status' => 'completed', 'open_count' => 3]], 4);
        $this->assertSame(1, $res[0]['opened']);
        $this->assertSame(25, $res[0]['percent']);

        $subs = LessonInsightService::submissionRates($items, [
            ['item_id' => 3, 'student_id' => 1, 'status' => 'marked', 'submitted_at' => '2026-05-05 10:00:00'],
            ['item_id' => 3, 'student_id' => 2, 'status' => 'submitted', 'submitted_at' => '2026-05-06 10:00:00'],
        ], 4);
        $this->assertSame([2, 1, 1, 50], [$subs[0]['submitted'], $subs[0]['late'], $subs[0]['marked'], $subs[0]['percent']]);
    }

    public function testMarkingQueueFiltersAreWhitelisted(): void
    {
        [$where, $params] = LearningModuleService::markingQueueFilters([
            'class_id' => '5', 'kind' => 'written', 'status' => 'waiting', 'from' => '2026-01-01', 'to' => "2026-02-01' OR 1=1",
        ]);
        $this->assertStringContainsString('class_id = ?', $where);
        $this->assertStringContainsString("status IN ('pending','submitted')", $where);
        $this->assertStringNotContainsString('lesson_date <=', $where, 'invalid dates are ignored');
        $this->assertSame([5, 'written', '2026-01-01'], $params);
    }

    public function testAuditCallsInPhase3PagesOnlyRecordReferences(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['campus/lesson_manage.php', 'campus/resource_library.php', 'campus/courses.php', 'campus/lesson_calendar.php', 'campus/online_lesson.php', 'student/lesson.php'] as $file) {
            $source = (string)file_get_contents($root . '/' . $file);
            preg_match_all('/log_audit\((.*?)\);/s', $source, $m);
            foreach ($m[1] as $call) {
                foreach (["_POST['body']", "_POST['prompt']", "_POST['note']", "_POST['essay']", "_POST['answers']", "_POST['comment']", 'password'] as $sensitive) {
                    $this->assertStringNotContainsString($sensitive, $call, $file . ' must not write content into the audit log');
                }
            }
        }
    }
}
