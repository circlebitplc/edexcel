<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/../config/sms_gateway.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\AnnouncementService;
use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\CommunicationService;

if (!($pdo instanceof PDO)) {
    exit(1);
}
ensure_ops_schema($pdo);
ops_job_start($pdo, 'communication_queue');
$ok = true;
$done = 0;
$failed = 0;
try {
    // Legacy scheduled rows without recipients: mark due and leave for hub or simple WA send.
    $legacy = (new CommunicationService($pdo))->due(20);
    foreach ($legacy as $row) {
        try {
            $hasRecipients = false;
            try {
                $c = $pdo->prepare('SELECT COUNT(*) FROM communication_recipients WHERE message_id=?');
                $c->execute([(int)$row['id']]);
                $hasRecipients = (int)$c->fetchColumn() > 0;
            } catch (Throwable $e) {
            }
            if ($hasRecipients) {
                $pdo->prepare("UPDATE communication_messages SET status='queued' WHERE id=?")->execute([(int)$row['id']]);
                continue;
            }
            $sent = false;
            $err = '';
            if ($row['channel'] === 'whatsapp' && function_exists('send_whatsapp') && !empty($row['recipient'])) {
                $sent = (bool)send_whatsapp($pdo, (string)$row['recipient'], (string)$row['body'], 'communication');
                if (!$sent && function_exists('send_whatsapp_last_error')) {
                    $err = trim((string)send_whatsapp_last_error());
                }
            } elseif ($row['channel'] === 'sms' && function_exists('sms_send') && !empty($row['recipient'])) {
                $sent = (bool)sms_send($pdo, (string)$row['recipient'], (string)$row['body']);
                if (!$sent && function_exists('sms_send_last_error')) {
                    $err = trim((string)sms_send_last_error());
                }
            } elseif ($row['channel'] === 'in_app') {
                $sent = true;
            } else {
                $err = 'This channel is not configured for automatic delivery.';
            }
            $pdo->prepare("UPDATE communication_messages SET status=?,sent_at=IF(?='sent',NOW(),sent_at),attempts=attempts+1,error_message=? WHERE id=?")
                ->execute([$sent ? 'sent' : 'failed', $sent ? 'sent' : 'failed', $err !== '' ? mb_substr($err, 0, 500) : null, (int)$row['id']]);
            if ($sent) {
                $done++;
            } else {
                $failed++;
                $ok = false;
            }
        } catch (Throwable $e) {
            $failed++;
            $ok = false;
        }
    }

    $stats = (new CommunicationHubService($pdo))->processQueue(40);
    $done += (int)$stats['sent'];
    $failed += (int)$stats['failed'];
    if ($stats['failed'] > 0) {
        $ok = false;
    }
    (new AnnouncementService($pdo))->expireDue();
} catch (Throwable $e) {
    $ok = false;
    ops_job_finish($pdo, 'communication_queue', false, $e->getMessage());
    exit(1);
}
ops_job_finish($pdo, 'communication_queue', $ok, "processed={$done} failed={$failed}");
exit($ok ? 0 : 1);
