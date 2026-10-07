<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Automated database + application file backups with retention, optional encryption,
 * integrity verification, and off-server copy. Never includes .env secrets.
 */
final class BackupService
{
    private const EXCLUDE_NAMES = [
        '.env',
        '.env.local',
        '.env.staging',
        '.env.production',
        'sftp.json',
        'id_rsa',
        'id_ed25519',
    ];

    private const EXCLUDE_DIR_NAMES = [
        'vendor',
        'node_modules',
        '.git',
        'cache',
        'storage/backups',
        'backups',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function config(): array
    {
        return [
            'enabled' => $this->setting('backup_enabled', '1') === '1',
            'database_enabled' => $this->setting('backup_database_enabled', '1') === '1',
            'files_enabled' => $this->setting('backup_files_enabled', '1') === '1',
            'location' => $this->setting('backup_location', 'storage/backups'),
            'frequency' => $this->setting('backup_frequency', 'daily'),
            'retention_daily' => max(1, (int)$this->setting('backup_retention_daily', '7')),
            'retention_weekly' => max(1, (int)$this->setting('backup_retention_weekly', '4')),
            'retention_monthly' => max(1, (int)$this->setting('backup_retention_monthly', '3')),
            'offsite_enabled' => $this->setting('backup_offsite_enabled', '0') === '1',
            'offsite_path' => $this->setting('backup_offsite_path', ''),
            'encryption_enabled' => $this->setting('backup_encryption_enabled', '0') === '1',
        ];
    }

    /**
     * @param array{triggered_by?:int|null,force?:bool,tier?:string} $opts
     * @return array<string,mixed>
     */
    public function run(array $opts = []): array
    {
        $cfg = $this->config();
        if (!$cfg['enabled'] && empty($opts['force'])) {
            return ['ok' => false, 'message' => 'Backups are disabled.'];
        }

        $tier = (string)($opts['tier'] ?? $this->resolveTier());
        $root = $this->absoluteBackupDir($cfg['location']);
        if (!is_dir($root) && !@mkdir($root, 0750, true)) {
            return ['ok' => false, 'message' => 'Cannot create backup directory.'];
        }
        $this->protectBackupDir($root);

        $stamp = date('Y-m-d_H-i-s');
        $baseName = 'eck_backup_' . $tier . '_' . $stamp;
        $workDir = $root . DIRECTORY_SEPARATOR . '_tmp_' . $stamp . '_' . bin2hex(random_bytes(4));
        @mkdir($workDir, 0750, true);

        $id = $this->insertHistory([
            'backup_type' => 'full',
            'status' => 'running',
            'retention_tier' => $tier,
            'includes_database' => $cfg['database_enabled'] ? 1 : 0,
            'includes_files' => $cfg['files_enabled'] ? 1 : 0,
            'triggered_by' => $opts['triggered_by'] ?? null,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $parts = [];
        $errors = [];

        try {
            if ($cfg['database_enabled']) {
                $sqlFile = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
                $this->dumpDatabase($sqlFile);
                $parts[] = $sqlFile;
            }
            if ($cfg['files_enabled']) {
                $filesZip = $workDir . DIRECTORY_SEPARATOR . 'files.zip';
                $this->zipImportantFiles($filesZip);
                $parts[] = $filesZip;
            }
            if ($parts === []) {
                throw new RuntimeException('Nothing to back up (database and files both disabled).');
            }

            $manifest = [
                'app' => 'edexcel/timetable',
                'version' => $this->setting('app_version', '2.0.0'),
                'created_at' => date('c'),
                'tier' => $tier,
                'parts' => array_map('basename', $parts),
                'excludes_env' => true,
            ];
            $manifestFile = $workDir . DIRECTORY_SEPARATOR . 'manifest.json';
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
            $parts[] = $manifestFile;

            $archivePath = $root . DIRECTORY_SEPARATOR . $baseName . '.zip';
            $this->zipParts($parts, $archivePath);

            $encrypted = false;
            $finalPath = $archivePath;
            if ($cfg['encryption_enabled']) {
                $encPath = $archivePath . '.enc';
                if ($this->encryptFile($archivePath, $encPath)) {
                    @unlink($archivePath);
                    $finalPath = $encPath;
                    $encrypted = true;
                }
            }

            $size = (int)@filesize($finalPath);
            $checksum = hash_file('sha256', $finalPath) ?: null;
            $offsiteOk = null;
            $offsitePath = null;
            if ($cfg['offsite_enabled'] && $cfg['offsite_path'] !== '') {
                $offsitePath = rtrim($cfg['offsite_path'], "/\\") . DIRECTORY_SEPARATOR . basename($finalPath);
                $offsiteOk = @copy($finalPath, $offsitePath) ? 1 : 0;
                if (!$offsiteOk) {
                    $errors[] = 'Off-server copy failed.';
                }
            }

            $this->updateHistory($id, [
                'status' => 'success',
                'storage_path' => $finalPath,
                'filename' => basename($finalPath),
                'size_bytes' => $size,
                'checksum_sha256' => $checksum,
                'encrypted' => $encrypted ? 1 : 0,
                'offsite_path' => $offsitePath,
                'offsite_ok' => $offsiteOk,
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => $errors !== [] ? implode(' ', $errors) : null,
            ]);

            $this->applyRetention($cfg);
            $this->rmTree($workDir);

            if (function_exists('log_audit')) {
                log_audit($this->pdo, 'backup_created', 'system_backups', $id, null, [
                    'filename' => basename($finalPath),
                    'size' => $size,
                    'tier' => $tier,
                ]);
            }

            return [
                'ok' => true,
                'id' => $id,
                'path' => $finalPath,
                'filename' => basename($finalPath),
                'size_bytes' => $size,
                'checksum' => $checksum,
                'encrypted' => $encrypted,
                'message' => 'Backup completed.',
            ];
        } catch (Throwable $e) {
            $this->rmTree($workDir);
            $this->updateHistory($id, [
                'status' => 'failed',
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => mb_substr($e->getMessage(), 0, 900),
            ]);
            error_log('BackupService: ' . $e->getMessage());
            return ['ok' => false, 'id' => $id, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function verify(int $backupId): array
    {
        $row = $this->find($backupId);
        if (!$row) {
            return ['ok' => false, 'message' => 'Backup not found.'];
        }
        $path = (string)($row['storage_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            $this->updateHistory($backupId, [
                'verified_at' => date('Y-m-d H:i:s'),
                'verify_ok' => 0,
                'error_message' => 'Backup file missing on disk.',
            ]);
            return ['ok' => false, 'message' => 'Backup file missing on disk.'];
        }

        $size = (int)@filesize($path);
        $checksum = hash_file('sha256', $path) ?: '';
        $expected = (string)($row['checksum_sha256'] ?? '');
        $ok = $expected === '' || hash_equals($expected, $checksum);
        if ((int)($row['size_bytes'] ?? 0) > 0 && $size !== (int)$row['size_bytes']) {
            $ok = false;
        }

        // For unencrypted zip, confirm it opens.
        if ($ok && str_ends_with(strtolower($path), '.zip') && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                $ok = false;
            } else {
                $zip->close();
            }
        }

        $this->updateHistory($backupId, [
            'verified_at' => date('Y-m-d H:i:s'),
            'verify_ok' => $ok ? 1 : 0,
            'checksum_sha256' => $checksum !== '' ? $checksum : ($row['checksum_sha256'] ?? null),
            'size_bytes' => $size,
        ]);

        return [
            'ok' => $ok,
            'message' => $ok ? 'Backup integrity verified.' : 'Backup verification failed.',
            'checksum' => $checksum,
            'size_bytes' => $size,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function history(int $limit = 50): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM system_backups ORDER BY id DESC LIMIT ?');
            $stmt->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM system_backups WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function healthSummary(): array
    {
        $lastOk = null;
        $lastFail = null;
        try {
            $lastOk = $this->pdo->query("
                SELECT * FROM system_backups WHERE status = 'success' ORDER BY id DESC LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC) ?: null;
            $lastFail = $this->pdo->query("
                SELECT * FROM system_backups WHERE status = 'failed' ORDER BY id DESC LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
        }

        $cfg = $this->config();
        $status = 'grey';
        $label = 'Not configured';
        if (!$cfg['enabled']) {
            $status = 'grey';
            $label = 'Disabled';
        } elseif ($lastFail && (!$lastOk || strtotime((string)$lastFail['finished_at']) > strtotime((string)($lastOk['finished_at'] ?? '')))) {
            $status = 'red';
            $label = 'Last backup failed';
        } elseif ($lastOk) {
            $age = time() - strtotime((string)$lastOk['finished_at']);
            if ($age > 48 * 3600) {
                $status = 'yellow';
                $label = 'Backup stale';
            } else {
                $status = 'green';
                $label = 'OK';
            }
        } else {
            $status = 'yellow';
            $label = 'No backups yet';
        }

        return [
            'status' => $status,
            'label' => $label,
            'last_success' => $lastOk,
            'last_failure' => $lastFail,
            'config' => $cfg,
        ];
    }

    /**
     * @param array<string,mixed> $cfg
     */
    public function saveConfig(array $cfg): void
    {
        $map = [
            'backup_enabled' => !empty($cfg['enabled']) ? '1' : '0',
            'backup_database_enabled' => !empty($cfg['database_enabled']) ? '1' : '0',
            'backup_files_enabled' => !empty($cfg['files_enabled']) ? '1' : '0',
            'backup_location' => (string)($cfg['location'] ?? 'storage/backups'),
            'backup_frequency' => (string)($cfg['frequency'] ?? 'daily'),
            'backup_retention_daily' => (string)max(1, (int)($cfg['retention_daily'] ?? 7)),
            'backup_retention_weekly' => (string)max(1, (int)($cfg['retention_weekly'] ?? 4)),
            'backup_retention_monthly' => (string)max(1, (int)($cfg['retention_monthly'] ?? 3)),
            'backup_offsite_enabled' => !empty($cfg['offsite_enabled']) ? '1' : '0',
            'backup_offsite_path' => (string)($cfg['offsite_path'] ?? ''),
            'backup_encryption_enabled' => !empty($cfg['encryption_enabled']) ? '1' : '0',
        ];
        foreach ($map as $k => $v) {
            $this->saveSetting($k, $v);
        }
    }

    private function resolveTier(): string
    {
        $day = (int)date('j');
        $dow = (int)date('N');
        if ($day === 1) {
            return 'monthly';
        }
        if ($dow === 7) {
            return 'weekly';
        }
        return 'daily';
    }

    private function absoluteBackupDir(string $location): string
    {
        if ($location !== '' && ($location[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\\/]/', $location))) {
            return rtrim($location, "/\\");
        }
        $base = dirname(__DIR__, 2);
        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($location, "/\\"));
    }

    private function protectBackupDir(string $dir): void
    {
        $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
        $idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($idx)) {
            @file_put_contents($idx, '');
        }
    }

    private function dumpDatabase(string $target): void
    {
        $secretKeys = function_exists('ops_secret_setting_keys') ? ops_secret_setting_keys() : [];
        $tables = [];
        $result = $this->pdo->query('SHOW TABLES');
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $fh = fopen($target, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Cannot write database dump.');
        }
        fwrite($fh, "-- Edexcel College automated backup (secrets redacted)\n");
        fwrite($fh, '-- Generated: ' . date('Y-m-d H:i:s') . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }
            fwrite($fh, "-- Table: {$table}\n");
            $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            fwrite($fh, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
            fwrite($fh, ($create['Create Table'] ?? '') . ";\n\n");

            $data = $this->pdo->query("SELECT * FROM `{$table}`");
            while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
                if ($table === 'settings' && isset($row['setting_key']) && in_array((string)$row['setting_key'], $secretKeys, true)) {
                    $row['setting_value'] = '[REDACTED]';
                }
                if ($table === 'payment_transactions') {
                    unset($row['gateway_response']);
                }
                if ($table === 'admin_totp_secrets') {
                    $row['secret_encrypted'] = '[REDACTED]';
                    $row['recovery_codes_hash'] = null;
                }
                $cols = array_keys($row);
                $values = array_map(function ($v) {
                    if ($v === null) {
                        return 'NULL';
                    }
                    return $this->pdo->quote((string)$v);
                }, $row);
                fwrite($fh, 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $values) . ");\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    }

    private function zipImportantFiles(string $target): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required for file backups.');
        }
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create files archive.');
        }

        $base = dirname(__DIR__, 2);
        $includeRoots = [
            'files',
            'uploads',
            'config',
            'src',
            'admin',
            'student',
            'parent',
            'campus',
            'api',
            'ajax',
            'cron',
            'tools',
            'database',
            'includes',
            'assets',
        ];
        foreach ($includeRoots as $rel) {
            $path = $base . DIRECTORY_SEPARATOR . $rel;
            if (is_dir($path)) {
                $this->addDirToZip($zip, $path, $rel);
            }
        }
        // Root PHP entry points (not secrets)
        foreach (glob($base . DIRECTORY_SEPARATOR . '*.php') ?: [] as $php) {
            $name = basename($php);
            if (in_array(strtolower($name), self::EXCLUDE_NAMES, true)) {
                continue;
            }
            $zip->addFile($php, $name);
        }
        $zip->close();
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $rel = $prefix . '/' . ltrim(str_replace('\\', '/', substr($full, strlen($dir))), '/');
            $baseName = strtolower($file->getBasename());
            if (in_array($baseName, self::EXCLUDE_NAMES, true)) {
                continue;
            }
            foreach (self::EXCLUDE_DIR_NAMES as $ex) {
                if (str_contains($rel, '/' . $ex . '/') || str_starts_with($rel, $ex . '/')) {
                    continue 2;
                }
            }
            // Never pack .env from any path
            if (preg_match('/(^|\/)\.env(\.|$)/i', $rel)) {
                continue;
            }
            $zip->addFile($full, $rel);
        }
    }

    /**
     * @param list<string> $parts
     */
    private function zipParts(array $parts, string $target): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required.');
        }
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create backup archive.');
        }
        foreach ($parts as $part) {
            $zip->addFile($part, basename($part));
        }
        $zip->close();
    }

    private function encryptFile(string $src, string $dest): bool
    {
        $key = (string)(getenv('BACKUP_ENCRYPTION_KEY') ?: '');
        if ($key === '' || !is_file($src)) {
            return false;
        }
        $rawKey = hash('sha256', $key, true);
        $iv = random_bytes(16);
        $plain = file_get_contents($src);
        if ($plain === false) {
            return false;
        }
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return false;
        }
        return file_put_contents($dest, $iv . $cipher) !== false;
    }

    /**
     * @param array<string,mixed> $cfg
     */
    private function applyRetention(array $cfg): void
    {
        $limits = [
            'daily' => (int)$cfg['retention_daily'],
            'weekly' => (int)$cfg['retention_weekly'],
            'monthly' => (int)$cfg['retention_monthly'],
        ];
        foreach ($limits as $tier => $keep) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT id, storage_path FROM system_backups
                    WHERE retention_tier = ? AND status = 'success'
                    ORDER BY id DESC
                ");
                $stmt->execute([$tier]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $i = 0;
                foreach ($rows as $row) {
                    $i++;
                    if ($i <= $keep) {
                        continue;
                    }
                    $path = (string)($row['storage_path'] ?? '');
                    if ($path !== '' && is_file($path)) {
                        @unlink($path);
                    }
                    $this->pdo->prepare("UPDATE system_backups SET status = 'purged' WHERE id = ?")
                        ->execute([(int)$row['id']]);
                }
            } catch (Throwable $e) {
                error_log('Backup retention: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function insertHistory(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_backups
                (backup_type, status, retention_tier, includes_database, includes_files, triggered_by, started_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['backup_type'] ?? 'full',
            $data['status'] ?? 'pending',
            $data['retention_tier'] ?? 'daily',
            (int)($data['includes_database'] ?? 1),
            (int)($data['includes_files'] ?? 0),
            $data['triggered_by'] ?? null,
            $data['started_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    private function updateHistory(int $id, array $data): void
    {
        if ($id < 1 || $data === []) {
            return;
        }
        $cols = [];
        $vals = [];
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-z_]+$/', $k)) {
                continue;
            }
            $cols[] = "`{$k}` = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        $this->pdo->prepare('UPDATE system_backups SET ' . implode(', ', $cols) . ' WHERE id = ?')
            ->execute($vals);
    }

    private function setting(string $key, string $default = ''): string
    {
        if (function_exists('ops_setting')) {
            return ops_setting($this->pdo, $key, $default);
        }
        return $default;
    }

    private function saveSetting(string $key, string $value): void
    {
        if (function_exists('ops_save_setting')) {
            ops_save_setting($this->pdo, $key, $value);
            return;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$key, $value]);
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
