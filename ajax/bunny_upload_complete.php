<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\OnlineLessonService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\TeacherVideoLibraryService;

require_staff();
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Invalid security token.');
    }
    $isAdmin = is_admin();
    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
    $kind = strtolower(trim((string)($_POST['kind'] ?? 'recording')));
    $videoId = trim((string)($_POST['video_id'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);

    if ($kind === 'library') {
        $library = new TeacherVideoLibraryService($pdo);
        if (!$library->markUploaded($id, $teacherId, $isAdmin)) {
            throw new RuntimeException('You cannot update this video.');
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    $recordings = new RecordingService($pdo);
    $recording = $recordings->find($id);
    if (!$recording) {
        throw new RuntimeException('Recording not found.');
    }
    $sub = (int)($recording['substitute_teacher_id'] ?? 0);
    if (!\Edexcel\Services\RecordingAccessService::teacherCanManageLesson(
        $teacherId,
        (int)$recording['lesson_teacher_id'],
        $isAdmin,
        $sub
    )) {
        throw new RuntimeException('You cannot update this recording.');
    }
    if ($videoId !== '') {
        $recordings->markUploadFinished($id, $videoId);
    } else {
        $recordings->markUploading($id);
        $pdo->prepare("UPDATE class_recordings SET status = 'processing' WHERE id = ?")->execute([$id]);
    }
    $lessonRow = $recordings->findLesson((int)$recording['timetable_id']);
    if ($lessonRow && OnlineLessonService::supportsDeliveryMode((string)($lessonRow['delivery_mode'] ?? 'physical'))) {
        (new OnlineLessonService($pdo))->getOrCreateForTimetable($lessonRow, $recording, (int)($_SESSION['user_id'] ?? 0));
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
