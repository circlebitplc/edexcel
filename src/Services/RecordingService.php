<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class RecordingService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function lessonForStaff(int $timetableId, int $teacherId, bool $isAdmin): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
                   r.name AS room_name, tt.lesson_status
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            LEFT JOIN rooms r ON r.id = tt.room_id
            WHERE tt.id = ? AND tt.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$timetableId]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lesson) {
            return null;
        }
        $sub = (int)($lesson['substitute_teacher_id'] ?? 0);
        if (!RecordingAccessService::teacherCanManageLesson($teacherId, (int)$lesson['teacher_id'], $isAdmin, $sub)) {
            return null;
        }
        return $lesson;
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrCreateForLesson(array $lesson, int $createdBy, string $title = '', string $description = ''): array
    {
        $timetableId = (int)$lesson['id'];
        $existing = $this->activeForLesson($timetableId);
        if ($existing) {
            if ($title !== '' && $title !== (string)$existing['title']) {
                $this->pdo->prepare('UPDATE class_recordings SET title = ?, description = ? WHERE id = ?')
                    ->execute([$title, $description !== '' ? $description : $existing['description'], $existing['id']]);
                $existing['title'] = $title;
                if ($description !== '') {
                    $existing['description'] = $description;
                }
            }
            return $existing;
        }

        $defaultTitle = $title !== ''
            ? $title
            : trim((string)($lesson['subject_name'] ?? 'Class') . ' — ' . date('d M Y', strtotime((string)$lesson['date'])));
        $this->pdo->prepare("
            INSERT INTO class_recordings (timetable_id, teacher_id, title, description, status, created_by)
            VALUES (?, ?, ?, ?, 'draft', ?)
        ")->execute([
            $timetableId,
            (int)$lesson['teacher_id'],
            $defaultTitle,
            $description !== '' ? $description : null,
            $createdBy > 0 ? $createdBy : null,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $row = $this->find($id);
        if (!$row) {
            throw new RuntimeException('Could not create recording.');
        }
        return $row;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, tt.date, tt.start_time, tt.end_time, tt.class_id, tt.subject_id,
                   tt.teacher_id AS lesson_teacher_id, tt.class_fee_per_student, tt.deleted_at AS lesson_deleted_at,
                   tt.lesson_status, tt.substitute_teacher_id,
                   s.name AS subject_name, c.name AS class_name, t.name AS teacher_name
            FROM class_recordings cr
            JOIN timetable tt ON tt.id = cr.timetable_id
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            WHERE cr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function activeForLesson(int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM class_recordings
            WHERE timetable_id = ? AND deleted_at IS NULL AND status <> 'deleted'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findLesson(int $timetableId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
                   tt.teacher_id AS lesson_teacher_id
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            WHERE tt.id = ?
            LIMIT 1
        ");
        $stmt->execute([$timetableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Latest recording row keyed by timetable lesson id.
     *
     * @param list<int> $timetableIds
     * @return array<int,array{id:int,status:string}>
     */
    public function mapForLessons(array $timetableIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $timetableIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT cr.timetable_id, cr.id, cr.status
            FROM class_recordings cr
            INNER JOIN (
                SELECT timetable_id, MAX(id) AS max_id
                FROM class_recordings
                WHERE deleted_at IS NULL AND status <> 'deleted' AND timetable_id IN ($placeholders)
                GROUP BY timetable_id
            ) latest ON latest.max_id = cr.id
        ");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['timetable_id']] = [
                'id' => (int)$row['id'],
                'status' => (string)$row['status'],
            ];
        }
        return $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function assets(int $recordingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM class_recording_assets
            WHERE recording_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$recordingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addAsset(int $recordingId, string $bunnyVideoId, string $libraryId, string $title = ''): int
    {
        $sortStmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM class_recording_assets WHERE recording_id = ?');
        $sortStmt->execute([$recordingId]);
        $sort = (int)$sortStmt->fetchColumn();
        $this->pdo->prepare("
            INSERT INTO class_recording_assets
                (recording_id, bunny_video_id, bunny_library_id, title, sort_order, processing_status)
            VALUES (?, ?, ?, ?, ?, 'created')
        ")->execute([$recordingId, $bunnyVideoId, $libraryId, $title !== '' ? $title : null, $sort]);
        $this->pdo->prepare("UPDATE class_recordings SET status = 'uploading' WHERE id = ? AND status IN ('draft','failed')")
            ->execute([$recordingId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function markUploading(int $recordingId): void
    {
        $this->pdo->prepare("UPDATE class_recordings SET status = 'uploading' WHERE id = ? AND deleted_at IS NULL")
            ->execute([$recordingId]);
    }

    public function markUploadFinished(int $recordingId, string $bunnyVideoId): void
    {
        $this->pdo->prepare("
            UPDATE class_recording_assets
            SET uploaded_at = COALESCE(uploaded_at, NOW()), processing_status = 'uploaded', bunny_status = 7
            WHERE recording_id = ? AND bunny_video_id = ?
        ")->execute([$recordingId, $bunnyVideoId]);
        $this->pdo->prepare("UPDATE class_recordings SET status = 'processing' WHERE id = ? AND status IN ('draft','uploading')")
            ->execute([$recordingId]);
    }

    /**
     * Apply Bunny metadata to an asset and roll up parent recording status.
     *
     * @param array<string,mixed> $video Bunny GET video payload
     */
    public function applyBunnyMetadata(string $bunnyVideoId, array $video, ?BunnyVideoService $bunny = null): void
    {
        $statusInt = BunnyVideoService::statusFromPayload($video);
        $mapped = $statusInt >= 0 ? BunnyVideoService::mapStatus($statusInt) : null;
        $duration = null;
        foreach (['length', 'Length', 'duration'] as $lenKey) {
            if (isset($video[$lenKey]) && $video[$lenKey] !== '' && $video[$lenKey] !== null) {
                $duration = (int)$video[$lenKey];
                break;
            }
        }
        $thumbName = (string)($video['thumbnailFileName'] ?? $video['ThumbnailFileName'] ?? '');
        $progress = isset($video['encodeProgress']) ? (int)$video['encodeProgress'] : (isset($video['EncodeProgress']) ? (int)$video['EncodeProgress'] : null);
        $thumb = '';
        if ($bunny instanceof BunnyVideoService && $bunnyVideoId !== '') {
            $thumb = $bunny->thumbnailUrl($bunnyVideoId, $thumbName !== '' ? $thumbName : null);
        }

        $asset = $this->pdo->prepare('SELECT * FROM class_recording_assets WHERE bunny_video_id = ? LIMIT 1');
        $asset->execute([$bunnyVideoId]);
        $row = $asset->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $keepStatus = $mapped === null;
            $this->pdo->prepare("
                UPDATE class_recording_assets
                SET bunny_status = ?,
                    processing_status = ?,
                    duration_seconds = COALESCE(?, duration_seconds),
                    thumbnail_url = CASE WHEN ? <> '' THEN ? ELSE thumbnail_url END,
                    encode_progress = COALESCE(?, encode_progress),
                    ready_at = CASE WHEN ? IN (3, 4) THEN COALESCE(ready_at, NOW()) ELSE ready_at END
                WHERE bunny_video_id = ?
            ")->execute([
                $keepStatus ? $row['bunny_status'] : $statusInt,
                $keepStatus ? $row['processing_status'] : BunnyVideoService::processingLabel($statusInt),
                $duration,
                $thumb,
                $thumb,
                $progress,
                $keepStatus ? -1 : $statusInt,
                $bunnyVideoId,
            ]);
            $this->refreshParentStatus((int)$row['recording_id']);
            return;
        }

        $lib = $this->pdo->prepare('SELECT id, status, teacher_id, title FROM teacher_video_library WHERE bunny_video_id = ? AND deleted_at IS NULL LIMIT 1');
        $lib->execute([$bunnyVideoId]);
        $libRow = $lib->fetch(PDO::FETCH_ASSOC);
        if (!$libRow) {
            return;
        }
        $newStatus = $mapped ?: (string)$libRow['status'];
        $this->pdo->prepare("
            UPDATE teacher_video_library
            SET bunny_status = ?,
                status = ?,
                duration_seconds = COALESCE(?, duration_seconds),
                thumbnail_url = CASE WHEN ? <> '' THEN ? ELSE thumbnail_url END,
                ready_at = CASE WHEN ? IN (3, 4) THEN COALESCE(ready_at, NOW()) ELSE ready_at END
            WHERE id = ?
        ")->execute([
            $mapped !== null ? $statusInt : ($libRow['bunny_status'] ?? null),
            $newStatus,
            $duration,
            $thumb,
            $thumb,
            $mapped !== null ? $statusInt : -1,
            (int)$libRow['id'],
        ]);
        if ($newStatus === 'ready' && (string)$libRow['status'] !== 'ready' && function_exists('campus_insert_teacher_notification')) {
            campus_insert_teacher_notification(
                $this->pdo,
                (int)$libRow['teacher_id'],
                'Library video ready',
                'Your video library upload is ready: ' . (string)$libRow['title'],
                'campus/video_library.php'
            );
        }
    }

    public function refreshParentStatus(int $recordingId): void
    {
        $assets = $this->assets($recordingId);
        if ($assets === []) {
            return;
        }
        $hasReady = false;
        $hasFailed = false;
        $hasProcessing = false;
        $duration = 0;
        $thumb = '';
        foreach ($assets as $asset) {
            $st = (int)($asset['bunny_status'] ?? -1);
            $mapped = BunnyVideoService::mapStatus($st);
            if ($mapped === 'ready' || !empty($asset['ready_at'])) {
                $hasReady = true;
                $duration += (int)($asset['duration_seconds'] ?? 0);
                if ($thumb === '' && !empty($asset['thumbnail_url'])) {
                    $thumb = (string)$asset['thumbnail_url'];
                }
            } elseif ($mapped === 'failed') {
                $hasFailed = true;
            } elseif ($mapped === 'processing' || $mapped === 'uploading') {
                $hasProcessing = true;
            }
        }
        $status = 'processing';
        if ($hasReady) {
            $status = 'ready';
        } elseif ($hasFailed && !$hasProcessing) {
            $status = 'failed';
        } elseif ($hasProcessing) {
            $status = 'processing';
        }
        $prevStmt = $this->pdo->prepare('SELECT status FROM class_recordings WHERE id = ? LIMIT 1');
        $prevStmt->execute([$recordingId]);
        $previous = (string)$prevStmt->fetchColumn();
        $this->pdo->prepare("
            UPDATE class_recordings
            SET status = ?, duration_seconds = ?, thumbnail_url = CASE WHEN ? <> '' THEN ? ELSE thumbnail_url END
            WHERE id = ? AND deleted_at IS NULL AND status <> 'deleted'
        ")->execute([$status, $duration > 0 ? $duration : null, $thumb, $thumb, $recordingId]);
        if ($status === 'ready' && $previous !== 'ready') {
            $this->notifyRecordingReady($recordingId);
        }
    }

    public function syncFromBunny(int $recordingId): bool
    {
        $assets = $this->assets($recordingId);
        if ($assets === []) {
            return false;
        }
        $ok = false;
        foreach ($assets as $asset) {
            $videoId = trim((string)($asset['bunny_video_id'] ?? ''));
            $libraryId = trim((string)($asset['bunny_library_id'] ?? ''));
            if ($videoId === '') {
                continue;
            }
            $bunny = $libraryId !== ''
                ? BunnyVideoService::tryForLibraryId($this->pdo, $libraryId)
                : BunnyVideoService::tryForStoredVideo($this->pdo, $videoId);
            if ($bunny === null) {
                continue;
            }
            try {
                $video = $bunny->getVideo($videoId, 12);
                $this->applyBunnyMetadata($videoId, $video, $bunny);
                $ok = true;
            } catch (\Throwable $e) {
                error_log('Bunny recording sync failed');
            }
        }
        return $ok;
    }

    /**
     * Signed embed URLs for assets that are actually playable.
     *
     * @return list<string>
     */
    public function playableEmbedUrls(int $recordingId, bool $firstOnly = true): array
    {
        $assets = $this->assets($recordingId);
        if ($firstOnly) {
            $assets = array_reverse($assets);
        }
        $urls = [];
        foreach ($assets as $asset) {
            $url = $this->signedEmbedForAsset($asset);
            if ($url === null) {
                continue;
            }
            $urls[] = $url;
            if ($firstOnly) {
                break;
            }
        }
        return $urls;
    }

    public function playableEmbedUrlForAsset(int $assetId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT * FROM class_recording_assets WHERE id = ? LIMIT 1');
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$asset) {
            return null;
        }
        return $this->signedEmbedForAsset($asset);
    }

    /**
     * @param array<string,mixed> $asset
     */
    public function signedEmbedForAsset(array $asset): ?string
    {
        $st = (int)($asset['bunny_status'] ?? -1);
        $playable = BunnyVideoService::mapStatus($st) === 'ready' || !empty($asset['ready_at']);
        if (!$playable) {
            return null;
        }
        $videoId = trim((string)($asset['bunny_video_id'] ?? ''));
        $libraryId = trim((string)($asset['bunny_library_id'] ?? ''));
        if ($videoId === '') {
            return null;
        }
        $bunny = $libraryId !== ''
            ? BunnyVideoService::tryForLibraryId($this->pdo, $libraryId)
            : BunnyVideoService::tryForStoredVideo($this->pdo, $videoId);
        if ($bunny === null) {
            return null;
        }
        return $bunny->signedEmbedUrl($videoId);
    }

    private function notifyRecordingReady(int $recordingId): void
    {
        try {
            $row = $this->find($recordingId);
            if (!$row || !function_exists('campus_class_student_ids') || !function_exists('campus_portal_notify')) {
                return;
            }
            $ids = campus_class_student_ids($this->pdo, (int)$row['class_id']);
            $date = date('d M Y', strtotime((string)$row['date']));
            campus_portal_notify(
                $this->pdo,
                $ids,
                'Class recording available',
                'Your class recording is now available: ' . $row['subject_name'] . ' — ' . $date,
                'dashboard.php?tab=recordings'
            );
            if (function_exists('campus_insert_teacher_notification')) {
                campus_insert_teacher_notification(
                    $this->pdo,
                    (int)$row['lesson_teacher_id'],
                    'Recording ready',
                    'Your class recording upload is ready: ' . (string)$row['title'],
                    'campus/recordings.php'
                );
            }
        } catch (\Throwable $e) {
            error_log('Recording ready notify');
        }
    }

    public function archive(int $recordingId, int $teacherId, bool $isAdmin): bool
    {
        $row = $this->find($recordingId);
        if (!$row || $row['deleted_at'] !== null) {
            return false;
        }
        $sub = (int)($row['substitute_teacher_id'] ?? 0);
        if (!RecordingAccessService::teacherCanManageLesson($teacherId, (int)$row['lesson_teacher_id'], $isAdmin, $sub)) {
            return false;
        }
        $this->pdo->prepare("
            UPDATE class_recordings
            SET status = 'deleted', retention_status = 'archived', deleted_at = NOW()
            WHERE id = ?
        ")->execute([$recordingId]);
        return true;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function teacherLessons(int $teacherId, bool $isAdmin, string $from, string $to, int $limit = 200): array
    {
        $sql = "
            SELECT tt.*, s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
                   cr.id AS recording_id, cr.status AS recording_status, cr.duration_seconds,
                   cr.created_at AS recording_uploaded_at, cr.title AS recording_title
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            LEFT JOIN class_recordings cr
                ON cr.timetable_id = tt.id AND cr.deleted_at IS NULL AND cr.status <> 'deleted'
            WHERE tt.deleted_at IS NULL
              AND tt.date BETWEEN ? AND ?
              AND (tt.lesson_status IS NULL OR tt.lesson_status IN ('scheduled','substituted'))
        ";
        $params = [$from, $to];
        if (!$isAdmin) {
            $sql .= ' AND (tt.teacher_id = ? OR tt.substitute_teacher_id = ?)';
            $params[] = $teacherId;
            $params[] = $teacherId;
        }
        $sql .= ' ORDER BY tt.date DESC, tt.start_time DESC LIMIT ' . max(1, min(400, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
