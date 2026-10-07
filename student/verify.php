<?php
declare(strict_types=1);

// Legacy phone OTP verification page — registration is Google-only now.
require_once __DIR__ . '/../config/config.php';
header('Location: ' . rtrim((string)BASE_URL, '/') . '/portal/login.php?role=student');
exit;
