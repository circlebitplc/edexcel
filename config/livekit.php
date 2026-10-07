<?php
declare(strict_types=1);

/**
 * LiveKit (SFU) configuration. Secrets stay on the server.
 */

require_once __DIR__ . '/bunny.php';

function livekit_config(?PDO $pdo = null): array
{
    $enabled = recordings_env_or_setting($pdo, 'LIVEKIT_ENABLED', 'livekit_enabled', '0');
    $url = livekit_ws_url(trim(recordings_env_or_setting($pdo, 'LIVEKIT_URL', 'livekit_url', '')));
    $ttl = (int)recordings_env_or_setting($pdo, 'LIVEKIT_TOKEN_TTL', 'livekit_token_ttl', '7200');
    if ($ttl < 300) {
        $ttl = 7200;
    }
    if ($ttl > 43200) {
        $ttl = 43200;
    }

    $forcePath = strtolower(recordings_env_or_setting($pdo, 'LIVEKIT_S3_FORCE_PATH_STYLE', 'livekit_s3_force_path_style', '1'));

    return [
        'enabled' => $enabled === '1' || strtolower($enabled) === 'true',
        'url' => $url,
        'api_key' => recordings_env_or_setting($pdo, 'LIVEKIT_API_KEY', 'livekit_api_key'),
        'api_secret' => recordings_env_or_setting($pdo, 'LIVEKIT_API_SECRET', 'livekit_api_secret'),
        'token_ttl' => $ttl,
        'http_base' => livekit_http_base($url),
        's3_endpoint' => livekit_normalize_s3_endpoint(recordings_env_or_setting($pdo, 'LIVEKIT_S3_ENDPOINT', 'livekit_s3_endpoint')),
        's3_internal_endpoint' => livekit_normalize_s3_endpoint(recordings_env_or_setting($pdo, 'LIVEKIT_S3_INTERNAL_ENDPOINT', 'livekit_s3_internal_endpoint')),
        's3_bucket' => trim(recordings_env_or_setting($pdo, 'LIVEKIT_S3_BUCKET', 'livekit_s3_bucket', 'livekit')),
        's3_region' => trim(recordings_env_or_setting($pdo, 'LIVEKIT_S3_REGION', 'livekit_s3_region', 'us-east-1')) ?: 'us-east-1',
        's3_access_key' => recordings_env_or_setting($pdo, 'LIVEKIT_S3_ACCESS_KEY', 'livekit_s3_access_key'),
        's3_secret' => recordings_env_or_setting($pdo, 'LIVEKIT_S3_SECRET', 'livekit_s3_secret'),
        's3_force_path_style' => $forcePath === '1' || $forcePath === 'true',
    ];
}

function livekit_normalize_s3_endpoint(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return rtrim($url, '/');
}

function livekit_is_cloud(array $cfg): bool
{
    return str_contains(strtolower((string)($cfg['url'] ?? '')), 'livekit.cloud');
}

function livekit_s3_configured(array $cfg): bool
{
    return trim((string)($cfg['s3_endpoint'] ?? '')) !== ''
        && trim((string)($cfg['s3_bucket'] ?? '')) !== ''
        && trim((string)($cfg['s3_access_key'] ?? '')) !== ''
        && trim((string)($cfg['s3_secret'] ?? '')) !== '';
}

/**
 * S3/MinIO credentials sent to LiveKit egress (upload). Internal Docker DNS is fine here.
 *
 * @return array{access_key:string,secret:string,region:string,endpoint:string,bucket:string,force_path_style:bool}|null
 */
function livekit_s3_for_egress(array $cfg): ?array
{
    $base = livekit_s3_for_presign($cfg);
    if ($base === null) {
        return null;
    }
    if (!livekit_is_cloud($cfg)) {
        $internal = trim((string)($cfg['s3_internal_endpoint'] ?? ''));
        if ($internal !== '') {
            $base['endpoint'] = $internal;
        }
    }
    return $base;
}

/**
 * Public HTTPS endpoint used to presign GET URLs so Bunny can fetch the MP4.
 *
 * @return array{access_key:string,secret:string,region:string,endpoint:string,bucket:string,force_path_style:bool}|null
 */
function livekit_s3_for_presign(array $cfg): ?array
{
    if (!livekit_s3_configured($cfg)) {
        return null;
    }
    return [
        'access_key' => (string)$cfg['s3_access_key'],
        'secret' => (string)$cfg['s3_secret'],
        'region' => trim((string)($cfg['s3_region'] ?? '')) !== '' ? (string)$cfg['s3_region'] : 'us-east-1',
        'endpoint' => (string)$cfg['s3_endpoint'],
        'bucket' => (string)$cfg['s3_bucket'],
        'force_path_style' => !empty($cfg['s3_force_path_style']),
    ];
}

function livekit_can_auto_record(array $cfg): bool
{
    return livekit_is_cloud($cfg) || livekit_s3_configured($cfg);
}

/**
 * Human-readable reason recording was skipped. Never includes secrets or endpoints.
 */
function livekit_record_skip_message(?array $cfg = null): string
{
    if (!is_array($cfg) || $cfg === []) {
        return 'Recording to Bunny needs MinIO/S3 on the VPS. Classes can still run.';
    }
    if (!livekit_can_auto_record($cfg)) {
        return 'Recording to Bunny needs MinIO/S3 on the VPS. Classes can still run.';
    }
    return 'Recording could not start. The class still runs.';
}

function livekit_ws_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $url = rtrim($url, '/');
    if (preg_match('#^https://#i', $url)) {
        $url = (string)preg_replace('#^https://#i', 'wss://', $url);
    } elseif (preg_match('#^http://#i', $url)) {
        $url = (string)preg_replace('#^http://#i', 'ws://', $url);
    } elseif (!preg_match('#^wss?://#i', $url)) {
        $url = 'wss://' . $url;
    }
    return livekit_college_signal_url($url);
}

/**
 * OpenLiteSpeed on :443 breaks Chrome WebSockets. This college's LiveKit Caddy listens on 8443.
 */
function livekit_college_signal_url(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $url;
    }
    if (strtolower((string)$parts['host']) !== 'live.kandy.edexcel.college') {
        return $url;
    }
    if (isset($parts['port']) && (int)$parts['port'] > 0) {
        return $url;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? 'wss'));
    $path = (string)($parts['path'] ?? '');
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $scheme . '://live.kandy.edexcel.college:8443' . $path . $query;
}

function livekit_http_base(string $wsUrl): string
{
    $url = trim($wsUrl);
    if ($url === '') {
        return '';
    }
    $url = preg_replace('#^ws://#i', 'http://', $url) ?? $url;
    $url = preg_replace('#^wss://#i', 'https://', $url) ?? $url;
    return rtrim($url, '/');
}

function livekit_ready(?PDO $pdo = null): bool
{
    $cfg = livekit_config($pdo);
    return $cfg['enabled']
        && $cfg['url'] !== ''
        && $cfg['api_key'] !== ''
        && $cfg['api_secret'] !== '';
}

function classroom_settings(?PDO $pdo = null): array
{
    $max = (int)recordings_env_or_setting($pdo, 'CLASSROOM_MAX_PARTICIPANTS', 'classroom_max_participants', '50');
    if ($max < 2) {
        $max = 2;
    }
    if ($max > 200) {
        $max = 200;
    }
    $early = (int)recordings_env_or_setting($pdo, 'CLASSROOM_JOIN_EARLY_MINUTES', 'classroom_join_early_minutes', '15');
    $early = max(0, min(120, $early));
    $joinLate = (int)recordings_env_or_setting($pdo, 'CLASSROOM_JOIN_LATE_MINUTES', 'classroom_join_late_minutes', '15');
    $joinLate = max(0, min(120, $joinLate));
    $present = (int)recordings_env_or_setting($pdo, 'CLASSROOM_PRESENT_MINUTES', 'classroom_present_minutes', '20');
    $present = max(1, min(180, $present));
    $late = (int)recordings_env_or_setting($pdo, 'CLASSROOM_LATE_GRACE_MINUTES', 'classroom_late_grace_minutes', '10');
    $late = max(0, min(60, $late));

    $bool = static function (string $env, string $key, string $default) use ($pdo): bool {
        $v = strtolower(recordings_env_or_setting($pdo, $env, $key, $default));
        return $v === '1' || $v === 'true';
    };

    return [
        'enabled' => $bool('CLASSROOM_ENABLED', 'classroom_enabled', '0'),
        'max_participants' => $max,
        'join_early_minutes' => $early,
        'join_late_minutes' => $joinLate,
        'present_minutes' => $present,
        'late_grace_minutes' => $late,
        'student_camera' => $bool('CLASSROOM_STUDENT_CAMERA', 'classroom_student_camera', '1'),
        'student_mic' => $bool('CLASSROOM_STUDENT_MIC', 'classroom_student_mic', '1'),
        'student_screenshare' => $bool('CLASSROOM_STUDENT_SCREENSHARE', 'classroom_student_screenshare', '0'),
        'chat_enabled' => $bool('CLASSROOM_CHAT_ENABLED', 'classroom_chat_enabled', '1'),
        'whiteboard_enabled' => $bool('CLASSROOM_WHITEBOARD_ENABLED', 'classroom_whiteboard_enabled', '1'),
        'auto_attendance' => $bool('CLASSROOM_AUTO_ATTENDANCE', 'classroom_auto_attendance', '1'),
        'waiting_room' => $bool('CLASSROOM_WAITING_ROOM', 'classroom_waiting_room', '0'),
        'auto_record' => $bool('CLASSROOM_AUTO_RECORD', 'classroom_auto_record', '1'),
        'pdf_whiteboard_enabled' => $bool('CLASSROOM_PDF_WHITEBOARD_ENABLED', 'classroom_pdf_whiteboard_enabled', '1'),
        'pdf_max_mb' => max(1, min(100, (int)recordings_env_or_setting($pdo, 'CLASSROOM_PDF_MAX_MB', 'classroom_pdf_max_mb', '30'))),
        'pdf_max_per_session' => max(1, min(20, (int)recordings_env_or_setting($pdo, 'CLASSROOM_PDF_MAX_PER_SESSION', 'classroom_pdf_max_per_session', '5'))),
        'student_pdf_download' => $bool('CLASSROOM_STUDENT_PDF_DOWNLOAD', 'classroom_student_pdf_download', '1'),
    ];
}

function classroom_feature_on(?PDO $pdo = null): bool
{
    $s = classroom_settings($pdo);
    return !empty($s['enabled']) && livekit_ready($pdo);
}
