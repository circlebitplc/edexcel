<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\GoogleAuthAccountService;
use Edexcel\Services\GoogleOAuthService;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$sessionIntent = strtolower(trim((string)($_SESSION['google_oauth_intent'] ?? '')));
$errorRedirect = match ($sessionIntent) {
    'admin_link_teacher', 'link_admin' => '/admin/teacher_oauth_migration.php',
    'staff', 'teacher', 'link_teacher' => '/login.php',
    'parent' => '/parent/login.php',
    default => '/portal/login.php',
};

try {
    if (isset($_GET['error'])) {
        $desc = trim((string)($_GET['error_description'] ?? $_GET['error'] ?? 'cancelled'));
        if ($desc === 'access_denied') {
            $desc = 'Google sign-in was cancelled.';
        } else {
            $desc = 'Google sign-in was cancelled or failed.';
        }
        header('Location: ' . $errorRedirect . '?error=' . rawurlencode($desc));
        exit;
    }

    $code = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    $oauth = GoogleOAuthService::fromConfig(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
    $profile = $oauth->complete($code, $state);

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database unavailable.');
    }

    $accounts = new GoogleAuthAccountService($pdo);
    $result = $accounts->handle($profile);
    if (empty($result['ok'])) {
        header('Location: ' . $errorRedirect . '?error=' . rawurlencode((string)($result['message'] ?? 'Sign-in failed.')));
        exit;
    }
    $redirect = (string)($result['redirect'] ?? '/portal/login.php');
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    error_log('google oauth callback: ' . $e->getMessage());
    $msg = $e->getMessage();
    $safe = (str_contains(strtolower($msg), 'google') || str_contains(strtolower($msg), 'sign-in') || str_contains(strtolower($msg), 'state'))
        && !str_contains(strtolower($msg), 'sqlstate')
        ? $msg
        : 'Sign-in could not be completed. Please try again or contact the administrator.';
    header('Location: ' . $errorRedirect . '?error=' . rawurlencode($safe));
    exit;
}
