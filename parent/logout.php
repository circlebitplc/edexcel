<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\ParentAuthService;

ParentAuthService::logout();
header('Location: /parent/login.php');
exit;
