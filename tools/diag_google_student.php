<?php
declare(strict_types=1);

/**
 * Retired schema diagnostic. It previously answered over HTTP with a fixed key.
 * Web requests are refused. Run only from the server CLI if a schema check is required.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/load_env.php';
require __DIR__ . '/../config/database.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "no database\n");
    exit(1);
}

echo "USERS COLUMNS:\n";
foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . ' | null=' . $c['Null'] . "\n";
}
