<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\LessonAccessService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\StudentLessonFeeService;

require_student();
ensure_online_lesson_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? 0);
$timetableId = (int)($_GET['lesson'] ?? 0);
$itemId = (int)($_GET['item'] ?? 0);

try {
    $recordings = new RecordingService($pdo);
    $lessons = new OnlineLessonService($pdo);
    $fees = new StudentLessonFeeService($pdo);
    $timetable = $recordings->findLesson($timetableId);
    $lesson = $lessons->findPublishedByTimetable($timetableId);
    if (!$timetable || !$lesson || !$fees->isEnrolled($studentId, $timetable)) {
        throw new RuntimeException('That file is not available.');
    }
    $fee = $fees->resolve($studentId, $timetable, null);
    if (OnlineLessonService::feeUnlockState($fee) !== 'ACCESS_GRANTED') {
        throw new RuntimeException('That file is not available.');
    }
    if (empty(OnlineLessonService::availabilityWindow($lesson, $timetable)['open'])
        || (new LessonAccessService($pdo))->unmetFor($studentId, (int)$lesson['id']) !== []) {
        throw new RuntimeException('That file is not available.');
    }
    $item = $lessons->item($itemId);
    if (!$item || (int)$item['lesson_id'] !== (int)$lesson['id'] || (string)$item['item_type'] !== 'resource') {
        throw new RuntimeException('That file is not available.');
    }
    $resource = (new LearningModuleService($pdo))->resource((int)($item['resource_id'] ?? 0));
    if (!$resource || !empty($resource['archived']) || empty($resource['file_key'])) {
        throw new RuntimeException('That file is not available.');
    }
    if ((int)($item['required'] ?? 1) === 1) {
        $lessons->markItemComplete($studentId, $itemId);
    }
    $inline = in_array((string)$resource['resource_type'], ['pdf', 'image'], true);
    online_lesson_stream_file((string)$resource['file_key'], (string)($resource['file_name'] ?? 'file'), (string)($resource['mime'] ?? ''), $inline);
} catch (Throwable $e) {
    http_response_code(404);
    echo 'That file is not available.';
}
