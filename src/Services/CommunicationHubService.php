<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Unified communication hub: preview → confirm → queue recipients → background delivery.
 * Reuses communication_messages, notification_center, whatsapp_outbox, sms_send.
 */
final class CommunicationHubService
{
    public const BULK_CONFIRM_THRESHOLD = 25;
    public const DAILY_LIMIT_DEFAULT = 2000;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->pdo->inTransaction()) {
            return;
        }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS communication_bulk_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                message_id BIGINT UNSIGNED NOT NULL,
                status ENUM('preview','confirmed','processing','completed','cancelled','failed') NOT NULL DEFAULT 'preview',
                audience_type VARCHAR(40) NOT NULL,
                audience_id INT NULL,
                channel VARCHAR(20) NOT NULL,
                estimated_recipients INT UNSIGNED NOT NULL DEFAULT 0,
                excluded_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_by INT NULL,
                confirmed_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                confirmed_at DATETIME NULL,
                completed_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_bulk_status (status, created_at),
                KEY idx_bulk_message (message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
        }
        foreach ([
            'category' => "VARCHAR(40) NULL",
            'priority' => "VARCHAR(20) NOT NULL DEFAULT 'normal'",
            'recipient_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'preview_json' => 'JSON NULL',
            'confirm_token' => 'CHAR(32) NULL',
            'confirmed_at' => 'DATETIME NULL',
            'idempotency_key' => 'VARCHAR(80) NULL',
        ] as $col => $def) {
            try {
                if (function_exists('campus_column_exists') && campus_column_exists($this->pdo, 'communication_messages', $col)) {
                    continue;
                }
                $this->pdo->exec("ALTER TABLE communication_messages ADD COLUMN {$col} {$def}");
            } catch (Throwable $e) {
            }
        }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS communication_recipients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                message_id BIGINT UNSIGNED NOT NULL,
                audience VARCHAR(20) NOT NULL,
                user_id INT NULL,
                parent_id INT NULL,
                phone VARCHAR(50) NULL,
                channel VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'queued',
                exclusion_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_comm_recip_message (message_id, status),
                KEY idx_comm_recip_user (audience, user_id, parent_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS communication_delivery_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                detail VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_delivery_recip (recipient_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
        }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS communication_idempotency (
                idempotency_key VARCHAR(80) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (idempotency_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
        }
        foreach ([
            'provider_message_id' => 'VARCHAR(120) NULL',
            'provider' => 'VARCHAR(40) NULL',
            'retry_count' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'last_retry_at' => 'DATETIME NULL',
        ] as $col => $def) {
            try {
                if (function_exists('campus_column_exists') && campus_column_exists($this->pdo, 'communication_recipients', $col)) {
                    continue;
                }
                $this->pdo->exec("ALTER TABLE communication_recipients ADD COLUMN {$col} {$def}");
            } catch (Throwable $e) {
            }
        }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS communication_channel_webhooks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                channel VARCHAR(20) NOT NULL,
                provider_event_id VARCHAR(120) NULL,
                status VARCHAR(40) NOT NULL,
                payload_json JSON NULL,
                processed TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_chan_webhook_provider (provider_event_id),
                KEY idx_chan_webhook_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
        }
    }

    /**
     * Build a preview without sending. Large audiences require confirmation.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function preview(array $input, int $userId): array
    {
        $channel = (string)($input['channel'] ?? 'in_app');
        $audienceType = (string)($input['audience_type'] ?? 'individual');
        $audienceId = ((int)($input['audience_id'] ?? 0)) ?: null;
        $category = $this->normalizeCategory((string)($input['category'] ?? 'system'));
        $priority = in_array(($input['priority'] ?? 'normal'), ['normal', 'important', 'urgent'], true) ? $input['priority'] : 'normal';
        $body = trim((string)($input['body'] ?? ''));
        $subject = trim((string)($input['subject'] ?? '')) ?: null;
        $templateId = ((int)($input['template_id'] ?? 0)) ?: null;
        $scheduledAt = trim((string)($input['scheduled_at'] ?? $input['schedule_at'] ?? '')) ?: null;
        $idem = trim((string)($input['idempotency_key'] ?? ''));
        $includeParents = !empty($input['include_parents']);

        if (!in_array($channel, ['whatsapp', 'sms', 'in_app'], true)) {
            throw new RuntimeException('Supported channels: in_app, whatsapp, sms.');
        }
        if ($body === '' && $templateId) {
            $tpl = $this->template($templateId);
            if (!$tpl) {
                throw new RuntimeException('Template not found.');
            }
            $body = (string)$tpl['body'];
            $subject = $subject ?: ($tpl['subject'] ?? null);
            $channel = (string)($tpl['channel'] ?: $channel);
        }
        if ($body === '') {
            throw new RuntimeException('Message body is required.');
        }
        $this->assertSafeContent($body . ' ' . (string)$subject);

        $vars = is_array($input['variables'] ?? null) ? $input['variables'] : [];
        $vars = array_merge($this->defaultVars(), $vars);
        $body = (new MessageTemplateService($this->pdo))->render($body, $vars);
        if ($subject) {
            $subject = (new MessageTemplateService($this->pdo))->render($subject, $vars);
        }

        $resolved = $this->resolveRecipients($audienceType, $audienceId, (string)($input['recipient'] ?? ''), $channel, $category, $includeParents);
        $included = array_values(array_filter($resolved, static fn($r) => empty($r['exclusion_reason'])));
        $excluded = array_values(array_filter($resolved, static fn($r) => !empty($r['exclusion_reason'])));
        $count = count($included);
        $needsConfirm = $count >= self::BULK_CONFIRM_THRESHOLD || in_array($audienceType, ['everyone', 'students', 'parents', 'teachers', 'qualification'], true);

        $token = bin2hex(random_bytes(16));
        $status = $scheduledAt ? 'scheduled' : ($needsConfirm ? 'queued' : 'queued');
        $preview = [
            'audience_type' => $audienceType,
            'audience_id' => $audienceId,
            'channel' => $channel,
            'category' => $category,
            'priority' => $priority,
            'include_parents' => $includeParents,
            'recipient_count' => $count,
            'excluded_count' => count($excluded),
            'excluded_sample' => array_slice(array_map(static fn($r) => $r['exclusion_reason'], $excluded), 0, 10),
            'needs_confirm' => $needsConfirm,
            'estimated_delivery' => $scheduledAt ?: 'background queue (not synchronous)',
            'sample_recipients' => array_slice(array_map(static function ($r) {
                return [
                    'audience' => $r['audience'],
                    'user_id' => $r['user_id'] ?? null,
                    'parent_id' => $r['parent_id'] ?? null,
                    'channel' => $r['channel'],
                ];
            }, $included), 0, 8),
        ];

        if ($idem !== '') {
            try {
                $this->pdo->prepare('INSERT INTO communication_idempotency(idempotency_key) VALUES(?)')->execute([mb_substr($idem, 0, 80)]);
            } catch (Throwable $e) {
                throw new RuntimeException('Duplicate communication request (idempotency).');
            }
        }

        $this->pdo->prepare("INSERT INTO communication_messages(channel,category,priority,audience_type,audience_id,recipient,template_id,subject,body,recipient_count,preview_json,confirm_token,status,scheduled_at,idempotency_key,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $channel, $category, $priority, $audienceType, $audienceId,
                $input['recipient'] ?? null, $templateId, $subject, mb_substr($body, 0, 5000),
                $count, json_encode($preview), $token, $needsConfirm ? 'queued' : $status,
                $scheduledAt, $idem !== '' ? mb_substr($idem, 0, 80) : null, $userId ?: null,
            ]);
        $messageId = (int)$this->pdo->lastInsertId();

        $bulkId = 0;
        if ($needsConfirm) {
            $this->pdo->prepare("INSERT INTO communication_bulk_jobs(message_id,status,audience_type,audience_id,channel,estimated_recipients,excluded_count,created_by) VALUES(?,'preview',?,?,?,?,?,?)")
                ->execute([$messageId, $audienceType, $audienceId, $channel, $count, count($excluded), $userId ?: null]);
            $bulkId = (int)$this->pdo->lastInsertId();
            // Keep message cancelled until confirmed so queue does not send accidentally.
            $this->pdo->prepare("UPDATE communication_messages SET status='cancelled' WHERE id=?")->execute([$messageId]);
        } else {
            $this->storeRecipients($messageId, $included, $excluded);
            if (!$scheduledAt) {
                $this->pdo->prepare("UPDATE communication_messages SET status='queued' WHERE id=?")->execute([$messageId]);
            }
        }

        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'communication_preview', 'communication_messages', $messageId, null, $preview);
        }

        return [
            'message_id' => $messageId,
            'bulk_job_id' => $bulkId,
            'confirm_token' => $needsConfirm ? $token : null,
            'needs_confirm' => $needsConfirm,
            'preview' => $preview,
            'body' => $body,
            'subject' => $subject,
        ];
    }

    public function confirmBulk(int $messageId, string $token, int $userId): void
    {
        $s = $this->pdo->prepare('SELECT * FROM communication_messages WHERE id=? LIMIT 1');
        $s->execute([$messageId]);
        $msg = $s->fetch(PDO::FETCH_ASSOC);
        if (!$msg || (string)($msg['confirm_token'] ?? '') === '' || !hash_equals((string)$msg['confirm_token'], $token)) {
            throw new RuntimeException('Invalid confirmation token. Refresh the page and try again.');
        }
        if ((string)($msg['status'] ?? '') !== 'cancelled') {
            throw new RuntimeException('This message is not waiting for confirmation.');
        }
        $preview = json_decode((string)($msg['preview_json'] ?? '{}'), true) ?: [];
        $resolved = $this->resolveRecipients(
            (string)$msg['audience_type'],
            ((int)($msg['audience_id'] ?? 0)) ?: null,
            (string)($msg['recipient'] ?? ''),
            (string)$msg['channel'],
            (string)($msg['category'] ?? 'system'),
            !empty($preview['include_parents'])
        );
        $included = array_values(array_filter($resolved, static fn($r) => empty($r['exclusion_reason'])));
        $excluded = array_values(array_filter($resolved, static fn($r) => !empty($r['exclusion_reason'])));
        $status = !empty($msg['scheduled_at']) && strtotime((string)$msg['scheduled_at']) > time() ? 'scheduled' : 'queued';

        $started = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }
            // Claim preview job first so a double-click cannot store recipients twice.
            $claim = $this->pdo->prepare("UPDATE communication_bulk_jobs SET status='confirmed',confirmed_by=?,confirmed_at=NOW(),estimated_recipients=? WHERE message_id=? AND status='preview'");
            $claim->execute([$userId, count($included), $messageId]);
            if ($claim->rowCount() < 1) {
                throw new RuntimeException('This message was already confirmed or cancelled.');
            }
            $this->pdo->prepare('DELETE FROM communication_recipients WHERE message_id=?')->execute([$messageId]);
            $this->storeRecipients($messageId, $included, $excluded);
            $this->pdo->prepare("UPDATE communication_messages SET status=?,confirmed_at=NOW(),recipient_count=?,confirm_token=NULL WHERE id=? AND status='cancelled'")
                ->execute([$status, count($included), $messageId]);
            if ($started) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Could not confirm this message. Please try again.', 0, $e);
        }
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'communication_bulk_confirmed', 'communication_messages', $messageId, null, ['recipients' => count($included), 'excluded' => count($excluded), 'preview' => $preview]);
        }
    }

    public function cancel(int $messageId, int $userId): void
    {
        $this->pdo->prepare("UPDATE communication_messages SET status='cancelled' WHERE id=? AND status IN ('queued','scheduled','cancelled')")->execute([$messageId]);
        $this->pdo->prepare("UPDATE communication_bulk_jobs SET status='cancelled' WHERE message_id=?")->execute([$messageId]);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'communication_cancelled', 'communication_messages', $messageId, null, ['user_id' => $userId]);
        }
    }

    /**
     * Process a batch of queued recipients. Never runs unbounded external sends.
     * @return array{processed:int,sent:int,failed:int}
     */
    public function processQueue(int $limit = 40): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];
        // Promote due scheduled messages.
        try {
            $this->pdo->exec("UPDATE communication_messages SET status='queued' WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at<=NOW()");
        } catch (Throwable $e) {
        }
        // Recover stale claims from crashed workers (VARCHAR status; updated_at auto).
        try {
            $this->pdo->exec("UPDATE communication_recipients SET status='queued', updated_at=NOW()
                WHERE status='processing' AND (updated_at IS NULL OR updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE))");
        } catch (Throwable $e) {
        }

        $rows = [];
        try {
            $s = $this->pdo->prepare("SELECT r.*, m.subject, m.body, m.category, m.priority, m.channel message_channel
                FROM communication_recipients r
                JOIN communication_messages m ON m.id=r.message_id
                WHERE r.status='queued' AND m.status='queued' AND (r.exclusion_reason IS NULL OR r.exclusion_reason='')
                ORDER BY r.id ASC LIMIT ?");
            $s->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
            $s->execute();
            $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $stats;
        }

        $prefs = new CommunicationPreferenceService($this->pdo);
        $center = new NotificationCenterService($this->pdo);
        $claim = $this->pdo->prepare("UPDATE communication_recipients SET status='processing', updated_at=NOW() WHERE id=? AND status='queued'");

        foreach ($rows as $row) {
            $rid = (int)$row['id'];
            try {
                $claim->execute([$rid]);
                if ($claim->rowCount() < 1) {
                    continue; // Another worker already claimed this row.
                }
            } catch (Throwable $e) {
                continue;
            }

            $stats['processed']++;
            $channel = (string)$row['channel'];
            $category = $this->normalizeCategory((string)($row['category'] ?? 'system'));
            $ok = false;
            $detail = '';
            try {
                if (!$prefs->allowed((string)$row['audience'], ((int)($row['user_id'] ?? 0)) ?: null, ((int)($row['parent_id'] ?? 0)) ?: null, $category, $channel)) {
                    $this->markRecipient($rid, 'excluded', 'Preference disabled this channel');
                    continue;
                }
                if ($channel === 'in_app') {
                    $nid = $center->create(
                        (string)$row['audience'],
                        $category,
                        (string)($row['subject'] ?: 'College message'),
                        (string)$row['body'],
                        null,
                        ((int)($row['user_id'] ?? 0)) ?: null,
                        ((int)($row['parent_id'] ?? 0)) ?: null,
                        (string)(($row['priority'] ?? 'normal') === 'urgent' ? 'urgent' : 'normal'),
                        ['communication_recipient_id' => $rid, 'message_id' => (int)$row['message_id']]
                    );
                    $ok = $nid > 0;
                    $detail = $ok
                        ? 'In-app notification created'
                        : 'Could not create in-app notification (empty title or database error).';
                } elseif ($channel === 'whatsapp') {
                    $phone = (string)($row['phone'] ?? '');
                    if ($phone === '' || !function_exists('send_whatsapp')) {
                        throw new RuntimeException('WhatsApp number is missing or WhatsApp sending is not available.');
                    }
                    require_once dirname(__DIR__, 2) . '/config/notifications.php';
                    $ok = (bool)send_whatsapp($this->pdo, $phone, (string)$row['body'], 'communication');
                    $providerId = function_exists('send_whatsapp_last_provider_id') ? (string)send_whatsapp_last_provider_id() : '';
                    if ($providerId !== '') {
                        $this->attachProviderId($rid, $providerId, 'whatsapp');
                    }
                    $waErr = function_exists('send_whatsapp_last_error') ? trim((string)send_whatsapp_last_error()) : '';
                    $detail = $ok
                        ? ($providerId !== '' ? 'Accepted by WhatsApp API; awaiting delivery receipt ('.$providerId.')' : 'Accepted by WhatsApp sender (not proof of handset delivery)')
                        : ($waErr !== '' ? $waErr : 'WhatsApp send failed or was queued to the outbox for retry.');
                } elseif ($channel === 'sms') {
                    $phone = (string)($row['phone'] ?? '');
                    if ($phone === '' || !function_exists('sms_send')) {
                        throw new RuntimeException('SMS number is missing or the SMS gateway is not available.');
                    }
                    $ok = (bool)sms_send($this->pdo, $phone, (string)$row['body']);
                    $providerId = function_exists('sms_send_last_id') ? (string)sms_send_last_id() : '';
                    if ($providerId !== '') {
                        $this->attachProviderId($rid, $providerId, 'sms');
                    }
                    $smsErr = function_exists('sms_send_last_error') ? trim((string)sms_send_last_error()) : '';
                    $detail = $ok
                        ? ($providerId !== '' ? 'Accepted by SMS gateway; awaiting delivery report ('.$providerId.')' : 'Accepted by SMS gateway (not proof of handset delivery)')
                        : ($smsErr !== '' ? $smsErr : 'SMS send failed.');
                } else {
                    throw new RuntimeException('Unsupported channel.');
                }
            } catch (Throwable $e) {
                $ok = false;
                $detail = $e->getMessage();
            }
            $this->markRecipient($rid, $ok ? 'sent' : 'failed', $detail);
            $this->event($rid, $ok ? 'sent' : 'failed', $detail);
            if ($ok) {
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }
        }

        // Close parent messages when all recipients finished (do not call all-failed "sent").
        try {
            $this->pdo->exec("UPDATE communication_messages m
                SET status='sent', sent_at=COALESCE(sent_at,NOW())
                WHERE m.status='queued'
                  AND EXISTS (SELECT 1 FROM communication_recipients r WHERE r.message_id=m.id AND r.status IN ('sent','delivered','read'))
                  AND NOT EXISTS (SELECT 1 FROM communication_recipients r WHERE r.message_id=m.id AND r.status IN ('queued','processing'))");
            $this->pdo->exec("UPDATE communication_messages m
                SET status='failed'
                WHERE m.status='queued'
                  AND EXISTS (SELECT 1 FROM communication_recipients r WHERE r.message_id=m.id)
                  AND NOT EXISTS (SELECT 1 FROM communication_recipients r WHERE r.message_id=m.id AND r.status IN ('queued','processing'))
                  AND NOT EXISTS (SELECT 1 FROM communication_recipients r WHERE r.message_id=m.id AND r.status IN ('sent','delivered','read'))
                  AND EXISTS (SELECT 1 FROM communication_recipients r WHERE r.message_id=m.id AND r.status='failed')");
            $this->pdo->exec("UPDATE communication_bulk_jobs b
                JOIN communication_messages m ON m.id=b.message_id
                SET b.status='completed', b.completed_at=NOW()
                WHERE b.status IN ('confirmed','processing') AND m.status='sent'");
            $this->pdo->exec("UPDATE communication_bulk_jobs b
                JOIN communication_messages m ON m.id=b.message_id
                SET b.status='failed', b.completed_at=NOW()
                WHERE b.status IN ('confirmed','processing') AND m.status='failed'");
        } catch (Throwable $e) {
        }

        return $stats;
    }

    /** @return array<string,mixed> */
    public function centreSnapshot(): array
    {
        $q = function (string $sql): int {
            try {
                return (int)$this->pdo->query($sql)->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        return [
            'recent' => $this->history(20),
            'unread_center' => $q("SELECT COUNT(*) FROM notification_center WHERE is_read=0"),
            'queued' => $q("SELECT COUNT(*) FROM communication_messages WHERE status='queued'") + $q("SELECT COUNT(*) FROM communication_recipients WHERE status='queued'"),
            'scheduled' => $q("SELECT COUNT(*) FROM communication_messages WHERE status='scheduled'"),
            'failed' => $q("SELECT COUNT(*) FROM communication_messages WHERE status='failed'") + $q("SELECT COUNT(*) FROM communication_recipients WHERE status='failed'"),
            'pending_bulk' => $q("SELECT COUNT(*) FROM communication_bulk_jobs WHERE status='preview'"),
            'announcements' => $q("SELECT COUNT(*) FROM college_announcements WHERE status='published'"),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function history(int $limit = 100): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM communication_messages ORDER BY id DESC LIMIT ?');
            $s->bindValue(1, max(1, min(300, $limit)), PDO::PARAM_INT);
            $s->execute();
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function search(array $filters, int $limit = 100): array
    {
        $where = ['1=1'];
        $p = [];
        if (!empty($filters['channel'])) {
            $where[] = 'channel=?';
            $p[] = $filters['channel'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status=?';
            $p[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'category=?';
            $p[] = $filters['category'];
        }
        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['from'])) {
            $where[] = 'DATE(created_at)>=?';
            $p[] = $filters['from'];
        }
        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['to'])) {
            $where[] = 'DATE(created_at)<=?';
            $p[] = $filters['to'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(body LIKE ? OR subject LIKE ? OR recipient LIKE ?)';
            $like = '%'.mb_substr((string)$filters['q'], 0, 80).'%';
            $p[] = $like;
            $p[] = $like;
            $p[] = $like;
        }
        try {
            $s = $this->pdo->prepare('SELECT * FROM communication_messages WHERE '.implode(' AND ', $where).' ORDER BY id DESC LIMIT '.(int)max(1, min(300, $limit)));
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function resolveRecipients(string $audienceType, ?int $audienceId, string $recipient, string $channel, string $category, bool $includeParents = false): array
    {
        $out = [];
        $add = function (string $audience, ?int $userId, ?int $parentId, ?string $phone, ?string $exclude = null) use (&$out, $channel): void {
            $out[] = [
                'audience' => $audience,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'phone' => $phone,
                'channel' => $channel,
                'exclusion_reason' => $exclude,
            ];
        };

        try {
            if ($audienceType === 'individual') {
                if ($recipient !== '') {
                    $phone = preg_replace('/\D+/', '', $recipient) ?? '';
                    $add('student', null, null, $phone !== '' ? $phone : $recipient, $channel === 'in_app' ? 'Individual in-app requires a user id' : null);
                } elseif ($audienceId) {
                    $add('student', $audienceId, null, $this->studentPhone($audienceId));
                    if ($includeParents) {
                        $p = $this->pdo->prepare('SELECT parent_id FROM parent_students WHERE student_id=?');
                        $p->execute([$audienceId]);
                        $pids = array_map('intval', $p->fetchAll(PDO::FETCH_COLUMN) ?: []);
                        $pPhones = $this->parentPhones($pids);
                        foreach ($pids as $pid) {
                            $add('parent', null, $pid, $pPhones[$pid] ?? null);
                        }
                    }
                }
            } elseif ($audienceType === 'class' && $audienceId) {
                $s = $this->pdo->prepare("SELECT u.id FROM student_enrollments se JOIN users u ON u.id=se.student_id WHERE se.class_id=? AND u.deleted_at IS NULL AND u.is_active=1");
                $s->execute([$audienceId]);
                $uids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $phones = $this->studentPhones($uids);
                foreach ($uids as $uid) {
                    $add('student', $uid, null, $phones[$uid] ?? null);
                }
                if ($includeParents) {
                    $p = $this->pdo->prepare("SELECT DISTINCT ps.parent_id FROM parent_students ps JOIN student_enrollments se ON se.student_id=ps.student_id WHERE se.class_id=?");
                    $p->execute([$audienceId]);
                    $pids = array_map('intval', $p->fetchAll(PDO::FETCH_COLUMN) ?: []);
                    $pPhones = $this->parentPhones($pids);
                    foreach ($pids as $pid) {
                        $add('parent', null, $pid, $pPhones[$pid] ?? null);
                    }
                }
            } elseif ($audienceType === 'students') {
                $uids = array_map('intval', $this->pdo->query("SELECT id FROM users WHERE role='student' AND deleted_at IS NULL AND is_active=1 LIMIT 5000")->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $phones = $this->studentPhones($uids);
                foreach ($uids as $uid) {
                    $add('student', $uid, null, $phones[$uid] ?? null);
                }
            } elseif ($audienceType === 'parents') {
                foreach ($this->pdo->query('SELECT id,phone FROM parent_accounts LIMIT 5000')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $add('parent', null, (int)$row['id'], (string)($row['phone'] ?? ''));
                }
            } elseif ($audienceType === 'teachers') {
                foreach ($this->pdo->query("SELECT id FROM users WHERE role='teacher' AND deleted_at IS NULL AND is_active=1 LIMIT 2000")->fetchAll(PDO::FETCH_COLUMN) ?: [] as $uid) {
                    $add('teacher', (int)$uid, null, null, $channel !== 'in_app' ? 'Teacher WhatsApp/SMS not configured in bulk resolver' : null);
                }
            } elseif ($audienceType === 'everyone') {
                return array_merge(
                    $this->resolveRecipients('students', null, '', $channel, $category, false),
                    $this->resolveRecipients('parents', null, '', $channel, $category, false),
                    $this->resolveRecipients('teachers', null, '', $channel, $category, false)
                );
            } elseif ($audienceType === 'subject' && $audienceId) {
                $s = $this->pdo->prepare("SELECT DISTINCT se.student_id FROM student_enrollments se JOIN student_classes c ON c.id=se.class_id WHERE c.subject_id=? AND c.deleted_at IS NULL");
                $s->execute([$audienceId]);
                $uids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $phones = $this->studentPhones($uids);
                foreach ($uids as $uid) {
                    $add('student', $uid, null, $phones[$uid] ?? null);
                }
            }
        } catch (Throwable $e) {
        }

        // Channel-specific exclusions
        foreach ($out as &$r) {
            if (!empty($r['exclusion_reason'])) {
                continue;
            }
            if (in_array($channel, ['whatsapp', 'sms'], true) && empty($r['phone'])) {
                $r['exclusion_reason'] = 'Missing phone number';
            }
        }
        unset($r);

        // Deduplicate
        $seen = [];
        $deduped = [];
        foreach ($out as $r) {
            $key = ($r['audience'] ?? '').'|'.($r['user_id'] ?? 0).'|'.($r['parent_id'] ?? 0).'|'.($r['phone'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $r;
        }
        return $deduped;
    }

    /** @param list<array<string,mixed>> $included @param list<array<string,mixed>> $excluded */
    private function storeRecipients(int $messageId, array $included, array $excluded): void
    {
        $ins = $this->pdo->prepare('INSERT INTO communication_recipients(message_id,audience,user_id,parent_id,phone,channel,status,exclusion_reason) VALUES(?,?,?,?,?,?,?,?)');
        foreach ($included as $r) {
            $ins->execute([$messageId, $r['audience'], $r['user_id'], $r['parent_id'], $r['phone'], $r['channel'], 'queued', null]);
            $this->event((int)$this->pdo->lastInsertId(), 'queued', 'Recipient queued');
        }
        foreach ($excluded as $r) {
            $ins->execute([$messageId, $r['audience'], $r['user_id'], $r['parent_id'], $r['phone'], $r['channel'], 'excluded', $r['exclusion_reason']]);
        }
    }

    /**
     * @param array{channel?:string,from?:string,to?:string,q?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function listFailedRecipients(int $limit = 50, array $filters = []): array
    {
        $where = ["r.status='failed'"];
        $p = [];
        $channel = trim((string)($filters['channel'] ?? ''));
        if ($channel !== '' && in_array($channel, ['in_app', 'whatsapp', 'sms'], true)) {
            $where[] = 'r.channel=?';
            $p[] = $channel;
        }
        $from = (string)($filters['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'DATE(COALESCE(r.updated_at,r.created_at))>=?';
            $p[] = $from;
        }
        $to = (string)($filters['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'DATE(COALESCE(r.updated_at,r.created_at))<=?';
            $p[] = $to;
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(m.subject LIKE ? OR m.body LIKE ? OR r.phone LIKE ? OR CAST(r.id AS CHAR)=? OR CAST(r.user_id AS CHAR)=? OR CAST(r.parent_id AS CHAR)=?)';
            $like = '%'.mb_substr($q, 0, 60).'%';
            array_push($p, $like, $like, $like, $q, $q, $q);
        }
        try {
            $sql = 'SELECT r.*, m.subject, m.body, m.category, m.priority
                FROM communication_recipients r
                JOIN communication_messages m ON m.id=r.message_id
                WHERE '.implode(' AND ', $where).'
                ORDER BY r.updated_at DESC, r.id DESC
                LIMIT '.(int)max(1, min(300, $limit));
            $s = $this->pdo->prepare($sql);
            $s->execute($p);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Retry all failed rows matching filters (capped). Never sends synchronously.
     * @param array{channel?:string,from?:string,to?:string,q?:string} $filters
     * @return array{retried:int,skipped:int,errors:list<string>}
     */
    public function retryFailedMatching(array $filters, int $userId, int $cap = 50): array
    {
        $rows = $this->listFailedRecipients($cap, $filters);
        $ids = array_map(static fn($r) => (int)$r['id'], $rows);
        return $this->retryFailedBatch($ids, $userId);
    }

    public const MAX_RETRIES = 3;

    /**
     * Re-queue a failed recipient for background delivery. Never sends synchronously.
     */
    public function retryFailed(int $recipientId, int $userId): void
    {
        $s = $this->pdo->prepare('SELECT * FROM communication_recipients WHERE id=? LIMIT 1');
        $s->execute([$recipientId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['status'] ?? '') !== 'failed') {
            throw new RuntimeException('Failed recipient not found.');
        }
        $retries = (int)($row['retry_count'] ?? 0);
        if ($retries >= self::MAX_RETRIES) {
            throw new RuntimeException('Retry limit reached for this recipient ('.self::MAX_RETRIES.').');
        }
        $channel = (string)$row['channel'];
        if (in_array($channel, ['whatsapp', 'sms'], true) && empty($row['phone'])) {
            throw new RuntimeException('Cannot retry: missing phone number.');
        }
        $this->pdo->prepare("UPDATE communication_recipients SET status='queued', exclusion_reason=NULL, provider_message_id=NULL, retry_count=retry_count+1, last_retry_at=NOW(), updated_at=NOW() WHERE id=?")
            ->execute([$recipientId]);
        $this->pdo->prepare("UPDATE communication_messages SET status='queued' WHERE id=? AND status IN ('sent','failed','queued')")
            ->execute([(int)$row['message_id']]);
        $this->event($recipientId, 'retried', 'Manual retry by user '.$userId);
        if (function_exists('log_audit')) {
            log_audit($this->pdo, 'communication_retry', 'communication_recipients', $recipientId, null, ['user_id' => $userId, 'channel' => $channel]);
        }
    }

    /**
     * @param list<int> $ids
     * @return array{retried:int,skipped:int,errors:list<string>}
     */
    public function retryFailedBatch(array $ids, int $userId): array
    {
        $out = ['retried' => 0, 'skipped' => 0, 'errors' => []];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id < 1) {
                continue;
            }
            try {
                $this->retryFailed($id, $userId);
                $out['retried']++;
            } catch (Throwable $e) {
                $out['skipped']++;
                if (count($out['errors']) < 8) {
                    $out['errors'][] = '#'.$id.': '.$e->getMessage();
                }
            }
        }
        return $out;
    }

    /** @return array<string,int|string> */
    public function opsSnapshot(): array
    {
        $q = function (string $sql): int {
            try {
                return (int)$this->pdo->query($sql)->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };
        return [
            'failed' => $q("SELECT COUNT(*) FROM communication_recipients WHERE status='failed'"),
            'queued' => $q("SELECT COUNT(*) FROM communication_recipients WHERE status='queued'") + $q("SELECT COUNT(*) FROM communication_recipients WHERE status='processing'"),
            'delivered' => $q("SELECT COUNT(*) FROM communication_recipients WHERE status='delivered'"),
            'read' => $q("SELECT COUNT(*) FROM communication_recipients WHERE status='read'"),
            'retries_today' => $q("SELECT COUNT(*) FROM communication_delivery_events WHERE event_type='retried' AND DATE(created_at)=CURDATE()"),
            'webhook_events_24h' => $q("SELECT COUNT(*) FROM communication_channel_webhooks WHERE created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)"),
            'note' => 'Retries re-queue only; delivery still runs via tools/communication_queue.php',
        ];
    }

    private function markRecipient(int $id, string $status, string $detail): void
    {
        try {
            $this->pdo->prepare('UPDATE communication_recipients SET status=?,exclusion_reason=COALESCE(?,exclusion_reason),updated_at=NOW() WHERE id=?')
                ->execute([$status, $detail !== '' ? mb_substr($detail, 0, 255) : null, $id]);
        } catch (Throwable $e) {
        }
    }

    private function attachProviderId(int $recipientId, string $providerMessageId, string $provider): void
    {
        try {
            $this->pdo->prepare('UPDATE communication_recipients SET provider_message_id=?, provider=?, updated_at=NOW() WHERE id=?')
                ->execute([mb_substr($providerMessageId, 0, 120), mb_substr($provider, 0, 40), $recipientId]);
        } catch (Throwable $e) {
        }
    }

    /**
     * Map provider delivery callbacks to recipient + delivery_events.
     * Does NOT claim "delivered" unless the provider status is delivered/read.
     * @return array{matched:bool,recipient_id:int,mapped_status:string}
     */
    public function recordProviderStatus(string $providerMessageId, string $providerStatus, string $channel = 'whatsapp', string $detail = ''): array
    {
        $providerMessageId = trim($providerMessageId);
        $providerStatus = strtolower(trim($providerStatus));
        if ($providerMessageId === '') {
            return ['matched' => false, 'recipient_id' => 0, 'mapped_status' => ''];
        }
        $mapped = match ($providerStatus) {
            'sent', 'accepted', 'queued', 'pending', 'processed' => 'accepted',
            'delivered', 'delivery' => 'delivered',
            'read', 'played', 'seen' => 'read',
            'failed', 'undelivered', 'rejected', 'error', 'expired' => 'failed',
            'deleted' => 'failed',
            default => $providerStatus !== '' ? $providerStatus : 'unknown',
        };
        $recipientId = 0;
        try {
            $s = $this->pdo->prepare('SELECT id, status FROM communication_recipients WHERE provider_message_id=? ORDER BY id DESC LIMIT 1');
            $s->execute([mb_substr($providerMessageId, 0, 120)]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $recipientId = (int)$row['id'];
                // Only upgrade toward terminal delivery states; never overwrite failed with sent.
                $current = (string)($row['status'] ?? '');
                $next = $current;
                if ($mapped === 'failed') {
                    $next = 'failed';
                } elseif ($mapped === 'read') {
                    $next = 'read';
                } elseif ($mapped === 'delivered' && !in_array($current, ['read', 'failed'], true)) {
                    $next = 'delivered';
                } elseif ($mapped === 'accepted' && in_array($current, ['queued', 'processing', 'sent', ''], true)) {
                    $next = 'sent'; // API accepted; still not handset-delivered
                }
                if ($next !== $current && $next !== '') {
                    $this->markRecipient($recipientId, $next, $detail !== '' ? $detail : ('Provider status: '.$providerStatus));
                }
                $this->event($recipientId, $mapped, $detail !== '' ? $detail : ($channel.' provider: '.$providerStatus));
            }
            try {
                $this->pdo->prepare('INSERT INTO communication_channel_webhooks(channel,provider_event_id,status,payload_json,processed) VALUES(?,?,?,?,?)')
                    ->execute([$channel, mb_substr($providerMessageId, 0, 120), $providerStatus, json_encode(['mapped' => $mapped, 'detail' => $detail]), $recipientId > 0 ? 1 : 0]);
            } catch (Throwable $e) {
            }
        } catch (Throwable $e) {
        }
        return ['matched' => $recipientId > 0, 'recipient_id' => $recipientId, 'mapped_status' => $mapped];
    }

    private function event(int $recipientId, string $type, string $detail): void
    {
        if ($recipientId < 1) {
            return;
        }
        try {
            $this->pdo->prepare('INSERT INTO communication_delivery_events(recipient_id,event_type,detail) VALUES(?,?,?)')
                ->execute([$recipientId, mb_substr($type, 0, 40), mb_substr($detail, 0, 500)]);
        } catch (Throwable $e) {
        }
    }

    private function studentPhone(int $userId): ?string
    {
        $map = $this->studentPhones([$userId]);
        return $map[$userId] ?? null;
    }

    /**
     * @param list<int> $userIds
     * @return array<int,string>
     */
    private function studentPhones(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }
        $map = [];
        try {
            foreach (array_chunk($userIds, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $s = $this->pdo->prepare("SELECT sp.user_id, COALESCE(NULLIF(sp.whatsapp_number,''), NULLIF(u.username,'')) AS phone
                    FROM student_profiles sp
                    JOIN users u ON u.id=sp.user_id
                    WHERE sp.user_id IN ($ph)");
                $s->execute($chunk);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $p = preg_replace('/\D+/', '', (string)($row['phone'] ?? '')) ?? '';
                    if ($p !== '') {
                        $map[(int)$row['user_id']] = $p;
                    }
                }
            }
        } catch (Throwable $e) {
        }
        return $map;
    }

    private function parentPhone(int $parentId): ?string
    {
        $map = $this->parentPhones([$parentId]);
        return $map[$parentId] ?? null;
    }

    /**
     * @param list<int> $parentIds
     * @return array<int,string>
     */
    private function parentPhones(array $parentIds): array
    {
        $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds), static fn(int $id): bool => $id > 0)));
        if ($parentIds === []) {
            return [];
        }
        $map = [];
        try {
            foreach (array_chunk($parentIds, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $s = $this->pdo->prepare("SELECT id, phone FROM parent_accounts WHERE id IN ($ph)");
                $s->execute($chunk);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $p = preg_replace('/\D+/', '', (string)($row['phone'] ?? '')) ?? '';
                    if ($p !== '') {
                        $map[(int)$row['id']] = $p;
                    }
                }
            }
        } catch (Throwable $e) {
        }
        return $map;
    }

    /** @return array<string,mixed>|null */
    private function template(int $id): ?array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM communication_templates WHERE id=?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,string> */
    private function defaultVars(): array
    {
        return [
            'college_name' => 'Edexcel College',
            'date' => date('d M Y'),
            'time' => date('h:i A'),
            'student_name' => '',
            'parent_name' => '',
            'class_name' => '',
            'teacher_name' => '',
            'subject' => '',
            'amount' => '',
            'programme' => '',
        ];
    }

    private function normalizeCategory(string $category): string
    {
        $category = strtolower(trim($category));
        return NotificationCenterService::isAllowedCategory($category) ? $category : 'system';
    }

    private function assertSafeContent(string $text): void
    {
        $lower = strtolower($text);
        foreach (['password', 'otp', 'api_key', 'apikey', 'secret', 'card number', 'cvv'] as $bad) {
            if (str_contains($lower, $bad)) {
                throw new RuntimeException('Messages must not contain sensitive credentials or secrets.');
            }
        }
    }
}
