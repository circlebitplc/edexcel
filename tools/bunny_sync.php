<?php
declare(strict_types=1);

/**
 * Poll Bunny for recordings still processing. Safe to run every 5–10 minutes.
 * Timezone: Asia/Colombo.
 * Uses each asset's teacher library ID (not a shared global library).
 */
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\RecordingService;

date_default_timezone_set('Asia/Colombo');

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No database.\n");
    exit(1);
}

ensure_campus_schema($pdo);
ensure_recordings_schema($pdo);
ops_job_start($pdo, 'bunny_sync');

$recordings = new RecordingService($pdo);

$assetStmt = $pdo->query("
    SELECT a.bunny_video_id, a.bunny_library_id
    FROM class_recording_assets a
    JOIN class_recordings cr ON cr.id = a.recording_id
    WHERE cr.deleted_at IS NULL
      AND cr.status IN ('uploading','processing','draft')
      AND a.created_at <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ORDER BY a.id ASC
    LIMIT 20
");
$libStmt = $pdo->query("
    SELECT bunny_video_id, bunny_library_id
    FROM teacher_video_library
    WHERE deleted_at IS NULL
      AND status IN ('uploading','processing','draft')
      AND created_at <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ORDER BY id ASC
    LIMIT 10
");

$rows = array_merge(
    $assetStmt ? ($assetStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [],
    $libStmt ? ($libStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : []
);

$clients = [];
$ok = 0;
$fail = 0;
foreach ($rows as $row) {
    $videoId = (string)($row['bunny_video_id'] ?? '');
    $libraryId = trim((string)($row['bunny_library_id'] ?? ''));
    if ($videoId === '') {
        continue;
    }
    try {
        $cacheKey = $libraryId !== '' ? $libraryId : ('video:' . $videoId);
        if (!isset($clients[$cacheKey])) {
            $client = $libraryId !== ''
                ? BunnyVideoService::tryForLibraryId($pdo, $libraryId)
                : BunnyVideoService::tryForStoredVideo($pdo, $videoId);
            if ($client === null) {
                $fail++;
                continue;
            }
            $clients[$cacheKey] = $client;
        }
        $bunny = $clients[$cacheKey];
        $video = $bunny->getVideo($videoId);
        $recordings->applyBunnyMetadata($videoId, $video, $bunny);
        $ok++;
    } catch (Throwable $e) {
        $fail++;
        error_log('bunny_sync: video lookup failed');
    }
}

$cfg = bunny_config($pdo);
if (!empty($cfg['retention_enabled'])) {
    $days = (int)$cfg['retention_days'];
    $pdo->prepare("
        UPDATE class_recordings
        SET retention_status = 'scheduled_for_deletion',
            scheduled_delete_at = NOW()
        WHERE retention_status = 'active'
          AND status = 'ready'
          AND deleted_at IS NULL
          AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ")->execute([$days]);
}

echo "Bunny sync complete. updated={$ok} failed={$fail}\n";
ops_job_finish($pdo, 'bunny_sync', $fail === 0, "updated={$ok} failed={$fail}");

if (PHP_SAPI === 'cli') {
    try {
        require_once __DIR__ . '/../config/classroom.php';
        if (function_exists('classroom_run_reminder_job')) {
            classroom_run_reminder_job($pdo, false, true);
        }
        if (class_exists(\Edexcel\Services\ClassroomLiveRecordingService::class)) {
            $n = (new \Edexcel\Services\ClassroomLiveRecordingService($pdo))->recoverRecent();
            echo "Live recording recover={$n}\n";
        }
    } catch (Throwable $e) {
        error_log('classroom jobs from bunny_sync: ' . $e->getMessage());
    }
}
