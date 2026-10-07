<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CoursoChatService;
use Edexcel\Services\CoursoPublicChatService;

header('Content-Type: application/json; charset=utf-8');

function public_ai_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function public_ai_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return array_merge($_POST, $json);
        }
    }
    return $_POST;
}

function public_ai_require_post(array $body): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        public_ai_json(['ok' => false, 'error' => 'Please try again.'], 405);
    }
    $token = (string)($body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
        public_ai_json(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 403);
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    public_ai_json(['ok' => false, 'error' => 'The assistant is temporarily unavailable.'], 503);
}

ensure_courso_schema($pdo);

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
$isStudent = $studentId > 0 && strtolower((string)($_SESSION['role'] ?? '')) === 'student';

$action = strtolower(trim((string)($_GET['action'] ?? public_ai_body()['action'] ?? '')));
$body = public_ai_body();
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

try {
    $public = new CoursoPublicChatService($pdo);

    switch ($action) {
        case 'history':
            if ($isStudent) {
                public_ai_json(['ok' => true, 'messages' => (new CoursoChatService($pdo))->history($studentId), 'signed_in' => true]);
            }
            public_ai_json(['ok' => true, 'messages' => $public->history(), 'signed_in' => false]);

        case 'chat':
            public_ai_require_post($body);
            $public->assertRateLimit($ip);
            $message = (string)($body['message'] ?? '');
            if ($isStudent) {
                $result = (new CoursoChatService($pdo))->ask($studentId, $message);
                public_ai_json([
                    'ok' => true,
                    'reply' => $result['reply'],
                    'message_id' => (int)($result['message_id'] ?? 0),
                    'signed_in' => true,
                ]);
            }
            $result = $public->ask($message);
            public_ai_json([
                'ok' => true,
                'reply' => $result['reply'],
                'message_id' => 0,
                'signed_in' => false,
            ]);

        case 'rate':
            public_ai_require_post($body);
            $helpful = !empty($body['helpful']) && $body['helpful'] !== '0' && $body['helpful'] !== false;
            if ($isStudent) {
                $rating = (new CoursoChatService($pdo))->rate($studentId, (int)($body['message_id'] ?? 0), $helpful);
                public_ai_json(['ok' => true, 'rating' => $rating]);
            }
            $rating = $public->rateLast($helpful);
            public_ai_json(['ok' => true, 'rating' => $rating]);

        default:
            public_ai_json(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if ($msg === '' || str_contains(strtolower($msg), 'sql') || str_contains(strtolower($msg), 'stack')) {
        $msg = 'Something went wrong. Please try again.';
    }
    public_ai_json(['ok' => false, 'error' => $msg], 400);
}
