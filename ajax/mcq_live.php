<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\McqLiveService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!function_exists('is_logged_in') || !is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sign in again.']);
    exit;
}
require_student();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Invalid security token.');
    }
    $studentId = (int)($_SESSION['user_id'] ?? 0);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $itemId = (int)($_POST['item_id'] ?? 0);
    $event = (string)($_POST['event'] ?? '');
    if (!in_array($event, ['open', 'question', 'answer', 'heartbeat', 'leave'], true)) {
        throw new RuntimeException('Unknown event.');
    }
    if ($studentId < 1 || $itemId < 1) {
        throw new RuntimeException('Lesson item was not found.');
    }
    $lessons = new OnlineLessonService($pdo);
    $item = $lessons->item($itemId);
    if (!$item || (string)$item['item_type'] !== 'activity') {
        throw new RuntimeException('That is not a question activity.');
    }

    if ($event === 'open') {
        $lesson = $lessons->find((int)$item['lesson_id']);
        if (!$lesson || empty($lesson['published'])) {
            throw new RuntimeException('That lesson is not available.');
        }
        $recordings = new RecordingService($pdo);
        $timetable = $recordings->findLesson((int)$lesson['timetable_id']);
        $fees = new StudentLessonFeeService($pdo);
        if (!$timetable || !empty($timetable['deleted_at']) || !$fees->isEnrolled($studentId, $timetable)) {
            throw new RuntimeException('You cannot open that lesson.');
        }
        $recording = $recordings->activeForLesson((int)$lesson['timetable_id']);
        $state = OnlineLessonService::feeUnlockState(
            $fees->resolve($studentId, $timetable, $recording ? (int)$recording['id'] : null)
        );
        if ($state !== RecordingAccessService::ACCESS_GRANTED) {
            throw new RuntimeException('Pay for this class to continue the lesson.');
        }
        $availability = OnlineLessonService::availabilityWindow($lesson, $timetable);
        if (empty($availability['open'])) {
            throw new RuntimeException((string)($availability['message'] ?: 'This lesson is not open yet.'));
        }
        $items = $lessons->items((int)$lesson['id']);
        $completed = $lessons->completedItemIds($studentId, (int)$lesson['id']);
        if (!OnlineLessonService::canOpenItem($items, $completed, $itemId, (int)$lesson['sequential'] === 1)) {
            throw new RuntimeException('Finish the previous part of the lesson first.');
        }
    }

    $result = (new McqLiveService($pdo, $lessons))->record($studentId, $item, $event, [
        'question_id' => (int)($_POST['question_id'] ?? 0),
        'choice' => isset($_POST['choice']) && $_POST['choice'] !== '' ? (int)$_POST['choice'] : null,
        'text' => !empty($_POST['text']),
        'answers' => substr((string)($_POST['answers'] ?? ''), 0, 20000),
        'interacted' => !empty($_POST['interacted']),
        'visible' => !empty($_POST['visible']),
    ]);
    echo json_encode([
        'ok' => true,
        'tracked' => (bool)($result['tracked'] ?? false),
        'heartbeat' => McqLiveService::HEARTBEAT_SECONDS,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
