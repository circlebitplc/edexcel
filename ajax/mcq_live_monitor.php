<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\McqLiveService;
use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!function_exists('is_logged_in') || !is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sign in again.']);
    exit;
}
require_staff();

$fail = static function (int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
};

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $fail(405, 'GET required.');
}

try {
    ensure_online_lesson_schema($pdo);
    $timetableId = (int)($_GET['lesson'] ?? 0);
    $staffLesson = $timetableId > 0 ? (new RecordingService($pdo))->lessonForStaff($timetableId, $teacherId, $isAdmin) : null;
    if (!$staffLesson) {
        $fail(403, 'You cannot view this class.');
    }
    $lessons = new OnlineLessonService($pdo);
    $onlineLesson = $lessons->findByTimetable($timetableId);
    if (!$onlineLesson) {
        $fail(404, 'This class does not have a video lesson yet.');
    }
    $lessonId = (int)$onlineLesson['id'];
    $classId = (int)$staffLesson['class_id'];

    $item = null;
    $itemId = (int)($_GET['item'] ?? 0);
    if ($itemId > 0) {
        $item = $lessons->item($itemId);
        if (!$item || (int)$item['lesson_id'] !== $lessonId || (string)$item['item_type'] !== 'activity') {
            $fail(404, 'That activity is not part of this lesson.');
        }
    }
    $attemptNo = max(0, min(1000, (int)($_GET['attempt'] ?? 0)));
    $live = new McqLiveService($pdo, $lessons);
    $now = time();
    $action = (string)($_GET['action'] ?? 'list');

    if ($action === 'detail') {
        $studentId = (int)($_GET['student'] ?? 0);
        if ($studentId < 1 || $item === null) {
            $fail(400, 'Choose a student and activity.');
        }
        $detail = $live->studentDetail($lessonId, $classId, $item, $studentId, $now);
        if ($detail === null) {
            $fail(404, 'No live attempt for that student.');
        }
        $detail['activity_title'] = (string)$item['title'];
        echo json_encode(['ok' => true, 'now' => date('Y-m-d H:i:s', $now), 'student' => $detail], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if ($action !== 'list') {
        $fail(400, 'Unknown action.');
    }

    $since = (string)($_GET['since'] ?? '');
    $since = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since) ? $since : null;
    $rows = $live->monitorRows($lessonId, $classId, $itemId, $attemptNo, $since, $now);
    echo json_encode([
        'ok' => true,
        'now' => date('Y-m-d H:i:s', $now),
        'full' => $since === null,
        'idle_seconds' => McqLiveService::idleSeconds(),
        'offline_seconds' => McqLiveService::offlineSeconds(),
        'rows' => $rows,
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('mcq live monitor: ' . $e->getMessage());
    $fail(500, 'The live monitor could not load.');
}
