<?php
declare(strict_types=1);

namespace Edexcel\Tests;

use Edexcel\Services\AssessmentScoring;
use Edexcel\Services\AssessmentService;
use Edexcel\Services\TopicMasteryService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AssessmentIntelligenceTest extends TestCase
{
    public function testMcqAutoMarkIsCorrectOrZero(): void
    {
        $ok = AssessmentScoring::autoMark('mcq', 2, 2, [], 5);
        $bad = AssessmentScoring::autoMark('mcq', 1, 2, [], 5);
        self::assertTrue($ok['automatic']);
        self::assertSame(5.0, $ok['marks']);
        self::assertSame(0.0, $bad['marks']);
    }

    public function testNumericAcceptsTolerance(): void
    {
        $ok = AssessmentScoring::autoMark('numeric', '10.01', null, [10], 2, 0.02);
        self::assertTrue($ok['correct']);
        self::assertSame(2.0, $ok['marks']);
    }

    public function testShortAnswerRequiresExactNormalizedMatch(): void
    {
        $ok = AssessmentScoring::autoMark('short', '  JOIN  ', null, ['join'], 1);
        $human = AssessmentScoring::autoMark('essay', 'long answer', null, [], 8);
        self::assertTrue($ok['correct']);
        self::assertNull($human);
    }

    public function testAdaptiveDifficultyMovesStepwise(): void
    {
        self::assertSame('hard', AssessmentScoring::nextAdaptiveDifficulty('medium', true));
        self::assertSame('easy', AssessmentScoring::nextAdaptiveDifficulty('medium', false));
        self::assertSame('medium', AssessmentScoring::nextAdaptiveDifficulty('hard', false));
    }

    public function testObservedDifficultyBands(): void
    {
        self::assertSame('easy', AssessmentScoring::observedDifficulty(80));
        self::assertSame('hard', AssessmentScoring::observedDifficulty(30));
        self::assertSame('medium', AssessmentScoring::observedDifficulty(55));
    }

    public function testTrendAndPredictionAreExplainable(): void
    {
        self::assertSame('improving', AssessmentScoring::trend([58, 64, 71, 76]));
        self::assertSame('declining', AssessmentScoring::trend([80, 70, 60]));
        $text = AssessmentScoring::predictiveWording('declining', ['Normalisation']);
        self::assertStringContainsString('may benefit from additional support', $text);
        self::assertStringNotContainsString('will definitely', $text);
    }

    public function testReadinessAveragesAvailableComponentsOnly(): void
    {
        self::assertSame(70.0, AssessmentScoring::readiness([60, 80, null]));
        self::assertNull(AssessmentScoring::readiness([null, null]));
    }

    public function testPassThresholdAndPercent(): void
    {
        self::assertSame(78.0, AssessmentScoring::percent(39, 50));
        self::assertTrue(AssessmentScoring::passed(78, 40));
        self::assertFalse(AssessmentScoring::passed(39, 40));
    }

    public function testBlueprintNormalization(): void
    {
        $svc = new AssessmentService($this->createMock(PDO::class));
        $bp = $svc->normalizeBlueprint(['qualification' => 'IAL', 'topics' => [['topic' => 'Databases', 'marks' => 15], ['topic' => 'Networks', 'marks' => 10]]]);
        self::assertSame(25.0, $bp['total_marks']);
        self::assertCount(2, $bp['topics']);
        self::assertSame('Databases', $bp['topics'][0]['topic']);
    }

    public function testTopicMasteryStatusBands(): void
    {
        $svc = new TopicMasteryService($this->createMock(PDO::class));
        self::assertSame('strong', $svc->statusFromPercent(85));
        self::assertSame('weak', $svc->statusFromPercent(20));
        self::assertSame('revision_required', $svc->statusFromPercent(45));
    }

    public function testShuffleIsDeterministicForSameSeed(): void
    {
        $a = AssessmentScoring::shuffleSeeded(['q1', 'q2', 'q3', 'q4'], 42);
        $b = AssessmentScoring::shuffleSeeded(['q1', 'q2', 'q3', 'q4'], 42);
        self::assertSame($a, $b);
    }

    public function testPublishedGeneratedPaperRequiresApprovalPath(): void
    {
        self::assertContains('approved', AssessmentService::STATUSES);
        self::assertContains('review', AssessmentService::STATUSES);
        self::assertContains('published', AssessmentService::STATUSES);
    }
}
