<?php
declare(strict_types=1);
require __DIR__ . '/config/load_env.php';
require __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || $_GET['k'] !== 'eck-diag-2026') {
    http_response_code(403);
    echo 'forbidden';
    exit;
}
if (!($pdo instanceof PDO)) {
    echo 'no pdo';
    exit;
}
echo "USERS COLUMNS:\n";
foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . ' | null=' . $c['Null'] . "\n";
}
echo "\nREQUIRED student_profiles:\n";
foreach ($pdo->query('SHOW COLUMNS FROM student_profiles')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if ($c['Null'] === 'NO' && $c['Default'] === null && strpos((string)$c['Extra'], 'auto_increment') === false) {
        echo 'REQUIRED ' . $c['Field'] . ' | ' . $c['Type'] . "\n";
    }
}
echo "\ngoogle_id=";
echo $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'")->fetch() ? 'yes' : 'NO';
echo "\naccount_status=";
echo $pdo->query("SHOW COLUMNS FROM users LIKE 'account_status'")->fetch() ? 'yes' : 'NO';
echo "\n";

// Simulate insert fields like GoogleAuthAccountService
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cols[$c['Field']] = true;
}
$data = [
    'username' => 'diag_test_' . time(),
    'password_hash' => password_hash('x', PASSWORD_DEFAULT),
    'role' => 'student',
    'is_active' => 0,
];
if (isset($cols['google_id'])) $data['google_id'] = 'diag_' . time();
if (isset($cols['google_email'])) $data['google_email'] = 'diag@example.com';
if (isset($cols['account_status'])) $data['account_status'] = 'pending';
echo "\nWould insert: " . implode(',', array_keys($data)) . "\n";
try {
    $pdo->beginTransaction();
    $fields = array_keys($data);
    $ph = implode(',', array_fill(0, count($fields), '?'));
    $pdo->prepare('INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . $ph . ')')->execute(array_values($data));
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO student_profiles (user_id, full_name, email) VALUES (?, ?, ?)')->execute([$id, 'Diag', 'diag@example.com']);
    $pdo->rollBack();
    echo "SIMULATED INSERT OK (rolled back) id=$id\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "SIMULATED INSERT FAIL: " . $e->getMessage() . "\n";
}
