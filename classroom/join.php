<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/classroom.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../student/otp_helpers.php';

$token = strtolower(trim((string)($_GET['t'] ?? '')));
$error = '';
$invite = null;

if ($token === '') {
    $error = 'This class join link is missing. Ask your teacher to send the SMS again.';
} else {
    try {
        ensure_classroom_schema($pdo);
        $invite = classroom_redeem_sms_join_token($pdo, $token);
    } catch (Throwable $e) {
        error_log('classroom sms join redeem: ' . $e->getMessage());
        $invite = null;
    }
    if (!$invite) {
        $error = 'This class join link has expired or is not valid. Ask your teacher to send a new SMS.';
    }
}

if ($error === '' && $invite) {
    $role = function_exists('current_role') ? current_role() : '';
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $studentId = (int)$invite['student_id'];
    $timetableId = (int)$invite['timetable_id'];

    if ($userId > 0 && in_array($role, ['teacher', 'admin'], true) && $userId !== $studentId) {
        $error = 'This join link is for the student. Open it on the student’s phone, or log out first.';
    } else {
        if ($userId !== $studentId || $role !== 'student') {
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE id = ?
                  AND role = 'student'
                  AND deleted_at IS NULL
                  AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $error = 'This student account is not active. Contact the college office.';
            } else {
                $signed = student_portal_sign_in($user, $pdo, 'classroom_sms');
                if (($signed['status'] ?? '') === 'otp') {
                    $_SESSION['classroom_sms_return'] = rtrim((string)BASE_URL, '/') . '/classroom/join.php?t=' . rawurlencode($token);
                    $login = function_exists('student_login_url') ? student_login_url() : (rtrim((string)BASE_URL, '/') . '/student/login.php');
                    if (str_contains($login, '#')) {
                        $login = strtok($login, '#') ?: $login;
                    }
                    if (!str_contains($login, 'student/login.php')) {
                        $login = rtrim((string)BASE_URL, '/') . '/student/login.php';
                    }
                    $sep = str_contains($login, '?') ? '&' : '?';
                    header('Location: ' . $login . $sep . 'mode=device');
                    exit;
                }
                if (in_array(($signed['status'] ?? ''), ['device_limit', 'device_blocked'], true)) {
                    header('Location: ' . rtrim((string)BASE_URL, '/') . '/student/device_gate.php');
                    exit;
                }
                if (($signed['status'] ?? '') !== 'ok') {
                    $error = (string)($signed['message'] ?? 'This device is not registered for this student account.');
                }
            }
        }

        if ($error === '') {
            classroom_consume_sms_join_token($pdo, (string)($invite['token_hash'] ?? ''));
            classroom_sync_lesson_meeting($pdo, $timetableId);
            $publicId = '';
            try {
                if (class_exists(\Edexcel\Services\OnlineMeetingService::class)) {
                    $meeting = (new \Edexcel\Services\OnlineMeetingService($pdo))->findByLesson($timetableId);
                    $publicId = (string)($meeting['public_id'] ?? '');
                }
            } catch (Throwable $e) {
                $publicId = '';
            }
            header('Location: ' . classroom_room_url($timetableId, $publicId));
            exit;
        }
    }
}

http_response_code($error !== '' ? 400 : 200);
?>
<!DOCTYPE html>
<html lang="en" <?= function_exists('app_theme_html_attrs') ? app_theme_html_attrs() : 'data-theme="dark" data-bs-theme="dark"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php if (function_exists('app_theme_boot_script')) { app_theme_boot_script(); } ?>
    <title>Join class · Edexcel College</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/classroom.css?v=<?= is_file(__DIR__ . '/../assets/css/classroom.css') ? filemtime(__DIR__ . '/../assets/css/classroom.css') : '1' ?>">
    <?php if (function_exists('app_theme_css_link')) { app_theme_css_link(); } ?>
</head>
<body class="ck-body ck-is-student">
    <header class="ck-top">
        <div class="ck-brand">Edexcel College</div>
        <div class="ck-title"><strong>Join class</strong></div>
    </header>
    <main class="ck-main p-4">
        <p class="mb-3"><?= e($error !== '' ? $error : 'Opening class…') ?></p>
        <p class="mb-0">
            <a href="<?= e(function_exists('student_login_url') ? student_login_url() : (BASE_URL . 'student/login.php')) ?>">Student login</a>
        </p>
    </main>
</body>
</html>
