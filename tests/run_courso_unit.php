<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CoursoChatService;
use Edexcel\Services\CoursoLearnService;
use Edexcel\Services\CoursoLearnerService;
use Edexcel\Services\CoursoQuizBank;
use Edexcel\Services\CoursoQuizService;

$failed = 0;
$passed = 0;

function expect_true(bool $ok, string $label): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo " PASS  {$label}\n";
        return;
    }
    $failed++;
    echo " FAIL  {$label}\n";
}

expect_true(
    CoursoLearnerService::streakFromDates(['2026-08-30', '2026-08-29', '2026-08-28'], '2026-08-30') === 3,
    'streak counts consecutive days including today'
);
expect_true(
    CoursoLearnerService::streakFromDates(['2026-08-29', '2026-08-28'], '2026-08-30') === 2,
    'streak still counts if today is in progress (yesterday chain)'
);
expect_true(
    CoursoLearnerService::streakFromDates(['2026-08-20'], '2026-08-30') === 0,
    'broken streak is zero'
);

$facts = CoursoLearnerService::extractFactsFromMessage('My goal is an A in IAL Physics and I am weak at kinematics');
expect_true(
    ($facts['goal'] ?? '') !== '' && ($facts['weakness'] ?? '') !== '',
    'chat memory extracts goal and weakness'
);

expect_true(
    CoursoLearnerService::searchScore('ict', 'IGCSE ICT', true, 'ict kinematics') > CoursoLearnerService::searchScore('ict', 'Year 6 Art', false, ''),
    'search ranks enrolled + focus-area matches higher'
);

$steps = (new ReflectionClass(CoursoLearnerService::class))->newInstanceWithoutConstructor()->nextSteps([
    'homework' => [['title' => 'Past paper 1', 'due_date' => '2026-09-01']],
    'weaknesses' => ['Physics (40%)'],
    'streak' => 4,
    'unwatched' => [],
    'join' => [],
    'next_exam' => null,
    'next_class' => null,
]);
expect_true(
    $steps !== [] && str_contains($steps[0]['title'], 'Past paper'),
    'next steps put due homework first'
);

expect_true(CoursoQuizService::scoreItem(2, 2) === true, 'correct quiz choice scores');
expect_true(CoursoQuizService::scoreItem(2, 0) === false, 'wrong quiz choice fails');

$bank = CoursoQuizBank::pick(['physics'], 'foundation', 3);
expect_true($bank !== [] && isset($bank[0]['prompt'], $bank[0]['choices']), 'quiz bank returns physics items');

$quiz = (new ReflectionClass(CoursoQuizService::class))->newInstanceWithoutConstructor();
$parsed = $quiz->parseGenerated('{"questions":[{"prompt":"2+2","choices":["3","4"],"correct_index":1,"explanation":"Addition","example":"Apples"}]}');
expect_true(count($parsed) === 1 && $parsed[0]['correct_index'] === 1, 'AI quiz JSON parser');

$chat = (new ReflectionClass(CoursoChatService::class))->newInstanceWithoutConstructor();
$blurb = $chat->progressBlurb([
    'streak' => 5,
    'completed_lessons' => 8,
    'recordings_watched' => 2,
    'quizzes_done' => 1,
    'strengths' => ['Maths (80%)'],
    'weaknesses' => ['Physics (40%)'],
    'next_steps' => [['title' => 'Practise Physics']],
]);
expect_true(str_contains($blurb, '5 days') && str_contains($blurb, 'Physics'), 'progress blurb includes streak and focus');

$fallback = $chat->fallbackReply('please give me a practice quiz', [
    'difficulty' => 'core',
    'weaknesses' => ['Algebra'],
    'next_steps' => [['title' => 'Practise Algebra', 'why' => 'low marks', 'href' => '#', 'cta' => 'Go']],
    'homework' => [],
]);
expect_true(str_contains(strtolower($fallback), 'practice') || str_contains(strtolower($fallback), 'quiz'), 'fallback points at practice');

expect_true(
    \Edexcel\Services\WhatsAppAssistant::looksLikeCollegeFactQuestion('What is the ICT teacher phone number?'),
    'class/teacher phone is treated as a college fact question'
);
expect_true(
    \Edexcel\Services\WhatsAppAssistant::looksLikeCollegeFactQuestion('when is my next class'),
    'timetable question is a college fact question'
);
expect_true(
    !\Edexcel\Services\WhatsAppAssistant::looksLikeCollegeFactQuestion('explain how quadratic graphs work'),
    'study-topic questions are not treated as directory lookups'
);

expect_true(
    CoursoLearnService::overlapScore('ICT teacher phone number', 'what is the ICT teacher phone?') > 0.4,
    'similar questions score as related for learning'
);
expect_true(
    CoursoLearnService::looksLikeCorrection('that is too vague, explain more'),
    'weak-answer follow-ups are detected'
);
expect_true(
    !CoursoLearnService::looksLikeCorrection('when is my physics class'),
    'normal questions are not treated as corrections'
);
$prompt = CoursoLearnService::lessonsPrompt(
    [['question' => 'Who teaches ICT?', 'answer' => 'Mr Silva, 077…', 'helpful' => 2, 'unhelpful' => 0]],
    true,
    false
);
expect_true(str_contains($prompt, 'Go deeper') && str_contains($prompt, 'ICT'), 'learned lessons are injected when a question is repeated');

$pub = (new ReflectionClass(\Edexcel\Services\CoursoPublicChatService::class))->newInstanceWithoutConstructor();
$hello = $pub->fallbackReply('hello there');
expect_true(str_contains(strtolower($hello), 'edexcel') && !str_contains(strtolower($hello), 'courso'), 'public greeting names the college AI');
$join = $pub->fallbackReply('how do I register as a new student');
expect_true(str_contains(strtolower($join), 'register') || str_contains(strtolower($join), 'account'), 'visitors get a registration path');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
