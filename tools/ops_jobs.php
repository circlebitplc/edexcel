<?php
declare(strict_types=1);

/**
 * Waitlist offer expiry, parent fee pings, health alerts, Bunny purge.
 * Safe every 5 minutes. Timezone: Asia/Colombo.
 */
date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../config/bunny.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\BunnyVideoService;
use Edexcel\Services\FeeStatementService;
use Edexcel\Services\SystemHealthService;
use Edexcel\Services\WaitlistOfferService;

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No database.\n");
    exit(1);
}

ensure_ops_schema($pdo);
ops_job_start($pdo, 'ops_jobs');

$messages = [];
try {
    $n = (new WaitlistOfferService($pdo))->expireOpen();
    $messages[] = "waitlist_expired={$n}";
} catch (Throwable $e) {
    $messages[] = 'waitlist:' . $e->getMessage();
}

try {
    $health = new SystemHealthService($pdo);
    $alerts = $health->alertIfStale();
    $wa = $health->whatsapp();
    if (!empty($wa['warning']) && (int)($wa['days_left'] ?? 99) <= 7) {
        $stmt = $pdo->prepare('SELECT last_alert_at FROM system_job_runs WHERE job_name = ?');
        $stmt->execute(['meta_token_alert']);
        $last = $stmt->fetchColumn();
        if (!$last || strtotime((string)$last) < time() - 86400) {
            ops_admin_alert(
                $pdo,
                'WhatsApp token expiring',
                'Cloud API token expires ' . (string)$wa['expires_at'] . '. Paste a new API Setup token on Connect WhatsApp.'
            );
            $pdo->prepare("
                INSERT INTO system_job_runs (job_name, last_alert_at, last_message)
                VALUES ('meta_token_alert', NOW(), ?)
                ON DUPLICATE KEY UPDATE last_alert_at = NOW(), last_message = VALUES(last_message)
            ")->execute([(string)$wa['expires_at']]);
        }
    }
    $messages[] = "health_alerts={$alerts}";
} catch (Throwable $e) {
    $messages[] = 'health:' . $e->getMessage();
}

try {
    $hour = (int)date('H');
    if ($hour === 9) {
        $today = date('Y-m-d');
        $students = $pdo->query("
            SELECT id FROM users WHERE role='student' AND deleted_at IS NULL AND is_active=1
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $fees = new FeeStatementService($pdo);
        $pinged = 0;
        foreach ($students as $sid) {
            $sid = (int)$sid;
            $exists = $pdo->prepare("SELECT 1 FROM parent_fee_pings WHERE student_id = ? AND ping_date = ?");
            try {
                $exists->execute([$sid, $today]);
                if ($exists->fetchColumn()) {
                    continue;
                }
            } catch (Throwable $e) {
                continue;
            }
            $stmt = $fees->forStudent($sid);
            if ((float)($stmt['due'] ?? 0) < 1) {
                continue;
            }
            $amount = number_format((float)$stmt['due']);
            campus_notify_phones(
                $pdo,
                $sid,
                "💳 *Fees due*\n\nRs {$amount} is outstanding. Pay at the college counter or open the student Fees tab / OnePay for class fees.\n\nEdexcel College",
                'FEE_DUE_PING'
            );
            try {
                (new \Edexcel\Services\CommunicationEventService($pdo))->feeOverdue($sid, (float)$stmt['due'], [
                    'ping_date' => $today,
                    'amount' => (float)$stmt['due'],
                ]);
            } catch (Throwable $e) {
                error_log('fee overdue communication hub: ' . $e->getMessage());
            }
            try {
                $pdo->prepare("INSERT IGNORE INTO parent_fee_pings (student_id, ping_date) VALUES (?, ?)")
                    ->execute([$sid, $today]);
            } catch (Throwable $e) {
            }
            $pinged++;
        }
        $messages[] = "fee_pings={$pinged}";
    }
} catch (Throwable $e) {
    $messages[] = 'fees:' . $e->getMessage();
}

try {
    $deleted = 0;
    $rows = $pdo->query("
        SELECT cr.id, a.bunny_video_id, a.bunny_library_id
        FROM class_recordings cr
        JOIN class_recording_assets a ON a.recording_id = cr.id
        WHERE cr.retention_status = 'scheduled_for_deletion'
          AND cr.deleted_at IS NULL
          AND cr.scheduled_delete_at IS NOT NULL
          AND cr.scheduled_delete_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        LIMIT 8
    ");
    foreach ($rows ? ($rows->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
        $videoId = (string)($row['bunny_video_id'] ?? '');
        $libraryId = trim((string)($row['bunny_library_id'] ?? ''));
        if ($videoId === '' || $libraryId === '') {
            continue;
        }
        $client = BunnyVideoService::tryForLibraryId($pdo, $libraryId);
        if ($client === null) {
            continue;
        }
        try {
            $client->deleteVideo($videoId);
            $pdo->prepare("
                UPDATE class_recordings
                SET retention_status = 'deleted', deleted_at = NOW(), status = 'deleted'
                WHERE id = ?
            ")->execute([(int)$row['id']]);
            $deleted++;
        } catch (Throwable $e) {
            error_log('bunny delete: ' . $e->getMessage());
        }
    }
    $messages[] = "bunny_deleted={$deleted}";
} catch (Throwable $e) {
    $messages[] = 'bunny:' . $e->getMessage();
}

try {
    require_once __DIR__ . '/../includes/recurring.php';
    $n = generate_future_entries($pdo, 14);
    $messages[] = "recurring={$n}";
} catch (Throwable $e) {
    $messages[] = 'recurring:' . $e->getMessage();
}

$msg = implode(' ', $messages);
ops_job_finish($pdo, 'ops_jobs', true, $msg);
echo $msg . "\n";
