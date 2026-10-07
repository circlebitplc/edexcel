<?php
$_SESSION['user_id'] = 92;
$_SESSION['role'] = 'student';
require 'config/bootstrap.php';

$studentRepo = new \Edexcel\Repositories\StudentRepository($pdo);
$student = $studentRepo->findById(92);

echo "FROM DB: " . ($student['profile_image'] ?? 'MISSING') . "\n";
