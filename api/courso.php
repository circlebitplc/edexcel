<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\CoursoChatService;
use Edexcel\Services\CoursoCommunityService;
use Edexcel\Services\CoursoLearnerService;
use Edexcel\Services\CoursoQuizService;

require_student();
ensure_courso_schema($pdo);

header('Content-Type: application/json; charset=utf-8');

function courso_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function courso_body(): array
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

function courso_require_post(array $body): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        courso_json(['ok' => false, 'error' => 'Please try again.'], 405);
    }
    $token = (string)($body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($token)) {
        courso_json(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 403);
    }
}

function courso_rate_limit(string $key, int $max, int $windowSeconds): void
{
    if (!isset($_SESSION['courso_rl']) || !is_array($_SESSION['courso_rl'])) {
        $_SESSION['courso_rl'] = [];
    }
    $now = time();
    $bucket = $_SESSION['courso_rl'][$key] ?? [];
    if (!is_array($bucket)) {
        $bucket = [];
    }
    $bucket = array_values(array_filter($bucket, static fn ($t) => is_int($t) && ($now - $t) < $windowSeconds));
    if (count($bucket) >= $max) {
        courso_json(['ok' => false, 'error' => 'Please wait a moment and try again.'], 429);
    }
    $bucket[] = $now;
    $_SESSION['courso_rl'][$key] = $bucket;
}

$studentId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if ($studentId < 1) {
    courso_json(['ok' => false, 'error' => 'Please sign in again.'], 403);
}

$action = strtolower(trim((string)($_GET['action'] ?? courso_body()['action'] ?? '')));
$body = courso_body();
$learner = new CoursoLearnerService($pdo);

try {
    switch ($action) {
        case 'snapshot':
            $learner->logActivity($studentId, 'portal', null, null, true);
            courso_json(['ok' => true, 'snapshot' => $learner->snapshot($studentId)]);

        case 'history':
            $chat = new CoursoChatService($pdo);
            courso_json(['ok' => true, 'messages' => $chat->history($studentId)]);

        case 'chat':
            courso_require_post($body);
            courso_rate_limit('chat:' . $studentId, 20, 300);
            $chat = new CoursoChatService($pdo);
            $result = $chat->ask($studentId, (string)($body['message'] ?? ''));
            courso_json([
                'ok' => true,
                'reply' => $result['reply'],
                'snapshot' => $result['snapshot'],
                'message_id' => (int)($result['message_id'] ?? 0),
            ]);

        case 'rate':
            courso_require_post($body);
            $chat = new CoursoChatService($pdo);
            $helpful = !empty($body['helpful']) && $body['helpful'] !== '0' && $body['helpful'] !== false;
            $rating = $chat->rate($studentId, (int)($body['message_id'] ?? 0), $helpful);
            courso_json(['ok' => true, 'rating' => $rating]);

        case 'search':
            $q = trim((string)($_GET['q'] ?? $body['q'] ?? ''));
            courso_json(['ok' => true, 'results' => $learner->search($studentId, $q)]);

        case 'profile':
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                courso_require_post($body);
                $days = $body['study_days'] ?? '1,2,3,4,5';
                if (is_array($days)) {
                    $days = implode(',', $days);
                }
                $saved = $learner->saveProfile($studentId, [
                    'goals' => (string)($body['goals'] ?? ''),
                    'interests' => (string)($body['interests'] ?? ''),
                    'ai_style' => (string)($body['ai_style'] ?? 'coach'),
                    'difficulty' => (string)($body['difficulty'] ?? 'adaptive'),
                    'notify_study' => !empty($body['notify_study']),
                    'notify_streak' => !empty($body['notify_streak']),
                    'notify_community' => !empty($body['notify_community']),
                    'study_days' => (string)$days,
                    'study_hour' => (int)($body['study_hour'] ?? 19),
                    'density' => (string)($body['density'] ?? 'comfortable'),
                ]);
                courso_json(['ok' => true, 'profile' => $saved, 'snapshot' => $learner->snapshot($studentId)]);
            }
            courso_json(['ok' => true, 'profile' => $learner->profile($studentId)]);

        case 'quiz_start':
            courso_require_post($body);
            courso_rate_limit('quiz:' . $studentId, 8, 300);
            $quiz = new CoursoQuizService($pdo);
            $started = $quiz->start(
                $studentId,
                $learner->snapshot($studentId),
                (int)($body['subject_id'] ?? 0) ?: null,
                (string)($body['topic'] ?? '')
            );
            courso_json(['ok' => true, 'quiz' => $started]);

        case 'quiz_submit':
            courso_require_post($body);
            $quiz = new CoursoQuizService($pdo);
            $answers = $body['answers'] ?? [];
            if (!is_array($answers)) {
                $answers = [];
            }
            $mapped = [];
            foreach ($answers as $id => $choice) {
                $mapped[(int)$id] = (int)$choice;
            }
            $done = $quiz->submit($studentId, (int)($body['quiz_id'] ?? 0), $mapped);
            courso_json(['ok' => true, 'quiz' => $done, 'snapshot' => $learner->snapshot($studentId)]);

        case 'community':
            $community = new CoursoCommunityService($pdo);
            $classId = (int)($_GET['class_id'] ?? $body['class_id'] ?? 0);
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                courso_require_post($body);
                courso_rate_limit('post:' . $studentId, 12, 300);
                $id = $community->post(
                    $studentId,
                    $classId,
                    (string)($body['body'] ?? ''),
                    (int)($body['parent_id'] ?? 0) ?: null
                );
                courso_json(['ok' => true, 'id' => $id, 'thread' => $community->thread($studentId, $classId)]);
            }
            if ($classId < 1) {
                courso_json(['ok' => true, 'thread' => []]);
            }
            courso_json(['ok' => true, 'thread' => $community->thread($studentId, $classId)]);

        default:
            courso_json(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if ($msg === '' || str_contains(strtolower($msg), 'sql') || str_contains(strtolower($msg), 'stack')) {
        $msg = 'Something went wrong. Please try again.';
    }
    courso_json(['ok' => false, 'error' => $msg], 400);
}
