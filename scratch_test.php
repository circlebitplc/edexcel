<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require 'config/database.php';
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'rasika9282@gmail.com'");
$stmt->execute();
$user = $stmt->fetch();
print_r($user);
if ($user) {
    $stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    print_r($stmt->fetch());
}
