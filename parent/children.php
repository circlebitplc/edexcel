<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\ParentAuthService;
use Edexcel\Services\ParentLinkService;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (!ParentAuthService::isLoggedIn() || !isset($pdo) || !($pdo instanceof PDO)) {
    header('Location: /portal/login.php');
    exit;
}

$auth = new ParentAuthService($pdo);
$links = new ParentLinkService($pdo);
$parentId = (int)$_SESSION['parent_id'];
if (!$auth->isPortalAllowed($parentId)) {
    ParentAuthService::logout();
    header('Location: /portal/login.php?error=' . rawurlencode('This parent account cannot access the portal.'));
    exit;
}

$children = $auth->children($parentId);
$pending = $links->pendingRequestsForParent($parentId);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>My Children · Parent Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/system.css">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<main class="parent-page" style="min-height:100vh;padding:24px 16px">
    <div style="width:min(100%,640px);margin:24px auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 fw-bold mb-0">My Children</h1>
            <a href="/parent/home.php">Home</a>
        </div>

        <?php if ($children === []): ?>
            <div class="alert alert-warning">
                Your parent account is awaiting verification. No verified students yet.
                <div class="mt-2"><a class="btn btn-sm btn-primary" href="/parent/verify.php">Request student access</a></div>
            </div>
        <?php else: ?>
            <div class="list-group mb-4">
                <?php foreach ($children as $c): ?>
                    <a class="list-group-item list-group-item-action" href="/parent/home.php?student=<?= (int)$c['id'] ?>">
                        <div class="fw-semibold">✓ <?= $h($c['student_name']) ?></div>
                        <div class="small text-muted">Student ID: <?= (int)$c['id'] ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pending !== []): ?>
            <h2 class="h6">Awaiting approval</h2>
            <ul class="list-group mb-3">
                <?php foreach ($pending as $row): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= $h($row['student_name'] ?? '') ?> · ID <?= (int)$row['student_id'] ?></span>
                        <span class="badge text-bg-warning">Pending</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a class="btn btn-outline-primary" href="/parent/verify.php">Link another student</a>
    </div>
</main>
</body>
</html>
