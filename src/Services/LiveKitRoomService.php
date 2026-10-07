<?php
declare(strict_types=1);

namespace Edexcel\Services;

use RuntimeException;
use Throwable;

/**
 * LiveKit Room Service (Twirp JSON). Used for mute, remove, health, end room.
 */
final class LiveKitRoomService
{
    public function __construct(
        private string $httpBase,
        private string $apiKey,
        private string $apiSecret
    ) {
        $this->httpBase = rtrim($httpBase, '/');
    }

    public static function fromConfig(array $cfg): self
    {
        return new self(
            (string)($cfg['http_base'] ?? ''),
            (string)($cfg['api_key'] ?? ''),
            (string)($cfg['api_secret'] ?? '')
        );
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function testConnection(): array
    {
        if ($this->httpBase === '' || $this->apiKey === '' || $this->apiSecret === '') {
            return ['ok' => false, 'message' => 'Enter the LiveKit URL, API key, and API secret first.'];
        }
        try {
            $this->call('ListRooms', new \stdClass());
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $low = strtolower($msg);
            if (str_contains($low, 'permission') || str_contains($low, 'unauthenticated') || str_contains($low, 'unauthorized')) {
                return [
                    'ok' => false,
                    'message' => 'LiveKit is reachable, but the API key and secret do not match the LiveKit server. Use the same pair as livekit.yaml (keys: and webhook.api_key).',
                ];
            }
            return ['ok' => false, 'message' => $msg];
        }

        $rtc = $this->probeRtc();
        if ($rtc === 'ws_ok') {
            return [
                'ok' => true,
                'message' => 'LiveKit WebSocket works over HTTP/1.1. If Chrome Join still fails, LiteSpeed HTTP/3 is still advertised (alt-svc: h3). On the VPS run: bash /opt/livekit/fix-ols-http3.sh — then open UDP 50000–50100 and 3478.',
            ];
        }
        if ($rtc === 'reached_http') {
            return [
                'ok' => false,
                'message' => 'The LiveKit API works, but /rtc is not completing a WebSocket upgrade. On the live. CyberPanel vhost add header Alt-Svc: clear and proxy WebSocket to 127.0.0.1:7880. Steps: deploy/livekit/CYBERPANEL.md',
            ];
        }
        if ($rtc === 'missing') {
            return [
                'ok' => false,
                'message' => 'The LiveKit API works, but /rtc is not proxied. Reverse-proxy live.kandy.edexcel.college to 127.0.0.1:7880 with WebSocket. See deploy/livekit/CYBERPANEL.md',
            ];
        }
        return [
            'ok' => true,
            'message' => 'LiveKit API is reachable, but the /rtc WebSocket check was inconclusive. Follow deploy/livekit/CYBERPANEL.md (Alt-Svc: clear, WebSocket proxy, UDP ports).',
        ];
    }

    /**
     * Classify a /rtc probe so Test Connection can tell API-ok from WebSocket-ok.
     */
    public static function rtcProbeKind(int $httpCode, string $headersAndBody): string
    {
        $blob = strtolower($headersAndBody);
        if ($httpCode === 101 || str_contains($blob, 'switching protocols')) {
            return 'ws_ok';
        }
        if ($httpCode === 404) {
            return 'missing';
        }
        if ($httpCode === 0) {
            return 'down';
        }
        if ($httpCode === 401 || str_contains($blob, 'no permissions') || str_contains($blob, 'unauthorized')) {
            return 'reached_http';
        }
        return 'other';
    }

    private function probeRtc(): string
    {
        $token = LiveKitTokenService::participantToken(
            $this->apiKey,
            $this->apiSecret,
            'eck-health',
            'Health check',
            [
                'roomJoin' => true,
                'room' => 'eck-healthcheck',
                'canPublish' => false,
                'canSubscribe' => true,
            ],
            120
        );
        $url = $this->httpBase . '/rtc?access_token=' . rawurlencode($token) . '&auto_subscribe=1&sdk=js&protocol=16';
        $ch = curl_init($url);
        if ($ch === false) {
            return 'down';
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Connection: Upgrade',
                'Upgrade: websocket',
                'Sec-WebSocket-Version: 13',
                'Sec-WebSocket-Key: ' . base64_encode(random_bytes(16)),
                'Origin: ' . (function_exists('edexcel_public_app_url') ? rtrim(edexcel_public_app_url(), '/') : 'https://edexcel.college'),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            $errL = strtolower($err);
            if ($code === 101 || str_contains($errL, 'switching')) {
                return 'ws_ok';
            }
            return self::rtcProbeKind($code, $err);
        }
        return self::rtcProbeKind($code, (string)$raw);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listParticipants(string $room): array
    {
        $res = $this->call('ListParticipants', ['room' => $room]);
        $list = $res['participants'] ?? $res['Participants'] ?? [];
        return is_array($list) ? array_values($list) : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getParticipant(string $room, string $identity): ?array
    {
        try {
            $res = $this->call('GetParticipant', [
                'room' => $room,
                'identity' => $identity,
            ]);
            return is_array($res) && $res !== [] ? $res : null;
        } catch (Throwable $e) {
            error_log('GetParticipant: ' . $e->getMessage());
            return null;
        }
    }

    public function muteParticipantAudio(string $room, string $identity, bool $muted = true): void
    {
        $this->mutePublishedSources($room, $identity, ['MICROPHONE', 'SCREEN_SHARE_AUDIO'], $muted);
    }

    /**
     * @param list<string> $sources
     */
    public function mutePublishedSources(string $room, string $identity, array $sources, bool $muted = true): void
    {
        $matched = [];
        try {
            foreach ($this->listParticipants($room) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                if (self::identitiesMatch(self::participantIdentity($p), $identity)) {
                    $matched[] = $p;
                }
            }
        } catch (Throwable $e) {
            error_log('mutePublishedSources list: ' . $e->getMessage());
        }
        if ($matched === []) {
            $one = $this->getParticipant($room, $identity);
            if (is_array($one)) {
                $matched[] = $one;
            }
        }
        foreach ($matched as $p) {
            $liveIdentity = self::participantIdentity($p);
            if ($liveIdentity === '') {
                $liveIdentity = $identity;
            }
            $sids = self::matchingPublishedTrackSids($p, $sources);
            if ($sids === []) {
                $sids = self::fallbackAudioTrackSids($p, $sources);
            }
            foreach ($sids as $sid) {
                try {
                    $this->call(
                        'MutePublishedTrack',
                        self::mutePublishedTrackBody($room, $liveIdentity, $sid, $muted)
                    );
                } catch (Throwable $e) {
                    error_log('MutePublishedTrack: ' . $e->getMessage());
                }
            }
        }
    }

    public static function identitiesMatch(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        return $a !== '' && $b !== '' && strcasecmp($a, $b) === 0;
    }

    /**
     * LiveKit identity is always u{userId} for this campus.
     *
     * @param array<string,mixed> $participant
     */
    public static function participantIdentity(array $participant): string
    {
        foreach (['identity', 'Identity'] as $key) {
            $v = trim((string)($participant[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        foreach (['info', 'Info', 'participant', 'Participant'] as $wrap) {
            $inner = $participant[$wrap] ?? null;
            if (!is_array($inner)) {
                continue;
            }
            foreach (['identity', 'Identity'] as $key) {
                $v = trim((string)($inner[$key] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
        }
        return '';
    }

    /**
     * LiveKit Twirp JSON may use camelCase or proto names.
     *
     * @param array<string,mixed> $track
     */
    public static function trackSidFromInfo(array $track): string
    {
        foreach (['sid', 'trackSid', 'track_sid'] as $key) {
            $sid = trim((string)($track[$key] ?? ''));
            if ($sid !== '') {
                return $sid;
            }
        }
        return '';
    }

    /**
     * @param array<string,mixed> $participant
     * @return list<array<string,mixed>>
     */
    public static function participantTrackInfos(array $participant): array
    {
        $bags = [];
        foreach (['tracks', 'Tracks', 'trackInfos', 'track_infos'] as $key) {
            if (isset($participant[$key]) && is_array($participant[$key])) {
                $bags[] = $participant[$key];
            }
        }
        foreach (['info', 'Info', 'participant', 'Participant'] as $wrap) {
            $inner = $participant[$wrap] ?? null;
            if (!is_array($inner)) {
                continue;
            }
            foreach (['tracks', 'Tracks'] as $key) {
                if (isset($inner[$key]) && is_array($inner[$key])) {
                    $bags[] = $inner[$key];
                }
            }
        }
        $out = [];
        foreach ($bags as $tracks) {
            foreach ($tracks as $track) {
                if (!is_array($track)) {
                    continue;
                }
                if (isset($track['track']) && is_array($track['track'])) {
                    $track = $track['track'];
                }
                $out[] = $track;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $participant
     * @param list<string> $sources
     * @return list<string>
     */
    public static function matchingPublishedTrackSids(array $participant, array $sources): array
    {
        $want = [];
        foreach ($sources as $s) {
            $mapped = self::normalizeTrackSource($s);
            if ($mapped !== '' && $mapped !== 'UNKNOWN') {
                $want[$mapped] = true;
            }
        }
        if ($want === []) {
            return [];
        }
        $sids = [];
        foreach (self::participantTrackInfos($participant) as $track) {
            $sid = self::trackSidFromInfo($track);
            if ($sid === '') {
                continue;
            }
            $source = self::normalizeTrackSource(
                $track['source'] ?? $track['Source'] ?? '',
                $track['type'] ?? $track['Type'] ?? '',
                (string)($track['name'] ?? $track['Name'] ?? '')
            );
            if (!isset($want[$source])) {
                continue;
            }
            if (!in_array($sid, $sids, true)) {
                $sids[] = $sid;
            }
        }
        return $sids;
    }

    /**
     * If source matching finds nothing, still mute AUDIO tracks when MICROPHONE was requested.
     *
     * @param array<string,mixed> $participant
     * @param list<string> $sources
     * @return list<string>
     */
    public static function fallbackAudioTrackSids(array $participant, array $sources): array
    {
        $wantMic = false;
        foreach ($sources as $s) {
            $mapped = self::normalizeTrackSource($s);
            if ($mapped === 'MICROPHONE' || $mapped === 'SCREEN_SHARE_AUDIO') {
                $wantMic = true;
                break;
            }
        }
        if (!$wantMic) {
            return [];
        }
        $sids = [];
        foreach (self::participantTrackInfos($participant) as $track) {
            $sid = self::trackSidFromInfo($track);
            if ($sid === '') {
                continue;
            }
            $type = $track['type'] ?? $track['Type'] ?? '';
            $mime = strtolower((string)($track['mimeType'] ?? $track['mime_type'] ?? $track['MimeType'] ?? ''));
            $isAudio = false;
            if (is_int($type) || (is_string($type) && $type !== '' && ctype_digit(trim($type)))) {
                $isAudio = (int)$type === 0;
            } else {
                $typeKey = strtoupper(str_replace(['-', ' '], '_', trim((string)$type)));
                $isAudio = in_array($typeKey, ['AUDIO', 'TYPE_AUDIO'], true);
            }
            if (!$isAudio && str_contains($mime, 'audio')) {
                $isAudio = true;
            }
            if ($isAudio && !in_array($sid, $sids, true)) {
                $sids[] = $sid;
            }
        }
        return $sids;
    }

    /**
     * @return array{room:string,identity:string,trackSid:string,track_sid:string,muted:bool}
     */
    public static function mutePublishedTrackBody(string $room, string $identity, string $trackSid, bool $muted): array
    {
        return [
            'room' => $room,
            'identity' => $identity,
            'trackSid' => $trackSid,
            'track_sid' => $trackSid,
            'muted' => $muted,
        ];
    }

    public static function normalizeTrackSource(mixed $source, mixed $type = '', string $name = ''): string
    {
        $named = [
            'CAMERA' => 'CAMERA',
            'SOURCE_CAMERA' => 'CAMERA',
            'MICROPHONE' => 'MICROPHONE',
            'SOURCE_MICROPHONE' => 'MICROPHONE',
            'MIC' => 'MICROPHONE',
            'AUDIO' => 'MICROPHONE',
            'TYPE_AUDIO' => 'MICROPHONE',
            'SCREEN_SHARE' => 'SCREEN_SHARE',
            'SCREENSHARE' => 'SCREEN_SHARE',
            'SOURCE_SCREEN_SHARE' => 'SCREEN_SHARE',
            'SCREEN_SHARE_AUDIO' => 'SCREEN_SHARE_AUDIO',
            'SCREENSHAREAUDIO' => 'SCREEN_SHARE_AUDIO',
            'SOURCE_SCREEN_SHARE_AUDIO' => 'SCREEN_SHARE_AUDIO',
        ];
        if (is_int($source) || (is_string($source) && ctype_digit(trim($source)))) {
            $mapped = match ((int)$source) {
                1 => 'CAMERA',
                2 => 'MICROPHONE',
                3 => 'SCREEN_SHARE',
                4 => 'SCREEN_SHARE_AUDIO',
                default => '',
            };
            if ($mapped !== '') {
                return $mapped;
            }
        } else {
            $key = strtoupper(str_replace(['-', ' '], '_', trim((string)$source)));
            if (isset($named[$key])) {
                return $named[$key];
            }
        }
        $low = strtolower($name);
        if (str_contains($low, 'screen')) {
            return str_contains($low, 'audio') ? 'SCREEN_SHARE_AUDIO' : 'SCREEN_SHARE';
        }
        if (str_contains($low, 'mic')) {
            return 'MICROPHONE';
        }
        if (str_contains($low, 'camera')) {
            return 'CAMERA';
        }

        $typeKey = '';
        if (is_int($type) || (is_string($type) && $type !== '' && ctype_digit(trim($type)))) {
            $typeKey = match ((int)$type) {
                0 => 'AUDIO',
                1 => 'VIDEO',
                2 => 'DATA',
                default => strtoupper(trim((string)$type)),
            };
        } else {
            $typeKey = strtoupper(str_replace(['-', ' '], '_', trim((string)$type)));
        }
        if (in_array($typeKey, ['AUDIO', 'TYPE_AUDIO'], true)) {
            return 'MICROPHONE';
        }
        if (in_array($typeKey, ['VIDEO', 'TYPE_VIDEO'], true)) {
            return 'CAMERA';
        }
        return 'UNKNOWN';
    }

    /**
     * LiveKit 1.13.6 treats canPublishSources 0 / [] as UNKNOWN and may reject
     * UpdateParticipant. Omit the field when empty; never send 0.
     *
     * @param list<string|int>|null $sources null = all sources allowed
     * @return array<string,mixed>
     */
    public static function participantPermissionBody(?array $sources): array
    {
        $permission = [
            'canSubscribe' => true,
            'canPublish' => $sources === null || $sources !== [],
            'canPublishData' => true,
        ];
        if ($sources === null) {
            return $permission;
        }
        $ids = [];
        foreach ($sources as $s) {
            if (is_int($s) || (is_string($s) && ctype_digit(trim((string)$s)))) {
                $n = (int)$s;
            } else {
                $n = match (strtoupper(str_replace(['-', ' '], '_', (string)$s))) {
                    'CAMERA' => 1,
                    'MICROPHONE' => 2,
                    'SCREEN_SHARE' => 3,
                    'SCREEN_SHARE_AUDIO' => 4,
                    default => 0,
                };
            }
            if ($n >= 1 && $n <= 4 && !in_array($n, $ids, true)) {
                $ids[] = $n;
            }
        }
        if ($ids !== []) {
            $permission['canPublishSources'] = $ids;
        }
        return $permission;
    }

    /**
     * @param list<string>|null $sources null = all sources allowed
     */
    public function setParticipantPublishSources(string $room, string $identity, ?array $sources): void
    {
        try {
            $this->call('UpdateParticipant', [
                'room' => $room,
                'identity' => $identity,
                'permission' => self::participantPermissionBody($sources),
            ]);
        } catch (Throwable $e) {
            error_log('UpdateParticipant sources: ' . $e->getMessage());
        }
    }

    /**
     * @param list<string> $identities
     */
    public function sendData(string $room, string $payload, array $identities = []): void
    {
        $body = [
            'room' => $room,
            'data' => base64_encode($payload),
            'kind' => 'RELIABLE',
        ];
        if ($identities !== []) {
            $body['destinationIdentities'] = $identities;
            $body['destination_identities'] = $identities;
        }
        $this->call('SendData', $body);
    }

    public function removeParticipant(string $room, string $identity): void
    {
        $this->call('RemoveParticipant', ['room' => $room, 'identity' => $identity]);
    }

    /**
     * @param array<string,mixed> $permission
     */
    public function updatePublishPermission(string $room, string $identity, bool $canPublish): void
    {
        $this->call('UpdateParticipant', [
            'room' => $room,
            'identity' => $identity,
            'permission' => [
                'canSubscribe' => true,
                'canPublish' => $canPublish,
                'canPublishData' => true,
            ],
        ]);
    }

    public function deleteRoom(string $room): void
    {
        try {
            $this->call('DeleteRoom', ['room' => $room]);
        } catch (RuntimeException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'not found')) {
                throw $e;
            }
        }
    }

    /**
     * @param array<string,mixed>|\stdClass $body
     * @return array<string,mixed>
     */
    private function call(string $method, array|\stdClass $body): array
    {
        if ($this->httpBase === '') {
            throw new RuntimeException('LiveKit is not configured.');
        }
        $url = $this->httpBase . '/twirp/livekit.RoomService/' . $method;
        $token = LiveKitTokenService::serverToken($this->apiKey, $this->apiSecret);
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not contact the video service.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Could not reach LiveKit. ' . $err);
        }
        $json = json_decode((string)$raw, true);
        if ($code >= 400) {
            $msg = is_array($json) ? (string)($json['msg'] ?? $json['message'] ?? $raw) : (string)$raw;
            throw new RuntimeException('Video service: ' . $msg);
        }
        return is_array($json) ? $json : [];
    }
}
