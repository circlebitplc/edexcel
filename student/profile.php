<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/config.php';

require_student();

header('Location: dashboard.php?tab=settings');
exit;
