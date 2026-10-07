<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\RecordingService;
use Edexcel\Services\TeacherVideoLibraryService;

require_staff();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    $csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($csrf)) {
        throw new RuntimeException('Invalid security token.');
    }
    ensure_recordings_schema($pdo);
    $isAdmin = is_admin();
    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
    if (!$isAdmin && $teacherId < 1) {
        throw new RuntimeException('Teacher account is not linked.');
    }

    $kind = strtolower(trim((string)($_POST['kind'] ?? 'recording')));
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $bunnyCfg = bunny_config($pdo);
    if (empty($bunnyCfg['enabled'])) {
        throw new RuntimeException('Bunny Stream is disabled. Ask an administrator to enable it in Settings.');
    }

    if ($kind === 'library') {
        if ($title === '') {
            throw new RuntimeException('Enter a video title.');
        }
        if (!$isAdmin && $teacherId < 1) {
            throw new RuntimeException('Not allowed.');
        }
        $ownerId = $isAdmin ? (int)($_POST['teacher_id'] ?? $teacherId) : $teacherId;
        if ($ownerId < 1) {
            $ownerId = $teacherId;
        }
        if (!$isAdmin && $ownerId !== $teacherId) {
            throw new RuntimeException('You can only upload to your own library.');
        }
        $bunny = BunnyVideoService::forTeacher($pdo, $ownerId);
        $created = $bunny->createVideo($title);
        $videoId = (string)($created['guid'] ?? '');
        $library = new TeacherVideoLibraryService($pdo);
        $visibility = (string)($_POST['visibility'] ?? 'private');
        $row = $library->createDraft(
            $ownerId,
            $videoId,
            $bunny->libraryId(),
            $title,
            $description,
            (int)($_POST['subject_id'] ?? 0) ?: null,
            (int)($_POST['class_id'] ?? 0) ?: null,
            trim((string)($_POST['tags'] ?? '')),
            $visibility
        );
        $tus = $bunny->tusAuthorization($videoId);
        log_audit($pdo, 'library_upload_start', 'teacher_video_library', (int)$row['id'], null, ['teacher_id' => $ownerId]);
        echo json_encode([
            'ok' => true,
            'kind' => 'library',
            'id' => (int)$row['id'],
            'tus' => $tus,
        ]);
        exit;
    }

    $timetableId = (int)($_POST['timetable_id'] ?? 0);
    $recordings = new RecordingService($pdo);
    $lesson = $recordings->lessonForStaff($timetableId, $teacherId, $isAdmin);
    if (!$lesson) {
        throw new RuntimeException('You cannot upload a recording for this lesson.');
    }
    if (strtolower((string)($lesson['lesson_status'] ?? 'scheduled')) === 'cancelled') {
        throw new RuntimeException('This lesson is cancelled.');
    }
    if ($title === '') {
        $title = trim((string)$lesson['subject_name'] . ' — ' . date('d M Y', strtotime((string)$lesson['date'])));
    }
    $recording = $recordings->getOrCreateForLesson($lesson, (int)($_SESSION['user_id'] ?? 0), $title, $description);
    $lessonTeacherId = (int)($lesson['teacher_id'] ?? 0);
    $bunny = BunnyVideoService::forTeacher($pdo, $lessonTeacherId);
    $created = $bunny->createVideo($title);
    $videoId = (string)($created['guid'] ?? '');
    $recordings->addAsset((int)$recording['id'], $videoId, $bunny->libraryId(), $title);
    $recordings->markUploading((int)$recording['id']);
    $tus = $bunny->tusAuthorization($videoId);
    log_audit($pdo, 'recording_upload_start', 'class_recordings', (int)$recording['id'], null, [
        'timetable_id' => $timetableId,
        'teacher_id' => (int)$lesson['teacher_id'],
    ]);
    echo json_encode([
        'ok' => true,
        'kind' => 'recording',
        'id' => (int)$recording['id'],
        'tus' => $tus,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
