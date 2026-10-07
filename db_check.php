<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require 'D:\all\server_new\edexcel.college\public_html\config\database.php';
global $pdo;

$emails = ['rasika9282@gmail.com'];
foreach ($emails as $email) {
    $stmt = $pdo->prepare('SELECT u.id, u.username, u.email, sp.parent_name, sp.parent_whatsapp FROM users u LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE u.email = ?');
    $stmt->execute([$email]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Data for $email:\n";
    print_r($data);
}
