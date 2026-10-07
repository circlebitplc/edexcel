<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/i18n.php';

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
$parent = $auth->getAccount($parentId);
$status = strtolower((string)($parent['status'] ?? 'pending'));

if (in_array($status, ['suspended', 'disabled'], true)) {
    ParentAuthService::logout();
    header('Location: /portal/login.php?error=' . rawurlencode('This parent account cannot access the portal.'));
    exit;
}

$error = trim((string)($_GET['error'] ?? ''));
$notice = '';
$children = $auth->children($parentId);
$pending = $links->pendingRequestsForParent($parentId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your session expired. Try again.');
        }
        if (isset($_POST['accept_invite'])) {
            $result = $links->acceptInvitation($parentId, (string)($_POST['invite_token'] ?? ''));
            if (!empty($result['ok'])) {
                header('Location: /parent/home.php');
                exit;
            }
            $error = (string)($result['message'] ?? 'Could not accept invitation.');
        } else {
            $result = $links->requestAccess($parentId, (string)($_POST['student_identifier'] ?? ''));
            if (!empty($result['ok'])) {
                $notice = (string)$result['message'];
                $pending = $links->pendingRequestsForParent($parentId);
            } else {
                $error = (string)($result['message'] ?? 'Could not submit request.');
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$lang = eck_lang();
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'si' ? 'si' : 'en' ?>" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Parent verification · Edexcel College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/system.css">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body>
<main class="parent-page" style="min-height:100vh;padding:24px 16px">
    <div style="width:min(100%,560px);margin:32px auto">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h1 class="h4 fw-bold mb-2">Parent account verification required</h1>
            <p class="text-muted">
                Signed in as <?= $h($parent['name'] ?: ($parent['email'] ?? $parent['phone'] ?? 'Parent')) ?>.
                You will not receive access to student information until your relationship with a specific student has been approved.
            </p>

            <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($notice !== ''): ?><div class="alert alert-success"><?= $h($notice) ?></div><?php endif; ?>

            <?php if ($children !== []): ?>
                <div class="alert alert-success">
                    You have verified access to <?= count($children) ?> student(s).
                    <a href="/parent/home.php" class="alert-link">Open parent home</a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-3">
                    Your parent account is awaiting verification. No student data is available yet.
                </div>
            <?php endif; ?>

            <?php if ($pending !== []): ?>
                <h2 class="h6 fw-bold">Pending requests</h2>
                <ul class="list-group mb-4">
                    <?php foreach ($pending as $row): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <?= $h($row['student_name'] ?? '') ?>
                                <small class="text-muted">ID <?= (int)$row['student_id'] ?></small>
                            </span>
                            <span class="badge text-bg-warning">Awaiting approval</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h2 class="h6 fw-bold">Request access to a student</h2>
            <p class="small text-muted">Enter the student ID (the number used by the college) or the student’s login username. Email alone is not accepted as proof of parenthood.</p>
            <form method="post" class="mb-4">
                <?= csrf_field() ?>
                <label class="form-label">Student ID or username</label>
                <input class="form-control mb-3" name="student_identifier" required autocomplete="off" placeholder="e.g. 125 or 077xxxxxxx">
                <button class="btn btn-primary w-100" type="submit">Submit access request</button>
            </form>

            <h2 class="h6 fw-bold">Have an invitation?</h2>
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label">Invitation token</label>
                <input class="form-control mb-3" name="invite_token" autocomplete="off">
                <button class="btn btn-outline-primary w-100" name="accept_invite" value="1" type="submit">Accept invitation</button>
            </form>

            <div class="mt-4 d-flex justify-content-between">
                <a href="/parent/home.php">Parent home</a>
                <a href="/parent/logout.php">Sign out</a>
            </div>
        </div>
    </div>
</main>
<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>
</body>
</html>
