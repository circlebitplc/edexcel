<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/device_helpers.php';
require_once __DIR__ . '/../config/campus.php';

use Edexcel\Services\EmergencyDeviceAccessService;

require_login();
if (current_role() !== 'student') {
    header('Location: ' . rtrim((string)BASE_URL, '/') . '/dashboard.php');
    exit;
}

$svc = student_devices($pdo);
$userId = (int)($_SESSION['user_id'] ?? 0);
$choice = (string)($_SESSION['student_device_choice'] ?? '');
if (!$svc || $userId < 1 || $choice === '') {
    header('Location: ' . rtrim((string)BASE_URL, '/') . '/student/dashboard.php');
    exit;
}

$error = '';
$askAgain = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (isset($_POST['ask_again'])) {
        $askAgain = true;
    } elseif ($choice === 'device_blocked') {
        $error = 'This device stays blocked until the date shown below.';
    } else {
        $result = $svc->replaceWithCurrent($userId, (int)($_POST['device_id'] ?? 0));
        if (($result['status'] ?? '') === 'ok') {
            try {
                (new EmergencyDeviceAccessService($pdo))->withdrawPending($userId);
            } catch (Throwable $e) {
            }
            $return = student_device_safe_return((string)($_SESSION['student_device_return'] ?? ''));
            unset($_SESSION['student_device_return']);
            $dest = rtrim((string)BASE_URL, '/') . '/student/dashboard.php';
            if ($return !== '') {
                $dest = $return;
            }
            header('Location: ' . $dest);
            exit;
        }
        if (($result['status'] ?? '') === 'device_blocked') {
            $_SESSION['student_device_choice'] = 'device_blocked';
            $_SESSION['student_device_blocked_until'] = (string)($result['blocked_until'] ?? '');
            $choice = 'device_blocked';
        }
        $error = (string)($result['message'] ?? 'Could not update devices.');
    }
}

$lessonId = $choice === 'device_limit' ? student_device_return_lesson_id($pdo) : 0;
$emergency = null;
$safeReturn = student_device_safe_return((string)($_SESSION['student_device_return'] ?? ''));
if ($choice === 'device_limit' && $lessonId > 0) {
    try {
        $emergencySvc = new EmergencyDeviceAccessService($pdo);
        $context = $emergencySvc->buildContext($lessonId, $userId);
        if ($context) {
            if ($askAgain) {
                $emergency = $emergencySvc->requestAccess($userId, $context);
            } else {
                $latest = $emergencySvc->latestForCurrentDevice($userId, $lessonId);
                $latestStatus = (string)($latest['status'] ?? '');
                if ($latest && $latestStatus === 'pending') {
                    $emergency = [
                        'status' => 'pending',
                        'message' => 'Your request is already waiting for approval.',
                        'duplicate' => true,
                        'request_id' => (int)$latest['id'],
                        'expires_at' => (string)$latest['expires_at'],
                    ];
                } elseif ($latest && $latestStatus === 'approved' && $emergencySvc->accessStillOpen($latest)) {
                    if ($safeReturn !== '') {
                        header('Location: ' . $safeReturn);
                        exit;
                    }
                    $emergency = [
                        'status' => 'approved',
                        'message' => 'This device is approved for this class.',
                        'duplicate' => false,
                        'request_id' => (int)$latest['id'],
                        'expires_at' => (string)$latest['expires_at'],
                    ];
                } elseif ($latest && in_array($latestStatus, ['denied', 'expired'], true)) {
                    $emergency = [
                        'status' => $latestStatus,
                        'message' => $latestStatus === 'denied'
                            ? 'Your teacher or admin did not approve this device.'
                            : 'The approval request expired.',
                        'duplicate' => false,
                        'request_id' => (int)$latest['id'],
                        'expires_at' => (string)($latest['expires_at'] ?? ''),
                    ];
                } else {
                    $emergency = $emergencySvc->requestAccess($userId, $context);
                }
            }
        } elseif ($askAgain) {
            $error = 'This class is no longer live, so a new device request was not sent.';
        }
    } catch (Throwable $e) {
        $emergency = null;
    }
}

$emergencyStatus = (string)($emergency['status'] ?? '');
$showEmergency = in_array($emergencyStatus, ['pending', 'denied', 'expired', 'approved'], true);
$blockedUntil = (string)($_SESSION['student_device_blocked_until'] ?? '');
$devices = $choice === 'device_limit' ? $svc->publicActiveDevices($userId) : [];
$blockedWhen = $blockedUntil !== '' ? \Edexcel\Services\StudentDeviceService::formatBlockedUntil($blockedUntil) : '';
$logout = rtrim((string)BASE_URL, '/') . '/logout.php';
$expiresEpoch = strtotime((string)($emergency['expires_at'] ?? '')) ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $choice === 'device_blocked' ? 'Device blocked' : 'Device limit reached' ?></title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0e1218;color:#f4f6fb;font-family:Segoe UI,sans-serif;padding:24px}
        .card{width:min(480px,100%);background:#171c24;border:1px solid #2a3342;border-radius:16px;padding:28px}
        h1{font-size:1.35rem;margin:0 0 8px}
        p{line-height:1.5;color:#d5dbe6}
        .device{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:12px 0;border-top:1px solid #2a3342}
        button,.link{background:#315efb;color:#fff;border:0;border-radius:8px;padding:8px 12px;font:inherit;cursor:pointer;text-decoration:none}
        .muted{color:#9aa6b8;font-size:.92rem}
        .error{color:#ffb4b4}
        .out{color:#8eb6ff}
        .wait{display:flex;align-items:center;gap:8px;color:#f4f6fb;font-weight:650}
        .dot{width:8px;height:8px;border-radius:50%;background:#f5c16c;box-shadow:0 0 0 0 rgba(245,193,108,.7);animation:pulse 1.4s infinite}
        @keyframes pulse{70%{box-shadow:0 0 0 8px rgba(245,193,108,0)}100%{box-shadow:0 0 0 0 rgba(245,193,108,0)}}
        details{margin-top:18px}
        summary{cursor:pointer;color:#8eb6ff}
    </style>
</head>
<body>
<div class="card" role="dialog" aria-modal="true" aria-labelledby="deviceGateTitle">
<?php if ($choice === 'device_blocked'): ?>
    <h1 id="deviceGateTitle">This device has been temporarily blocked</h1>
    <p>This device was replaced on your account.</p>
    <p>It cannot be used again until <?= e($blockedWhen) ?>.</p>
    <p>Please contact the institute if you need assistance.</p>
<?php elseif ($showEmergency && $emergencyStatus === 'pending'): ?>
    <h1 id="deviceGateTitle">Device Limit Reached</h1>
    <p>Your account is already active on 2 devices.</p>
    <p>Your teacher/admin has been notified and can approve temporary access to this device.</p>
    <p>Please wait for approval.</p>
    <?php if (!empty($emergency['duplicate'])): ?>
        <p><strong>Your request is already waiting for approval.</strong></p>
    <?php endif; ?>
    <p class="wait" id="emergencyWait"><span class="dot" aria-hidden="true"></span> Waiting for teacher/admin approval...</p>
    <p class="muted" id="emergencyClock" data-expires="<?= (int)$expiresEpoch ?>"></p>
<?php elseif ($showEmergency && $emergencyStatus === 'denied'): ?>
    <h1 id="deviceGateTitle">Device Limit Reached</h1>
    <p>Your teacher or admin did not approve this device. You remain limited to 2 devices.</p>
    <form method="post">
        <?= csrf_field() ?>
        <button type="submit" name="ask_again" value="1">Request approval again</button>
    </form>
<?php elseif ($showEmergency && $emergencyStatus === 'expired'): ?>
    <h1 id="deviceGateTitle">Device Limit Reached</h1>
    <p>The approval request expired. You remain limited to 2 devices.</p>
    <form method="post">
        <?= csrf_field() ?>
        <button type="submit" name="ask_again" value="1">Request approval again</button>
    </form>
<?php elseif ($showEmergency && $emergencyStatus === 'approved'): ?>
    <h1 id="deviceGateTitle">Device approved</h1>
    <p>This device can join the class that was approved. Open that class link again.</p>
    <?php if ($safeReturn !== ''): ?><p><a class="link" href="<?= e($safeReturn) ?>">Join the class</a></p><?php endif; ?>
<?php else: ?>
    <h1 id="deviceGateTitle">Device Limit Reached</h1>
    <p>Your account is already registered on 2 devices.</p>
    <p>You can deactivate one of your existing devices to use this device.</p>
    <p class="muted">Select a device to deactivate. Your account can stay registered on 2 devices. A different browser on the same computer uses a device slot. Changing network does not.</p>
<?php endif; ?>
    <?php if ($error !== ''): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if ($choice !== 'device_blocked' && $devices !== []): ?>
    <?php if ($showEmergency && $emergencyStatus === 'pending'): ?>
        <details>
            <summary>Deactivate an existing device instead</summary>
    <?php endif; ?>
    <?php foreach ($devices as $device): ?>
        <form class="device" method="post" onsubmit="return confirm('Deactivate this device? It cannot be used again for 14 days.');">
            <?= csrf_field() ?>
            <input type="hidden" name="device_id" value="<?= (int)$device['id'] ?>">
            <div>
                <strong><?= e((string)$device['label']) ?></strong>
                <div class="muted">Last used: <?= e((string)$device['last_used']) ?></div>
            </div>
            <button type="submit">Deactivate this device</button>
        </form>
    <?php endforeach; ?>
    <?php if ($showEmergency && $emergencyStatus === 'pending'): ?>
        </details>
    <?php endif; ?>
<?php elseif ($choice !== 'device_blocked' && !$showEmergency): ?>
    <p>No active devices could be shown. Contact the institute.</p>
<?php endif; ?>
    <p><a class="out" href="<?= e($logout) ?>">Sign out</a></p>
</div>
<?php if ($showEmergency && $emergencyStatus === 'pending' && $lessonId > 0): ?>
    <?php student_device_emergency_popup_script('student', $lessonId, $safeReturn); ?>
<?php endif; ?>
</body>
</html>
