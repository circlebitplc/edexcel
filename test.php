<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require 'config/database.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($res);
} catch (Exception $e) {
    echo $e->getMessage();
}
