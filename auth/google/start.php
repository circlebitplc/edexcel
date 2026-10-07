<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\GoogleOAuthService;
use Edexcel\Services\TeacherMigrationService;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$allowedIntents = ['student', 'parent', 'staff', 'teacher', 'link_teacher', 'admin_link_teacher', 'link_admin'];
$intent = strtolower(trim((string)($_GET['intent'] ?? 'student')));
if (!in_array($intent, $allowedIntents, true)) {
    $intent = 'student';
}
$invite = trim((string)($_GET['invite'] ?? ''));

// Admin staff linking uses a target stored by the admin page POST.
// A user id in the query string is ignored.
$adminLinkIntent = $intent === 'admin_link_teacher' || $intent === 'link_admin';
if (!$adminLinkIntent) {
    unset(
        $_SESSION['admin_linking_teacher_user_id'],
        $_SESSION['admin_linking_staff_ready'],
        $_SESSION['admin_linking_staff_intent']
    );
} else {
    $clearAdminLink = static function (): void {
        unset(
            $_SESSION['admin_linking_teacher_user_id'],
            $_SESSION['admin_linking_staff_ready'],
            $_SESSION['admin_linking_staff_intent']
        );
    };
    if (!function_exists('is_admin') || !is_admin()) {
        $clearAdminLink();
        header('Location: /login.php?error=' . rawurlencode('Sign in as an administrator to link a staff account.'));
        exit;
    }
    $targetUserId = (int)($_SESSION['admin_linking_teacher_user_id'] ?? 0);
    $ready = !empty($_SESSION['admin_linking_staff_ready']);
    $storedIntent = (string)($_SESSION['admin_linking_staff_intent'] ?? '');
    if (!$ready || $targetUserId < 1 || $storedIntent !== $intent || !isset($pdo) || !$pdo instanceof PDO) {
        $clearAdminLink();
        header('Location: /admin/teacher_oauth_migration.php?error=' . rawurlencode('Start staff linking from the admin page.'));
        exit;
    }
    $target = (new TeacherMigrationService($pdo))->eligibleStaffLinkTarget($targetUserId);
    if ($target === null) {
        $clearAdminLink();
        header('Location: /admin/teacher_oauth_migration.php?error=' . rawurlencode('That staff account cannot be linked.'));
        exit;
    }
    $_SESSION['admin_linking_teacher_user_id'] = (int)$target['id'];
}

try {
    $oauth = GoogleOAuthService::fromConfig(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
    if (!$oauth->isConfigured() || !GoogleOAuthService::isPortalEnabled(isset($pdo) && $pdo instanceof PDO ? $pdo : null)) {
        $target = match ($intent) {
            'parent' => '/parent/login.php',
            'staff', 'teacher', 'link_teacher' => '/login.php',
            'admin_link_teacher', 'link_admin' => '/admin/teacher_oauth_migration.php',
            default => '/portal/login.php',
        };
        header('Location: ' . $target . '?error=' . rawurlencode('Google sign-in is not configured yet.'));
        exit;
    }
    $url = $oauth->begin($intent, $invite !== '' ? $invite : null);
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    error_log('google oauth start: ' . $e->getMessage());
    $errorTarget = in_array($intent, ['staff', 'teacher', 'link_teacher', 'admin_link_teacher', 'link_admin'], true)
        ? '/login.php'
        : '/portal/login.php';
    header('Location: ' . $errorTarget . '?error=' . rawurlencode('Could not start Google sign-in.'));
    exit;
}
