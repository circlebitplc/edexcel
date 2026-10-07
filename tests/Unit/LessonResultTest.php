<?php
declare(strict_types=1);

use Edexcel\Services\LessonResultBuilder;
use PHPUnit\Framework\TestCase;

final class LessonResultTest extends TestCase
{
    public function testOverallMarkUsesTotalMarksNotAnAverageOfPercentages(): void
    {
        $report = LessonResultBuilder::analyse(
            [
                $this->activity(1, 10, 'mcq'),
                $this->activity(2, 20, 'mcq'),
            ],
            [
                $this->question(1, 10, 'mcq', 20, 0),
                $this->question(2, 20, 'mcq', 10, 1),
            ],
            [$this->student(7)],
            [
                ['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10],
                ['id' => 101, 'student_id' => 7, 'item_id' => 2, 'activity_id' => 20],
            ],
            [
                $this->answer(100, 1, 0, 1, 18),
                $this->answer(101, 2, 1, 1, 9),
            ],
            [],
            [],
            null
        );
        $row = $report['students'][0];
        $this->assertSame(27.0, $row['obtained']);
        $this->assertSame(30.0, $row['counted_max']);
        $this->assertEqualsWithDelta(90.0, $row['percent'], 0.01);
        $this->assertNull($row['outcome']);
    }

    public function testUnsubmittedEssayIsLeftOutAndIsNotZero(): void
    {
        $report = LessonResultBuilder::analyse(
            [
                $this->activity(1, 10, 'mcq'),
                $this->activity(2, 20, 'essay'),
            ],
            [
                $this->question(1, 10, 'mcq', 20, 0),
                $this->question(3, 20, 'essay', 10, null),
            ],
            [$this->student(7)],
            [
                ['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10],
            ],
            [
                $this->answer(100, 1, 0, 1, 15),
            ],
            [],
            [],
            50
        );
        $row = $report['students'][0];
        $this->assertSame(15.0, $row['obtained']);
        $this->assertSame(20.0, $row['counted_max']);
        $this->assertSame(75.0, $row['percent']);
        $this->assertSame('pending', $row['outcome']);
        $this->assertNotSame('fail', $row['outcome']);
        $this->assertNull($row['essay_obtained']);
    }

    public function testUnmarkedEssayStaysPending(): void
    {
        $report = LessonResultBuilder::analyse(
            [$this->activity(2, 20, 'essay')],
            [$this->question(3, 20, 'essay', 20, null)],
            [$this->student(7)],
            [['id' => 100, 'student_id' => 7, 'item_id' => 2, 'activity_id' => 20]],
            [['attempt_id' => 100, 'question_id' => 3, 'choice_index' => null, 'is_correct' => null, 'marks_awarded' => null]],
            [],
            [],
            50
        );
        $row = $report['students'][0];
        $this->assertNull($row['obtained']);
        $this->assertTrue($row['essay_pending']);
        $this->assertSame('pending', $row['outcome']);
        $this->assertSame('pending', $row['activities'][0]['status']);
    }

    public function testTeacherEssayMarkIsUsedAndPassUsesTheLessonMark(): void
    {
        $report = LessonResultBuilder::analyse(
            [
                $this->activity(1, 10, 'mcq'),
                $this->activity(2, 20, 'essay'),
            ],
            [
                $this->question(1, 10, 'mcq', 20, 1),
                $this->question(3, 20, 'essay', 20, null),
            ],
            [$this->student(7), $this->student(8)],
            [
                ['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10],
                ['id' => 101, 'student_id' => 7, 'item_id' => 2, 'activity_id' => 20],
                ['id' => 102, 'student_id' => 8, 'item_id' => 1, 'activity_id' => 10],
                ['id' => 103, 'student_id' => 8, 'item_id' => 2, 'activity_id' => 20],
            ],
            [
                $this->answer(100, 1, 1, 1, 18),
                $this->answer(101, 3, null, null, 18),
                $this->answer(102, 1, 0, 0, 8),
                $this->answer(103, 3, null, null, 10),
            ],
            [],
            [],
            80
        );
        $pass = $report['students'][0];
        $fail = $report['students'][1];
        $this->assertSame(36.0, $pass['obtained']);
        $this->assertSame(40.0, $pass['counted_max']);
        $this->assertSame('pass', $pass['outcome']);
        $this->assertSame(18.0, $fail['obtained']);
        $this->assertSame('fail', $fail['outcome']);
        $this->assertSame(50.0, $report['summary']['pass_rate']);
        $this->assertEqualsWithDelta(67.5, $report['summary']['average_percent'], 0.01);
    }

    public function testQuestionChoicesAndUnequalQuestionMarks(): void
    {
        $report = LessonResultBuilder::analyse(
            [$this->activity(1, 10, 'mcq')],
            [[
                'id' => 1,
                'activity_id' => 10,
                'question_type' => 'mcq',
                'prompt' => 'Capital?',
                'choices' => ['Colombo', 'Kandy', 'Galle', 'Jaffna'],
                'correct_index' => 1,
                'marks' => 2,
            ]],
            [$this->student(7), $this->student(8)],
            [
                ['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10],
                ['id' => 101, 'student_id' => 8, 'item_id' => 1, 'activity_id' => 10],
            ],
            [
                $this->answer(100, 1, 1, 1, 2),
                $this->answer(101, 1, 0, 0, 0),
            ],
            [],
            [],
            null
        );
        $question = $report['activities'][0]['questions'][0];
        $this->assertSame('B', $question['correct_letter']);
        $this->assertSame(1, $question['choices'][1]['count']);
        $this->assertSame(1, $question['choices'][0]['count']);
        $this->assertSame(50.0, $question['percent']);
        $this->assertSame(2.0, $report['students'][0]['obtained']);
        $this->assertSame(0.0, $report['students'][1]['obtained']);
        $this->assertSame(2.0, $report['lesson_max']);
    }

    public function testVideoIsExcludedFromTheMarkTotal(): void
    {
        $report = LessonResultBuilder::analyse(
            [
                ['id' => 5, 'item_type' => 'video', 'title' => 'Clip', 'activity_id' => null],
                $this->activity(1, 10, 'mcq'),
            ],
            [$this->question(1, 10, 'mcq', 10, 0)],
            [$this->student(7)],
            [['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10]],
            [$this->answer(100, 1, 0, 1, 10)],
            [[
                'student_id' => 7,
                'item_id' => 5,
                'status' => 'completed',
                'active_seconds' => 120,
                'open_count' => 1,
                'video_seconds' => 80,
                'video_duration_seconds' => 100,
            ]],
            [],
            null
        );
        $this->assertSame(10.0, $report['lesson_max']);
        $this->assertSame(10.0, $report['students'][0]['obtained']);
        $this->assertFalse($report['activities'][0]['academic']);
        $this->assertSame(80, $report['students'][0]['activities'][0]['watch_percent']);
    }

    public function testUnmarkedShortAnswerStaysPending(): void
    {
        $report = LessonResultBuilder::analyse(
            [$this->activity(1, 10, 'short')],
            [$this->question(1, 10, 'short', 4, null)],
            [$this->student(7)],
            [['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10]],
            [$this->answer(100, 1, null, null, null)],
            [],
            [],
            null
        );
        $row = $report['students'][0];
        $this->assertNull($row['obtained']);
        $this->assertSame('pending', $row['marking']);
        $this->assertTrue($row['short_pending']);
        $this->assertSame(4.0, $report['lesson_max']);
    }

    public function testMarkedExamQuestionUsesTheSavedMark(): void
    {
        $report = LessonResultBuilder::analyse(
            [$this->activity(1, 10, 'exam')],
            [$this->question(1, 10, 'exam', 6, null)],
            [$this->student(7)],
            [['id' => 100, 'student_id' => 7, 'item_id' => 1, 'activity_id' => 10]],
            [$this->answer(100, 1, null, null, 3)],
            [],
            [],
            null
        );
        $row = $report['students'][0];
        $this->assertSame(3.0, $row['obtained']);
        $this->assertSame(6.0, $row['counted_max']);
        $this->assertSame(3.0, $row['exam_obtained']);
    }

    public function testMissingAssignmentIsNotScoredAsZero(): void
    {
        $item = $this->activity(1, 10, 'assignment');
        $item['max_marks'] = 10;
        $report = LessonResultBuilder::analyse(
            [$item],
            [],
            [$this->student(7)],
            [],
            [],
            [],
            [],
            null,
            []
        );
        $this->assertNull($report['students'][0]['obtained']);
        $this->assertSame('not_submitted', $report['students'][0]['activities'][0]['status']);
        $this->assertSame(10.0, $report['lesson_max']);
    }

    public function testMarkedAssignmentUsesTheSavedMark(): void
    {
        $item = $this->activity(1, 10, 'assignment');
        $item['max_marks'] = 10;
        $report = LessonResultBuilder::analyse(
            [$item],
            [],
            [$this->student(7)],
            [],
            [],
            [],
            [],
            null,
            [7 => [1 => ['status' => 'marked', 'marks_awarded' => 6, 'teacher_comment' => 'Show the cache example.']]]
        );
        $row = $report['students'][0];
        $this->assertSame(6.0, $row['obtained']);
        $this->assertSame(6.0, $row['assignment_obtained']);
        $this->assertSame('Show the cache example.', $row['activities'][0]['feedback']);
    }

    /**
     * @return array<string,mixed>
     */
    private function activity(int $itemId, int $activityId, string $type): array
    {
        return [
            'id' => $itemId,
            'item_type' => 'activity',
            'activity_type' => $type,
            'activity_id' => $activityId,
            'title' => $type,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function question(int $id, int $activityId, string $type, float $marks, ?int $correct): array
    {
        return [
            'id' => $id,
            'activity_id' => $activityId,
            'question_type' => $type,
            'prompt' => 'Q' . $id,
            'choices' => ['A', 'B', 'C', 'D'],
            'correct_index' => $correct,
            'marks' => $marks,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function student(int $id): array
    {
        return [
            'id' => $id,
            'name' => 'Student ' . $id,
            'status' => 'complete',
            'percent' => 100,
            'completed_count' => 1,
            'total' => 1,
            'active_seconds' => 60,
            'last_seen_at' => '2026-09-27 09:58:00',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function answer(int $attemptId, int $questionId, ?int $choice, ?int $correct, ?float $marks): array
    {
        return [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'choice_index' => $choice,
            'is_correct' => $correct,
            'marks_awarded' => $marks,
        ];
    }
}
