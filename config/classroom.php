<?php
declare(strict_types=1);

require_once __DIR__ . '/livekit.php';

function classroom_normalize_delivery_mode(string $mode): string
{
    $mode = strtolower(trim($mode));
    return in_array($mode, ['physical', 'online', 'hybrid'], true) ? $mode : 'physical';
}

/**
 * @return array<string,string>
 */
function classroom_delivery_labels(): array
{
    return [
        'physical' => 'In college',
        'online' => 'Online',
        'hybrid' => 'Online and in college',
    ];
}

function classroom_delivery_label(string $mode): string
{
    $labels = classroom_delivery_labels();
    $mode = classroom_normalize_delivery_mode($mode);
    return $labels[$mode] ?? 'In college';
}

/**
 * Per-class waiting room. The meeting flag wins when present; otherwise the admin setting.
 */
function classroom_meeting_waiting_room(?array $meeting, array $settings): bool
{
    if (is_array($meeting) && array_key_exists('waiting_room', $meeting)) {
        return (int)$meeting['waiting_room'] === 1;
    }
    return !empty($settings['waiting_room']);
}

function classroom_is_online_mode(string $mode): bool
{
    return in_array(classroom_normalize_delivery_mode($mode), ['online', 'hybrid'], true);
}

/**
 * Short place line for WhatsApp and parent pages (room name, or Online).
 *
 * @param array<string,mixed> $lesson
 */
function classroom_lesson_place_line(array $lesson): string
{
    $mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
    $room = trim((string)($lesson['room_name'] ?? ''));
    if ($mode === 'online') {
        return 'Online class';
    }
    if ($mode === 'hybrid') {
        return $room !== '' ? 'Online + ' . $room : 'Online and in college';
    }
    return $room !== '' ? $room : 'In college';
}

function classroom_status_label(string $status): string
{
    return match (strtolower($status)) {
        'live' => 'Live now',
        'starting_soon' => 'Starting soon',
        'scheduled' => 'Scheduled',
        'ended' => 'Ended',
        'cancelled' => 'Cancelled',
        'recording' => 'Saving recording',
        'recording_ready' => 'Recording ready',
        default => 'Scheduled',
    };
}

/**
 * Columns ensure_classroom_schema() must ADD onto existing campus tables.
 *
 * @return list<array{0:string,1:string,2:string}>
 */
function classroom_schema_column_alters(): array
{
    return [
        ['timetable', 'delivery_mode', "ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical'"],
        ['recurring_schedules', 'delivery_mode', "ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical'"],
        ['student_profiles', 'parent_view_token', 'CHAR(32) NULL'],
        ['online_meetings', 'egress_id', 'VARCHAR(80) NULL'],
        ['meeting_participants', 'camera_blocked', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['meeting_participants', 'mic_blocked', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['meeting_participants', 'hand_raised_at', 'DATETIME NULL'],
        ['meeting_participants', 'screenshare_allowed', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['online_meetings', 'waiting_room', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['meeting_participants', 'waiting_status', "ENUM('none','waiting','admitted','denied') NOT NULL DEFAULT 'none'"],
        ['meeting_participants', 'segment_started_at', 'DATETIME NULL'],
    ];
}

function classroom_sql_duplicate_column(Throwable $e): bool
{
    $msg = strtolower($e->getMessage());
    return str_contains($msg, 'duplicate')
        || str_contains($msg, 'already exists')
        || str_contains($msg, 'duplicate column');
}

function classroom_sql_missing_column(Throwable $e, string $column = ''): bool
{
    $msg = strtolower($e->getMessage());
    $hit = str_contains($msg, 'unknown column')
        || str_contains($msg, 'no such column')
        || str_contains($msg, 'no such field')
        || str_contains($msg, '42s22');
    if (!$hit) {
        return false;
    }
    if ($column === '') {
        return true;
    }
    return str_contains($msg, strtolower($column));
}

/**
 * ALTER TABLE ADD COLUMN, ignoring "already exists". Does not trust
 * information_schema — campus_column_exists can false-positive or throw.
 */
function classroom_try_add_column(PDO $pdo, string $table, string $column, string $definition): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
    if ($table === '' || $column === '') {
        return false;
    }
    $attempts = [
        "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `{$column}` {$definition}",
        "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}",
    ];
    foreach ($attempts as $sql) {
        try {
            $pdo->exec($sql);
            return true;
        } catch (Throwable $e) {
            if (classroom_sql_duplicate_column($e)) {
                return true;
            }
            $low = strtolower($e->getMessage());
            if (str_contains($low, 'syntax') && str_contains($sql, 'IF NOT EXISTS')) {
                continue;
            }
            error_log("Classroom schema {$table}.{$column}: " . $e->getMessage());
        }
    }
    return false;
}

function classroom_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): bool
{
    $exists = false;
    if (function_exists('campus_column_exists')) {
        try {
            $exists = campus_column_exists($pdo, $table, $column);
        } catch (Throwable $e) {
            $exists = false;
        }
    }
    if ($exists) {
        return true;
    }
    return classroom_try_add_column($pdo, $table, $column, $definition);
}

function ensure_classroom_schema(PDO $pdo, bool $force = false): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    static $done = false;
    if ($done && !$force) {
        return;
    }

    $sqlFile = dirname(__DIR__) . '/database/migrations/011_online_classroom.sql';
    if (is_file($sqlFile)) {
        try {
            $sql = (string)file_get_contents($sqlFile);
            $lines = [];
            foreach (preg_split("/\r\n|\n|\r/", $sql) ?: [] as $line) {
                if (preg_match('/^\s*--/', $line)) {
                    continue;
                }
                $lines[] = $line;
            }
            $sql = implode("\n", $lines);
            foreach (preg_split('/;\s*/', $sql) ?: [] as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '' || !preg_match('/^CREATE TABLE/i', $chunk)) {
                    continue;
                }
                $pdo->exec($chunk);
            }
        } catch (Throwable $e) {
            error_log('Classroom tables: ' . $e->getMessage());
        }
    }

    $pending = false;
    foreach (classroom_schema_column_alters() as [$table, $column, $definition]) {
        if (!classroom_add_column_if_missing($pdo, $table, $column, $definition)) {
            $pending = true;
        }
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS classroom_reminder_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                timetable_id INT NOT NULL,
                reminder_type VARCHAR(40) NOT NULL,
                sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cr_lesson_type (timetable_id, reminder_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        error_log('classroom_reminder_log: ' . $e->getMessage());
    }

    try {
        if (function_exists('campus_column_exists') && campus_column_exists($pdo, 'student_profiles', 'parent_view_token')) {
            $pdo->exec("UPDATE student_profiles SET parent_view_token = NULL WHERE parent_view_token = ''");
        }
        if (function_exists('campus_index_exists') && !campus_index_exists($pdo, 'student_profiles', 'uq_parent_view_token')) {
            $pdo->exec('CREATE UNIQUE INDEX uq_parent_view_token ON student_profiles (parent_view_token)');
        }
    } catch (Throwable $e) {
        error_log('parent_view_token index: ' . $e->getMessage());
    }

    try {
        $exists = $pdo->query("SELECT id FROM rooms WHERE name = 'Online classroom' AND deleted_at IS NULL LIMIT 1");
        if ($exists && !$exists->fetchColumn()) {
            $pdo->exec("INSERT INTO rooms (name, capacity) VALUES ('Online classroom', 200)");
        }
    } catch (Throwable $e) {
        error_log('Online classroom room: ' . $e->getMessage());
    }

    $chatAlters = [
        ['meeting_chat_messages', 'is_private', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['meeting_chat_messages', 'recipient_user_id', 'INT NULL'],
    ];
    foreach ($chatAlters as [$table, $column, $definition]) {
        if (!classroom_add_column_if_missing($pdo, $table, $column, $definition)) {
            $pending = true;
        }
    }
    try {
        if (function_exists('campus_index_exists') && !campus_index_exists($pdo, 'meeting_chat_messages', 'idx_mc_private')) {
            $pdo->exec('CREATE INDEX idx_mc_private ON meeting_chat_messages (meeting_id, is_private, recipient_user_id)');
        }
    } catch (Throwable $e) {
        error_log('Classroom schema idx_mc_private: ' . $e->getMessage());
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS classroom_sms_joins (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                token_hash CHAR(64) NOT NULL,
                student_id INT NOT NULL,
                timetable_id INT NOT NULL,
                phone VARCHAR(20) NOT NULL,
                sent_by INT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_classroom_sms_joins_token (token_hash),
                KEY idx_classroom_sms_joins_lesson (student_id, timetable_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        error_log('classroom_sms_joins: ' . $e->getMessage());
    }

    classroom_pdf_ensure_schema($pdo);

    $done = !$pending;
}

function classroom_sync_lesson_meeting(PDO $pdo, int $timetableId): void
{
    if ($timetableId < 1) {
        return;
    }
    try {
        if (!$pdo->inTransaction()) {
            ensure_classroom_schema($pdo);
        }
        (new \Edexcel\Services\OnlineMeetingService($pdo))->ensureForLesson($timetableId);
    } catch (Throwable $e) {
        error_log('classroom_sync_lesson_meeting: ' . $e->getMessage());
    }
}

/**
 * Host-only actions accepted by api/classroom/control.php. Students must never
 * be granted these; the API still checks is_host even if markup is hidden.
 *
 * @return list<string>
 */
function classroom_host_control_actions(): array
{
    return [
        'mute',
        'allow_mic',
        'kick',
        'lock',
        'unlock',
        'lower_hand',
        'allow_speak',
        'clear_chat',
        'block_camera',
        'unblock_camera',
        'allow_share',
        'revoke_share',
        'mute_all',
        'start_recording',
        'stop_recording',
        'waiting_on',
        'waiting_off',
        'admit',
        'deny',
    ];
}

/**
 * LiveKit data payload for teacher camera force on/off.
 * force=true means the student client must setCameraEnabled immediately (no in-app confirm).
 *
 * @return array{t:string,blocked:bool,force:bool}
 */
function classroom_cam_control_payload(bool $blocked, bool $force = true): array
{
    return [
        't' => 'cam',
        'blocked' => $blocked,
        'force' => $force,
    ];
}

/**
 * Allow only http/https links (campus file URLs as stored). Relative /paths become HTTPS.
 */
function classroom_safe_http_url(?string $url, string $base = ''): ?string
{
    $url = trim((string)$url);
    if ($url === '' || str_contains($url, "\0") || preg_match('/[\r\n]/', $url)) {
        return null;
    }
    if (preg_match('/^(javascript|data|vbscript|file|about):/i', $url)) {
        return null;
    }
    if (isset($url[0]) && $url[0] === '/' && !str_starts_with($url, '//')) {
        $origin = rtrim($base, '/');
        if ($origin === '' && defined('BASE_URL')) {
            $origin = rtrim((string)BASE_URL, '/');
        }
        if ($origin === '' && function_exists('edexcel_public_app_url')) {
            $origin = rtrim(edexcel_public_app_url(), '/');
        }
        if ($origin === '') {
            $origin = 'https://edexcel.college';
        }
        $url = $origin . $url;
    }
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    $scheme = strtolower((string)$parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }
    $host = (string)$parts['host'];
    if ($host === '' || str_contains($host, ' ')) {
        return null;
    }
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    $path = (string)($parts['path'] ?? '');
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $scheme . '://' . $host . $port . $path . $query;
}

/**
 * @return array{x:float,y:float}|null
 */
function classroom_whiteboard_point(mixed $point): ?array
{
    if (!is_array($point) || !isset($point['x'], $point['y'])) {
        return null;
    }
    $x = (float)$point['x'];
    $y = (float)$point['y'];
    if (!is_finite($x) || !is_finite($y)) {
        return null;
    }
    return ['x' => round($x, 1), 'y' => round($y, 1)];
}

/**
 * Compact, safe whiteboard payload (drawing tools + collaborative ops + edu widgets).
 *
 * @param array<string,mixed> $stroke
 * @return array<string,mixed>|null
 */
function classroom_normalize_whiteboard_stroke(array $stroke): ?array
{
    $tool = strtolower(trim((string)($stroke['tool'] ?? 'pen')));
    $allowed = ['pen', 'highlight', 'erase', 'text', 'rect', 'line', 'ellipse', 'arrow', 'sticky', 'edu', 'op'];
    if (!in_array($tool, $allowed, true)) {
        $tool = 'pen';
    }

    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($stroke['id'] ?? ''));
    if (strlen($id) > 40) {
        $id = substr($id, 0, 40);
    }

    if ($tool === 'op') {
        $op = strtolower(trim((string)($stroke['op'] ?? '')));
        if (!in_array($op, ['delete', 'patch', 'transform', 'bg', 'page'], true)) {
            return null;
        }
        $out = ['tool' => 'op', 'op' => $op];
        if ($id !== '') {
            $out['id'] = $id;
        }
        if ($op === 'delete') {
            return ($id !== '') ? $out : null;
        }
        if ($op === 'page') {
            $out['page'] = max(0, min(40, (int)($stroke['page'] ?? 0)));
            $name = trim((string)($stroke['name'] ?? ''));
            if ($name !== '') {
                $name = function_exists('mb_substr') ? (string)mb_substr($name, 0, 40) : substr($name, 0, 40);
                $out['name'] = $name;
            }
            if (!empty($stroke['follow'])) {
                $out['follow'] = true;
            }
            return $out;
        }
        if ($op === 'bg') {
            $grid = strtolower(trim((string)($stroke['grid'] ?? 'none')));
            if (!in_array($grid, ['none', 'dot', 'line', 'coords'], true)) {
                $grid = 'none';
            }
            $bg = (string)($stroke['bg'] ?? '#ffffff');
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $bg)) {
                $bg = '#ffffff';
            }
            $out['grid'] = $grid;
            $out['bg'] = $bg;
            return $out;
        }
        if ($op === 'patch') {
            if ($id === '' || !isset($stroke['patch']) || !is_array($stroke['patch'])) {
                return null;
            }
            $patch = [];
            $src = $stroke['patch'];
            if (isset($src['color']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$src['color'])) {
                $patch['color'] = (string)$src['color'];
            }
            if (isset($src['width'])) {
                $patch['width'] = max(1, min(64, (int)$src['width']));
            }
            if (isset($src['opacity'])) {
                $patch['opacity'] = max(0.1, min(1, round((float)$src['opacity'], 2)));
            }
            if (isset($src['size'])) {
                $patch['size'] = max(12, min(72, (int)$src['size']));
            }
            if (isset($src['text'])) {
                $text = (string)$src['text'];
                $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
                $text = trim($text);
                $text = function_exists('mb_substr') ? (string)mb_substr($text, 0, 800) : substr($text, 0, 800);
                $patch['text'] = $text;
            }
            if (isset($src['font'])) {
                $font = strtolower(trim((string)$src['font']));
                if (in_array($font, ['arial', 'sans', 'serif', 'mono'], true)) {
                    $patch['font'] = $font;
                }
            }
            if (isset($src['style'])) {
                $style = strtolower(trim((string)$src['style']));
                if (in_array($style, ['normal', 'bold', 'italic', 'bolditalic'], true)) {
                    $patch['style'] = $style;
                }
            }
            if (isset($src['align'])) {
                $align = strtolower(trim((string)$src['align']));
                if (in_array($align, ['left', 'center', 'right'], true)) {
                    $patch['align'] = $align;
                }
            }
            if (array_key_exists('underline', $src)) {
                $patch['underline'] = !empty($src['underline']);
            }
            if (isset($src['lineHeight'])) {
                $lh = (float)$src['lineHeight'];
                if (is_finite($lh)) {
                    $patch['lineHeight'] = max(1.0, min(2.4, round($lh, 2)));
                }
            }
            if (array_key_exists('bg', $src)) {
                $bg = (string)$src['bg'];
                if ($bg === '' || $bg === 'transparent') {
                    $patch['bg'] = '';
                } elseif (preg_match('/^#[0-9a-fA-F]{3,8}$/', $bg)) {
                    $patch['bg'] = $bg;
                }
            }
            if (isset($src['fill']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$src['fill'])) {
                $patch['fill'] = (string)$src['fill'];
            }
            if (array_key_exists('locked', $src)) {
                $patch['locked'] = !empty($src['locked']);
            }
            if (array_key_exists('hidden', $src)) {
                $patch['hidden'] = !empty($src['hidden']);
            }
            if (isset($src['data']) && is_array($src['data'])) {
                $encoded = json_encode($src['data'], JSON_UNESCAPED_UNICODE);
                if ($encoded !== false && strlen($encoded) <= 8000) {
                    $patch['data'] = json_decode($encoded, true);
                }
            }
            if (isset($src['page'])) {
                $patch['page'] = max(0, min(40, (int)$src['page']));
            }
            if ($patch === []) {
                return null;
            }
            $out['patch'] = $patch;
            return $out;
        }
        if ($id === '') {
            return null;
        }
        $dx = (float)($stroke['dx'] ?? 0);
        $dy = (float)($stroke['dy'] ?? 0);
        if (!is_finite($dx) || !is_finite($dy)) {
            return null;
        }
        $out['dx'] = round($dx, 1);
        $out['dy'] = round($dy, 1);
        if (isset($stroke['points']) && is_array($stroke['points'])) {
            $pts = [];
            foreach ($stroke['points'] as $p) {
                $pt = classroom_whiteboard_point($p);
                if ($pt !== null) {
                    $pts[] = $pt;
                }
                if (count($pts) >= 200) {
                    break;
                }
            }
            if (count($pts) >= 2) {
                $out['points'] = $pts;
            }
        }
        foreach (['x', 'y', 'w', 'h'] as $k) {
            if (isset($stroke[$k]) && is_finite((float)$stroke[$k])) {
                $out[$k] = round((float)$stroke[$k], 1);
            }
        }
        return $out;
    }

    $color = (string)($stroke['color'] ?? '#111');
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $color = '#111';
    }
    $width = max(1, min(64, (int)($stroke['width'] ?? 3)));
    $opacity = isset($stroke['opacity']) ? max(0.1, min(1, round((float)$stroke['opacity'], 2))) : null;

    if ($tool === 'edu') {
        $kind = strtolower(trim((string)($stroke['kind'] ?? '')));
        // Accept catalog ids ("equation") and namespaced ids ("math.equation").
        if (!preg_match('/^(?:[a-z]{2,12}\.)?[a-z0-9_-]{1,32}$/', $kind)) {
            return null;
        }
        $x = (float)($stroke['x'] ?? 40);
        $y = (float)($stroke['y'] ?? 40);
        $w = (float)($stroke['w'] ?? 160);
        $h = (float)($stroke['h'] ?? 100);
        if (!is_finite($x) || !is_finite($y) || !is_finite($w) || !is_finite($h)) {
            return null;
        }
        $data = [];
        if (isset($stroke['data']) && is_array($stroke['data'])) {
            $encoded = json_encode($stroke['data'], JSON_UNESCAPED_UNICODE);
            if ($encoded !== false && strlen($encoded) <= 12000) {
                $data = json_decode($encoded, true) ?: [];
            }
        }
        $out = [
            'tool' => 'edu',
            'kind' => $kind,
            'x' => round($x, 1),
            'y' => round($y, 1),
            'w' => max(24, min(880, round($w, 1))),
            'h' => max(24, min(540, round($h, 1))),
            'color' => $color,
            'data' => $data,
            'page' => max(0, min(40, (int)($stroke['page'] ?? 0))),
        ];
        if ($id !== '') {
            $out['id'] = $id;
        }
        if (!empty($stroke['locked'])) {
            $out['locked'] = true;
        }
        if (!empty($stroke['hidden'])) {
            $out['hidden'] = true;
        }
        if (isset($stroke['points']) && is_array($stroke['points'])) {
            $pts = [];
            foreach ($stroke['points'] as $p) {
                $pt = classroom_whiteboard_point($p);
                if ($pt !== null) {
                    $pts[] = $pt;
                }
                if (count($pts) >= 40) {
                    break;
                }
            }
            if ($pts !== []) {
                $out['points'] = $pts;
            }
        }
        return $out;
    }

    if ($tool === 'text' || $tool === 'sticky') {
        // Preserve newlines for multiline board text; trim only ends.
        $text = (string)($stroke['text'] ?? '');
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = trim($text);
        $maxLen = $tool === 'sticky' ? 400 : 800;
        $text = function_exists('mb_substr') ? (string)mb_substr($text, 0, $maxLen) : substr($text, 0, $maxLen);
        if ($text === '') {
            return null;
        }
        $fromPts = (isset($stroke['points'][0]) && is_array($stroke['points'][0])) ? $stroke['points'][0] : [];
        $x = (float)($stroke['x'] ?? $fromPts['x'] ?? 0);
        $y = (float)($stroke['y'] ?? $fromPts['y'] ?? 0);
        $size = max(12, min(72, (int)($stroke['size'] ?? ($tool === 'sticky' ? 16 : 18))));
        $font = strtolower(trim((string)($stroke['font'] ?? 'sans')));
        if (!in_array($font, ['arial', 'sans', 'serif', 'mono'], true)) {
            $font = 'sans';
        }
        $style = strtolower(trim((string)($stroke['style'] ?? 'normal')));
        if (!in_array($style, ['normal', 'bold', 'italic', 'bolditalic'], true)) {
            $style = 'normal';
        }
        $align = strtolower(trim((string)($stroke['align'] ?? 'left')));
        if (!in_array($align, ['left', 'center', 'right'], true)) {
            $align = 'left';
        }
        $lineHeight = isset($stroke['lineHeight']) ? (float)$stroke['lineHeight'] : 1.35;
        if (!is_finite($lineHeight)) {
            $lineHeight = 1.35;
        }
        $lineHeight = max(1.0, min(2.4, round($lineHeight, 2)));
        $out = [
            'tool' => $tool,
            'x' => round($x, 1),
            'y' => round($y, 1),
            'text' => $text,
            'color' => $color,
            'size' => $size,
            'font' => $font,
            'style' => $style,
            'align' => $align,
            'lineHeight' => $lineHeight,
            'page' => max(0, min(40, (int)($stroke['page'] ?? 0))),
        ];
        if ($id !== '') {
            $out['id'] = $id;
        }
        if (!empty($stroke['underline'])) {
            $out['underline'] = true;
        }
        if (isset($stroke['bg']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$stroke['bg'])) {
            $out['bg'] = (string)$stroke['bg'];
        }
        if (isset($stroke['w']) && is_finite((float)$stroke['w'])) {
            $out['w'] = max(40, min(860, round((float)$stroke['w'], 1)));
        }
        if (isset($stroke['h']) && is_finite((float)$stroke['h'])) {
            $out['h'] = max(24, min(520, round((float)$stroke['h'], 1)));
        }
        if ($tool === 'sticky') {
            $fill = (string)($stroke['fill'] ?? '#fef08a');
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $fill)) {
                $fill = '#fef08a';
            }
            $out['fill'] = $fill;
            $out['w'] = max(80, min(420, (float)($stroke['w'] ?? 160)));
            $out['h'] = max(60, min(320, (float)($stroke['h'] ?? 120)));
        }
        if (!empty($stroke['locked'])) {
            $out['locked'] = true;
        }
        return $out;
    }

    if (in_array($tool, ['rect', 'line', 'ellipse', 'arrow'], true)) {
        $points = $stroke['points'] ?? [];
        if (isset($stroke['a'], $stroke['b']) && is_array($stroke['a']) && is_array($stroke['b'])) {
            $points = [$stroke['a'], $stroke['b']];
        }
        if (!is_array($points) || count($points) < 2) {
            return null;
        }
        $a = classroom_whiteboard_point($points[0]);
        $b = classroom_whiteboard_point($points[count($points) - 1]);
        if ($a === null || $b === null) {
            return null;
        }
        $out = [
            'tool' => $tool,
            'points' => [$a, $b],
            'color' => $color,
            'width' => $width,
            'page' => max(0, min(40, (int)($stroke['page'] ?? 0))),
        ];
        if ($id !== '') {
            $out['id'] = $id;
        }
        if ($opacity !== null) {
            $out['opacity'] = $opacity;
        }
        if (!empty($stroke['filled']) && in_array($tool, ['rect', 'ellipse'], true)) {
            $out['filled'] = true;
        }
        if (!empty($stroke['locked'])) {
            $out['locked'] = true;
        }
        return $out;
    }

    $pts = [];
    foreach ((array)($stroke['points'] ?? []) as $p) {
        $pt = classroom_whiteboard_point($p);
        if ($pt === null) {
            continue;
        }
        $pts[] = $pt;
        if (count($pts) >= 200) {
            break;
        }
    }
    if (count($pts) < 2) {
        return null;
    }
    $out = [
        'tool' => $tool,
        'points' => $pts,
        'color' => $color,
        'width' => $tool === 'erase' ? max($width, 8) : $width,
        'page' => max(0, min(40, (int)($stroke['page'] ?? 0))),
    ];
    if ($id !== '') {
        $out['id'] = $id;
    }
    if ($tool === 'highlight') {
        $out['opacity'] = $opacity !== null ? $opacity : 0.35;
        $out['width'] = max($width, 8);
    } elseif ($opacity !== null) {
        $out['opacity'] = $opacity;
    }
    if (!empty($stroke['locked'])) {
        $out['locked'] = true;
    }
    if (!empty($stroke['hidden'])) {
        $out['hidden'] = true;
    }
    return $out;
}

/**
 * Whiteboard GET is view-for-everyone who is in the class (ALLOW).
 * Waiting-room students cannot fetch yet. Drawing stays host-only unless students_can_draw.
 *
 * @param array{code?:string,is_host?:bool,message?:string} $access
 * @param array{whiteboard_enabled?:mixed} $settings
 * @return array{ok:bool,status:int,error:string,can_view:bool,can_draw:bool,wait?:bool}
 */
function classroom_whiteboard_access(
    array $access,
    array $settings,
    string $method = 'GET',
    string $action = 'stroke',
    bool $studentsCanDraw = false
): array {
    $code = (string)($access['code'] ?? '');
    $isHost = !empty($access['is_host']);
    $canDraw = $isHost || $studentsCanDraw;
    $msg = trim((string)($access['message'] ?? ''));
    $method = strtoupper($method);
    $action = strtolower(trim($action));

    if (in_array($code, [
        \Edexcel\Services\ClassroomAccessService::DENY,
        \Edexcel\Services\ClassroomAccessService::PAY,
        \Edexcel\Services\ClassroomAccessService::SETUP,
    ], true)) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => $msg !== '' ? $msg : 'You cannot join this class.',
            'can_view' => false,
            'can_draw' => false,
        ];
    }
    if ($code === \Edexcel\Services\ClassroomAccessService::WAIT) {
        return [
            'ok' => false,
            'status' => 409,
            'error' => $msg !== '' ? $msg : 'Please wait to be admitted.',
            'wait' => true,
            'can_view' => false,
            'can_draw' => false,
        ];
    }
    if (empty($settings['whiteboard_enabled'])) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'Whiteboard is turned off.',
            'can_view' => false,
            'can_draw' => false,
        ];
    }

    if ($method === 'GET') {
        return [
            'ok' => true,
            'status' => 200,
            'error' => '',
            'can_view' => true,
            'can_draw' => $canDraw,
        ];
    }

    if ($action === 'clear' || $action === 'allow_draw') {
        if (!$isHost) {
            return [
                'ok' => false,
                'status' => 403,
                'error' => $action === 'clear'
                    ? 'Only the teacher can clear the board.'
                    : 'Only the teacher can change this.',
                'can_view' => true,
                'can_draw' => $canDraw,
            ];
        }
        return [
            'ok' => true,
            'status' => 200,
            'error' => '',
            'can_view' => true,
            'can_draw' => true,
        ];
    }

    if (!$canDraw) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'The board is view-only right now.',
            'can_view' => true,
            'can_draw' => false,
        ];
    }

    return [
        'ok' => true,
        'status' => 200,
        'error' => '',
        'can_view' => true,
        'can_draw' => true,
    ];
}

/**
 * Class papers + homework for the live classroom. Empty list if tables are missing.
 *
 * @return list<array{kind:string,title:string,due:?string,url:?string}>
 */
function classroom_lesson_material_items(PDO $pdo, int $classId): array
{
    if ($classId < 1) {
        return [];
    }
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
    $items = [];
    $seen = [];
    $add = static function (array $item) use (&$items, &$seen): void {
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            return;
        }
        $url = $item['url'] ?? null;
        $key = strtolower($title) . '|' . (string)$url;
        if (isset($seen[$key])) {
            $i = $seen[$key];
            if (($items[$i]['url'] ?? null) === null && $url) {
                $items[$i] = $item;
            }
            return;
        }
        $seen[$key] = count($items);
        $items[] = $item;
    };

    try {
        $stmt = $pdo->prepare("
            SELECT title, file_url
            FROM student_materials
            WHERE class_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 24
        ");
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $add([
                'kind' => 'material',
                'title' => (string)$row['title'],
                'due' => null,
                'url' => classroom_safe_http_url((string)($row['file_url'] ?? ''), $base),
            ]);
        }
    } catch (Throwable $e) {
    }

    try {
        $stmt = $pdo->prepare("
            SELECT title, due_date, link
            FROM student_homework
            WHERE class_id = ?
            ORDER BY COALESCE(due_date, '9999-12-31') ASC, id DESC
            LIMIT 24
        ");
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $due = trim((string)($row['due_date'] ?? ''));
            $add([
                'kind' => 'homework',
                'title' => (string)$row['title'],
                'due' => $due !== '' ? $due : null,
                'url' => classroom_safe_http_url((string)($row['link'] ?? ''), $base),
            ]);
        }
    } catch (Throwable $e) {
    }

    return $items;
}

/**
 * ISO-8601 UTC for the classroom elapsed timer. Null when the class is not live.
 */
function classroom_started_at_iso(?string $startedAt): ?string
{
    $startedAt = trim((string)$startedAt);
    if ($startedAt === '') {
        return null;
    }
    $ts = strtotime($startedAt);
    if ($ts === false) {
        return null;
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function classroom_identity(int $userId): string
{
    return 'u' . $userId;
}

/**
 * Drop a participant from one LiveKit room. A later join is checked again by the webhook.
 */
function classroom_remove_livekit_identity(PDO $pdo, string $room, string $identity): void
{
    $room = trim($room);
    $identity = trim($identity);
    if ($room === '' || $identity === '' || !function_exists('livekit_config')) {
        return;
    }
    try {
        $cfg = livekit_config($pdo);
        if (!is_array($cfg) || (string)($cfg['api_key'] ?? '') === '') {
            return;
        }
        \Edexcel\Services\LiveKitRoomService::fromConfig($cfg)->removeParticipant($room, $identity);
    } catch (Throwable $e) {
        error_log('livekit remove participant: ' . $e->getMessage());
    }
}

/**
 * Remove a student from live rooms that belong to one class after they leave that class.
 */
function classroom_disconnect_student_class(PDO $pdo, int $studentId, int $classId): void
{
    if ($studentId < 1 || $classId < 1) {
        return;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT m.livekit_room
            FROM online_meetings m
            INNER JOIN timetable tt ON tt.id = m.timetable_id
            WHERE tt.class_id = ?
              AND m.livekit_room IS NOT NULL
              AND m.livekit_room != ''
              AND m.status NOT IN ('ended', 'cancelled')
        ");
        $stmt->execute([$classId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $identity = classroom_identity($studentId);
        foreach ($rooms as $room) {
            classroom_remove_livekit_identity($pdo, (string)$room, $identity);
        }
    } catch (Throwable $e) {
        error_log('livekit class disconnect: ' . $e->getMessage());
    }
}

function classroom_user_id_from_identity(string $identity): int
{
    if (preg_match('/^u(\d+)$/', $identity, $m)) {
        return (int)$m[1];
    }
    return 0;
}

/**
 * User ids that count as this lesson's hosts (meeting starter + class teachers).
 *
 * @param array<string,mixed> $lesson
 * @param array<string,mixed>|null $meeting
 * @return list<int>
 */
function classroom_lesson_host_user_ids(PDO $pdo, array $lesson, ?array $meeting): array
{
    $ids = [];
    $hostUid = (int)($meeting['host_user_id'] ?? 0);
    if ($hostUid > 0) {
        $ids[$hostUid] = $hostUid;
    }
    $teacherIds = [];
    foreach (['teacher_id', 'substitute_teacher_id'] as $key) {
        $tid = (int)($lesson[$key] ?? 0);
        if ($tid > 0) {
            $teacherIds[] = $tid;
        }
    }
    $teacherIds = array_values(array_unique($teacherIds));
    if ($teacherIds !== []) {
        try {
            $in = implode(',', array_fill(0, count($teacherIds), '?'));
            $stmt = $pdo->prepare("SELECT id FROM users WHERE teacher_id IN ({$in})");
            $stmt->execute($teacherIds);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) {
                    $ids[$uid] = $uid;
                }
            }
        } catch (Throwable $e) {
        }
    }
    return array_values($ids);
}

function classroom_chat_default_host_user_id(PDO $pdo, array $lesson, ?array $meeting): int
{
    $hostUid = (int)($meeting['host_user_id'] ?? 0);
    if ($hostUid > 0) {
        return $hostUid;
    }
    $hosts = classroom_lesson_host_user_ids($pdo, $lesson, $meeting);
    return $hosts[0] ?? 0;
}

function classroom_chat_recipient_is_student(PDO $pdo, int $meetingId, int $classId, int $userId): bool
{
    if ($userId < 1) {
        return false;
    }
    if ($classId > 0 && classroom_student_enrolled($pdo, $userId, $classId)) {
        return true;
    }
    if ($meetingId < 1) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM meeting_participants
            WHERE meeting_id = ? AND user_id = ? AND role = 'student'
            LIMIT 1
        ");
        $stmt->execute([$meetingId, $userId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function classroom_chat_normalize_row(array $row): array
{
    $private = (int)($row['is_private'] ?? 0) === 1 ? 1 : 0;
    $recipient = $private === 1 ? (int)($row['recipient_user_id'] ?? 0) : 0;
    return [
        'id' => (int)($row['id'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'display_name' => (string)($row['display_name'] ?? ''),
        'body' => (string)($row['body'] ?? ''),
        'is_announcement' => !empty($row['is_announcement']) ? 1 : 0,
        'is_private' => $private,
        'recipient_user_id' => $recipient > 0 ? $recipient : null,
        'created_at' => (string)($row['created_at'] ?? ''),
    ];
}

/**
 * LiveKit canPublishSources for a student. Empty means they must not publish.
 *
 * SCREEN_SHARE is included when the global admin setting is on, or this meeting
 * has granted share to that student.
 *
 * @param array<string,mixed> $settings
 * @return list<string>
 */
function classroom_student_publish_sources(
    array $settings,
    bool $cameraBlocked,
    bool $screenshareAllowed = false,
    bool $micBlocked = false
): array {
    $sources = [];
    if (!empty($settings['student_mic']) && !$micBlocked) {
        $sources[] = 'MICROPHONE';
    }
    if (!$cameraBlocked) {
        $sources[] = 'CAMERA';
    }
    if (!empty($settings['student_screenshare']) || $screenshareAllowed) {
        $sources[] = 'SCREEN_SHARE';
    }
    return $sources;
}

/**
 * JWT canPublishSources. LiveKit compares these case-sensitively to
 * "camera" / "microphone" / "screen_share" (not proto names CAMERA).
 *
 * @param list<string> $sources
 * @return list<string>
 */
function classroom_livekit_jwt_publish_sources(array $sources): array
{
    $out = [];
    foreach ($sources as $s) {
        $low = strtolower(trim((string)$s));
        if ($low !== '') {
            $out[] = $low;
        }
    }
    return $out;
}

function classroom_display_name(PDO $pdo, int $userId, string $role): string
{
    if ($role === 'student') {
        try {
            $stmt = $pdo->prepare('SELECT full_name FROM student_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $name = trim((string)$stmt->fetchColumn());
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable $e) {
        }
    }
    if ($role === 'teacher') {
        try {
            $stmt = $pdo->prepare('SELECT t.name FROM users u JOIN teachers t ON t.id = u.teacher_id WHERE u.id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $name = trim((string)$stmt->fetchColumn());
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable $e) {
        }
    }
    try {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $name = trim((string)$stmt->fetchColumn());
        if ($name !== '') {
            return $name;
        }
    } catch (Throwable $e) {
    }
    return $role === 'admin' ? 'Administrator' : 'User';
}

function classroom_student_enrolled(PDO $pdo, int $studentId, int $classId, ?int $teacherId = null): bool
{
    if ($studentId < 1 || $classId < 1) {
        return false;
    }
    $hasTeacher = function_exists('campus_column_exists')
        && campus_column_exists($pdo, 'student_enrollments', 'teacher_id');
    if ($hasTeacher) {
        $stmt = $pdo->prepare('SELECT teacher_id FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
        $stmt->execute([$studentId, $classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $want = (int)($row['teacher_id'] ?? 0);
        if ($teacherId === null || $want < 1) {
            return true;
        }
        return $want === $teacherId;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM student_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
    $stmt->execute([$studentId, $classId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Students may join from (start − early) through (end + late).
 * Default late grace is 15 minutes after the scheduled class end.
 */
function classroom_within_join_window(array $lesson, int $earlyMinutes, int $lateMinutes = 15): bool
{
    $date = (string)($lesson['date'] ?? '');
    $start = (string)($lesson['start_time'] ?? '');
    $end = (string)($lesson['end_time'] ?? '');
    if ($date === '' || $start === '') {
        return false;
    }
    $startTs = strtotime($date . ' ' . $start);
    $endTs = $end !== '' ? strtotime($date . ' ' . $end) : $startTs + 3600;
    if ($startTs === false || $endTs === false) {
        return false;
    }
    $now = time();
    $lateMinutes = max(0, min(120, $lateMinutes));
    return $now >= ($startTs - ($earlyMinutes * 60)) && $now <= ($endTs + ($lateMinutes * 60));
}

/**
 * True once scheduled end + late-join grace has passed.
 */
function classroom_join_window_closed(array $lesson, int $lateMinutes = 15, ?int $now = null): bool
{
    $date = (string)($lesson['date'] ?? '');
    $start = (string)($lesson['start_time'] ?? '');
    $end = (string)($lesson['end_time'] ?? '');
    if ($date === '' || $start === '') {
        return false;
    }
    $startTs = strtotime($date . ' ' . $start);
    $endTs = $end !== '' ? strtotime($date . ' ' . $end) : $startTs + 3600;
    if ($startTs === false || $endTs === false) {
        return false;
    }
    $lateMinutes = max(0, min(120, $lateMinutes));
    $now = $now ?? time();
    return $now > ($endTs + ($lateMinutes * 60));
}

function classroom_room_url(int $timetableId, string $publicId = ''): string
{
    $path = $publicId !== ''
        ? '/classroom/room.php?m=' . rawurlencode($publicId)
        : '/classroom/room.php?lesson=' . $timetableId;
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
    return ($base === '' ? '' : $base) . $path;
}

function classroom_public_join_url(int $timetableId, string $publicId = ''): string
{
    $origin = function_exists('edexcel_public_app_url')
        ? rtrim(edexcel_public_app_url(), '/')
        : 'https://edexcel.college';
    $path = $publicId !== ''
        ? '/classroom/room.php?m=' . rawurlencode($publicId)
        : '/classroom/room.php?lesson=' . $timetableId;
    return $origin . $path;
}

function classroom_sms_join_token_hash(string $token): string
{
    return hash('sha256', strtolower(trim($token)));
}

function classroom_sms_join_url(string $token): string
{
    $origin = function_exists('edexcel_public_app_url')
        ? rtrim(edexcel_public_app_url(), '/')
        : 'https://edexcel.college';
    return $origin . '/classroom/join.php?t=' . rawurlencode(strtolower(trim($token)));
}

/**
 * @param array<string,mixed> $lesson
 */
function classroom_sms_join_expires_at(array $lesson): string
{
    $date = (string)($lesson['date'] ?? '');
    $end = (string)($lesson['end_time'] ?? $lesson['start_time'] ?? '');
    $endTs = ($date !== '' && $end !== '') ? strtotime($date . ' ' . $end) : false;
    $until = time() + (6 * 3600);
    if ($endTs !== false) {
        $until = max($until, $endTs + (4 * 3600));
    }
    $until = min($until, time() + (36 * 3600));
    return date('Y-m-d H:i:s', $until);
}

/**
 * @param array<string,mixed> $lesson
 */
function classroom_sms_join_message(array $lesson, string $joinUrl): string
{
    $subject = trim((string)($lesson['subject_name'] ?? 'Class'));
    $className = trim((string)($lesson['class_name'] ?? ''));
    $time = !empty($lesson['start_time']) ? date('g:i A', strtotime((string)$lesson['start_time'])) : '';
    $day = !empty($lesson['date']) ? date('d M', strtotime((string)$lesson['date'])) : '';
    $label = $subject;
    if ($className !== '' && strcasecmp($subject, $className) !== 0) {
        $label .= ' · ' . $className;
    }
    $when = trim($day . ($time !== '' ? ' ' . $time : ''));
    $lines = [
        'Edexcel College',
        'Join ' . ($label !== '' ? $label : 'class') . ($when !== '' ? ' at ' . $when : '') . '.',
        '',
        'Tap to join:',
        $joinUrl,
    ];
    return implode("\n", $lines);
}

/**
 * @return array<string,mixed>|null
 */
function classroom_lesson_for_join_sms(PDO $pdo, int $timetableId): ?array
{
    if ($timetableId < 1) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT tt.id, tt.date, tt.start_time, tt.end_time, tt.class_id, tt.teacher_id,
               tt.substitute_teacher_id, tt.delivery_mode,
               s.name AS subject_name, c.name AS class_name
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        WHERE tt.id = ? AND tt.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$timetableId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function classroom_issue_sms_join_token(
    PDO $pdo,
    int $studentId,
    int $timetableId,
    string $phone,
    int $sentBy,
    array $lesson
): string {
    ensure_classroom_schema($pdo);
    $token = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare("
        INSERT INTO classroom_sms_joins (token_hash, student_id, timetable_id, phone, sent_by, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        classroom_sms_join_token_hash($token),
        $studentId,
        $timetableId,
        $phone,
        $sentBy > 0 ? $sentBy : null,
        classroom_sms_join_expires_at($lesson),
    ]);
    return $token;
}

/**
 * @return array{student_id:int,timetable_id:int}|null
 */
function classroom_redeem_sms_join_token(PDO $pdo, string $token): ?array
{
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
        return null;
    }
    ensure_classroom_schema($pdo);
    $hash = classroom_sms_join_token_hash($token);
    $stmt = $pdo->prepare("
        SELECT student_id, timetable_id
        FROM classroom_sms_joins
        WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $studentId = (int)$row['student_id'];
    $timetableId = (int)$row['timetable_id'];
    if ($studentId < 1 || $timetableId < 1) {
        return null;
    }
    return [
        'student_id' => $studentId,
        'timetable_id' => $timetableId,
        'token_hash' => $hash,
    ];
}

function classroom_consume_sms_join_token(PDO $pdo, string $tokenHash): void
{
    $tokenHash = trim($tokenHash);
    if ($tokenHash === '') {
        return;
    }
    try {
        $pdo->prepare("
            UPDATE classroom_sms_joins
            SET used_at = NOW()
            WHERE token_hash = ? AND used_at IS NULL
        ")->execute([$tokenHash]);
    } catch (Throwable $e) {
        error_log('classroom sms join used_at: ' . $e->getMessage());
    }
}

/**
 * Enrol the student, text a tap-to-join SMS, and return a status for the teacher.
 *
 * @return array{ok:bool,message:string,needs_name?:bool,name?:string}
 */
function classroom_send_lesson_join_sms(
    PDO $pdo,
    int $timetableId,
    string $whatsapp,
    int $sentBy = 0,
    bool $skipIfRecent = false
): array {
    $phone = function_exists('campus_lk_whatsapp') ? campus_lk_whatsapp($whatsapp) : '';
    if ($phone === '') {
        return ['ok' => false, 'message' => 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.'];
    }

    $lesson = classroom_lesson_for_join_sms($pdo, $timetableId);
    if (!$lesson) {
        return ['ok' => false, 'message' => 'Choose a class first.'];
    }

    $found = function_exists('campus_find_student_by_whatsapp')
        ? campus_find_student_by_whatsapp($pdo, $phone)
        : null;
    if (!$found) {
        return [
            'ok' => false,
            'needs_name' => true,
            'message' => 'This number is new. Enter the name, register them, and the join SMS will be sent.',
        ];
    }

    $studentId = (int)$found['id'];
    $classId = (int)$lesson['class_id'];
    if ($studentId < 1 || $classId < 1) {
        return ['ok' => false, 'message' => 'Could not add this student to the class.'];
    }

    try {
        $recent = $pdo->prepare("
            SELECT COUNT(*) FROM classroom_sms_joins
            WHERE student_id = ? AND timetable_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $recent->execute([$studentId, $timetableId]);
        $recentCount = (int)$recent->fetchColumn();
        if ($skipIfRecent && $recentCount >= 1) {
            $name = trim((string)($found['full_name'] ?? ''));
            return [
                'ok' => true,
                'name' => $name,
                'message' => 'Class join SMS already sent to this number.',
            ];
        }
        if ($recentCount >= 3) {
            return ['ok' => false, 'message' => 'That join SMS was already sent. Wait a few minutes before sending again.'];
        }
    } catch (Throwable $e) {
        error_log('classroom sms join rate: ' . $e->getMessage());
    }

    try {
        $pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id, class_id) VALUES (?, ?)')
            ->execute([$studentId, $classId]);
    } catch (Throwable $e) {
        error_log('classroom sms join enrol: ' . $e->getMessage());
    }

    classroom_sync_lesson_meeting($pdo, $timetableId);

    $token = classroom_issue_sms_join_token($pdo, $studentId, $timetableId, $phone, $sentBy, $lesson);
    $joinUrl = classroom_sms_join_url($token);
    $text = classroom_sms_join_message($lesson, $joinUrl);

    require_once __DIR__ . '/sms_gateway.php';
    if (!sms_send($pdo, $phone, $text)) {
        $detail = function_exists('sms_send_last_error') ? trim(sms_send_last_error()) : '';
        return [
            'ok' => false,
            'name' => (string)($found['full_name'] ?? ''),
            'message' => $detail !== ''
                ? ('SMS was not sent. ' . $detail)
                : 'SMS was not sent. Check the SMS gateway in Settings.',
        ];
    }

    if (function_exists('log_audit')) {
        log_audit($pdo, 'sms_class_join', 'timetable', $timetableId, null, [
            'student_id' => $studentId,
            'phone' => $phone,
        ]);
    }

    $name = trim((string)($found['full_name'] ?? ''));
    return [
        'ok' => true,
        'name' => $name,
        'message' => ($name !== '' ? $name . ': ' : '') . 'class join link sent by SMS.',
    ];
}

function classroom_parent_page_url(string $token): string
{
    $origin = function_exists('edexcel_public_app_url')
        ? rtrim(edexcel_public_app_url(), '/')
        : 'https://edexcel.college';
    return $origin . '/parent/today.php?t=' . rawurlencode($token);
}

function classroom_livekit_webhook_url(): string
{
    $origin = function_exists('edexcel_public_app_url')
        ? rtrim(edexcel_public_app_url(), '/')
        : 'https://edexcel.college';
    return $origin . '/api/livekit/webhook.php';
}

function classroom_parent_view_token(PDO $pdo, int $studentId): string
{
    if ($studentId < 1) {
        return '';
    }
    try {
        $stmt = $pdo->prepare('SELECT parent_view_token, parent_view_token_rotated_at FROM student_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $token = strtolower(preg_replace('/[^a-f0-9]/', '', (string)($row['parent_view_token'] ?? '')) ?? '');
        $rotated = (string)($row['parent_view_token_rotated_at'] ?? '');
        if (strlen($token) === 32) {
            if ($rotated === '' && function_exists('campus_column_exists') && campus_column_exists($pdo, 'student_profiles', 'parent_view_token_rotated_at')) {
                $pdo->prepare('UPDATE student_profiles SET parent_view_token_rotated_at = NOW() WHERE user_id = ? AND parent_view_token_rotated_at IS NULL')
                    ->execute([$studentId]);
            }
            return $token;
        }
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("
            INSERT INTO student_profiles (user_id, parent_view_token)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE parent_view_token = IF(parent_view_token IS NULL OR parent_view_token = '', VALUES(parent_view_token), parent_view_token)
        ")->execute([$studentId, $token]);
        $stmt->execute([$studentId]);
        $saved = strtolower(preg_replace('/[^a-f0-9]/', '', (string)$stmt->fetchColumn()) ?? '');
        return strlen($saved) === 32 ? $saved : $token;
    } catch (Throwable $e) {
        error_log('parent_view_token: ' . $e->getMessage());
        return '';
    }
}

function classroom_parent_rotate_token(PDO $pdo, int $studentId): string
{
    if ($studentId < 1) {
        return '';
    }
    $token = bin2hex(random_bytes(16));
    try {
        if (function_exists('campus_column_exists') && campus_column_exists($pdo, 'student_profiles', 'parent_view_token_rotated_at')) {
            $pdo->prepare("
                INSERT INTO student_profiles (user_id, parent_view_token, parent_view_token_rotated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE parent_view_token = VALUES(parent_view_token), parent_view_token_rotated_at = NOW()
            ")->execute([$studentId, $token]);
        } else {
            $pdo->prepare("
                INSERT INTO student_profiles (user_id, parent_view_token)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE parent_view_token = VALUES(parent_view_token)
            ")->execute([$studentId, $token]);
        }
        return $token;
    } catch (Throwable $e) {
        error_log('parent_view_token rotate: ' . $e->getMessage());
        return '';
    }
}

function classroom_reminder_already_sent(PDO $pdo, int $timetableId, string $type): bool
{
    $stmt = $pdo->prepare("
        SELECT id FROM classroom_reminder_log
        WHERE timetable_id = ? AND reminder_type = ?
        LIMIT 1
    ");
    $stmt->execute([$timetableId, $type]);
    return (bool)$stmt->fetchColumn();
}

function classroom_reminder_mark_sent(PDO $pdo, int $timetableId, string $type): void
{
    $pdo->prepare("
        INSERT INTO classroom_reminder_log (timetable_id, reminder_type)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE sent_at = sent_at
    ")->execute([$timetableId, $type]);
}

function classroom_send_whatsapp(PDO $pdo, string $phone, string $message, string $event): void
{
    $phone = function_exists('campus_normalize_phone')
        ? campus_normalize_phone($phone)
        : trim($phone);
    if ($phone === '') {
        return;
    }
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/evolution.php';
        require_once __DIR__ . '/whatsapp_gateway.php';
        $api = whatsapp_sender($pdo);
        $api->sendText($phone, $message);
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_bot_messages (phone, direction, message, event_name)
            VALUES (?, 'outbound', ?, ?)
        ");
        $stmt->execute([$phone, $message, $event]);
    } catch (Throwable $e) {
        error_log('classroom WhatsApp: ' . $e->getMessage());
    }
}

/**
 * Lessons whose start is between $minMinutes and $maxMinutes from now (Asia/Colombo wall clock).
 *
 * @return list<array<string,mixed>>
 */
function classroom_lessons_starting_in_window(PDO $pdo, int $minMinutes = 10, int $maxMinutes = 20): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo'));
    $lo = $now->modify('+' . max(0, $minMinutes) . ' minutes')->format('Y-m-d H:i:s');
    $hi = $now->modify('+' . max($minMinutes, $maxMinutes) . ' minutes')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT
            tt.id,
            tt.date,
            tt.start_time,
            tt.end_time,
            tt.class_id,
            tt.teacher_id,
            tt.substitute_teacher_id,
            tt.delivery_mode,
            COALESCE(tt.lesson_status, 'scheduled') AS lesson_status,
            COALESCE(sc.name, 'Class') AS class_name,
            COALESCE(s.name, 'Subject') AS subject_name,
            COALESCE(t.name, 'Teacher') AS teacher_name,
            COALESCE(st.name, '') AS substitute_name,
            COALESCE(r.name, '') AS room_name,
            om.public_id
        FROM timetable tt
        LEFT JOIN student_classes sc ON sc.id = tt.class_id AND sc.deleted_at IS NULL
        LEFT JOIN subjects s ON s.id = tt.subject_id AND s.deleted_at IS NULL
        LEFT JOIN teachers t ON t.id = tt.teacher_id AND t.deleted_at IS NULL
        LEFT JOIN teachers st ON st.id = tt.substitute_teacher_id
        LEFT JOIN rooms r ON r.id = tt.room_id AND r.deleted_at IS NULL
        LEFT JOIN online_meetings om ON om.timetable_id = tt.id
        WHERE tt.deleted_at IS NULL
          AND COALESCE(tt.lesson_status, 'scheduled') <> 'cancelled'
          AND TIMESTAMP(tt.date, tt.start_time) BETWEEN ? AND ?
        ORDER BY tt.start_time ASC, tt.id ASC
    ");
    $stmt->execute([$lo, $hi]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function classroom_reminder_job_log(string $message): void
{
    $file = dirname(__DIR__) . '/tools/classroom_reminders.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Send ~15-minute student/parent/teacher reminders. Safe to call from cron or a web tick.
 *
 * @return array{done:int,holiday_skip:int,window:int}
 */
function classroom_run_reminder_job(PDO $pdo, bool $dryRun = false, bool $echo = false): array
{
    date_default_timezone_set('Asia/Colombo');
    ensure_classroom_schema($pdo);
    $lessons = classroom_lessons_starting_in_window($pdo, 10, 20);
    classroom_reminder_job_log('Window lessons=' . count($lessons) . ($dryRun ? ' DRY-RUN' : ''));

    $holidayStmt = $pdo->prepare('SELECT id FROM holidays WHERE date = ? LIMIT 1');
    $done = 0;
    $skippedHoliday = 0;

    foreach ($lessons as $lesson) {
        $date = (string)($lesson['date'] ?? '');
        $id = (int)($lesson['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $isHoliday = false;
        if ($date !== '') {
            try {
                $holidayStmt->execute([$date]);
                $isHoliday = (bool)$holidayStmt->fetchColumn();
            } catch (Throwable $e) {
                $isHoliday = false;
            }
        }
        if ($isHoliday) {
            $skippedHoliday++;
            classroom_reminder_job_log('SKIP holiday timetable=' . $id);
            continue;
        }
        if ($dryRun) {
            classroom_reminder_job_log(
                'DRY RUN timetable=' . $id
                . ' ' . ($lesson['subject_name'] ?? '')
                . ' ' . ($lesson['start_time'] ?? '')
            );
            $done++;
            continue;
        }
        try {
            classroom_notify_starting_soon($pdo, $lesson);
            classroom_notify_teacher_ready($pdo, $lesson);
            $done++;
        } catch (Throwable $e) {
            classroom_reminder_job_log('FAIL timetable=' . $id . ' ' . $e->getMessage());
        }
    }

    $summary = "Done lessons={$done} holiday_skip={$skippedHoliday}";
    classroom_reminder_job_log($summary);
    if ($echo) {
        echo $summary . PHP_EOL;
    }
    return [
        'done' => $done,
        'holiday_skip' => $skippedHoliday,
        'window' => count($lessons),
    ];
}

function classroom_web_reminder_tick(?PDO $pdo): void
{
    if (!$pdo instanceof PDO || PHP_SAPI === 'cli') {
        return;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/api/', '/ajax/', '/tools/', '/cron/'] as $skip) {
        if (str_contains($script, $skip)) {
            return;
        }
    }
    $lockDir = dirname(__DIR__) . '/data';
    if (!is_dir($lockDir) || !is_writable($lockDir)) {
        $lockDir = sys_get_temp_dir();
    }
    $lockPath = $lockDir . '/classroom_reminder.tick';
    $fh = @fopen($lockPath, 'c+');
    if ($fh === false || !flock($fh, LOCK_EX | LOCK_NB)) {
        if ($fh) {
            fclose($fh);
        }
        return;
    }
    $last = (int)@filemtime($lockPath);
    if ($last > 1 && (time() - $last) < 240) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return;
    }
    @touch($lockPath);

    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $scriptFile = dirname(__DIR__) . '/tools/classroom_reminders.php';
    $launched = false;
    $disabled = array_map('strtolower', array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    if (
        is_file($scriptFile)
        && function_exists('exec')
        && !in_array('exec', $disabled, true)
    ) {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($scriptFile);
        if (stripos(PHP_OS, 'WIN') !== 0) {
            $cmd .= ' >/dev/null 2>&1 &';
        }
        @exec($cmd);
        $launched = true;
    }

    if ($launched) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return;
    }

    register_shutdown_function(static function () use ($pdo, $fh): void {
        try {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            classroom_run_reminder_job($pdo, false, false);
        } catch (Throwable $e) {
            error_log('classroom web tick: ' . $e->getMessage());
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    });
}

/**
 * @param array<string,mixed> $lesson
 */
function classroom_notify_starting_soon(PDO $pdo, array $lesson): void
{
    $id = (int)($lesson['id'] ?? 0);
    if ($id < 1 || classroom_reminder_already_sent($pdo, $id, 'starting_soon')) {
        return;
    }
    $subject = (string)($lesson['subject_name'] ?? 'Class');
    $teacher = (string)(($lesson['substitute_name'] ?: $lesson['teacher_name']) ?? 'Teacher');
    $time = !empty($lesson['start_time']) ? date('g:i A', strtotime((string)$lesson['start_time'])) : '';
    $mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
    $online = classroom_is_online_mode($mode);
    $join = $online ? classroom_public_join_url($id, (string)($lesson['public_id'] ?? '')) : '';
    $where = classroom_lesson_place_line($lesson);
    $studentMsg = "Your *{$subject}* class starts in 15 minutes.\n\n{$time}\nTeacher: {$teacher}\n{$where}";
    if ($join !== '') {
        $studentMsg .= "\n\nJoin class: {$join}";
    }
    $ids = function_exists('campus_class_student_ids')
        ? campus_class_student_ids($pdo, (int)$lesson['class_id'])
        : [];
    foreach ($ids as $studentId) {
        $studentId = (int)$studentId;
        if ($studentId < 1) {
            continue;
        }
        $contacts = function_exists('campus_student_contacts')
            ? campus_student_contacts($pdo, $studentId)
            : ['student_phone' => '', 'parent_phone' => ''];
        $token = classroom_parent_view_token($pdo, $studentId);
        $parentMsg = $studentMsg;
        if ($token !== '') {
            $parentMsg .= "\n\nSee today's classes: " . classroom_parent_page_url($token);
        }
        $studentPhone = (string)($contacts['student_phone'] ?? '');
        $parentPhone = (string)($contacts['parent_phone'] ?? '');
        if ($studentPhone !== '') {
            classroom_send_whatsapp($pdo, $studentPhone, $studentMsg, 'CLASS_STARTING_SOON');
        }
        if ($parentPhone !== '' && $parentPhone !== $studentPhone) {
            classroom_send_whatsapp($pdo, $parentPhone, $parentMsg, 'CLASS_STARTING_SOON_PARENT');
        }
    }
    $portalLink = $online ? classroom_room_url($id, (string)($lesson['public_id'] ?? '')) : 'dashboard.php?tab=timetable';
    if (function_exists('campus_portal_notify')) {
        campus_portal_notify($pdo, $ids, $subject . ' starts in 15 minutes', $where . ($time !== '' ? ' · ' . $time : ''), $portalLink);
    }
    classroom_reminder_mark_sent($pdo, $id, 'starting_soon');
}

/**
 * @param array<string,mixed> $lesson
 */
function classroom_notify_teacher_ready(PDO $pdo, array $lesson): void
{
    $id = (int)($lesson['id'] ?? 0);
    $teacherId = (int)($lesson['substitute_teacher_id'] ?? 0) > 0
        ? (int)$lesson['substitute_teacher_id']
        : (int)($lesson['teacher_id'] ?? 0);
    if ($id < 1 || $teacherId < 1 || classroom_reminder_already_sent($pdo, $id, 'teacher_ready')) {
        return;
    }
    $subject = (string)($lesson['subject_name'] ?? 'Class');
    $time = !empty($lesson['start_time']) ? date('g:i A', strtotime((string)$lesson['start_time'])) : '';
    $mode = classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical'));
    $online = classroom_is_online_mode($mode);
    $link = $online ? classroom_room_url($id, (string)($lesson['public_id'] ?? '')) : 'campus/today.php';
    $title = $online ? 'Your class is ready' : 'Class in 15 minutes';
    $message = $subject . ($time !== '' ? ' · ' . $time : '');
    if (function_exists('campus_insert_teacher_notification')) {
        campus_insert_teacher_notification($pdo, $teacherId, $title, $message, $link);
    }
    try {
        $stmt = $pdo->prepare('SELECT phone FROM teachers WHERE id = ? LIMIT 1');
        $stmt->execute([$teacherId]);
        $phone = trim((string)$stmt->fetchColumn());
        if ($phone !== '') {
            $wa = "{$title}\n\n*{$subject}*\n{$time}";
            if ($online) {
                $wa .= "\n\nStart class: " . classroom_public_join_url($id, (string)($lesson['public_id'] ?? ''));
            }
            classroom_send_whatsapp($pdo, $phone, $wa, 'CLASS_TEACHER_READY');
        }
    } catch (Throwable $e) {
        error_log('teacher ready WhatsApp: ' . $e->getMessage());
    }
    classroom_reminder_mark_sent($pdo, $id, 'teacher_ready');
}

/**
 * @return array{
 *   code:string,message:string,wait_kind:?string,is_host:bool,enrolled:bool,
 *   amount_due:float,fee_status:string,ctx:array<string,mixed>
 * }
 */
function classroom_access_context(PDO $pdo, array $lesson, ?array $meeting): array
{
    $role = current_role();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $sessionTeacher = in_array($role, ['teacher', 'admin'], true)
        ? (int)($_SESSION['teacher_id'] ?? 0)
        : 0;
    $settings = classroom_settings($pdo);
    $isHost = \Edexcel\Services\RecordingAccessService::teacherCanManageLesson(
        $sessionTeacher,
        (int)($lesson['teacher_id'] ?? 0),
        $role === 'admin',
        (int)($lesson['substitute_teacher_id'] ?? 0)
    );
    $enrolled = $role === 'student'
        ? classroom_student_enrolled($pdo, $userId, (int)$lesson['class_id'], (int)($lesson['teacher_id'] ?? 0))
        : $isHost;
    $kicked = false;
    $waitingStatus = 'none';
    $hasParticipant = false;
    $svc = new \Edexcel\Services\OnlineMeetingService($pdo);
    if ($meeting && $userId > 0) {
        $part = $svc->participantState((int)$meeting['id'], $userId);
        $hasParticipant = $part !== [];
        $kicked = (int)($part['kicked'] ?? 0) === 1;
        $waitingStatus = strtolower((string)($part['waiting_status'] ?? 'none'));
        if ($waitingStatus === '') {
            $waitingStatus = 'none';
        }
    }
    $feeStatus = 'paid';
    $amountDue = 0.0;
    if ($role === 'student' && $enrolled && !$isHost) {
        $feeStatus = 'unpaid';
        try {
            if (function_exists('lesson_sync_onepay')) {
                lesson_sync_onepay($pdo, $userId, (int)($lesson['id'] ?? $lesson['timetable_id'] ?? 0));
            } else {
                $checkout = dirname(__DIR__) . '/includes/lesson_checkout.php';
                if (is_file($checkout)) {
                    require_once $checkout;
                    lesson_sync_onepay($pdo, $userId, (int)($lesson['id'] ?? $lesson['timetable_id'] ?? 0));
                }
            }
            $resolved = (new \Edexcel\Services\StudentLessonFeeService($pdo))->resolve($userId, $lesson);
            $amountDue = (float)($resolved['amount_due'] ?? 0);
            if (\Edexcel\Services\StudentLessonFeeService::isUnlocked(
                (string)($resolved['status'] ?? ''),
                (bool)($resolved['covered_by_monthly'] ?? false)
            )) {
                $feeStatus = (string)($resolved['status'] ?? 'paid');
                if ($feeStatus === '' || (bool)($resolved['covered_by_monthly'] ?? false)) {
                    $feeStatus = 'paid';
                }
            } else {
                $feeStatus = (string)($resolved['status'] ?? 'unpaid');
                if ($feeStatus === '') {
                    $feeStatus = 'unpaid';
                }
            }
        } catch (Throwable $e) {
            error_log('Classroom lesson fee: ' . $e->getMessage());
            $feeStatus = 'unpaid';
        }
    }
    $ctx = [
        'authenticated' => $userId > 0,
        'role' => $role,
        'user_id' => $userId,
        'teacher_id' => $sessionTeacher,
        'lesson_teacher_id' => (int)($lesson['teacher_id'] ?? 0),
        'substitute_teacher_id' => (int)($lesson['substitute_teacher_id'] ?? 0),
        'enrolled' => $enrolled,
        'delivery_mode' => classroom_normalize_delivery_mode((string)($lesson['delivery_mode'] ?? 'physical')),
        'lesson_status' => (string)($lesson['lesson_status'] ?? 'scheduled'),
        'meeting_status' => (string)($meeting['status'] ?? 'scheduled'),
        'locked' => !empty($meeting['locked']),
        'kicked' => $kicked,
        'waiting_room' => classroom_meeting_waiting_room($meeting, $settings),
        'waiting_status' => $waitingStatus,
        'has_participant' => $hasParticipant,
        'classroom_enabled' => !empty($settings['enabled']),
        'livekit_ready' => livekit_ready($pdo),
        'within_join_window' => classroom_within_join_window(
            $lesson,
            (int)$settings['join_early_minutes'],
            (int)$settings['join_late_minutes']
        ),
        'join_window_closed' => classroom_join_window_closed($lesson, (int)$settings['join_late_minutes']),
        'is_host' => $isHost || $role === 'admin',
        'fee_status' => $feeStatus,
        'payment_pending' => $feeStatus === 'pending',
    ];
    $decision = \Edexcel\Services\ClassroomAccessService::decide($ctx);
    return [
        'code' => $decision['code'],
        'message' => $decision['message'],
        'wait_kind' => $decision['wait_kind'] ?? null,
        'is_host' => !empty($ctx['is_host']),
        'enrolled' => $enrolled,
        'amount_due' => $amountDue,
        'fee_status' => $feeStatus,
        'ctx' => $ctx,
    ];
}

/**
 * A copied classroom URL is not enough. Someone who is signed in as a different
 * account, or who is not enrolled in this class, never sees the room or a token.
 */
function classroom_join_forbidden(array $access): bool
{
    if (!empty($access['is_host']) || !empty($access['enrolled'])) {
        return false;
    }
    $code = (string)($access['code'] ?? '');
    return in_array($code, [
        \Edexcel\Services\ClassroomAccessService::DENY,
        \Edexcel\Services\ClassroomAccessService::SETUP,
        \Edexcel\Services\ClassroomAccessService::PAY,
        \Edexcel\Services\ClassroomAccessService::WAIT,
    ], true);
}

function classroom_render_join_denied(): never
{
    $message = \Edexcel\Services\ClassroomAccessService::UNAUTHORIZED_MESSAGE;
    $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
    $role = function_exists('current_role') ? current_role() : '';
    $home = $base . ($role === 'student' ? '/student/dashboard.php' : '/dashboard.php');
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $homeSafe = htmlspecialchars($home, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Class access</title>';
    echo '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0e1218;color:#f4f6fb;font-family:Segoe UI,sans-serif;padding:24px}.card{max-width:440px;text-align:center}h1{font-size:1.25rem;font-weight:650;margin:0 0 12px}p{margin:0 0 20px;line-height:1.5;color:#d5dbe6}a{color:#8eb6ff}</style>';
    echo '</head><body><div class="card"><h1>Class access</h1><p>' . $safe . '</p><p><a href="' . $homeSafe . '">Go to home</a></p></div></body></html>';
    exit;
}

/**
 * Put an enrolled student in this meeting's waiting list (no LiveKit token).
 *
 * @param array{meeting:?array,access:array,svc:\Edexcel\Services\OnlineMeetingService} $loaded
 */
function classroom_register_waiting_if_needed(array $loaded, array $settings, int $userId): void
{
    $access = $loaded['access'] ?? [];
    $meeting = $loaded['meeting'] ?? null;
    $svc = $loaded['svc'] ?? null;
    if (!is_array($meeting) || !$svc instanceof \Edexcel\Services\OnlineMeetingService) {
        return;
    }
    if (!empty($access['is_host']) || ($access['code'] ?? '') !== \Edexcel\Services\ClassroomAccessService::WAIT) {
        return;
    }
    if (($access['wait_kind'] ?? '') === 'pay_pending') {
        return;
    }
    if (($meeting['status'] ?? '') !== 'live' || empty($access['enrolled']) || $userId < 1) {
        return;
    }
    if (!classroom_meeting_waiting_room($meeting, $settings)) {
        return;
    }
    $svc->markWaiting((int)$meeting['id'], $userId, classroom_identity($userId));
}

function classroom_display_state(array $lesson, ?array $meeting, int $earlyMinutes = 15): string
{
    $lessonStatus = strtolower((string)($lesson['lesson_status'] ?? 'scheduled'));
    if ($lessonStatus === 'cancelled' || ($meeting['status'] ?? '') === 'cancelled') {
        return 'cancelled';
    }
    $m = strtolower((string)($meeting['status'] ?? ''));
    if ($m === 'live') {
        return 'live';
    }
    if ($m === 'ended') {
        return 'ended';
    }
    $date = (string)($lesson['date'] ?? '');
    $start = (string)($lesson['start_time'] ?? '');
    $startTs = ($date !== '' && $start !== '') ? strtotime($date . ' ' . $start) : false;
    if ($startTs !== false && time() >= ($startTs - ($earlyMinutes * 60)) && time() < $startTs) {
        return 'starting_soon';
    }
    return 'scheduled';
}

/**
 * @return list<array<string,mixed>>
 */
function classroom_lessons_for_teacher(PDO $pdo, int $teacherId, string $date): array
{
    ensure_classroom_schema($pdo);
    $sql = "
        SELECT tt.id, tt.date, tt.start_time, tt.end_time, tt.teacher_id, tt.class_id,
               tt.delivery_mode, tt.lesson_status, tt.substitute_teacher_id,
               s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
               om.id AS meeting_id, om.status AS meeting_status, om.public_id, om.locked,
               (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_id = tt.class_id) AS enrolled
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        JOIN teachers t ON t.id = tt.teacher_id
        LEFT JOIN online_meetings om ON om.timetable_id = tt.id
        WHERE tt.deleted_at IS NULL AND tt.date = ?
    ";
    $params = [$date];
    if ($teacherId > 0) {
        $sql .= " AND (tt.teacher_id = ? OR tt.substitute_teacher_id = ?)";
        $params[] = $teacherId;
        $params[] = $teacherId;
    }
    $sql .= " ORDER BY tt.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param list<int> $classIds
 * @return list<array<string,mixed>>
 */
function classroom_lessons_for_student(PDO $pdo, array $classIds, string $fromDate, string $toDate): array
{
    $classIds = array_values(array_filter(array_map('intval', $classIds), static fn ($id) => $id > 0));
    if ($classIds === []) {
        return [];
    }
    ensure_classroom_schema($pdo);
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $sql = "
        SELECT tt.id, tt.date, tt.start_time, tt.end_time, tt.teacher_id, tt.class_id,
               tt.delivery_mode, tt.lesson_status, tt.substitute_teacher_id,
               s.name AS subject_name, c.name AS class_name, t.name AS teacher_name, r.name AS room_name,
               om.id AS meeting_id, om.status AS meeting_status, om.public_id, om.locked
        FROM timetable tt
        JOIN subjects s ON s.id = tt.subject_id
        JOIN student_classes c ON c.id = tt.class_id
        JOIN teachers t ON t.id = tt.teacher_id
        LEFT JOIN rooms r ON r.id = tt.room_id AND r.deleted_at IS NULL
        LEFT JOIN online_meetings om ON om.timetable_id = tt.id
        WHERE tt.deleted_at IS NULL
          AND tt.class_id IN ($placeholders)
          AND tt.date BETWEEN ? AND ?
        ORDER BY tt.date, tt.start_time
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$classIds, $fromDate, $toDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string,mixed> $session
 * @return array{live:?array<string,mixed>,next:?array<string,mixed>,role:string,live_count:int}
 */
function classroom_dashboard_cards(PDO $pdo, array $session): array
{
    ensure_classroom_schema($pdo);
    $role = strtolower((string)($session['role'] ?? ''));
    $out = ['live' => null, 'next' => null, 'role' => $role, 'live_count' => 0];
    $settings = classroom_settings($pdo);
    $early = (int)$settings['join_early_minutes'];
    $joinLate = (int)$settings['join_late_minutes'];
    $today = date('Y-m-d');
    $now = date('H:i:s');

    if ($role === 'admin') {
        $svc = new \Edexcel\Services\OnlineMeetingService($pdo);
        $live = $svc->liveMeetings();
        $out['live_count'] = count($live);
        $out['live'] = $live[0] ?? null;
        $stmt = $pdo->prepare("
            SELECT tt.id, tt.date, tt.start_time, tt.end_time, tt.delivery_mode, tt.lesson_status,
                   s.name AS subject_name, c.name AS class_name, t.name AS teacher_name,
                   om.status AS meeting_status, om.public_id
            FROM timetable tt
            JOIN subjects s ON s.id = tt.subject_id
            JOIN student_classes c ON c.id = tt.class_id
            JOIN teachers t ON t.id = tt.teacher_id
            LEFT JOIN online_meetings om ON om.timetable_id = tt.id
            WHERE tt.deleted_at IS NULL AND tt.date = ?
              AND (tt.lesson_status IS NULL OR tt.lesson_status IN ('scheduled','substituted'))
              AND tt.end_time >= ?
              AND tt.delivery_mode IN ('online','hybrid')
            ORDER BY tt.start_time
            LIMIT 1
        ");
        $stmt->execute([$today, $now]);
        $out['next'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $out;
    }

    if ($role === 'teacher') {
        $teacherId = (int)($session['teacher_id'] ?? 0);
        if ($teacherId < 1) {
            return $out;
        }
        $lessons = classroom_lessons_for_teacher($pdo, $teacherId, $today);
        foreach ($lessons as $row) {
            $state = classroom_display_state($row, [
                'status' => $row['meeting_status'] ?? '',
            ], $early);
            $row['display_state'] = $state;
            $mode = classroom_normalize_delivery_mode((string)($row['delivery_mode'] ?? 'physical'));
            $online = in_array($mode, ['online', 'hybrid'], true);
            if ($state === 'live' && $online && $out['live'] === null) {
                $out['live'] = $row;
            }
        }
        foreach ($lessons as $row) {
            if (($row['lesson_status'] ?? '') === 'cancelled') {
                continue;
            }
            if ($row['end_time'] < $now && ($row['meeting_status'] ?? '') !== 'live') {
                continue;
            }
            $out['next'] = $row;
            $out['next']['display_state'] = classroom_display_state($row, ['status' => $row['meeting_status'] ?? ''], $early);
            break;
        }
        return $out;
    }

    if ($role === 'student') {
        $studentId = (int)($session['user_id'] ?? 0);
        $ids = [];
        try {
            $stmt = $pdo->prepare('SELECT class_id FROM student_enrollments WHERE student_id = ?');
            $stmt->execute([$studentId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return $out;
        }
        $lessons = classroom_lessons_for_student($pdo, $ids, $today, date('Y-m-d', strtotime('+14 days')));
        foreach ($lessons as $row) {
            $state = classroom_display_state($row, ['status' => $row['meeting_status'] ?? ''], $early);
            $row['display_state'] = $state;
            $mode = classroom_normalize_delivery_mode((string)($row['delivery_mode'] ?? 'physical'));
            if (
                $state === 'live'
                && in_array($mode, ['online', 'hybrid'], true)
                && $out['live'] === null
                && !classroom_join_window_closed($row, $joinLate)
            ) {
                $out['live'] = $row;
            }
        }
        foreach ($lessons as $row) {
            if (($row['lesson_status'] ?? '') === 'cancelled') {
                continue;
            }
            if ($row['date'] < $today) {
                continue;
            }
            if ($row['date'] === $today && $row['end_time'] < $now && ($row['meeting_status'] ?? '') !== 'live') {
                continue;
            }
            $out['next'] = $row;
            $out['next']['display_state'] = classroom_display_state($row, ['status' => $row['meeting_status'] ?? ''], $early);
            break;
        }
    }

    return $out;
}

function classroom_notify_live(PDO $pdo, array $lesson, string $joinUrl): void
{
    if (!function_exists('campus_class_student_ids')) {
        return;
    }
    $ids = campus_class_student_ids($pdo, (int)$lesson['class_id']);
    $subject = (string)($lesson['subject_name'] ?? 'Class');
    $teacher = (string)($lesson['teacher_name'] ?? 'Teacher');
    $msg = "🔴 *{$subject}* is now live.\n\nTeacher: {$teacher}\nJoin class: {$joinUrl}";
    if (function_exists('campus_notify_students')) {
        campus_notify_students($pdo, $ids, $msg, 'CLASS_LIVE');
    }
    if (function_exists('campus_portal_notify')) {
        campus_portal_notify($pdo, $ids, $subject . ' is live', 'Tap to join class.', $joinUrl);
    }
}

function classroom_notify_ended(PDO $pdo, array $lesson): void
{
    if (!function_exists('campus_class_student_ids')) {
        return;
    }
    $ids = campus_class_student_ids($pdo, (int)$lesson['class_id']);
    $subject = (string)($lesson['subject_name'] ?? 'Class');
    $msg = "Today's *{$subject}* class has ended.\n\nThe recording will appear under Recordings when it is ready.";
    if (function_exists('campus_notify_students')) {
        campus_notify_students($pdo, $ids, $msg, 'CLASS_ENDED');
    }
    if (function_exists('campus_portal_notify')) {
        campus_portal_notify(
            $pdo,
            $ids,
            $subject . ' has ended',
            'Recording will be available when processing is complete.',
            rtrim((string)BASE_URL, '/') . '/student/dashboard.php?tab=recordings'
        );
    }
}

// ─── PDF Whiteboard ────────────────────────────────────────────────────────────

/**
 * Ensure classroom_pdf_documents and classroom_pdf_download_log tables exist,
 * and add active_pdf_id / active_pdf_page / active_pdf_zoom columns to online_meetings.
 * Safe to call multiple times (idempotent via static flag).
 */
function classroom_pdf_ensure_schema(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }
    static $pdone = false;
    if ($pdone) {
        return;
    }
    $sqlFile = dirname(__DIR__) . '/database/migrations/040_classroom_pdf_whiteboard.sql';
    if (is_file($sqlFile)) {
        try {
            $sql = (string)file_get_contents($sqlFile);
            $lines = [];
            foreach (preg_split("/\r\n|\n|\r/", $sql) ?: [] as $line) {
                if (preg_match('/^\s*--/', $line)) {
                    continue;
                }
                $lines[] = $line;
            }
            $sql = implode("\n", $lines);
            foreach (preg_split('/;\s*/', $sql) ?: [] as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '' || !preg_match('/^CREATE TABLE/i', $chunk)) {
                    continue;
                }
                $pdo->exec($chunk);
            }
        } catch (Throwable $e) {
            error_log('classroom_pdf tables: ' . $e->getMessage());
        }
    }
    $cols = [
        ['online_meetings', 'active_pdf_id',   'BIGINT UNSIGNED NULL DEFAULT NULL'],
        ['online_meetings', 'active_pdf_page',  'INT UNSIGNED NOT NULL DEFAULT 1'],
        ['online_meetings', 'active_pdf_zoom',  "DECIMAL(5,2) NOT NULL DEFAULT '1.00'"],
    ];
    foreach ($cols as [$table, $col, $def]) {
        classroom_try_add_column($pdo, $table, $col, $def);
    }
    $pdone = true;
}

/**
 * Check whether the current user may upload/close PDFs (teacher/host only)
 * or merely view/download them (enrolled student with access).
 *
 * @param array{code?:string,is_host?:bool} $access
 * @param array{pdf_whiteboard_enabled?:mixed,student_pdf_download?:mixed} $settings
 * @return array{can_upload:bool,can_view:bool,can_download:bool,enabled:bool}
 */
function classroom_pdf_access(array $access, array $settings): array
{
    $enabled = !empty($settings['pdf_whiteboard_enabled']);
    $isHost  = !empty($access['is_host']);
    $code    = (string)($access['code'] ?? '');
    $denied  = in_array($code, [
        \Edexcel\Services\ClassroomAccessService::DENY,
        \Edexcel\Services\ClassroomAccessService::PAY,
        \Edexcel\Services\ClassroomAccessService::SETUP,
    ], true);

    if ($denied || !$enabled) {
        return ['can_upload' => false, 'can_view' => false, 'can_download' => false, 'enabled' => $enabled];
    }

    $isWaiting = $code === \Edexcel\Services\ClassroomAccessService::WAIT;
    $canView   = !$isWaiting;
    $dlEnabled = !empty($settings['student_pdf_download']);

    return [
        'can_upload'   => $isHost,
        'can_view'     => $canView,
        'can_download' => $canView && ($isHost || $dlEnabled),
        'enabled'      => true,
    ];
}

/**
 * Generate a short-lived signed download token for a PDF document.
 * Token = base64url(json payload with HMAC-SHA256 signature).
 * Expires in $ttlSeconds (default 900 = 15 minutes).
 */
function classroom_pdf_download_token(int $pdfId, int $userId, int $ttlSeconds = 900): string
{
    $exp    = time() + $ttlSeconds;
    $secret = defined('APP_KEY') ? (string)APP_KEY : (ini_get('session.name') . '|ck-pdf');
    $msg    = "pdf:{$pdfId}:{$userId}:{$exp}";
    $sig    = hash_hmac('sha256', $msg, $secret);
    $payload = json_encode(['p' => $pdfId, 'u' => $userId, 'e' => $exp, 's' => $sig]);
    return rtrim(strtr(base64_encode((string)$payload), '+/', '-_'), '=');
}

/**
 * Verify and decode a PDF download token. Returns array or null on failure/expiry.
 *
 * @return array{pdf_id:int,user_id:int}|null
 */
function classroom_pdf_verify_download_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) > 600) {
        return null;
    }
    $pad = strlen($token) % 4;
    $raw = base64_decode(strtr($token, '-_', '+/') . ($pad > 0 ? str_repeat('=', 4 - $pad) : ''), true);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['p'], $data['u'], $data['e'], $data['s'])) {
        return null;
    }
    $pdfId  = (int)$data['p'];
    $userId = (int)$data['u'];
    $exp    = (int)$data['e'];
    $sig    = (string)$data['s'];
    if ($pdfId < 1 || $userId < 1 || $exp < time()) {
        return null;
    }
    $secret   = defined('APP_KEY') ? (string)APP_KEY : (ini_get('session.name') . '|ck-pdf');
    $msg      = "pdf:{$pdfId}:{$userId}:{$exp}";
    $expected = hash_hmac('sha256', $msg, $secret);
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    return ['pdf_id' => $pdfId, 'user_id' => $userId];
}

/**
 * Return the currently active PDF state for a meeting.
 * Returns null when no PDF is active or the PDF was closed/replaced.
 *
 * @return array{doc_id:int,page:int,zoom:float,filename:string,total_pages:int,download_token:string}|null
 */
function classroom_pdf_active_state(PDO $pdo, int $meetingId, int $userId): ?array
{
    if ($meetingId < 1) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT om.active_pdf_id, om.active_pdf_page, om.active_pdf_zoom,
                   cpd.original_filename, cpd.total_pages, cpd.status
            FROM online_meetings om
            LEFT JOIN classroom_pdf_documents cpd ON cpd.id = om.active_pdf_id
            WHERE om.id = ?
            LIMIT 1
        ");
        $stmt->execute([$meetingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['active_pdf_id'] || ($row['status'] ?? '') !== 'active') {
            return null;
        }
        $downloadToken = classroom_pdf_download_token((int)$row['active_pdf_id'], $userId, 900);
        return [
            'doc_id'         => (int)$row['active_pdf_id'],
            'page'           => max(1, (int)$row['active_pdf_page']),
            'zoom'           => max(0.1, min(5.0, (float)$row['active_pdf_zoom'])),
            'filename'       => (string)$row['original_filename'],
            'total_pages'    => (int)$row['total_pages'],
            'download_token' => $downloadToken,
        ];
    } catch (Throwable $e) {
        error_log('classroom_pdf_active_state: ' . $e->getMessage());
        return null;
    }
}

/**
 * Log a student PDF download for audit purposes. Silent on failure.
 */
function classroom_pdf_log_download(PDO $pdo, int $pdfId, int $userId): void
{
    try {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $pdo->prepare("
            INSERT INTO classroom_pdf_download_log (pdf_id, user_id, ip_address)
            VALUES (?, ?, ?)
        ")->execute([$pdfId, $userId, $ip]);
        $pdo->prepare(
            "UPDATE classroom_pdf_documents SET download_count = download_count + 1 WHERE id = ?"
        )->execute([$pdfId]);
    } catch (Throwable $e) {
        error_log('classroom_pdf_log_download: ' . $e->getMessage());
    }
}

/**
 * Retrieve a PDF document row, verifying it belongs to the specified meeting.
 *
 * @return array<string,mixed>|null
 */
function classroom_pdf_get_document(PDO $pdo, int $pdfId, int $meetingId): ?array
{
    if ($pdfId < 1 || $meetingId < 1) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT id, meeting_id, timetable_id, uploaded_by, original_filename,
                   stored_path, mime_type, file_size, total_pages, status, created_at
            FROM classroom_pdf_documents
            WHERE id = ? AND meeting_id = ?
            LIMIT 1
        ");
        $stmt->execute([$pdfId, $meetingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('classroom_pdf_get_document: ' . $e->getMessage());
        return null;
    }
}

