<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class SystemHealthService
{
    public function __construct(private PDO $pdo)
    {
        if (function_exists('ensure_ops_schema')) {
            ensure_ops_schema($this->pdo);
        }
    }

    /**
     * Full V2 health snapshot with status indicators: green|yellow|red|grey.
     *
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $jobs = $this->jobs();
        $whatsapp = $this->whatsappDetailed();
        $bunny = $this->bunnyDetailed();
        $livekit = $this->livekitDetailed();
        $onepay = $this->onepayDetailed();
        $outbox = $this->outboxDetailed();
        $backup = (new BackupService($this->pdo))->healthSummary();
        $disk = $this->disk();
        $php = $this->phpInfo();
        $database = $this->database();
        $uploads = $this->uploads();
        $cache = $this->cacheStatus();
        $cron = $this->cronStatus($jobs);
        $ai = $this->aiStatus();
        $sms = $this->smsStatus();
        $ssl = $this->sslStatus();
        $payments = $this->payments();
        $migrations = $this->migrations();
        $app = $this->appVersion();

        return [
            'database' => $database,
            'php' => $php,
            'disk' => $disk,
            'uploads' => $uploads,
            'cache' => $cache,
            'cron' => $cron,
            'jobs' => $jobs,
            'whatsapp' => $whatsapp,
            'onepay' => $onepay,
            'bunny' => $bunny,
            'livekit' => $livekit,
            'ai' => $ai,
            'sms' => $sms,
            'ssl' => $ssl,
            'backup' => $backup,
            'payments' => $payments,
            'outbox' => $outbox,
            'migrations' => $migrations,
            'app' => $app,
            'indicators' => $this->indicatorsCompact([
                'Database' => $database,
                'PHP' => $php,
                'Disk' => $disk,
                'SSL' => $ssl,
                'Cron' => $cron,
                'WhatsApp' => $whatsapp,
                'OnePay' => $onepay,
                'Bunny' => $bunny,
                'LiveKit' => $livekit,
                'AI' => $ai,
                'SMS' => $sms,
                'Backup' => $backup,
            ]),
            'counts' => [
                'failed_jobs' => $this->countFailedJobs($jobs),
                'failed_payments' => (int)($payments['failed'] ?? 0),
                'pending_bank_slips' => (int)($payments['pending_bank_slips'] ?? 0),
                'stuck_payments' => (int)($payments['stuck'] ?? 0),
                'stuck_recordings' => (int)($bunny['stuck_processing'] ?? 0),
                'failed_whatsapp' => (int)($outbox['failed_today'] ?? 0),
                'pending_outbox' => (int)($outbox['pending'] ?? 0),
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function staleJobs(int $minutes = 20): array
    {
        $stale = [];
        $expected = [
            'classroom_reminders' => 20,
            'bunny_sync' => 30,
            'whatsapp_outbox' => 15,
            'ops_jobs' => 20,
            'automated_backup' => 36 * 60,
        ];
        foreach ($this->jobs() as $row) {
            $name = (string)$row['job_name'];
            $limit = $expected[$name] ?? 0;
            if ($limit < 1) {
                continue;
            }
            $finished = (string)($row['last_finished_at'] ?? '');
            if ($finished === '' || strtotime($finished) < time() - ($limit * 60)) {
                $stale[] = $row;
            }
        }
        return $stale;
    }

    public function alertIfStale(): int
    {
        $stale = $this->staleJobs();
        $names = array_map(static fn(array $r) => (string)$r['job_name'], $stale);
        $snap = $this->snapshot();
        $snap['cron']['stale_names'] = $names;
        if ($names !== []) {
            $snap['cron']['status'] = 'red';
        }
        return (new HealthAlertService($this->pdo))->evaluateAndAlert($snap);
    }

    public function whatsapp(): array
    {
        return $this->whatsappDetailed();
    }

    /**
     * @param array<string,array<string,mixed>> $map
     * @return list<array{name:string,status:string,label:string,detail:string}>
     */
    private function indicatorsCompact(array $map): array
    {
        $out = [];
        foreach ($map as $name => $row) {
            $status = (string)($row['status'] ?? 'grey');
            $label = (string)($row['label'] ?? strtoupper($status));
            $detail = (string)($row['detail'] ?? $row['message'] ?? '');
            $out[] = compact('name', 'status', 'label', 'detail');
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function jobs(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT * FROM system_job_runs ORDER BY job_name');
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<array<string,mixed>> $jobs
     */
    private function countFailedJobs(array $jobs): int
    {
        $n = 0;
        foreach ($jobs as $j) {
            if ((int)($j['last_ok'] ?? 1) !== 1) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @return array<string,mixed>
     */
    private function database(): array
    {
        try {
            $this->pdo->query('SELECT 1');
            $ver = (string)$this->pdo->query('SELECT VERSION()')->fetchColumn();
            return [
                'status' => 'green',
                'label' => 'OK',
                'detail' => $ver,
                'message' => 'Connected',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'red',
                'label' => 'DOWN',
                'detail' => '',
                'message' => 'Database unavailable',
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function phpInfo(): array
    {
        $required = ['pdo', 'pdo_mysql', 'openssl', 'curl', 'mbstring', 'json', 'zip'];
        $missing = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        $ver = PHP_VERSION;
        $ok = version_compare($ver, '8.1.0', '>=') && $missing === [];
        return [
            'status' => $ok ? 'green' : ($missing !== [] ? 'red' : 'yellow'),
            'label' => $ver,
            'detail' => $missing !== [] ? ('Missing: ' . implode(', ', $missing)) : 'Extensions OK',
            'version' => $ver,
            'extensions' => $required,
            'missing' => $missing,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function disk(): array
    {
        $path = dirname(__DIR__, 2);
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free === false || $total === false || $total <= 0) {
            return ['status' => 'grey', 'label' => 'Unknown', 'used_percent' => null, 'detail' => 'Cannot read disk'];
        }
        $usedPct = (int)round((($total - $free) / $total) * 100);
        $warn = (int)(function_exists('ops_setting') ? ops_setting($this->pdo, 'disk_warning_percent', '85') : 85);
        $crit = (int)(function_exists('ops_setting') ? ops_setting($this->pdo, 'disk_critical_percent', '95') : 95);
        $status = 'green';
        if ($usedPct >= $crit) {
            $status = 'red';
        } elseif ($usedPct >= $warn) {
            $status = 'yellow';
        }
        return [
            'status' => $status,
            'label' => $usedPct . '%',
            'used_percent' => $usedPct,
            'free_bytes' => (int)$free,
            'total_bytes' => (int)$total,
            'detail' => $this->formatBytes((int)$free) . ' free',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function uploads(): array
    {
        $dirs = ['files', 'uploads', 'files/payment_slips', 'files/documents', 'files/homework'];
        $base = dirname(__DIR__, 2);
        $bad = [];
        foreach ($dirs as $d) {
            $path = $base . '/' . $d;
            if (!is_dir($path) || !is_writable($path)) {
                $bad[] = $d;
            }
        }
        return [
            'status' => $bad === [] ? 'green' : 'yellow',
            'label' => $bad === [] ? 'Writable' : 'Check dirs',
            'detail' => $bad === [] ? 'Upload directories OK' : ('Issues: ' . implode(', ', $bad)),
            'problems' => $bad,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function cacheStatus(): array
    {
        $dir = dirname(__DIR__, 2) . '/cache';
        if (!is_dir($dir)) {
            return ['status' => 'grey', 'label' => 'Missing', 'detail' => 'cache/ not found'];
        }
        $writable = is_writable($dir);
        $files = glob($dir . '/*.cache') ?: [];
        return [
            'status' => $writable ? 'green' : 'yellow',
            'label' => $writable ? 'OK' : 'Read-only',
            'detail' => count($files) . ' cache files',
            'count' => count($files),
        ];
    }

    /**
     * @param list<array<string,mixed>> $jobs
     * @return array<string,mixed>
     */
    private function cronStatus(array $jobs): array
    {
        $expected = ['classroom_reminders', 'bunny_sync', 'ops_jobs', 'whatsapp_outbox'];
        $ok = 0;
        $stale = $this->staleJobs();
        $staleNames = array_map(static fn(array $r) => (string)$r['job_name'], $stale);
        foreach ($expected as $name) {
            foreach ($jobs as $j) {
                if ((string)$j['job_name'] === $name && (int)($j['last_ok'] ?? 0) === 1 && !empty($j['last_finished_at'])) {
                    $ok++;
                    break;
                }
            }
        }
        $total = count($expected);
        $status = 'green';
        if ($ok === 0) {
            $status = 'yellow';
        }
        if ($staleNames !== []) {
            $status = 'red';
        }
        return [
            'status' => $status,
            'label' => "{$ok}/{$total}",
            'detail' => $staleNames !== [] ? ('Stale: ' . implode(', ', $staleNames)) : 'Jobs reporting',
            'stale_names' => $staleNames,
            'ok' => $ok,
            'total' => $total,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function whatsappDetailed(): array
    {
        $base = $this->whatsappBase();
        $configured = !empty($base['configured']);
        $valid = $base['valid'];
        $status = 'grey';
        $label = 'Not configured';
        if ($configured) {
            if ($valid === false) {
                $status = 'red';
                $label = 'Auth failed';
            } elseif (!empty($base['warning'])) {
                $status = 'yellow';
                $label = 'Token expiring';
            } else {
                $status = 'green';
                $label = 'Connected';
            }
        }
        return array_merge($base, [
            'status' => $status,
            'label' => $label,
            'detail' => (string)($base['message'] ?? ''),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function whatsappBase(): array
    {
        $token = '';
        $expires = '';
        try {
            if (function_exists('ops_setting')) {
                $token = ops_setting($this->pdo, 'meta_access_token');
                $expires = ops_setting($this->pdo, 'meta_token_expires_at');
            }
        } catch (Throwable $e) {
        }
        $days = null;
        if ($expires !== '' && strtotime($expires)) {
            $days = (int)floor((strtotime($expires) - time()) / 86400);
        }
        $debug = $this->debugMetaToken($token);
        if ($debug['expires_at'] !== '') {
            $expires = $debug['expires_at'];
            $days = (int)floor((strtotime($expires) - time()) / 86400);
            try {
                if (function_exists('ops_save_setting')) {
                    ops_save_setting($this->pdo, 'meta_token_expires_at', $expires);
                }
            } catch (Throwable $e) {
            }
        }
        return [
            'configured' => $token !== '',
            'expires_at' => $expires,
            'days_left' => $days,
            'warning' => $days !== null && $days <= 14,
            'valid' => $debug['valid'],
            'message' => $debug['message'],
        ];
    }

    /**
     * @return array{valid:?bool,expires_at:string,message:string}
     */
    private function debugMetaToken(string $token): array
    {
        if ($token === '') {
            return ['valid' => null, 'expires_at' => '', 'message' => 'No Cloud API token saved.'];
        }
        $url = 'https://graph.facebook.com/v21.0/debug_token?input_token=' . rawurlencode($token)
            . '&access_token=' . rawurlencode($token);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['valid' => null, 'expires_at' => '', 'message' => 'Could not reach Meta.'];
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        if ($data === []) {
            return ['valid' => null, 'expires_at' => '', 'message' => 'Meta did not return token debug data.'];
        }
        $expiresAt = '';
        if (!empty($data['expires_at']) && (int)$data['expires_at'] > 0) {
            $expiresAt = date('Y-m-d H:i:s', (int)$data['expires_at']);
        } elseif (!empty($data['data_access_expires_at'])) {
            $expiresAt = date('Y-m-d H:i:s', (int)$data['data_access_expires_at']);
        }
        $ok = !empty($data['is_valid']);
        return [
            'valid' => $ok,
            'expires_at' => $expiresAt,
            'message' => $ok ? 'Token is valid.' : 'Token is not valid. Paste a new API Setup token.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function bunnyDetailed(): array
    {
        $base = $this->bunny();
        $stuck = (int)($base['stuck_processing'] ?? 0);
        $configured = false;
        try {
            if (function_exists('ops_setting')) {
                $configured = ops_setting($this->pdo, 'bunny_library_id') !== ''
                    || ops_setting($this->pdo, 'bunny_api_key') !== '';
            }
        } catch (Throwable $e) {
        }
        if (!$configured && defined('BUNNY_LIBRARY_ID')) {
            $configured = (string)BUNNY_LIBRARY_ID !== '';
        }
        $status = 'grey';
        $label = 'Not configured';
        if ($configured || $stuck > 0 || (int)($base['pending_delete'] ?? 0) > 0) {
            if ($stuck >= 3) {
                $status = 'red';
                $label = 'Stuck';
            } elseif ($stuck > 0) {
                $status = 'yellow';
                $label = 'Processing';
            } else {
                $status = 'green';
                $label = 'Connected';
            }
        }
        return array_merge($base, [
            'status' => $status,
            'label' => $label,
            'detail' => "Stuck={$stuck}",
            'configured' => $configured,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function bunny(): array
    {
        try {
            $stuck = (int)$this->pdo->query("
                SELECT COUNT(*) FROM class_recordings
                WHERE deleted_at IS NULL AND status IN ('uploading','processing')
                  AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ")->fetchColumn();
            $pendingDelete = (int)$this->pdo->query("
                SELECT COUNT(*) FROM class_recordings
                WHERE retention_status = 'scheduled_for_deletion' AND deleted_at IS NULL
            ")->fetchColumn();
            return ['stuck_processing' => $stuck, 'pending_delete' => $pendingDelete];
        } catch (Throwable $e) {
            return ['stuck_processing' => 0, 'pending_delete' => 0];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function livekitDetailed(): array
    {
        $base = $this->livekit();
        $url = (string)(getenv('LIVEKIT_URL') ?: '');
        $key = (string)(getenv('LIVEKIT_API_KEY') ?: '');
        $configured = $url !== '' && $key !== '';
        try {
            if (!$configured && function_exists('ops_setting')) {
                $configured = ops_setting($this->pdo, 'livekit_url') !== '';
            }
        } catch (Throwable $e) {
        }
        $status = $configured ? 'green' : 'grey';
        $label = $configured ? 'Connected' : 'Not configured';
        $last = (string)($base['last_meeting_update'] ?? '');
        if ($configured && $last !== '' && strtotime($last) < time() - 14 * 86400) {
            $status = 'yellow';
            $label = 'Quiet';
        }
        return array_merge($base, [
            'status' => $status,
            'label' => $label,
            'detail' => $last !== '' ? ('Last meeting update ' . $last) : 'No recent meetings',
            'message' => $configured ? 'Configured' : 'LiveKit not configured',
            'configured' => $configured,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function livekit(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT MAX(updated_at) FROM online_meetings');
            $last = $stmt ? (string)$stmt->fetchColumn() : '';
            return ['last_meeting_update' => $last];
        } catch (Throwable $e) {
            return ['last_meeting_update' => ''];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function onepayDetailed(): array
    {
        $base = $this->onepay();
        $configured = false;
        try {
            if (function_exists('ops_setting')) {
                $configured = ops_setting($this->pdo, 'onepay_app_id') !== ''
                    || ops_setting($this->pdo, 'onepay_merchant_id') !== '';
            }
        } catch (Throwable $e) {
        }
        $open = (int)($base['open_checkouts'] ?? 0);
        $status = $configured ? 'green' : 'grey';
        $label = $configured ? 'Connected' : 'Not configured';
        if ($configured && $open > 50) {
            $status = 'yellow';
            $label = 'Many open';
        }
        return array_merge($base, [
            'status' => $status,
            'label' => $label,
            'detail' => "Open checkouts (24h): {$open}",
            'message' => $configured ? 'OnePay configured' : 'OnePay not configured',
            'configured' => $configured,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function onepay(): array
    {
        try {
            $pending = (int)$this->pdo->query("
                SELECT COUNT(*) FROM payment_transactions
                WHERE gateway = 'onepay' AND status IN ('initiated','pending')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ")->fetchColumn();
            return ['open_checkouts' => $pending];
        } catch (Throwable $e) {
            return ['open_checkouts' => 0];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function outboxDetailed(): array
    {
        $base = $this->outbox();
        $pending = 0;
        try {
            $pending = (int)$this->pdo->query("
                SELECT COUNT(*) FROM whatsapp_outbox
                WHERE status IN ('pending','queued','sending')
            ")->fetchColumn();
        } catch (Throwable $e) {
        }
        $failed = (int)($base['failed_today'] ?? 0);
        $status = 'green';
        if ($failed >= 20 || $pending >= 50) {
            $status = 'red';
        } elseif ($failed > 0 || $pending > 10) {
            $status = 'yellow';
        }
        return array_merge($base, [
            'pending' => $pending,
            'status' => $status,
            'label' => $failed > 0 ? "{$failed} failed" : 'OK',
            'detail' => "Pending={$pending}",
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function outbox(): array
    {
        try {
            $failed = (int)$this->pdo->query("
                SELECT COUNT(*) FROM whatsapp_outbox
                WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ")->fetchColumn();
            return ['failed_today' => $failed];
        } catch (Throwable $e) {
            return ['failed_today' => 0];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function aiStatus(): array
    {
        $groq = (string)(getenv('GROQ_API_KEY') ?: '');
        $gemini = (string)(getenv('GEMINI_API_KEY') ?: '');
        try {
            if ($groq === '' && function_exists('ops_setting')) {
                $groq = ops_setting($this->pdo, 'groq_api_key');
            }
            if ($gemini === '' && function_exists('ops_setting')) {
                $gemini = ops_setting($this->pdo, 'gemini_api_key');
            }
        } catch (Throwable $e) {
        }
        $configured = $groq !== '' || $gemini !== '';
        return [
            'status' => $configured ? 'green' : 'grey',
            'label' => $configured ? 'Connected' : 'Not configured',
            'detail' => $configured ? 'AI provider key present' : 'No AI key',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function smsStatus(): array
    {
        $configured = false;
        try {
            if (function_exists('ops_setting')) {
                $configured = ops_setting($this->pdo, 'sms_api_key') !== ''
                    || ops_setting($this->pdo, 'sms_gateway_url') !== '';
            }
        } catch (Throwable $e) {
        }
        return [
            'status' => $configured ? 'green' : 'grey',
            'label' => $configured ? 'Connected' : 'Not configured',
            'detail' => $configured ? 'SMS gateway configured' : 'SMS optional',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function sslStatus(): array
    {
        $host = parse_url((string)(getenv('APP_URL') ?: 'https://edexcel.college'), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return ['status' => 'grey', 'label' => 'Unknown', 'detail' => 'No APP_URL host'];
        }
        $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true], 'http' => ['timeout' => 5]]);
        $client = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$client) {
            return ['status' => 'yellow', 'label' => 'Unreachable', 'detail' => 'Could not probe SSL'];
        }
        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['status' => 'yellow', 'label' => 'Unknown', 'detail' => 'No peer cert'];
        }
        $parsed = openssl_x509_parse($cert);
        $to = isset($parsed['validTo_time_t']) ? (int)$parsed['validTo_time_t'] : 0;
        if ($to < time()) {
            return ['status' => 'red', 'label' => 'Expired', 'detail' => 'Certificate expired', 'expires_at' => date('Y-m-d', $to)];
        }
        $days = (int)floor(($to - time()) / 86400);
        $status = $days <= 14 ? 'yellow' : 'green';
        return [
            'status' => $status,
            'label' => $days <= 14 ? "Expires {$days}d" : 'Valid',
            'detail' => 'Expires ' . date('Y-m-d', $to),
            'expires_at' => date('Y-m-d', $to),
            'days_left' => $days,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function payments(): array
    {
        $failed = 0;
        $pendingSlips = 0;
        $stuck = 0;
        try {
            $failed = (int)$this->pdo->query("
                SELECT COUNT(*) FROM payment_transactions
                WHERE status IN ('failed','error') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $pendingSlips = (int)$this->pdo->query("
                SELECT COUNT(*) FROM bank_transfer_slips
                WHERE status IN ('pending','submitted') AND deleted_at IS NULL
            ")->fetchColumn();
        } catch (Throwable $e) {
            try {
                $pendingSlips = (int)$this->pdo->query("
                    SELECT COUNT(*) FROM bank_transfer_slips WHERE status = 'pending'
                ")->fetchColumn();
            } catch (Throwable $e2) {
            }
        }
        try {
            $stuck = (int)$this->pdo->query("
                SELECT COUNT(*) FROM payment_transactions
                WHERE status IN ('initiated','pending')
                  AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ")->fetchColumn();
        } catch (Throwable $e) {
        }
        return [
            'failed' => $failed,
            'pending_bank_slips' => $pendingSlips,
            'stuck' => $stuck,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function migrations(): array
    {
        $dir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        $fileNames = array_map('basename', $files);
        $applied = [];
        try {
            $applied = $this->pdo->query('SELECT migration FROM schema_migrations')
                ->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [
                'status' => 'yellow',
                'label' => 'Unknown',
                'detail' => 'schema_migrations missing',
                'message' => 'Run php bin/migrate.php',
                'pending' => $fileNames,
                'schema_version' => null,
            ];
        }
        $pending = array_values(array_diff($fileNames, $applied));
        $latest = $fileNames !== [] ? $fileNames[count($fileNames) - 1] : null;
        $status = $pending === [] ? 'green' : 'yellow';
        return [
            'status' => $status,
            'label' => $pending === [] ? 'Up to date' : (count($pending) . ' pending'),
            'detail' => $latest ? ('Latest file: ' . $latest) : '',
            'message' => $pending === [] ? 'All migrations applied' : ('Pending: ' . implode(', ', $pending)),
            'pending' => $pending,
            'applied_count' => count($applied),
            'schema_version' => $latest,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function appVersion(): array
    {
        $ver = '2.0.0';
        try {
            if (function_exists('ops_setting')) {
                $ver = ops_setting($this->pdo, 'app_version', '2.0.0');
            }
        } catch (Throwable $e) {
        }
        return [
            'status' => 'green',
            'label' => $ver,
            'detail' => 'Application version',
            'version' => $ver,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float)$bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }
        return round($n, 1) . ' ' . $units[$i];
    }
}
