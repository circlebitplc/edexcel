<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingAccessService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_student();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Invalid security token.');
    }
    $studentId = (int)($_SESSION['user_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $seconds = (int)($_POST['seconds'] ?? 0);
    if ($studentId < 1 || $itemId < 1) {
        throw new RuntimeException('Lesson item was not found.');
    }
    $lessons = new OnlineLessonService($pdo);
    $item = $lessons->item($itemId);
    $lesson = $item ? $lessons->find((int)$item['lesson_id']) : null;
    if (!$item || !$lesson || empty($lesson['published'])) {
        throw new RuntimeException('That lesson is not available.');
    }
    $recordings = new RecordingService($pdo);
    $timetable = $recordings->findLesson((int)$lesson['timetable_id']);
    $fees = new StudentLessonFeeService($pdo);
    if (!$timetable || !$fees->isEnrolled($studentId, $timetable)) {
        throw new RuntimeException('You cannot update that lesson.');
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
    $lessons->recordActiveTime($studentId, (int)$lesson['id'], $itemId, $seconds, empty($_POST['final']));
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
