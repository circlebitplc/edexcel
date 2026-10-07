<?php
declare(strict_types=1);

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonAuthoringService;
use PHPUnit\Framework\TestCase;

final class LessonAuthoringTest extends TestCase
{
    public function testPublishIsBlockedWhenAnMcqHasNoCorrectAnswer(): void
    {
        $issues = LessonAuthoringService::issues(
            ['title' => 'CPU', 'archived' => 0],
            [['title' => 'Quiz', 'item_type' => 'activity', 'activity_id' => 4, 'body' => null, 'link_url' => null]],
            [['activity_id' => 4, 'question_type' => 'mcq', 'correct_index' => null, 'marks' => 1]]
        );
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('no correct answer', implode(' ', $issues));
    }

    public function testEmptyPageAndInvalidLinkAreReported(): void
    {
        $issues = LessonAuthoringService::issues(
            ['title' => 'CPU', 'archived' => 0],
            [
                ['title' => 'Notes', 'item_type' => 'page', 'activity_id' => null, 'body' => '   ', 'link_url' => null],
                ['title' => 'Slides', 'item_type' => 'external_link', 'activity_id' => null, 'body' => null, 'link_url' => 'javascript:alert(1)'],
            ],
            []
        );
        $text = implode(' ', $issues);
        $this->assertStringContainsString('empty text page', $text);
        $this->assertStringContainsString('invalid link', $text);
    }

    public function testReadyLessonHasNoIssues(): void
    {
        $issues = LessonAuthoringService::issues(
            ['title' => 'CPU', 'archived' => 0],
            [['title' => 'Quiz', 'item_type' => 'activity', 'activity_id' => 4, 'body' => null, 'link_url' => null]],
            [['activity_id' => 4, 'question_type' => 'mcq', 'correct_index' => 1, 'marks' => 2]]
        );
        $this->assertSame([], $issues);
    }

    public function testTemplateSnapshotOmitsRecordingsAndStudentData(): void
    {
        $snapshot = LessonAuthoringService::snapshot(
            ['title' => 'CPU', 'plan_objectives' => "Explain cache\n"],
            [['id' => 3, 'title' => 'Memory', 'estimated_minutes' => 15]],
            [
                ['id' => 1, 'item_type' => 'video', 'title' => 'Clip', 'section_id' => 3, 'video_asset_id' => 99, 'activity_id' => null],
                ['id' => 2, 'item_type' => 'activity', 'title' => 'Quiz', 'section_id' => 3, 'activity_id' => 8, 'activity_type' => 'mcq', 'required' => 1],
            ],
            [8 => [[
                'question_type' => 'mcq',
                'prompt' => 'What is cache?',
                'choices' => ['RAM', 'Fast memory'],
                'correct_index' => 1,
                'marks' => 2,
                'explanation' => 'It is faster than RAM.',
            ]]]
        );
        $this->assertSame('Explain cache', trim($snapshot['plan']['objectives']));
        $this->assertCount(1, $snapshot['sections'][0]['items']);
        $this->assertSame('activity', $snapshot['sections'][0]['items'][0]['type']);
        $this->assertSame(2.0, $snapshot['sections'][0]['items'][0]['questions'][0]['marks']);
        $this->assertStringNotContainsString('video_asset', json_encode($snapshot));
    }

    public function testDurationWarningUsesThePlannedMinutes(): void
    {
        $warning = LessonAuthoringService::durationWarning(60, [
            ['estimated_minutes' => 40],
            ['estimated_minutes' => 32],
        ]);
        $this->assertSame('Estimated lesson duration exceeds the planned 60 minutes by 12 minutes.', $warning);
        $this->assertNull(LessonAuthoringService::durationWarning(null, [['estimated_minutes' => 90]]));
    }

    public function testDurationStatusFitsInsideThePlannedTime(): void
    {
        $status = LessonAuthoringService::durationStatus(90, [
            ['estimated_minutes' => 35],
            ['estimated_minutes' => 47],
        ]);
        $this->assertSame('fits', $status['state']);
        $this->assertSame(82, $status['estimated']);
    }

    public function testShortAnswerDoesNotNeedACorrectOption(): void
    {
        $issues = LessonAuthoringService::issues(
            ['title' => 'CPU', 'archived' => 0],
            [['id' => 2, 'title' => 'Check', 'item_type' => 'activity', 'activity_id' => 4, 'activity_type' => 'short', 'body' => null, 'link_url' => null]],
            [['activity_id' => 4, 'question_type' => 'short', 'correct_index' => null, 'marks' => 4]]
        );
        $this->assertSame([], $issues);
    }

    public function testAssignmentNeedsInstructionsAndMarks(): void
    {
        $issues = LessonAuthoringService::issues(
            ['title' => 'CPU', 'archived' => 0],
            [['id' => 2, 'title' => 'Homework', 'item_type' => 'activity', 'activity_id' => 4, 'activity_type' => 'homework', 'activity_instructions' => '', 'max_marks' => 0, 'allow_text' => 1, 'allow_file' => 1, 'body' => null, 'link_url' => null]],
            []
        );
        $text = implode(' ', $issues);
        $this->assertStringContainsString('no instructions', $text);
        $this->assertStringContainsString('no maximum marks', $text);
    }

    public function testEmptySectionIsReported(): void
    {
        $issues = LessonAuthoringService::issues(
            ['title' => 'CPU', 'archived' => 0],
            [['id' => 2, 'title' => 'Notes', 'item_type' => 'page', 'section_id' => 1, 'activity_id' => null, 'body' => 'Cache is fast memory.', 'link_url' => null]],
            [],
            [['id' => 1, 'title' => 'Memory'], ['id' => 9, 'title' => 'Unused']]
        );
        $this->assertStringContainsString('Unused is an empty section', implode(' ', $issues));
    }

    public function testCopiedQuestionDoesNotChangeTheOriginal(): void
    {
        $original = [
            'question_type' => 'mcq',
            'prompt' => 'What is cache?',
            'choices' => ['RAM', 'Fast memory'],
            'correct_index' => 1,
            'marks' => 3,
            'explanation' => 'Faster than RAM.',
        ];
        $copy = LearningModuleService::independentQuestionCopy($original);
        $copy['prompt'] = 'Changed copy';
        $copy['marks'] = 9;
        $this->assertSame('What is cache?', $original['prompt']);
        $this->assertSame(3, $original['marks']);
        $this->assertNull($copy['id'] ?? null);
    }

    public function testCriteriaAwardIsCappedAtTheQuestionMaximum(): void
    {
        $award = LearningModuleService::criteriaAward([
            ['id' => 1, 'marks' => 1],
            ['id' => 2, 'marks' => 2],
            ['id' => 3, 'marks' => 1],
        ], [1, 2, 3]);
        $this->assertSame(4.0, $award);
        $this->assertSame(4.0, LearningModuleService::cappedAward($award, 4));
        $this->assertSame(3.0, LearningModuleService::cappedAward(9, 3));
    }
}
