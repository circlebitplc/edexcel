<?php
declare(strict_types=1);

namespace Edexcel\Http;

use PDO;
use Throwable;

/**
 * File-cache rate limits and temporary blocks.
 * Counters stay off the database. A database row is written only when an
 * administrator blocks an address, or the first time a limit trips in a window.
 */
final class AbuseGuard
{
    private static ?string $storeOverride = null;

    /** @return array<string,array{max:int,window:int,block:int}> */
    public static function defaults(): array
    {
        return [
            'global_anon' => ['max' => 300, 'window' => 60, 'block' => 30],
            'global_auth_ip' => ['max' => 6000, 'window' => 60, 'block' => 30],
            'global_auth_user' => ['max' => 240, 'window' => 60, 'block' => 20],
            'login' => ['max' => 30, 'window' => 900, 'block' => 900],
            'oauth' => ['max' => 40, 'window' => 600, 'block' => 300],
            'register' => ['max' => 10, 'window' => 3600, 'block' => 600],
            'payment_create' => ['max' => 8, 'window' => 600, 'block' => 300],
            'payment_webhook' => ['max' => 180, 'window' => 60, 'block' => 30],
            'webhook' => ['max' => 300, 'window' => 60, 'block' => 20],
            'sms' => ['max' => 8, 'window' => 600, 'block' => 600],
            'sms_ip' => ['max' => 40, 'window' => 600, 'block' => 300],
            'visitor' => ['max' => 2000, 'window' => 60, 'block' => 15],
            'api_public' => ['max' => 90, 'window' => 60, 'block' => 60],
            'classroom_user' => ['max' => 200, 'window' => 60, 'block' => 20],
            'admin_user' => ['max' => 120, 'window' => 60, 'block' => 30],
        ];
    }

    /** @return array<string,int> */
    public static function floors(): array
    {
        return [
            'global_anon' => 60,
            'global_auth_ip' => 1000,
            'global_auth_user' => 60,
            'login' => 8,
            'oauth' => 10,
            'register' => 3,
            'payment_create' => 3,
            'payment_webhook' => 30,
            'webhook' => 30,
            'sms' => 3,
            'sms_ip' => 10,
            'visitor' => 120,
            'api_public' => 20,
            'classroom_user' => 60,
            'admin_user' => 30,
        ];
    }

    public static function useStore(?string $dir): void
    {
        self::$storeOverride = $dir !== null && $dir !== '' ? $dir : null;
    }

    public static function storeDir(): string
    {
        if (self::$storeOverride !== null) {
            return self::$storeOverride;
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'abuse';
    }

    /** @return array<string,array{max:int,window:int,block:int}> */
    public static function thresholds(): array
    {
        $merged = self::defaults();
        $file = self::storeDir() . DIRECTORY_SEPARATOR . 'thresholds.json';
        if (!is_file($file)) {
            return $merged;
        }
        $json = json_decode((string)file_get_contents($file), true);
        if (!is_array($json)) {
            return $merged;
        }
        foreach ($merged as $name => $rule) {
            if (!isset($json[$name]) || !is_array($json[$name])) {
                continue;
            }
            $merged[$name] = self::sanitizeRule($name, $json[$name], $rule);
        }
        return $merged;
    }

    /** @param array<string,mixed> $incoming */
    public static function saveThresholds(array $incoming): void
    {
        $out = [];
        foreach (self::defaults() as $name => $rule) {
            $out[$name] = self::sanitizeRule($name, is_array($incoming[$name] ?? null) ? $incoming[$name] : [], $rule);
        }
        self::ensureDir(self::storeDir());
        file_put_contents(
            self::storeDir() . DIRECTORY_SEPARATOR . 'thresholds.json',
            json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function allow(string $bucket, string $identity, int $max, int $window, int $blockSeconds = 0): bool
    {
        $max = max(1, $max);
        $window = max(1, $window);
        $blockSeconds = max(0, min(86400, $blockSeconds));
        $identity = $identity !== '' ? $identity : 'unknown';
        $redis = self::redis();
        if ($redis instanceof \Redis) {
            return self::allowRedis($redis, $bucket, $identity, $max, $window, $blockSeconds);
        }
        return self::allowFile($bucket, $identity, $max, $window, $blockSeconds);
    }

    public static function enforceRequest(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $disabled = getenv('ABUSE_GUARD');
        if ($disabled === '0' || $disabled === 'off') {
            return;
        }
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '' || str_ends_with($script, '/error_page.php')) {
            return;
        }
        self::registerMetrics();
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            $ip = '0';
        }
        $manual = self::manualBlock($ip);
        if ($manual !== null) {
            self::deny(429, max(1, $manual['until'] - time()), 'This network is temporarily limited. Try again shortly.');
        }
        if (self::originLockedOut()) {
            self::noteRejection('origin', 'Direct origin request rejected', 60);
            self::deny(403, 60, 'Forbidden.');
        }
        $tooBig = self::bodyTooLarge($script);
        if ($tooBig !== null) {
            self::noteRejection('body', 'Request body rejected', 60);
            self::deny(413, 60, $tooBig);
        }
        foreach (self::rulesFor($script) as $rule) {
            $identity = (string)$rule['identity'];
            if (!self::allow((string)$rule['bucket'], $identity, (int)$rule['max'], (int)$rule['window'], (int)$rule['block'])) {
                $retry = max(1, (int)$rule['block'] ?: (int)$rule['window']);
                self::noteRejection((string)$rule['bucket'], 'Rate limit reached', $retry);
                self::deny(429, $retry, 'Too many requests. Please wait and try again.');
            }
        }
    }

    /**
     * @return list<array{bucket:string,identity:string,max:int,window:int,block:int}>
     */
    public static function rulesFor(string $script): array
    {
        $t = self::thresholds();
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            $ip = '0';
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $parent = (int)($_SESSION['parent_id'] ?? 0);
        $actor = $uid > 0 ? 'user:' . $uid : ($parent > 0 ? 'parent:' . $parent : '');
        $authed = $actor !== '';
        $rules = [];
        $push = static function (string $name, string $identity) use (&$rules, $t): void {
            $rule = $t[$name] ?? null;
            if ($rule === null) {
                return;
            }
            $rules[] = [
                'bucket' => $name,
                'identity' => $identity,
                'max' => (int)$rule['max'],
                'window' => (int)$rule['window'],
                'block' => (int)$rule['block'],
            ];
        };

        if (str_contains($script, '/ajax/track_visitor.php') || str_contains($script, '/ajax/track_class_analytics.php')) {
            $push('visitor', 'ip:' . $ip);
            return $rules;
        }
        if (self::isWebhook($script)) {
            $name = str_contains($script, '/api/onepay/') ? 'payment_webhook' : 'webhook';
            $push($name, 'ip:' . $ip);
            return $rules;
        }

        if ($authed) {
            $push('global_auth_ip', 'ip:' . $ip);
            $push('global_auth_user', $actor);
        } else {
            $push('global_anon', 'ip:' . $ip);
        }

        $post = $method === 'POST';
        if ($post && self::isLoginAttempt($script)) {
            $push('login', 'ip:' . $ip);
        }
        if (str_contains($script, '/auth/google/')) {
            $push('oauth', 'ip:' . $ip);
        }
        if ($post && (str_contains($script, '/student/register.php') || str_contains($script, '/admissions/'))) {
            $push('register', 'ip:' . $ip);
        }
        if ($post && str_contains($script, 'pay_lesson.php')) {
            $push('payment_create', 'ip:' . $ip);
            if ($actor !== '') {
                $push('payment_create', $actor);
            }
        }
        if ($post && self::isOtpSend()) {
            $push('sms_ip', 'ip:' . $ip);
            $phone = self::postedPhone();
            if ($phone !== '') {
                $push('sms', hash('sha256', $phone));
            }
        }
        if ($post && str_contains($script, '/admin/') && $actor !== '') {
            $push('admin_user', $actor);
        }
        if (str_contains($script, '/api/v1/public.php')) {
            $push('api_public', 'ip:' . $ip);
        }
        return $rules;
    }

    public static function block(string $ip, int $seconds, string $reason, string $scope = 'manual', ?int $actorId = null): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $seconds = max(60, min(86400, $seconds));
        $until = time() + $seconds;
        $reason = substr(trim(strip_tags($reason)), 0, 160);
        if ($reason === '') {
            $reason = 'Temporary block';
        }
        $blocks = self::readBlocks();
        $blocks[$ip] = ['until' => $until, 'reason' => $reason, 'scope' => substr($scope, 0, 32), 'at' => time()];
        self::writeBlocks($blocks);
        self::rememberLimit($ip, $scope, $until, $reason);
        self::insertControl($ip, $scope, $reason, $until, $actorId);
        return true;
    }

    public static function unblock(string $ip): void
    {
        $blocks = self::readBlocks();
        unset($blocks[$ip]);
        self::writeBlocks($blocks);
        $active = self::readActive();
        $active = array_values(array_filter($active, static fn ($row): bool => (string)($row['ip'] ?? '') !== $ip));
        self::writeActive($active);
        self::deleteControls($ip);
        self::clearCounters($ip);
    }

    public static function clearIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }
        self::unblock($ip);
    }

    public static function noteRejection(string $bucket, string $message = 'Rate limit reached', int $retry = 60): void
    {
        $ip = function_exists('eck_client_ip') ? eck_client_ip() : trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            $ip = '0';
        }
        $retry = max(1, min(86400, $retry));
        $dir = self::storeDir() . DIRECTORY_SEPARATOR . 'notes';
        self::ensureDir($dir);
        $stamp = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip . '|' . $bucket);
        if (is_file($stamp) && (time() - (int)filemtime($stamp)) < 60) {
            return;
        }
        @touch($stamp);
        self::rememberLimit($ip, $bucket, time() + $retry, $message);
        self::logEvent($ip, $bucket, $message);
        self::maybeSecurityEvent($ip, $bucket);
    }

    /** @return array{until:int,reason:string,scope:string}|null */
    public static function manualBlock(string $ip): ?array
    {
        $row = self::readBlocks()[$ip] ?? null;
        if (!is_array($row)) {
            return null;
        }
        $until = (int)($row['until'] ?? 0);
        if ($until <= time()) {
            return null;
        }
        return [
            'until' => $until,
            'reason' => (string)($row['reason'] ?? 'Temporary block'),
            'scope' => (string)($row['scope'] ?? 'manual'),
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function activeLimits(): array
    {
        $now = time();
        $rows = [];
        foreach (self::readActive() as $row) {
            if (!is_array($row) || (int)($row['until'] ?? 0) <= $now) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, static fn ($a, $b): int => (int)($b['until'] ?? 0) <=> (int)($a['until'] ?? 0));
        return array_slice($rows, 0, 200);
    }

    /** @return list<array<string,mixed>> */
    public static function recentEvents(int $limit = 80): array
    {
        $file = self::storeDir() . DIRECTORY_SEPARATOR . 'events.jsonl';
        if (!is_file($file)) {
            return [];
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @return array{minute:array<string,int>,recent:array<string,int>,alerts:list<string>,load:?float,php_memory:int} */
    public static function metrics(): array
    {
        $minute = self::countMinute(time());
        $recent = ['total' => 0, 's4' => 0, 's5' => 0, 's429' => 0];
        for ($i = 0; $i < 5; $i++) {
            $row = self::countMinute(time() - ($i * 60));
            foreach ($recent as $k => $v) {
                $recent[$k] = $v + (int)($row[$k] ?? 0);
            }
        }
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $alerts = [];
        if ((int)$minute['s429'] >= 80) {
            $alerts[] = '429 responses are elevated this minute.';
        }
        if ((int)$minute['s5'] >= 20) {
            $alerts[] = 'Server errors are elevated this minute.';
        }
        return [
            'minute' => $minute,
            'recent' => $recent,
            'alerts' => $alerts,
            'load' => is_array($load) ? (float)$load[0] : null,
            'php_memory' => function_exists('memory_get_usage') ? (int)memory_get_usage(true) : 0,
        ];
    }

    /** @return list<array{bucket:string,count:int}> */
    public static function topBuckets(int $limit = 8): array
    {
        $counts = [];
        foreach (self::recentEvents(400) as $event) {
            $bucket = (string)($event['bucket'] ?? '');
            if ($bucket === '') {
                continue;
            }
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        }
        arsort($counts);
        $out = [];
        foreach (array_slice($counts, 0, $limit, true) as $bucket => $count) {
            $out[] = ['bucket' => (string)$bucket, 'count' => (int)$count];
        }
        return $out;
    }

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS abuse_controls (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                scope VARCHAR(64) NOT NULL,
                reason VARCHAR(160) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_abuse_ip (ip_address, expires_at),
                KEY idx_abuse_exp (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private static function allowFile(string $bucket, string $identity, int $max, int $window, int $blockSeconds): bool
    {
        $key = hash('sha256', $bucket . '|' . $identity);
        $dir = self::storeDir() . DIRECTORY_SEPARATOR . 'c' . DIRECTORY_SEPARATOR . substr($key, 0, 2);
        self::ensureDir($dir);
        $path = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return true;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return true;
            }
            $raw = stream_get_contents($fh);
            $data = json_decode($raw ?: '', true);
            if (!is_array($data)) {
                $data = ['s' => time(), 'n' => 0, 'b' => 0];
            }
            $now = time();
            if ((int)($data['b'] ?? 0) > $now) {
                return false;
            }
            if ($now - (int)($data['s'] ?? 0) >= $window) {
                $data = ['s' => $now, 'n' => 0, 'b' => 0];
            }
            $data['n'] = (int)($data['n'] ?? 0) + 1;
            $data['bucket'] = $bucket;
            $data['ip'] = str_starts_with($identity, 'ip:') ? substr($identity, 3) : (string)($data['ip'] ?? '');
            $limited = (int)$data['n'] > $max;
            if ($limited && $blockSeconds > 0) {
                $data['b'] = $now + $blockSeconds;
            }
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($data));
            fflush($fh);
            return !$limited;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private static function allowRedis(\Redis $redis, string $bucket, string $identity, int $max, int $window, int $blockSeconds): bool
    {
        $blockKey = 'ab:blk:' . hash('sha256', $bucket . '|' . $identity);
        if ((int)$redis->exists($blockKey) === 1) {
            return false;
        }
        $slot = (int)floor(time() / $window);
        $key = 'ab:' . hash('sha256', $bucket . '|' . $identity . '|' . $slot);
        $count = (int)$redis->incr($key);
        if ($count === 1) {
            $redis->expire($key, $window + 5);
        }
        if ($count <= $max) {
            return true;
        }
        if ($blockSeconds > 0) {
            $redis->setex($blockKey, $blockSeconds, '1');
        }
        return false;
    }

    private static function redis(): ?\Redis
    {
        if (!class_exists(\Redis::class)) {
            return null;
        }
        $host = trim((string)(getenv('REDIS_HOST') ?: ''));
        if ($host === '') {
            return null;
        }
        static $conn = false;
        if ($conn instanceof \Redis) {
            return $conn;
        }
        if ($conn === null) {
            return null;
        }
        try {
            $redis = new \Redis();
            $port = (int)(getenv('REDIS_PORT') ?: 6379);
            if (!$redis->connect($host, $port, 0.2)) {
                $conn = null;
                return null;
            }
            $conn = $redis;
            return $redis;
        } catch (Throwable $e) {
            $conn = null;
            return null;
        }
    }

    /** @return array<string,array<string,mixed>> */
    private static function readBlocks(): array
    {
        $file = self::storeDir() . DIRECTORY_SEPARATOR . 'blocks.json';
        if (!is_file($file)) {
            return [];
        }
        $json = json_decode((string)file_get_contents($file), true);
        if (!is_array($json)) {
            return [];
        }
        $now = time();
        $kept = [];
        foreach ($json as $ip => $row) {
            if (!is_array($row) || (int)($row['until'] ?? 0) <= $now) {
                continue;
            }
            $kept[(string)$ip] = $row;
        }
        return $kept;
    }

    /** @param array<string,array<string,mixed>> $blocks */
    private static function writeBlocks(array $blocks): void
    {
        self::ensureDir(self::storeDir());
        file_put_contents(
            self::storeDir() . DIRECTORY_SEPARATOR . 'blocks.json',
            json_encode($blocks, JSON_UNESCAPED_SLASHES)
        );
    }

    /** @return list<array<string,mixed>> */
    private static function readActive(): array
    {
        $file = self::storeDir() . DIRECTORY_SEPARATOR . 'active.json';
        if (!is_file($file)) {
            return [];
        }
        $json = json_decode((string)file_get_contents($file), true);
        return is_array($json) ? array_values(array_filter($json, 'is_array')) : [];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function writeActive(array $rows): void
    {
        self::ensureDir(self::storeDir());
        file_put_contents(
            self::storeDir() . DIRECTORY_SEPARATOR . 'active.json',
            json_encode(array_slice($rows, -300), JSON_UNESCAPED_SLASHES)
        );
    }

    private static function rememberLimit(string $ip, string $bucket, int $until, string $reason): void
    {
        $rows = self::readActive();
        $rows[] = [
            'ip' => $ip,
            'bucket' => $bucket,
            'until' => $until,
            'reason' => substr($reason, 0, 160),
            'at' => time(),
        ];
        $now = time();
        $rows = array_values(array_filter($rows, static fn ($row): bool => (int)($row['until'] ?? 0) > $now));
        self::writeActive($rows);
    }

    private static function eventLayer(string $bucket): string
    {
        return match ($bucket) {
            'origin' => 'origin-lock',
            'body' => 'request-size',
            default => 'application-rate-limit',
        };
    }

    private static function logEvent(string $ip, string $bucket, string $message): void
    {
        $dir = self::storeDir();
        self::ensureDir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . 'events.jsonl';
        if (is_file($file) && filesize($file) > 200000) {
            @rename($file, $file . '.1');
        }
        $line = json_encode([
            't' => time(),
            'ip' => $ip,
            'bucket' => $bucket,
            'layer' => self::eventLayer($bucket),
            'message' => substr($message, 0, 160),
        ], JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    private static function maybeSecurityEvent(string $ip, string $bucket): void
    {
        $stampDir = self::storeDir() . DIRECTORY_SEPARATOR . 'stamps';
        self::ensureDir($stampDir);
        $stamp = $stampDir . DIRECTORY_SEPARATOR . hash('sha256', $ip . '|' . $bucket);
        if (is_file($stamp) && (time() - (int)filemtime($stamp)) < 600) {
            return;
        }
        @touch($stamp);
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO || !class_exists(\Edexcel\Services\SecurityEventService::class)) {
            return;
        }
        try {
            (new \Edexcel\Services\SecurityEventService($pdo))->record('RATE_LIMIT', [
                'result' => 'limited',
                'message' => 'Temporary limit on ' . substr($bucket, 0, 40),
            ]);
        } catch (Throwable $e) {
            error_log('Abuse guard event: ' . $e->getMessage());
        }
    }

    private static function insertControl(string $ip, string $scope, string $reason, int $until, ?int $actorId): void
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            return;
        }
        try {
            self::ensureSchema($pdo);
            $pdo->prepare('INSERT INTO abuse_controls (ip_address, scope, reason, expires_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([
                    $ip,
                    substr($scope, 0, 64),
                    $reason,
                    date('Y-m-d H:i:s', $until),
                    $actorId !== null && $actorId > 0 ? $actorId : null,
                    date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable $e) {
            error_log('Abuse control insert: ' . $e->getMessage());
        }
    }

    private static function deleteControls(string $ip): void
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            return;
        }
        try {
            self::ensureSchema($pdo);
            $pdo->prepare('DELETE FROM abuse_controls WHERE ip_address = ? AND expires_at > NOW()')->execute([$ip]);
        } catch (Throwable $e) {
        }
    }

    private static function clearCounters(string $ip): void
    {
        $root = self::storeDir() . DIRECTORY_SEPARATOR . 'c';
        if (!is_dir($root)) {
            return;
        }
        $n = 0;
        foreach (glob($root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if ($n++ > 5000) {
                break;
            }
            $json = json_decode((string)@file_get_contents($file), true);
            if (is_array($json) && (string)($json['ip'] ?? '') === $ip) {
                @unlink($file);
            }
        }
    }

    private static function registerMetrics(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        register_shutdown_function(static function (): void {
            $code = http_response_code();
            if (!is_int($code) || $code < 100) {
                $code = 200;
            }
            $kind = 't';
            if ($code === 429) {
                $kind = '9';
            } elseif ($code >= 500) {
                $kind = '5';
            } elseif ($code >= 400) {
                $kind = '4';
            }
            $dir = self::storeDir() . DIRECTORY_SEPARATOR . 'm';
            self::ensureDir($dir);
            $file = $dir . DIRECTORY_SEPARATOR . gmdate('YmdHi') . '.log';
            @file_put_contents($file, $kind, FILE_APPEND | LOCK_EX);
            if (random_int(1, 40) === 1) {
                foreach (glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $old) {
                    if (filemtime($old) < time() - 7200) {
                        @unlink($old);
                    }
                }
            }
            if ($code === 429) {
                self::maybeSpikeAlert();
            }
        });
    }

    /** @return array{total:int,s4:int,s5:int,s429:int} */
    private static function countMinute(int $when): array
    {
        $file = self::storeDir() . DIRECTORY_SEPARATOR . 'm' . DIRECTORY_SEPARATOR . gmdate('YmdHi', $when) . '.log';
        $counts = ['total' => 0, 's4' => 0, 's5' => 0, 's429' => 0];
        if (!is_file($file)) {
            return $counts;
        }
        $raw = (string)@file_get_contents($file);
        $counts['total'] = strlen($raw);
        $counts['s4'] = substr_count($raw, '4');
        $counts['s5'] = substr_count($raw, '5');
        $counts['s429'] = substr_count($raw, '9');
        return $counts;
    }

    private static function maybeSpikeAlert(): void
    {
        $minute = self::countMinute(time());
        if ((int)$minute['s429'] < 80) {
            return;
        }
        $stamp = self::storeDir() . DIRECTORY_SEPARATOR . 'spike.stamp';
        if (is_file($stamp) && (time() - (int)filemtime($stamp)) < 900) {
            return;
        }
        @touch($stamp);
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO && function_exists('campus_notify_admins')) {
            campus_notify_admins($pdo, 'Request limiting is active', 'Many requests were answered with HTTP 429 in the last minute. Check Admin → Protection.', 'admin/protection.php');
        }
    }

    private static function originLockedOut(): bool
    {
        $flag = strtolower(trim((string)(getenv('ORIGIN_LOCK_CLOUDFLARE') ?: '')));
        if (!in_array($flag, ['1', 'true', 'on'], true)) {
            return false;
        }
        $remote = function_exists('eck_remote_addr') ? eck_remote_addr() : '';
        if ($remote === '' || $remote === '127.0.0.1' || $remote === '::1') {
            return false;
        }
        return !function_exists('eck_proxy_is_trusted') || !eck_proxy_is_trusted($remote);
    }

    private static function bodyTooLarge(string $script): ?string
    {
        $files = 0;
        foreach ($_FILES as $file) {
            if (!is_array($file)) {
                continue;
            }
            $files += is_array($file['name'] ?? null) ? count($file['name']) : 1;
        }
        if ($files > 12) {
            return 'Too many files in one request.';
        }
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($len < 1) {
            return null;
        }
        $max = 12 * 1024 * 1024;
        if (str_contains($script, '/api/classroom/pdf.php')) {
            $max = 40 * 1024 * 1024;
        }
        $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json') && !self::isWebhook($script) && !str_contains($script, '/api/classroom/pdf.php')) {
            $max = min($max, 1024 * 1024);
        }
        if ($len > $max) {
            return 'Request is too large.';
        }
        return null;
    }

    private static function isWebhook(string $script): bool
    {
        foreach (['/api/onepay/', '/api/livekit/webhook.php', '/api/whatsapp/webhook.php', '/api/sms/webhook.php', '/api/bunny/webhook.php'] as $part) {
            if (str_contains($script, $part)) {
                return true;
            }
        }
        return false;
    }

    private static function isLoginScript(string $script): bool
    {
        foreach (['/login.php', '/portal/login.php', '/parent/login.php', '/student/login.php', '/index.php'] as $part) {
            if (str_ends_with($script, $part)) {
                return true;
            }
        }
        return false;
    }

    private static function isLoginAttempt(string $script): bool
    {
        if (str_ends_with($script, '/index.php')) {
            foreach ([
                'teacher_login', 'student_login',
                'teacher_otp_send', 'teacher_otp_verify', 'teacher_otp_resend',
                'student_otp_send', 'student_otp_verify', 'student_otp_resend',
                'student_device_otp_verify', 'student_device_otp_resend',
                'parent_otp_send', 'parent_otp_verify', 'parent_otp_resend',
            ] as $key) {
                if (isset($_POST[$key])) {
                    return true;
                }
            }
            return false;
        }
        return self::isLoginScript($script);
    }

    private static function isOtpSend(): bool
    {
        foreach ([
            'staff_otp_send', 'staff_otp_resend',
            'teacher_otp_send', 'teacher_otp_resend',
            'student_otp_send', 'student_otp_resend',
            'student_device_otp_resend',
            'parent_otp_send', 'parent_otp_resend',
        ] as $key) {
            if (isset($_POST[$key])) {
                return true;
            }
        }
        return false;
    }

    private static function postedPhone(): string
    {
        foreach (['username', 'teacher_phone', 'teacher_username', 'phone', 'parent_phone'] as $key) {
            $value = strtolower(trim((string)($_POST[$key] ?? '')));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /** @param array<string,mixed> $incoming @param array{max:int,window:int,block:int} $fallback */
    private static function sanitizeRule(string $name, array $incoming, array $fallback): array
    {
        $floor = self::floors()[$name] ?? 1;
        $max = (int)($incoming['max'] ?? $fallback['max']);
        $window = (int)($incoming['window'] ?? $fallback['window']);
        $block = (int)($incoming['block'] ?? $fallback['block']);
        return [
            'max' => max($floor, min(20000, $max)),
            'window' => max(10, min(86400, $window)),
            'block' => max(0, min(86400, $block)),
        ];
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    private static function deny(int $status, int $retry, string $message): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Retry-After: ' . max(1, $retry));
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $json = str_contains($uri, '/api/')
            || str_contains($uri, '/ajax/')
            || str_contains($accept, 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '');
        if ($json) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'error' => $message]);
        } else {
            echo $message;
        }
        exit;
    }
}
