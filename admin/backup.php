<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/ops.php';

use Edexcel\Services\AdminTotpService;
use Edexcel\Services\BackupService;
use Edexcel\Services\SecurityEventService;

require_admin();
ensure_ops_schema($pdo);

$error = '';
$success = '';
$allowRestore = defined('APP_ENV') && APP_ENV !== 'production';
$backup = new BackupService($pdo);
$totp = new AdminTotpService($pdo);
$userId = (int)($_SESSION['user_id'] ?? 0);
$cfg = $backup->config();
$health = $backup->healthSummary();
$rpo = ops_setting($pdo, 'rpo_hours', '24');
$rto = ops_setting($pdo, 'rto_hours', '4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save_config') {
                if (!$totp->hasValidReauth($userId, 'backup_settings') && $totp->isEnabledForUser($userId)) {
                    throw new RuntimeException('Re-authenticate before changing backup settings (Admin → Security → 2FA).');
                }
                $backup->saveConfig([
                    'enabled' => isset($_POST['backup_enabled']),
                    'database_enabled' => isset($_POST['backup_database_enabled']),
                    'files_enabled' => isset($_POST['backup_files_enabled']),
                    'location' => trim((string)($_POST['backup_location'] ?? 'storage/backups')),
                    'frequency' => (string)($_POST['backup_frequency'] ?? 'daily'),
                    'retention_daily' => (int)($_POST['backup_retention_daily'] ?? 7),
                    'retention_weekly' => (int)($_POST['backup_retention_weekly'] ?? 4),
                    'retention_monthly' => (int)($_POST['backup_retention_monthly'] ?? 3),
                    'offsite_enabled' => isset($_POST['backup_offsite_enabled']),
                    'offsite_path' => trim((string)($_POST['backup_offsite_path'] ?? '')),
                    'encryption_enabled' => isset($_POST['backup_encryption_enabled']),
                ]);
                ops_save_setting($pdo, 'rpo_hours', (string)max(1, (int)($_POST['rpo_hours'] ?? 24)));
                ops_save_setting($pdo, 'rto_hours', (string)max(1, (int)($_POST['rto_hours'] ?? 4)));
                $success = 'Backup settings saved.';
                $cfg = $backup->config();
                log_audit($pdo, 'backup_settings_saved', 'settings');
            } elseif ($action === 'run_backup') {
                @set_time_limit(300);
                $result = $backup->run(['triggered_by' => $userId, 'force' => true]);
                if (!empty($result['ok'])) {
                    $success = 'Backup created: ' . ($result['filename'] ?? '') . ' (' . number_format((int)($result['size_bytes'] ?? 0) / 1048576, 2) . ' MB).';
                    (new SecurityEventService($pdo))->record('backup_manual', 'Manual backup created', 'info', $userId, null, 'admin', 'backup');
                } else {
                    $error = 'Backup failed: ' . ($result['message'] ?? 'unknown');
                }
            } elseif ($action === 'verify') {
                $id = (int)($_POST['backup_id'] ?? 0);
                $result = $backup->verify($id);
                if (!empty($result['ok'])) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'] ?? 'Verification failed.';
                }
            } elseif ($action === 'download_sql') {
                // Legacy quick SQL download (secrets redacted) — not stored on disk for HTTP.
                $secretKeys = ops_secret_setting_keys();
                $tables = [];
                $result = $pdo->query('SHOW TABLES');
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
                echo "-- Edexcel College Database Backup (secrets redacted)\n";
                echo '-- Generated: ' . date('Y-m-d H:i:s') . "\n\n";
                foreach ($tables as $table) {
                    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                        continue;
                    }
                    echo "-- Table: $table\n";
                    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                    $create = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo ($create['Create Table'] ?? '') . ";\n\n";
                    $data = $pdo->query("SELECT * FROM `$table`");
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
                        $values = array_map(static function ($v) use ($pdo) {
                            return $v === null ? 'NULL' : $pdo->quote((string)$v);
                        }, $row);
                        echo 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $values) . ");\n";
                    }
                    echo "\n";
                }
                log_audit($pdo, 'backup_download', 'system');
                exit;
            } elseif ($action === 'restore' && $allowRestore) {
                if (trim((string)($_POST['confirm_text'] ?? '')) !== 'RESTORE-' . date('Y-m-d')) {
                    throw new RuntimeException('Type RESTORE-' . date('Y-m-d') . ' to confirm.');
                }
                if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Please upload a valid SQL file.');
                }
                $upload = $_FILES['sql_file'];
                if ((int)$upload['size'] > 25 * 1024 * 1024) {
                    throw new RuntimeException('Backup file is too large (max 25 MB for browser restore).');
                }
                if (!preg_match('/\.sql$/i', (string)$upload['name'])) {
                    throw new RuntimeException('SQL file required.');
                }
                $content = file_get_contents($upload['tmp_name']);
                if ($content === false) {
                    throw new RuntimeException('Failed to read file.');
                }
                if (preg_match('/(^|\R)\s*(?:SOURCE|LOAD\s+DATA|\\!|SYSTEM)\b/i', $content)) {
                    throw new RuntimeException('Unsupported server-level commands.');
                }
                $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $content))));
                $pdo->beginTransaction();
                foreach ($statements as $stmt) {
                    if (!preg_match('/^(?:--[^\n]*\n\s*)*(?:CREATE\s+TABLE|INSERT\s+INTO|SET\s+|DROP\s+TABLE)/is', $stmt)) {
                        throw new RuntimeException('Unsupported SQL statement found.');
                    }
                    $pdo->exec($stmt);
                }
                $pdo->commit();
                log_audit($pdo, 'restore_database', 'system');
                $success = 'Database restored (non-production only).';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
    $health = $backup->healthSummary();
    $cfg = $backup->config();
    $rpo = ops_setting($pdo, 'rpo_hours', '24');
    $rto = ops_setting($pdo, 'rto_hours', '4');
}

$history = $backup->history(40);
$statusIcon = static function (string $s): string {
    return match ($s) {
        'green' => '🟢',
        'yellow' => '🟡',
        'red' => '🔴',
        default => '⚪',
    };
};

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-1"><i class="bi bi-shield-check"></i> Backup &amp; Disaster Recovery</h1>
    <p class="text-muted">Automated backups never include <code>.env</code>. Backup files are stored outside the public HTTP path.</p>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">Backup health</div>
                <div class="fs-5 fw-bold"><?= $statusIcon((string)$health['status']) ?> <?= e((string)$health['label']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">Last success</div>
                <div class="fw-semibold"><?= e((string)($health['last_success']['finished_at'] ?? '—')) ?></div>
                <div class="small text-muted"><?= e((string)($health['last_success']['filename'] ?? '')) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">Last failure</div>
                <div class="fw-semibold"><?= e((string)($health['last_failure']['finished_at'] ?? '—')) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="small text-muted">RPO / RTO</div>
                <div class="fw-semibold"><?= (int)$rpo ?>h / <?= (int)$rto ?>h</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h2 class="h5">Run backup</h2>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="run_backup">
                        <button class="btn btn-success rounded-pill" type="submit">Create backup now</button>
                    </form>
                    <form method="post" class="d-inline ms-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="download_sql">
                        <button class="btn btn-outline-primary rounded-pill" type="submit">Download SQL (redacted)</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">Cron: <code>15 2 * * * php …/tools/automated_backup.php</code></p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h2 class="h5">Configuration</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_config">
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="backup_enabled" id="be" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="be">Backups enabled</label></div>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="backup_database_enabled" id="bd" <?= !empty($cfg['database_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="bd">Database backup</label></div>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="backup_files_enabled" id="bf" <?= !empty($cfg['files_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="bf">Files / app backup</label></div>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="backup_offsite_enabled" id="bo" <?= !empty($cfg['offsite_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="bo">Off-server copy</label></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="backup_encryption_enabled" id="ben" <?= !empty($cfg['encryption_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="ben">Encrypt (needs BACKUP_ENCRYPTION_KEY in .env)</label></div>
                        <div class="mb-2"><label class="form-label" for="bloc">Backup location</label><input class="form-control" id="bloc" name="backup_location" value="<?= e((string)$cfg['location']) ?>"></div>
                        <div class="mb-2"><label class="form-label" for="bof">Off-server path</label><input class="form-control" id="bof" name="backup_offsite_path" value="<?= e((string)$cfg['offsite_path']) ?>"></div>
                        <div class="mb-2"><label class="form-label" for="bfreq">Frequency</label>
                            <select class="form-select" id="bfreq" name="backup_frequency">
                                <?php foreach (['daily','weekly','monthly'] as $f): ?>
                                    <option value="<?= $f ?>" <?= $cfg['frequency'] === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col"><label class="form-label">Daily keep</label><input type="number" min="1" class="form-control" name="backup_retention_daily" value="<?= (int)$cfg['retention_daily'] ?>"></div>
                            <div class="col"><label class="form-label">Weekly keep</label><input type="number" min="1" class="form-control" name="backup_retention_weekly" value="<?= (int)$cfg['retention_weekly'] ?>"></div>
                            <div class="col"><label class="form-label">Monthly keep</label><input type="number" min="1" class="form-control" name="backup_retention_monthly" value="<?= (int)$cfg['retention_monthly'] ?>"></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col"><label class="form-label">RPO (hours)</label><input type="number" min="1" class="form-control" name="rpo_hours" value="<?= (int)$rpo ?>"></div>
                            <div class="col"><label class="form-label">RTO (hours)</label><input type="number" min="1" class="form-control" name="rto_hours" value="<?= (int)$rto ?>"></div>
                        </div>
                        <button class="btn btn-primary rounded-pill" type="submit">Save settings</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h2 class="h5">Recovery playbook</h2>
                    <p class="small mb-2">See <a href="<?= e(BASE_URL) ?>docs/DISASTER_RECOVERY.md">docs/DISASTER_RECOVERY.md</a> for full procedures.</p>
                    <ul class="small mb-0">
                        <li>Database corruption → restore latest verified SQL from <code>storage/backups</code> via SSH/mysql CLI</li>
                        <li>Server/disk failure → redeploy code, restore DB + <code>files/</code>/<code>uploads/</code>, never overwrite production <code>.env</code></li>
                        <li>Bad deployment → rollback files from previous backup archive; re-run health checks</li>
                        <li>Payment inconsistencies → reconcile <code>payment_transactions</code> vs gateway; do not auto-refund</li>
                        <li>WhatsApp / Bunny / LiveKit outage → mark integration down; queue retries via existing jobs</li>
                    </ul>
                    <?php if (!$allowRestore): ?>
                        <p class="small text-warning mt-3 mb-0">Browser one-click restore is disabled in production by design.</p>
                    <?php else: ?>
                        <hr>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="restore">
                            <div class="mb-2"><label class="form-label" for="sql_file">SQL restore (non-production)</label><input type="file" class="form-control" id="sql_file" name="sql_file" accept=".sql" required></div>
                            <div class="mb-2"><label class="form-label">Type RESTORE-<?= date('Y-m-d') ?></label><input class="form-control" name="confirm_text" required></div>
                            <button class="btn btn-danger rounded-pill" type="submit">Restore</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h2 class="h5">Backup history</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>When</th>
                                <th>Tier</th>
                                <th>Status</th>
                                <th>Size</th>
                                <th>Verified</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td>
                                        <div><?= e((string)($row['finished_at'] ?? $row['started_at'] ?? '')) ?></div>
                                        <div class="small text-muted text-break"><?= e((string)($row['filename'] ?? '')) ?></div>
                                    </td>
                                    <td><?= e((string)$row['retention_tier']) ?></td>
                                    <td><?= e((string)$row['status']) ?></td>
                                    <td><?= number_format(((int)$row['size_bytes']) / 1048576, 2) ?> MB</td>
                                    <td>
                                        <?php if ($row['verified_at']): ?>
                                            <?= ((int)$row['verify_ok'] === 1) ? 'OK' : 'FAIL' ?>
                                            <div class="small text-muted"><?= e((string)$row['verified_at']) ?></div>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'success'): ?>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="verify">
                                                <input type="hidden" name="backup_id" value="<?= (int)$row['id'] ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">Verify</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$history): ?>
                                <tr><td colspan="6" class="text-muted">No backups yet. Create one or wait for the nightly cron.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
