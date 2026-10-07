<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../config/ops.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No database.\n");
    exit(1);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS whatsapp_outbox (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'general',
        attempts INT NOT NULL DEFAULT 0,
        next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_error TEXT NULL,
        sent_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_outbox_next (sent_at, next_attempt_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

ops_job_start($pdo, 'whatsapp_outbox');
$sender = whatsapp_sender($pdo);
$ok = 0;
$fail = 0;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->query("
        SELECT * FROM whatsapp_outbox
        WHERE sent_at IS NULL AND next_attempt_at <= NOW() AND attempts < 5
        ORDER BY id ASC
        LIMIT 30
        FOR UPDATE SKIP LOCKED
    ");
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $claim = $pdo->prepare("
        UPDATE whatsapp_outbox
        SET attempts = attempts + 1, next_attempt_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
        WHERE id = ? AND sent_at IS NULL
    ");
    foreach ($rows as $row) {
        $claim->execute([(int)$row['id']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $rows = [];
    error_log('whatsapp_outbox claim: ' . $e->getMessage());
}
foreach ($rows as $row) {
    try {
        $sender->sendText((string)$row['phone'], (string)$row['message']);
        $pdo->prepare("UPDATE whatsapp_outbox SET sent_at = NOW(), last_error = NULL WHERE id = ?")->execute([(int)$row['id']]);
        $ok++;
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE whatsapp_outbox SET last_error = ? WHERE id = ?")
            ->execute([substr($e->getMessage(), 0, 500), (int)$row['id']]);
        $fail++;
    }
}
echo "outbox sent={$ok} failed={$fail}\n";
ops_job_finish($pdo, 'whatsapp_outbox', $fail === 0, "sent={$ok} failed={$fail}");
