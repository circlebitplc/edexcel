<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\LearningModuleService;
use Edexcel\Services\RecordingService;

require_staff();
ensure_online_lesson_schema($pdo);

$isAdmin = is_admin();
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$timetableId = (int)($_GET['lesson'] ?? 0);
$submissionId = (int)($_GET['submission'] ?? 0);
$resourceId = (int)($_GET['resource'] ?? 0);
$modules = new LearningModuleService($pdo);

try {
    if ($submissionId > 0) {
        $recordings = new RecordingService($pdo);
        $lesson = $timetableId > 0 ? $recordings->lessonForStaff($timetableId, $teacherId, $isAdmin) : null;
        if (!$lesson) {
            throw new RuntimeException('That file is not available.');
        }
        $stmt = $pdo->prepare('
            SELECT s.file_key, s.file_name, s.mime
            FROM online_lesson_submissions s
            JOIN online_lesson_items i ON i.id = s.item_id
            JOIN online_lessons ol ON ol.id = i.lesson_id
            WHERE s.id = ? AND ol.timetable_id = ?
            LIMIT 1
        ');
        $stmt->execute([$submissionId, $timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_key'])) {
            throw new RuntimeException('That file is not available.');
        }
        online_lesson_stream_file((string)$row['file_key'], (string)($row['file_name'] ?? 'file'), (string)($row['mime'] ?? ''), false);
        exit;
    }
    $resource = $resourceId > 0 ? $modules->resource($resourceId) : null;
    if (!$resource || (!$isAdmin && (int)$resource['owner_user_id'] !== $userId) || empty($resource['file_key'])) {
        throw new RuntimeException('That file is not available.');
    }
    $inline = in_array((string)$resource['resource_type'], ['pdf', 'image'], true);
    online_lesson_stream_file((string)$resource['file_key'], (string)($resource['file_name'] ?? 'file'), (string)($resource['mime'] ?? ''), $inline);
} catch (Throwable $e) {
    http_response_code(404);
    echo 'That file is not available.';
}
