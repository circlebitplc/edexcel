<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/classroom.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\ClassroomAccessService;
use Edexcel\Services\LiveKitTokenService;
use Edexcel\Services\OnlineMeetingService;

require_login();
ensure_classroom_schema($pdo);
if (!function_exists('student_require_parent_phone')) {
    require_once __DIR__ . '/../../student/device_helpers.php';
}
if (function_exists('student_require_parent_phone')) {
    student_require_parent_phone($pdo);
}
if (function_exists('student_require_presence')) {
    student_require_presence($pdo);
}

header('Content-Type: application/json; charset=utf-8');

function classroom_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function classroom_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        classroom_json(['ok' => false, 'error' => 'Please try again.'], 405);
    }
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($token)) {
        classroom_json(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 403);
    }
}

function classroom_rate_limit(string $key, int $max, int $windowSeconds): void
{
    $ip = function_exists('eck_client_ip') ? eck_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $identity = $key . '|u:' . $uid . '|ip:' . $ip;
    if (class_exists(\Edexcel\Http\AbuseGuard::class)) {
        if (!\Edexcel\Http\AbuseGuard::allow('classroom_action', $identity, $max, $windowSeconds, min(120, $windowSeconds))) {
            \Edexcel\Http\AbuseGuard::noteRejection('classroom_action', 'Classroom action limit reached');
            classroom_json(['ok' => false, 'error' => 'Please wait a moment and try again.'], 429);
        }
        return;
    }
    if (!isset($_SESSION['classroom_rl']) || !is_array($_SESSION['classroom_rl'])) {
        $_SESSION['classroom_rl'] = [];
    }
    $now = time();
    $bucket = $_SESSION['classroom_rl'][$key] ?? [];
    if (!is_array($bucket)) {
        $bucket = [];
    }
    $bucket = array_values(array_filter($bucket, static fn ($t) => is_int($t) && ($now - $t) < $windowSeconds));
    if (count($bucket) >= $max) {
        classroom_json(['ok' => false, 'error' => 'Please wait a moment and try again.'], 429);
    }
    $bucket[] = $now;
    $_SESSION['classroom_rl'][$key] = $bucket;
}

/**
 * Apply the same access-state matrix to classroom feature endpoints.
 *
 * @param array<string,mixed> $access
 * @param list<string> $allowedCodes
 */
function classroom_api_require_access(array $access, array $allowedCodes = [ClassroomAccessService::ALLOW]): void
{
    $code = (string)($access['code'] ?? ClassroomAccessService::DENY);
    if (in_array($code, $allowedCodes, true)) {
        return;
    }

    $message = trim((string)($access['message'] ?? ''));
    $payload = ['ok' => false, 'error' => $message !== '' ? $message : 'You cannot use this classroom right now.'];
    $status = 403;

    if ($code === ClassroomAccessService::PAY) {
        $payload['payment_required'] = true;
    } elseif ($code === ClassroomAccessService::WAIT) {
        $payload['wait'] = true;
        $payload['wait_kind'] = $access['wait_kind'] ?? null;
        $status = 409;
    } elseif ($code === ClassroomAccessService::SETUP) {
        $payload['setup_required'] = true;
        $status = 503;
    }

    classroom_json($payload, $status);
}

function classroom_read_json_body(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $cached = $_POST;
        return $cached;
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        $cached = $_POST;
        return $cached;
    }
    $cached = array_merge($_POST, $json);
    return $cached;
}

/**
 * @return array{lesson:array<string,mixed>,meeting:?array<string,mixed>,access:array<string,mixed>,svc:OnlineMeetingService}
 */
function classroom_load_lesson(PDO $pdo): array
{
    $input = array_merge($_GET, classroom_read_json_body());
    $svc = new OnlineMeetingService($pdo);
    $lessonId = (int)($input['lesson'] ?? $input['timetable_id'] ?? 0);
    $publicId = preg_replace('/[^a-f0-9]/i', '', (string)($input['m'] ?? $input['public_id'] ?? '')) ?? '';
    if ($lessonId < 1 && $publicId !== '') {
        $meeting = $svc->findByPublicId($publicId);
        $lessonId = (int)($meeting['timetable_id'] ?? 0);
    }
    if ($lessonId < 1) {
        classroom_json(['ok' => false, 'error' => 'Class not found.'], 404);
    }
    $lesson = $svc->lesson($lessonId);
    if (!$lesson) {
        classroom_json(['ok' => false, 'error' => 'Class not found.'], 404);
    }
    $meeting = $svc->findByLesson($lessonId);
    $access = classroom_access_context($pdo, $lesson, $meeting);
    if (classroom_join_forbidden($access)) {
        classroom_json([
            'ok' => false,
            'error' => ClassroomAccessService::UNAUTHORIZED_MESSAGE,
        ], 403);
    }
    if (current_role() === 'student' && empty($access['is_host'])) {
        $deviceSvc = function_exists('student_devices') ? student_devices($pdo) : null;
        if ($deviceSvc) {
            $deviceGate = $deviceSvc->liveClassGate((int)($_SESSION['user_id'] ?? 0), $lessonId);
            if (empty($deviceGate['ok'])) {
                if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), 'token.php')) {
                    try {
                        (new \Edexcel\Services\SecurityEventService($pdo))->record('LIVE_CLASS_ACCESS_DENIED', [
                            'user_id' => (int)($_SESSION['user_id'] ?? 0),
                            'timetable_id' => $lessonId,
                            'result' => 'denied',
                            'message' => (string)($deviceGate['code'] ?? 'device'),
                        ]);
                    } catch (Throwable $e) {
                    }
                }
                classroom_json([
                    'ok' => false,
                    'error' => (string)($deviceGate['message'] ?? 'You are not authorized to join this class. Please contact the institute if you believe this is an error.'),
                    'device_revoked' => true,
                    'device_code' => (string)($deviceGate['code'] ?? ''),
                ], 403);
            }
        }
    }
    return compact('lesson', 'meeting', 'access', 'svc');
}

$eckClassroomUser = (int)($_SESSION['user_id'] ?? 0);
if (class_exists(\Edexcel\Http\AbuseGuard::class)) {
    $eckClassroomRule = \Edexcel\Http\AbuseGuard::thresholds()['classroom_user'];
    if (!\Edexcel\Http\AbuseGuard::allow(
        'classroom_user',
        'user:' . $eckClassroomUser,
        (int)$eckClassroomRule['max'],
        (int)$eckClassroomRule['window'],
        (int)$eckClassroomRule['block']
    )) {
        \Edexcel\Http\AbuseGuard::noteRejection('classroom_user', 'Classroom request limit reached');
        classroom_json(['ok' => false, 'error' => 'Please wait a moment and try again.'], 429);
    }
}
