<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * One Bunny Stream video library per teacher. Library IDs are unique.
 * Account API (api.bunny.net) creates libraries; Stream API (video.bunnycdn.com)
 * uploads with that library's AccessKey.
 */
final class TeacherBunnyLibraryService
{
    public function __construct(private PDO $pdo)
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/config/bunny.php';
        require_once $root . '/config/campus.php';
        if (function_exists('ensure_recordings_schema')) {
            ensure_recordings_schema($this->pdo);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function credentialsForTeacher(int $teacherId): ?array
    {
        if ($teacherId < 1 || !campus_column_exists($this->pdo, 'teachers', 'bunny_library_id')) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT id, name, bunny_library_id, bunny_api_key, bunny_token_key,
                   bunny_webhook_secret, bunny_cdn_hostname, bunny_library_name,
                   bunny_pull_zone_id, bunny_library_created_at
            FROM teachers
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$teacherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $libraryId = trim((string)($row['bunny_library_id'] ?? ''));
        $apiKey = trim((string)($row['bunny_api_key'] ?? ''));
        if ($libraryId === '' || $apiKey === '') {
            return null;
        }
        return $row;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function credentialsForLibraryId(string $libraryId): ?array
    {
        $libraryId = trim($libraryId);
        if ($libraryId === '' || !campus_column_exists($this->pdo, 'teachers', 'bunny_library_id')) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT id, name, bunny_library_id, bunny_api_key, bunny_token_key,
                   bunny_webhook_secret, bunny_cdn_hostname, bunny_library_name,
                   bunny_pull_zone_id, bunny_library_created_at
            FROM teachers
            WHERE bunny_library_id = ?
            LIMIT 1
        ");
        $stmt->execute([$libraryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function credentialsForStoredVideo(string $bunnyVideoId): ?array
    {
        $bunnyVideoId = trim($bunnyVideoId);
        if ($bunnyVideoId === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT bunny_library_id FROM class_recording_assets WHERE bunny_video_id = ? LIMIT 1');
        $stmt->execute([$bunnyVideoId]);
        $libraryId = trim((string)($stmt->fetchColumn() ?: ''));
        if ($libraryId === '') {
            $stmt = $this->pdo->prepare('SELECT bunny_library_id FROM teacher_video_library WHERE bunny_video_id = ? LIMIT 1');
            $stmt->execute([$bunnyVideoId]);
            $libraryId = trim((string)($stmt->fetchColumn() ?: ''));
        }
        if ($libraryId === '') {
            return null;
        }
        return $this->credentialsForLibraryId($libraryId);
    }

    /**
     * Merge teacher library secrets with global Bunny Stream endpoints/TTL.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function streamConfig(array $row): array
    {
        $global = bunny_config($this->pdo);
        $apiKey = trim((string)($row['bunny_api_key'] ?? ''));
        $tokenKey = trim((string)($row['bunny_token_key'] ?? ''));
        $webhookSecret = trim((string)($row['bunny_webhook_secret'] ?? ''));
        return array_merge($global, [
            'library_id' => trim((string)($row['bunny_library_id'] ?? '')),
            'api_key' => $apiKey,
            'cdn_hostname' => trim((string)($row['bunny_cdn_hostname'] ?? '')),
            'token_key' => $tokenKey !== '' ? $tokenKey : $apiKey,
            'webhook_secret' => $webhookSecret !== '' ? $webhookSecret : $apiKey,
        ]);
    }

    public function teacherIsConfigured(int $teacherId): bool
    {
        return $this->credentialsForTeacher($teacherId) !== null;
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function teachersMissingLibrary(): array
    {
        if (!campus_column_exists($this->pdo, 'teachers', 'bunny_library_id')) {
            return [];
        }
        $stmt = $this->pdo->query("
            SELECT id, name
            FROM teachers
            WHERE deleted_at IS NULL
              AND (bunny_library_id IS NULL OR bunny_library_id = '' OR bunny_api_key IS NULL OR bunny_api_key = '')
            ORDER BY name
            LIMIT 50
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function testAccountConnection(): array
    {
        $key = bunny_account_api_key($this->pdo);
        if ($key === '') {
            return ['ok' => false, 'message' => 'Account API key is required to create teacher libraries. Paste it under Bunny.net settings.'];
        }
        try {
            $this->accountRequest('GET', '/videolibrary?page=1&perPage=1');
            return ['ok' => true, 'message' => 'Bunny account API key accepted. Create a unique Stream library on each teacher’s Edit page.'];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a Stream library for this teacher via the Bunny account API.
     *
     * @return array{library_id:string}
     */
    public function createOnBunny(int $teacherId): array
    {
        $teacher = $this->teacherRow($teacherId);
        $existing = trim((string)($teacher['bunny_library_id'] ?? ''));
        if ($existing !== '' && trim((string)($teacher['bunny_api_key'] ?? '')) !== '') {
            throw new RuntimeException('This teacher already has Bunny library ID ' . $existing . '. Clear it on Update Teacher before creating a new library.');
        }
        if (bunny_account_api_key($this->pdo) === '') {
            throw new RuntimeException('Save the Bunny account API key under Admin → Settings → Bunny.net first.');
        }

        $name = self::libraryDisplayName((string)$teacher['name'], $teacherId);
        $created = $this->accountRequest('POST', '/videolibrary', ['Name' => $name]);
        $libraryId = trim((string)($created['Id'] ?? $created['id'] ?? ''));
        if ($libraryId === '') {
            throw new RuntimeException('Bunny did not return a video library ID.');
        }
        $this->assertLibraryIdUnique($libraryId, $teacherId);

        $webhookUrl = bunny_config($this->pdo)['webhook_url'];
        $updated = $created;
        try {
            $updated = $this->accountRequest('POST', '/videolibrary/' . rawurlencode($libraryId), [
                'Name' => $name,
                'WebhookUrl' => $webhookUrl,
                'EnableTokenAuthentication' => true,
                'EnableTokenIPVerification' => false,
                'AllowEarlyPlay' => false,
            ]);
        } catch (Throwable $e) {
            error_log('Bunny library webhook/token update failed for teacher ' . $teacherId);
        }

        $pullZoneId = trim((string)($updated['PullZoneId'] ?? $created['PullZoneId'] ?? ''));
        $cdn = self::cdnHostnameFromLibraryPayload(is_array($updated) ? $updated : []);
        if ($cdn === '' && $pullZoneId !== '') {
            try {
                $zone = $this->accountRequest('GET', '/pullzone/' . rawurlencode($pullZoneId));
                $cdn = self::cdnHostnameFromPullZonePayload($zone);
            } catch (Throwable $e) {
                $cdn = '';
            }
        }

        $apiKey = trim((string)($updated['ApiKey'] ?? $created['ApiKey'] ?? ''));
        $readOnly = trim((string)($updated['ReadOnlyApiKey'] ?? $created['ReadOnlyApiKey'] ?? ''));
        $tokenKey = self::tokenKeyFromLibraryPayload(is_array($updated) ? $updated : [], $apiKey);
        if ($apiKey === '') {
            throw new RuntimeException('Bunny created the library but did not return an AccessKey. Open the teacher page and paste the library credentials from the Bunny dashboard.');
        }

        $this->persist($teacherId, [
            'library_id' => $libraryId,
            'api_key' => $apiKey,
            'token_key' => $tokenKey,
            'webhook_secret' => $readOnly !== '' ? $readOnly : $apiKey,
            'cdn_hostname' => $cdn,
            'library_name' => $name,
            'pull_zone_id' => $pullZoneId,
            'created_at' => true,
        ]);

        return ['library_id' => $libraryId];
    }

    /**
     * Save pasted credentials. Blank secret fields keep the stored value.
     * Empty library ID unlinks this teacher from Bunny.
     */
    public function saveManual(int $teacherId, string $libraryId, string $apiKey, string $tokenKey, string $webhookSecret, string $cdnHostname): void
    {
        $this->teacherRow($teacherId);
        $libraryId = trim($libraryId);
        $cdnHostname = preg_replace('#^https?://#i', '', trim($cdnHostname)) ?? '';
        $cdnHostname = rtrim($cdnHostname, '/');

        if ($libraryId === '') {
            $this->persist($teacherId, [
                'library_id' => null,
                'api_key' => null,
                'token_key' => null,
                'webhook_secret' => null,
                'cdn_hostname' => null,
                'library_name' => null,
                'pull_zone_id' => null,
                'created_at' => false,
            ]);
            return;
        }

        $this->assertLibraryIdUnique($libraryId, $teacherId);
        $current = $this->teacherRow($teacherId);
        $keepApi = $apiKey !== '' ? $apiKey : trim((string)($current['bunny_api_key'] ?? ''));
        if ($keepApi === '') {
            throw new RuntimeException('A library AccessKey is required for this teacher.');
        }
        $keepToken = $tokenKey !== '' ? $tokenKey : trim((string)($current['bunny_token_key'] ?? ''));
        $keepWebhook = $webhookSecret !== '' ? $webhookSecret : trim((string)($current['bunny_webhook_secret'] ?? ''));
        $keepCdn = $cdnHostname !== '' ? $cdnHostname : trim((string)($current['bunny_cdn_hostname'] ?? ''));

        $this->persist($teacherId, [
            'library_id' => $libraryId,
            'api_key' => $keepApi,
            'token_key' => $keepToken !== '' ? $keepToken : $keepApi,
            'webhook_secret' => $keepWebhook !== '' ? $keepWebhook : $keepApi,
            'cdn_hostname' => $keepCdn,
            'library_name' => trim((string)($current['bunny_library_name'] ?? '')) ?: null,
            'pull_zone_id' => trim((string)($current['bunny_pull_zone_id'] ?? '')) ?: null,
            'created_at' => false,
        ]);
    }

    public static function libraryDisplayName(string $teacherName, int $teacherId): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $teacherName) ?? '');
        if ($name === '') {
            $name = 'Teacher';
        }
        $label = 'Edexcel Kandy — ' . $name . ' (#' . $teacherId . ')';
        if (strlen($label) > 150) {
            $label = substr($label, 0, 147) . '...';
        }
        return $label;
    }

    /**
     * @param array<string,mixed> $library
     */
    public static function cdnHostnameFromLibraryPayload(array $library): string
    {
        foreach (['CdnHostname', 'cdnHostname', 'Hostname', 'PlayerHostname'] as $key) {
            $value = self::hostFromString((string)($library[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        $zone = $library['PullZone'] ?? null;
        if (is_array($zone)) {
            return self::cdnHostnameFromPullZonePayload($zone);
        }
        return '';
    }

    /**
     * @param array<string,mixed> $zone
     */
    public static function cdnHostnameFromPullZonePayload(array $zone): string
    {
        $hosts = $zone['Hostnames'] ?? $zone['hostnames'] ?? [];
        if (is_array($hosts)) {
            foreach ($hosts as $host) {
                if (is_string($host)) {
                    $value = self::hostFromString($host);
                    if ($value !== '') {
                        return $value;
                    }
                }
                if (is_array($host)) {
                    foreach (['Value', 'Hostname', 'Name'] as $key) {
                        $value = self::hostFromString((string)($host[$key] ?? ''));
                        if ($value !== '') {
                            return $value;
                        }
                    }
                }
            }
        }
        foreach (['CdnHostname', 'Hostname', 'Name'] as $key) {
            $value = self::hostFromString((string)($zone[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * @param array<string,mixed> $library
     */
    public static function tokenKeyFromLibraryPayload(array $library, string $fallbackApiKey): string
    {
        foreach (['TokenAuthenticationKey', 'tokenAuthenticationKey', 'EmbedTokenKey'] as $key) {
            $value = trim((string)($library[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return $fallbackApiKey;
    }

    /**
     * @return array<string,mixed>
     */
    private function teacherRow(int $teacherId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teachers WHERE id = ? LIMIT 1');
        $stmt->execute([$teacherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Teacher not found.');
        }
        return $row;
    }

    private function assertLibraryIdUnique(string $libraryId, int $excludeTeacherId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name FROM teachers
            WHERE bunny_library_id = ? AND id <> ?
            LIMIT 1
        ");
        $stmt->execute([$libraryId, $excludeTeacherId]);
        $other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($other) {
            throw new RuntimeException(
                'Bunny library ID ' . $libraryId . ' is already assigned to ' . (string)$other['name'] . '. Each teacher must have a different library.'
            );
        }
    }

    /**
     * @param array{
     *   library_id:?string,
     *   api_key:?string,
     *   token_key:?string,
     *   webhook_secret:?string,
     *   cdn_hostname:?string,
     *   library_name:?string,
     *   pull_zone_id:?string,
     *   created_at:bool
     * } $data
     */
    private function persist(int $teacherId, array $data): void
    {
        if ($data['created_at']) {
            $sql = "
                UPDATE teachers
                SET bunny_library_id = ?,
                    bunny_api_key = ?,
                    bunny_token_key = ?,
                    bunny_webhook_secret = ?,
                    bunny_cdn_hostname = ?,
                    bunny_library_name = ?,
                    bunny_pull_zone_id = ?,
                    bunny_library_created_at = COALESCE(bunny_library_created_at, NOW())
                WHERE id = ?
            ";
            $this->pdo->prepare($sql)->execute([
                $data['library_id'],
                $data['api_key'],
                $data['token_key'],
                $data['webhook_secret'],
                $data['cdn_hostname'] !== '' ? $data['cdn_hostname'] : null,
                $data['library_name'],
                $data['pull_zone_id'] !== '' ? $data['pull_zone_id'] : null,
                $teacherId,
            ]);
            return;
        }
        $sql = "
            UPDATE teachers
            SET bunny_library_id = ?,
                bunny_api_key = ?,
                bunny_token_key = ?,
                bunny_webhook_secret = ?,
                bunny_cdn_hostname = ?,
                bunny_library_name = ?,
                bunny_pull_zone_id = ?,
                bunny_library_created_at = CASE WHEN ? IS NULL THEN NULL ELSE bunny_library_created_at END
            WHERE id = ?
        ";
        $this->pdo->prepare($sql)->execute([
            $data['library_id'],
            $data['api_key'],
            $data['token_key'],
            $data['webhook_secret'],
            $data['cdn_hostname'] !== '' ? $data['cdn_hostname'] : null,
            $data['library_name'],
            $data['pull_zone_id'] !== '' ? $data['pull_zone_id'] : null,
            $data['library_id'],
            $teacherId,
        ]);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private function accountRequest(string $method, string $path, ?array $payload = null): array
    {
        $key = bunny_account_api_key($this->pdo);
        if ($key === '') {
            throw new RuntimeException('Bunny account API key is not configured.');
        }
        $url = 'https://api.bunny.net' . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to start Bunny account request.');
        }
        $headers = [
            'AccessKey: ' . $key,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Bunny account API connection failed.');
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($http === 401 || $http === 403) {
            throw new RuntimeException('Bunny rejected the account API key. Use the account AccessKey from the bunny.net dashboard, not a Stream library key.');
        }
        if ($http >= 400) {
            $msg = is_array($decoded) ? (string)($decoded['Message'] ?? $decoded['title'] ?? $raw) : (string)$raw;
            if (strlen($msg) > 240) {
                $msg = substr($msg, 0, 240);
            }
            throw new RuntimeException('Bunny account API HTTP ' . $http . ($msg !== '' ? ': ' . $msg : ''));
        }
        return is_array($decoded) ? $decoded : ['raw' => $raw];
    }

    private static function hostFromString(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('#^https?://#i', '', $value) ?? $value;
        $value = rtrim($value, '/');
        if (str_contains($value, '/')) {
            $value = explode('/', $value, 2)[0];
        }
        return $value;
    }
}
