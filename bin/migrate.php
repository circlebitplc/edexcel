<?php
declare(strict_types=1);

/**
 * bin/migrate.php
 *
 * Lightweight, deterministic database migration runner for Edexcel College.
 * Usage: php bin/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "CLI only.\n";
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection failed. Migration aborted.\n");
    exit(1);
}

echo "=== Edexcel College Database Migration Runner ===\n";

// 1. Ensure schema_migrations table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 2. Fetch already applied migrations
$appliedStmt = $pdo->query("SELECT migration FROM schema_migrations");
$applied = $appliedStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// 3. Scan migration files
$migrationDir = __DIR__ . '/../database/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

$count = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (in_array($filename, $applied, true)) {
        echo " [SKIP] {$filename} (already applied)\n";
        continue;
    }

    echo " [APPLYING] {$filename} ... ";
    $sql = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {
        echo "EMPTY (Skipped)\n";
        continue;
    }

    try {
        $pdo->beginTransaction();
        // Split statements so PDO/MySQL works without MULTI_STATEMENTS.
        $statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                // Allow comment-only chunks; strip leading comment lines.
                $lines = preg_split('/\R/', $statement) ?: [];
                $clean = [];
                foreach ($lines as $line) {
                    $t = ltrim($line);
                    if ($t === '' || str_starts_with($t, '--')) {
                        continue;
                    }
                    $clean[] = $line;
                }
                $statement = trim(implode("\n", $clean));
            }
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }

        $recordStmt = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
        $recordStmt->execute([$filename]);

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        echo "SUCCESS\n";
        $count++;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "FAILED\n";
        fwrite(STDERR, "Error applying {$filename}: " . $e->getMessage() . "\n");
        exit(2);
    }
}

echo "\nMigration complete. {$count} new migration(s) applied.\n";
