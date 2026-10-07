<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\HandwritingRecognitionService;

$loaded = classroom_load_lesson($pdo);
$access = $loaded['access'];
$meeting = $loaded['meeting'];
$settings = classroom_settings($pdo);

if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'Class not found.'], 404);
}
classroom_api_require_access($access);

classroom_require_post();

$studentsCanDraw = !empty($meeting['students_can_draw']);
$gate = classroom_whiteboard_access($access, $settings, 'POST', 'stroke', $studentsCanDraw);
if (!$gate['ok']) {
    classroom_json(['ok' => false, 'error' => $gate['error'] ?: 'You cannot use the board right now.'], (int)$gate['status']);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
classroom_rate_limit('h2t:' . $userId, 12, 60);

$input = classroom_read_json_body();
$image = (string)($input['image'] ?? '');
if (str_starts_with($image, 'data:')) {
    if (!preg_match('#^data:(image/(?:png|jpeg|webp));base64,(.+)$#is', $image, $m)) {
        classroom_json(['ok' => false, 'error' => 'Invalid image data.'], 422);
    }
    $mime = strtolower($m[1]);
    $image = $m[2];
} else {
    $mime = strtolower(trim((string)($input['mime'] ?? 'image/png')));
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        $mime = 'image/png';
    }
}

$subject = strtolower(trim((string)($input['subject'] ?? 'general')));
$hint = trim((string)($input['hint'] ?? ''));

$svc = new HandwritingRecognitionService($pdo);
$result = $svc->recognize($image, $mime, [
    'subject' => $subject,
    'hint' => $hint,
]);

if (empty($result['ok'])) {
    classroom_json([
        'ok' => false,
        'error' => (string)($result['error'] ?? 'Recognition failed.'),
    ], 422);
}

classroom_json([
    'ok' => true,
    'raw_text' => $result['raw_text'],
    'corrected_text' => $result['corrected_text'],
    'confidence' => $result['confidence'],
    'type' => $result['type'],
    'latex' => $result['latex'] ?? '',
    'alternatives' => $result['alternatives'] ?? [],
    'corrections' => $result['corrections'] ?? [],
    'uncertain' => !empty($result['uncertain']),
]);
