<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$loaded = classroom_load_lesson($pdo);
$access = $loaded['access'];
$meeting = $loaded['meeting'];
$settings = classroom_settings($pdo);
if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'Class not found.'], 404);
}
classroom_api_require_access($access);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$studentsCanDraw = !empty($meeting['students_can_draw']);

if ($method === 'GET') {
    $gate = classroom_whiteboard_access($access, $settings, 'GET', 'stroke', $studentsCanDraw);
    if (!$gate['ok']) {
        $payload = ['ok' => false, 'error' => $gate['error']];
        if (!empty($gate['wait'])) {
            $payload['wait'] = true;
        }
        classroom_json($payload, (int)$gate['status']);
    }
    $after = (int)($_GET['after'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT id, user_id, stroke_json, created_at
        FROM whiteboard_strokes
        WHERE meeting_id = ? AND id > ?
        ORDER BY id ASC
        LIMIT 500
    ");
    $stmt->execute([(int)$meeting['id'], $after]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $json = json_decode((string)$row['stroke_json'], true);
        $rows[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'stroke' => is_array($json) ? $json : null,
        ];
    }
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $pdfState = classroom_pdf_active_state($pdo, (int)$meeting['id'], $userId);

    classroom_json([
        'ok' => true,
        'strokes' => $rows,
        'can_draw' => !empty($gate['can_draw']),
        'can_view' => true,
        'students_can_draw' => $studentsCanDraw,
        'pdf_state' => $pdfState,
    ]);
}

classroom_require_post();
$input = classroom_read_json_body();
$action = strtolower(trim((string)($input['action'] ?? 'stroke')));
$userId = (int)($_SESSION['user_id'] ?? 0);
$gate = classroom_whiteboard_access($access, $settings, 'POST', $action, $studentsCanDraw);
if (!$gate['ok']) {
    classroom_json(['ok' => false, 'error' => $gate['error']], (int)$gate['status']);
}

if ($action === 'clear') {
    $pdo->prepare("DELETE FROM whiteboard_strokes WHERE meeting_id = ?")->execute([(int)$meeting['id']]);
    $loaded['svc']->event((int)$meeting['id'], $userId, 'whiteboard_clear', null);
    classroom_json(['ok' => true, 'cleared' => true]);
}

if ($action === 'allow_draw') {
    $allow = !empty($input['allow']) ? 1 : 0;
    $pdo->prepare("UPDATE online_meetings SET students_can_draw = ? WHERE id = ?")
        ->execute([$allow, (int)$meeting['id']]);
    classroom_json(['ok' => true, 'students_can_draw' => $allow === 1, 'can_draw' => true]);
}

$stroke = $input['stroke'] ?? null;
if (!is_array($stroke)) {
    classroom_json(['ok' => false, 'error' => 'Could not save that drawing.'], 422);
}
$stroke = classroom_normalize_whiteboard_stroke($stroke);
if ($stroke === null) {
    classroom_json(['ok' => false, 'error' => 'Could not save that drawing.'], 422);
}
$encoded = json_encode($stroke, JSON_UNESCAPED_UNICODE);
if ($encoded === false || strlen($encoded) > 60000) {
    classroom_json(['ok' => false, 'error' => 'That drawing is too large.'], 422);
}
$ins = $pdo->prepare("INSERT INTO whiteboard_strokes (meeting_id, user_id, stroke_json) VALUES (?, ?, ?)");
$ins->execute([(int)$meeting['id'], $userId, $encoded]);
classroom_json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
