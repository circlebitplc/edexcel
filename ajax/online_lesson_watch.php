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
    if (function_exists('student_require_presence')) {
        student_require_presence($pdo);
    }
    ensure_online_lesson_schema($pdo);

    $studentId = (int)($_SESSION['user_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $seconds = (int)($_POST['seconds'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 0);
    $ended = !empty($_POST['ended']);
    if ($studentId < 1 || $itemId < 1) {
        throw new RuntimeException('Lesson item was not found.');
    }

    $lessons = new OnlineLessonService($pdo);
    $item = $lessons->item($itemId);
    if (!$item) {
        throw new RuntimeException('Lesson item was not found.');
    }
    $lesson = $lessons->find((int)$item['lesson_id']);
    if (!$lesson || empty($lesson['published'])) {
        throw new RuntimeException('That lesson is not available.');
    }
    $recordings = new RecordingService($pdo);
    $timetable = $recordings->findLesson((int)$lesson['timetable_id']);
    $fees = new StudentLessonFeeService($pdo);
    if (
        !$timetable
        || !$fees->isEnrolled($studentId, $timetable)
        || !OnlineLessonService::supportsDeliveryMode((string)($timetable['delivery_mode'] ?? 'physical'))
    ) {
        throw new RuntimeException('You cannot update that lesson.');
    }
    $recording = $recordings->activeForLesson((int)$lesson['timetable_id']);
    if (!$recording) {
        throw new RuntimeException('You cannot update that lesson.');
    }
    $state = (new RecordingAccessService($pdo))->evaluate($studentId, (int)$recording['id'])['state'] ?? '';
    if ($state !== RecordingAccessService::ACCESS_GRANTED) {
        throw new RuntimeException('Pay for this class to continue the lesson.');
    }
    $availability = OnlineLessonService::availabilityWindow($lesson, $timetable);
    if (empty($availability['open'])) {
        throw new RuntimeException((string)($availability['message'] ?: 'This lesson is not open yet.'));
    }

    $watch = $lessons->recordWatchProgress($studentId, $itemId, $seconds, $duration, $ended);
    echo json_encode(['ok' => true, 'watch' => $watch]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
