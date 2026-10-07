<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * SiteVisitorAnalyticsService
 * 
 * Secure, privacy-first website analytics and visitor tracking service.
 * Respects user privacy: IP addresses are hashed/anonymized, sensitive URL
 * parameters are stripped, and no credentials or personal data are recorded.
 */
class SiteVisitorAnalyticsService
{
    private const IP_SALT = 'edexcel_visitor_analytics_salt_v1';

    /**
     * Map of ISO country codes to English names.
     */
    public const COUNTRY_NAMES = [
        'LK' => 'Sri Lanka',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'AE' => 'United Arab Emirates',
        'QA' => 'Qatar',
        'OM' => 'Oman',
        'KW' => 'Kuwait',
        'SA' => 'Saudi Arabia',
        'IN' => 'India',
        'IT' => 'Italy',
        'MV' => 'Maldives',
        'AU' => 'Australia',
        'CA' => 'Canada',
        'DE' => 'Germany',
        'FR' => 'France',
        'SG' => 'Singapore',
        'MY' => 'Malaysia',
        'NZ' => 'New Zealand',
        'JP' => 'Japan',
        'CN' => 'China',
        'PK' => 'Pakistan',
        'BD' => 'Bangladesh',
        'NP' => 'Nepal',
        'ZA' => 'South Africa',
    ];

    /**
     * Ensure database schema exists (runtime self-healing for MySQL & SQLite).
     */
    public static function ensureSchema(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS site_visitor_sessions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    visitor_id TEXT NOT NULL,
                    session_id TEXT NOT NULL UNIQUE,
                    user_id INTEGER NULL,
                    user_type TEXT NULL,
                    ip_hash TEXT NULL,
                    country_code TEXT NULL,
                    country_name TEXT NULL,
                    city TEXT NULL,
                    device_type TEXT NOT NULL DEFAULT 'desktop',
                    os TEXT NULL,
                    browser TEXT NULL,
                    referrer_url TEXT NULL,
                    referrer_host TEXT NULL,
                    traffic_source TEXT NOT NULL DEFAULT 'direct',
                    entry_page TEXT NOT NULL,
                    exit_page TEXT NULL,
                    pageviews_count INTEGER NOT NULL DEFAULT 1,
                    duration_seconds INTEGER NOT NULL DEFAULT 0,
                    is_bounce INTEGER NOT NULL DEFAULT 1,
                    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_sv_visitor ON site_visitor_sessions(visitor_id);
                CREATE INDEX IF NOT EXISTS idx_sv_user ON site_visitor_sessions(user_id);
                CREATE INDEX IF NOT EXISTS idx_sv_first_seen ON site_visitor_sessions(first_seen_at);
                CREATE INDEX IF NOT EXISTS idx_sv_last_seen ON site_visitor_sessions(last_seen_at);
                CREATE INDEX IF NOT EXISTS idx_sv_source ON site_visitor_sessions(traffic_source);
                CREATE INDEX IF NOT EXISTS idx_sv_device ON site_visitor_sessions(device_type);
                CREATE INDEX IF NOT EXISTS idx_sv_country ON site_visitor_sessions(country_code);

                CREATE TABLE IF NOT EXISTS site_visitor_pageviews (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL,
                    visitor_id TEXT NOT NULL,
                    user_id INTEGER NULL,
                    user_type TEXT NULL,
                    page_url TEXT NOT NULL,
                    page_path TEXT NOT NULL,
                    page_title TEXT NULL,
                    referrer_url TEXT NULL,
                    duration_seconds INTEGER NOT NULL DEFAULT 0,
                    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_sp_session ON site_visitor_pageviews(session_id);
                CREATE INDEX IF NOT EXISTS idx_sp_visitor ON site_visitor_pageviews(visitor_id);
                CREATE INDEX IF NOT EXISTS idx_sp_user ON site_visitor_pageviews(user_id);
                CREATE INDEX IF NOT EXISTS idx_sp_page_path ON site_visitor_pageviews(page_path);
                CREATE INDEX IF NOT EXISTS idx_sp_viewed_at ON site_visitor_pageviews(viewed_at);
            ");
            return;
        }

        // MySQL / MariaDB
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_visitor_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                visitor_id VARCHAR(64) NOT NULL,
                session_id VARCHAR(64) NOT NULL,
                user_id INT NULL,
                user_type VARCHAR(20) NULL,
                ip_hash VARCHAR(64) NULL,
                country_code VARCHAR(8) NULL,
                country_name VARCHAR(64) NULL,
                city VARCHAR(64) NULL,
                device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
                os VARCHAR(50) NULL,
                browser VARCHAR(50) NULL,
                referrer_url VARCHAR(1000) NULL,
                referrer_host VARCHAR(255) NULL,
                traffic_source VARCHAR(50) NOT NULL DEFAULT 'direct',
                entry_page VARCHAR(500) NOT NULL,
                exit_page VARCHAR(500) NULL,
                pageviews_count INT UNSIGNED NOT NULL DEFAULT 1,
                duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                is_bounce TINYINT(1) NOT NULL DEFAULT 1,
                first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_site_visitor_session (session_id),
                KEY idx_sv_visitor (visitor_id),
                KEY idx_sv_user (user_id),
                KEY idx_sv_first_seen (first_seen_at),
                KEY idx_sv_last_seen (last_seen_at),
                KEY idx_sv_source (traffic_source),
                KEY idx_sv_device (device_type),
                KEY idx_sv_country (country_code),
                KEY idx_sv_entry_page (entry_page(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_visitor_pageviews (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                session_id VARCHAR(64) NOT NULL,
                visitor_id VARCHAR(64) NOT NULL,
                user_id INT NULL,
                user_type VARCHAR(20) NULL,
                page_url VARCHAR(500) NOT NULL,
                page_path VARCHAR(255) NOT NULL,
                page_title VARCHAR(255) NULL,
                referrer_url VARCHAR(1000) NULL,
                duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sp_session (session_id),
                KEY idx_sp_visitor (visitor_id),
                KEY idx_sp_user (user_id),
                KEY idx_sp_page_path (page_path),
                KEY idx_sp_viewed_at (viewed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Self-healing: ensure user_id column exists if table was created in older migration
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM site_visitor_sessions LIKE 'user_id'")->fetch();
            if (!$checkCol) {
                $pdo->exec("ALTER TABLE site_visitor_sessions ADD COLUMN user_id INT NULL AFTER session_id, ADD INDEX idx_sv_user (user_id)");
            }
            $checkType = $pdo->query("SHOW COLUMNS FROM site_visitor_sessions LIKE 'user_type'")->fetch();
            if (!$checkType) {
                $pdo->exec("ALTER TABLE site_visitor_sessions ADD COLUMN user_type VARCHAR(20) NULL AFTER user_id");
            }
            $checkPvUser = $pdo->query("SHOW COLUMNS FROM site_visitor_pageviews LIKE 'user_id'")->fetch();
            if (!$checkPvUser) {
                $pdo->exec("ALTER TABLE site_visitor_pageviews ADD COLUMN user_id INT NULL AFTER visitor_id, ADD INDEX idx_sp_user (user_id)");
            }
            $checkPvType = $pdo->query("SHOW COLUMNS FROM site_visitor_pageviews LIKE 'user_type'")->fetch();
            if (!$checkPvType) {
                $pdo->exec("ALTER TABLE site_visitor_pageviews ADD COLUMN user_type VARCHAR(20) NULL AFTER user_id");
            }
        } catch (Throwable) {}
    }

    /**
     * Anonymize IP address with SHA-256 and salt.
     */
    public static function anonymizeIp(?string $ip): string
    {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return '';
        }
        return substr(hash('sha256', $ip . self::IP_SALT), 0, 32);
    }

    /**
     * Clean and sanitize URL by removing sensitive query parameters.
     */
    public static function cleanUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '/';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return '/';
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $cleanQuery = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
            $sensitiveKeys = [
                'token', 'password', 'pwd', 'pass', 'otp', 'code', 'secret',
                'key', 'api_key', 'auth', 'hash', 'signature', 'session',
                'csrf_token', 'admin_key'
            ];
            foreach ($sensitiveKeys as $k) {
                unset($params[$k]);
            }
            if (!empty($params)) {
                $cleanQuery = '?' . http_build_query($params);
            }
        }

        return $path . $cleanQuery;
    }

    /**
     * Extract clean path without query string.
     */
    public static function cleanPath(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        return $path !== '' ? $path : '/';
    }

    /**
     * Parse User-Agent string to detect Device, OS, Browser, and Bots.
     *
     * @return array{device_type: string, os: string, browser: string, is_bot: bool}
     */
    public static function parseUserAgent(?string $ua): array
    {
        $ua = trim((string)$ua);
        if ($ua === '') {
            return [
                'device_type' => 'desktop',
                'os' => 'Unknown',
                'browser' => 'Unknown',
                'is_bot' => false,
            ];
        }

        $uaLower = strtolower($ua);

        // Bot / Crawler detection
        $isBot = (bool)preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|googlebot|bingbot|yandex|baidu|semrush|ahrefs|petalbot|curl|wget/i',
            $ua
        );

        // Device detection
        $device = 'desktop';
        if (preg_match('/ipad|tablet|(android(?!.*mobi))/i', $uaLower)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android.*mobile|blackberry|iemobile|opera mini/i', $uaLower)) {
            $device = 'mobile';
        }

        // Operating System detection
        $os = 'Unknown';
        if (str_contains($uaLower, 'windows nt 10.0') || str_contains($uaLower, 'windows nt 11.0')) {
            $os = 'Windows 10/11';
        } elseif (str_contains($uaLower, 'windows nt 6.3')) {
            $os = 'Windows 8.1';
        } elseif (str_contains($uaLower, 'windows nt 6.1')) {
            $os = 'Windows 7';
        } elseif (str_contains($uaLower, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ipod')) {
            $os = 'iOS';
        } elseif (str_contains($uaLower, 'macintosh') || str_contains($uaLower, 'mac os x')) {
            $os = 'macOS';
        } elseif (str_contains($uaLower, 'android')) {
            $os = 'Android';
        } elseif (str_contains($uaLower, 'linux')) {
            $os = 'Linux';
        } elseif (str_contains($uaLower, 'cros')) {
            $os = 'Chrome OS';
        }

        // Browser detection
        $browser = 'Unknown';
        if (str_contains($uaLower, 'edg/') || str_contains($uaLower, 'edge/')) {
            $browser = 'Edge';
        } elseif (str_contains($uaLower, 'samsungbrowser')) {
            $browser = 'Samsung Internet';
        } elseif (str_contains($uaLower, 'opr/') || str_contains($uaLower, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($uaLower, 'chrome/') || str_contains($uaLower, 'crios/')) {
            $browser = 'Chrome';
        } elseif (str_contains($uaLower, 'firefox/') || str_contains($uaLower, 'fxios/')) {
            $browser = 'Firefox';
        } elseif (str_contains($uaLower, 'safari/') && !str_contains($uaLower, 'chrome/')) {
            $browser = 'Safari';
        }

        return [
            'device_type' => $device,
            'os' => $os,
            'browser' => $browser,
            'is_bot' => $isBot,
        ];
    }

    /**
     * Categorize traffic source based on referrer and landing page query params.
     *
     * @return array{source: string, host: string}
     */
    public static function parseTrafficSource(?string $referrerUrl, ?string $landingUrl = null): array
    {
        $referrerUrl = trim((string)$referrerUrl);
        $landingUrl = trim((string)$landingUrl);

        // Check UTM parameters on landing URL first
        if ($landingUrl !== '') {
            $query = parse_url($landingUrl, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                $utmSource = strtolower(trim((string)($params['utm_source'] ?? '')));
                $utmMedium = strtolower(trim((string)($params['utm_medium'] ?? '')));

                if (in_array($utmSource, ['facebook', 'instagram', 'twitter', 'x', 'linkedin', 'whatsapp', 'youtube', 'tiktok', 'social'], true) || $utmMedium === 'social') {
                    return ['source' => 'social', 'host' => $utmSource];
                }
                if ($utmSource === 'google' || str_contains($utmSource, 'google')) {
                    return ['source' => 'google', 'host' => 'google'];
                }
                if (in_array($utmMedium, ['cpc', 'ppc', 'paid', 'ad'], true)) {
                    return ['source' => 'campaign', 'host' => $utmSource ?: 'ad'];
                }
            }
        }

        if ($referrerUrl === '') {
            return ['source' => 'direct', 'host' => 'direct'];
        }

        $host = strtolower((string)parse_url($referrerUrl, PHP_URL_HOST));
        if ($host === '') {
            return ['source' => 'direct', 'host' => 'direct'];
        }

        // Internal referrer check
        $siteHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'edexcel.college'));
        if ($host === $siteHost || str_ends_with($host, '.' . $siteHost)) {
            return ['source' => 'direct', 'host' => 'internal'];
        }

        // Google search
        if (str_contains($host, 'google.')) {
            return ['source' => 'google', 'host' => 'google'];
        }

        // Other Search Engines
        if (str_contains($host, 'bing.') || str_contains($host, 'yahoo.') || str_contains($host, 'duckduckgo.')
            || str_contains($host, 'baidu.') || str_contains($host, 'yandex.') || str_contains($host, 'ecosia.')) {
            return ['source' => 'search', 'host' => $host];
        }

        // Social Media
        if (str_contains($host, 'facebook.') || str_contains($host, 'fb.com') || str_contains($host, 'instagram.')
            || str_contains($host, 'whatsapp.') || str_contains($host, 'twitter.') || str_contains($host, 't.co')
            || str_contains($host, 'x.com') || str_contains($host, 'linkedin.') || str_contains($host, 'youtube.')
            || str_contains($host, 'tiktok.') || str_contains($host, 'pinterest.') || str_contains($host, 'reddit.')) {
            return ['source' => 'social', 'host' => $host];
        }

        return ['source' => 'referral', 'host' => $host];
    }

    /**
     * Get English country name from ISO code.
     */
    public static function countryNameFromCode(?string $code): string
    {
        $code = strtoupper(trim((string)$code));
        return self::COUNTRY_NAMES[$code] ?? ($code !== '' ? $code : 'Unknown');
    }

    /**
     * Get country flag emoji from ISO code.
     */
    public static function countryFlagEmoji(?string $code): string
    {
        $code = strtoupper(trim((string)$code));
        if (strlen($code) !== 2 || !ctype_alpha($code)) {
            return '🌐';
        }
        $c1 = ord($code[0]) - 65 + 0x1F1E6;
        $c2 = ord($code[1]) - 65 + 0x1F1E6;
        return mb_chr($c1, 'UTF-8') . mb_chr($c2, 'UTF-8');
    }

    /**
     * Record a pageview and update or insert visitor session.
     *
     * @param array<string,mixed> $payload
     * @return array{ok: bool, session_id: string, is_new: bool}
     */
    public static function recordVisit(PDO $pdo, array $payload): array
    {
        self::ensureSchema($pdo);

        $sessionId = trim((string)($payload['session_id'] ?? ''));
        if ($sessionId === '' || !preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $sessionId)) {
            $sessionId = bin2hex(random_bytes(16));
        }

        $visitorId = trim((string)($payload['visitor_id'] ?? ''));
        if ($visitorId === '' || !preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $visitorId)) {
            $visitorId = bin2hex(random_bytes(16));
        }

        $rawUrl = (string)($payload['page_url'] ?? '/');
        $pageUrl = self::cleanUrl($rawUrl);
        $pagePath = self::cleanPath($pageUrl);
        $pageTitle = mb_substr(trim((string)($payload['page_title'] ?? '')), 0, 250);
        $referrerUrl = trim((string)($payload['referrer_url'] ?? ''));
        $userAgent = trim((string)($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        $clientIp = class_exists(GeoIpService::class) ? GeoIpService::clientIp() : '';

        $parsedUa = self::parseUserAgent($userAgent);
        $sourceData = self::parseTrafficSource($referrerUrl, $rawUrl);
        $ipHash = self::anonymizeIp($clientIp);

        // Country & City detection
        $countryCode = '';
        $cfTrusted = function_exists('eck_proxy_is_trusted') && eck_proxy_is_trusted();
        if ($countryCode === '' && $cfTrusted && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $cf = strtoupper(trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']));
            if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
                $countryCode = $cf;
            }
        }
        if ($countryCode === '' && class_exists(GeoIpService::class)) {
            $countryCode = GeoIpService::countryCode();
        }
        if ($countryCode === '') {
            $countryCode = 'LK'; // Default for local
        }
        $countryName = self::countryNameFromCode($countryCode);

        $city = trim((string)($payload['city'] ?? ($_SERVER['HTTP_CF_IPCITY'] ?? '')));
        if ($city === '' && $countryCode === 'LK') {
            $city = 'Kandy';
        }

        $now = date('Y-m-d H:i:s');
        $isNew = false;
        $sessionRotated = false;

        $userId = isset($payload['user_id']) && is_numeric($payload['user_id']) && (int)$payload['user_id'] > 0
            ? (int)$payload['user_id']
            : null;
        $userType = isset($payload['user_type']) ? strtolower(trim((string)$payload['user_type'])) : null;
        if ($userType === '') {
            $userType = null;
        }

        // Check if session exists
        $checkStmt = $pdo->prepare("SELECT id, pageviews_count, entry_page, user_id, user_type FROM site_visitor_sessions WHERE session_id = ? LIMIT 1");
        $checkStmt->execute([$sessionId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $existingUserId = !empty($existing['user_id']) ? (int)$existing['user_id'] : null;
            $existingUserType = !empty($existing['user_type']) ? strtolower(trim((string)$existing['user_type'])) : null;

            // Session forking / rotation on logout or user switch:
            // If the existing session was tied to a registered user, but current visit is a guest (user logged out),
            // or if a different user logged in on this browser:
            // Fork a new session so the previous user's session record is preserved and not contaminated!
            if (($existingUserId !== null && $userId === null) ||
                ($existingUserId !== null && $userId !== null && ($existingUserId !== $userId || $existingUserType !== $userType))) {
                $sessionId = bin2hex(random_bytes(16));
                $existing = false;
                $sessionRotated = true;
            }
        }

        if ($existing) {
            // Update session
            if ($userId !== null) {
                $upStmt = $pdo->prepare("
                    UPDATE site_visitor_sessions
                    SET last_seen_at = ?,
                        exit_page = ?,
                        pageviews_count = pageviews_count + 1,
                        is_bounce = 0,
                        user_id = ?,
                        user_type = ?
                    WHERE session_id = ?
                ");
                $upStmt->execute([$now, $pagePath, $userId, $userType, $sessionId]);
            } else {
                $upStmt = $pdo->prepare("
                    UPDATE site_visitor_sessions
                    SET last_seen_at = ?,
                        exit_page = ?,
                        pageviews_count = pageviews_count + 1,
                        is_bounce = 0
                    WHERE session_id = ?
                ");
                $upStmt->execute([$now, $pagePath, $sessionId]);
            }
        } else {
            $isNew = true;
            // Insert new session
            $insStmt = $pdo->prepare("
                INSERT INTO site_visitor_sessions (
                    visitor_id, session_id, user_id, user_type, ip_hash, country_code, country_name, city,
                    device_type, os, browser, referrer_url, referrer_host, traffic_source,
                    entry_page, exit_page, pageviews_count, duration_seconds, is_bounce,
                    first_seen_at, last_seen_at, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, 1, 0, 1,
                    ?, ?, ?
                )
            ");
            $insStmt->execute([
                $visitorId,
                $sessionId,
                $userId,
                $userType,
                $ipHash,
                $countryCode,
                $countryName,
                $city ?: null,
                $parsedUa['device_type'],
                $parsedUa['os'],
                $parsedUa['browser'],
                mb_substr($referrerUrl, 0, 1000),
                $sourceData['host'] ?: null,
                $sourceData['source'],
                $pagePath,
                $pagePath,
                $now,
                $now,
                $now,
            ]);
        }

        // Insert Pageview
        $pvStmt = $pdo->prepare("
            INSERT INTO site_visitor_pageviews (
                session_id, visitor_id, user_id, user_type, page_url, page_path, page_title, referrer_url, duration_seconds, viewed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ");
        $pvStmt->execute([
            $sessionId,
            $visitorId,
            $userId,
            $userType,
            $pageUrl,
            $pagePath,
            $pageTitle ?: null,
            mb_substr($referrerUrl, 0, 1000),
            $now,
        ]);

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'is_new' => $isNew,
            'session_rotated' => $sessionRotated,
        ];
    }

    /**
     * Heartbeat / duration ping to update session duration and exit page.
     */
    public static function updateHeartbeat(PDO $pdo, string $sessionId, int $durationSeconds, ?string $exitPage = null, ?int $userId = null, ?string $userType = null): bool
    {
        self::ensureSchema($pdo);

        $sessionId = trim($sessionId);
        if ($sessionId === '' || $durationSeconds < 0) {
            return false;
        }

        // Cap reasonable duration at 24 hours (86400 seconds)
        $durationSeconds = min(86400, $durationSeconds);
        $now = date('Y-m-d H:i:s');

        $exitPath = ($exitPage !== null && $exitPage !== '') ? self::cleanPath($exitPage) : null;

        if ($userId !== null && $userId > 0) {
            $userType = $userType ? strtolower(trim($userType)) : null;
            $stmt = $pdo->prepare("
                UPDATE site_visitor_sessions
                SET duration_seconds = CASE WHEN ? > duration_seconds THEN ? ELSE duration_seconds END,
                    last_seen_at = ?,
                    exit_page = COALESCE(?, exit_page),
                    user_id = COALESCE(user_id, ?),
                    user_type = COALESCE(user_type, ?)
                WHERE session_id = ?
            ");
            return $stmt->execute([$durationSeconds, $durationSeconds, $now, $exitPath, $userId, $userType, $sessionId]);
        }

        if ($exitPath !== null) {
            $stmt = $pdo->prepare("
                UPDATE site_visitor_sessions
                SET duration_seconds = CASE WHEN ? > duration_seconds THEN ? ELSE duration_seconds END,
                    last_seen_at = ?,
                    exit_page = ?
                WHERE session_id = ?
            ");
            return $stmt->execute([$durationSeconds, $durationSeconds, $now, $exitPath, $sessionId]);
        }

        $stmt = $pdo->prepare("
            UPDATE site_visitor_sessions
            SET duration_seconds = CASE WHEN ? > duration_seconds THEN ? ELSE duration_seconds END,
                last_seen_at = ?
            WHERE session_id = ?
        ");
        return $stmt->execute([$durationSeconds, $durationSeconds, $now, $sessionId]);
    }

    /**
     * Count real-time active visitors within the last N minutes.
     */
    public static function getRealtimeActiveCount(PDO $pdo, int $windowMinutes = 5): int
    {
        self::ensureSchema($pdo);
        $windowMinutes = max(1, min(60, $windowMinutes));
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT visitor_id)
                FROM site_visitor_sessions
                WHERE datetime(last_seen_at) >= datetime('now', '-' || ? || ' minutes')
            ");
            $stmt->execute([$windowMinutes]);
            return (int)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT visitor_id)
            FROM site_visitor_sessions
            WHERE last_seen_at >= NOW() - INTERVAL ? MINUTE
        ");
        $stmt->execute([$windowMinutes]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Compute overview KPIs and comparisons with the previous period.
     *
     * @return array{
     *   from: string,
     *   to: string,
     *   prev_from: string,
     *   prev_to: string,
     *   days: int,
     *   current: array{visits: int, unique_visitors: int, pageviews: int, avg_duration: int, bounce_rate: float},
     *   previous: array{visits: int, unique_visitors: int, pageviews: int, avg_duration: int, bounce_rate: float},
     *   change: array{visits: float, unique_visitors: float, pageviews: float, avg_duration: float, bounce_rate: float}
     * }
     */
    public static function getDashboardStats(PDO $pdo, string $fromDate, string $toDate): array
    {
        self::ensureSchema($pdo);

        $fromDt = new \DateTime($fromDate . ' 00:00:00');
        $toDt = new \DateTime($toDate . ' 23:59:59');

        $days = max(1, (int)$fromDt->diff($toDt)->format('%a') + 1);

        // Preceding period of the exact same length
        $prevToDt = clone $fromDt;
        $prevToDt->modify('-1 second');
        $prevFromDt = clone $prevToDt;
        $prevFromDt->modify('-' . ($days - 1) . ' days');
        $prevFromDt->setTime(0, 0, 0);

        $currFromStr = $fromDt->format('Y-m-d H:i:s');
        $currToStr = $toDt->format('Y-m-d H:i:s');
        $prevFromStr = $prevFromDt->format('Y-m-d H:i:s');
        $prevToStr = $prevToDt->format('Y-m-d H:i:s');

        $fetchPeriod = static function (string $start, string $end) use ($pdo): array {
            // Sessions stats
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT visitor_id) as unique_visitors,
                    COALESCE(AVG(duration_seconds), 0) as avg_duration,
                    COALESCE(SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END), 0) as bounces
                FROM site_visitor_sessions
                WHERE first_seen_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start, $end]);
            $sData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Pageviews count
            $pvStmt = $pdo->prepare("
                SELECT COUNT(*) as total_pageviews
                FROM site_visitor_pageviews
                WHERE viewed_at BETWEEN ? AND ?
            ");
            $pvStmt->execute([$start, $end]);
            $pvCount = (int)$pvStmt->fetchColumn();

            $visits = (int)($sData['total_visits'] ?? 0);
            $unique = (int)($sData['unique_visitors'] ?? 0);
            $avgDur = (int)round((float)($sData['avg_duration'] ?? 0));
            $bounces = (int)($sData['bounces'] ?? 0);
            $bounceRate = $visits > 0 ? round(($bounces / $visits) * 100, 1) : 0.0;

            return [
                'visits' => $visits,
                'unique_visitors' => $unique,
                'pageviews' => $pvCount,
                'avg_duration' => $avgDur,
                'bounce_rate' => $bounceRate,
            ];
        };

        $current = $fetchPeriod($currFromStr, $currToStr);
        $previous = $fetchPeriod($prevFromStr, $prevToStr);

        // Compute percentage changes
        $calcChange = static function (float|int $curr, float|int $prev): float {
            if ($prev == 0) {
                return $curr > 0 ? 100.0 : 0.0;
            }
            return round((($curr - $prev) / (float)$prev) * 100, 1);
        };

        $change = [
            'visits' => $calcChange($current['visits'], $previous['visits']),
            'unique_visitors' => $calcChange($current['unique_visitors'], $previous['unique_visitors']),
            'pageviews' => $calcChange($current['pageviews'], $previous['pageviews']),
            'avg_duration' => $calcChange($current['avg_duration'], $previous['avg_duration']),
            'bounce_rate' => round($current['bounce_rate'] - $previous['bounce_rate'], 1), // percentage point diff
        ];

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'prev_from' => $prevFromDt->format('Y-m-d'),
            'prev_to' => $prevToDt->format('Y-m-d'),
            'days' => $days,
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
        ];
    }

    /**
     * Timeline chart data (Visits and Pageviews over time).
     *
     * @return array{labels: list<string>, visits: list<int>, pageviews: list<int>}
     */
    public static function getTimeline(PDO $pdo, string $fromDate, string $toDate, string $interval = 'day'): array
    {
        self::ensureSchema($pdo);

        $fromDt = new \DateTime($fromDate . ' 00:00:00');
        $toDt = new \DateTime($toDate . ' 23:59:59');
        $currFromStr = $fromDt->format('Y-m-d H:i:s');
        $currToStr = $toDt->format('Y-m-d H:i:s');

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sessStmt = $pdo->prepare("
                SELECT strftime('%Y-%m-%d', first_seen_at) as date_key, COUNT(*) as cnt
                FROM site_visitor_sessions
                WHERE first_seen_at BETWEEN ? AND ?
                GROUP BY date_key
                ORDER BY date_key ASC
            ");
            $sessStmt->execute([$currFromStr, $currToStr]);
            $sessData = $sessStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $pvStmt = $pdo->prepare("
                SELECT strftime('%Y-%m-%d', viewed_at) as date_key, COUNT(*) as cnt
                FROM site_visitor_pageviews
                WHERE viewed_at BETWEEN ? AND ?
                GROUP BY date_key
                ORDER BY date_key ASC
            ");
            $pvStmt->execute([$currFromStr, $currToStr]);
            $pvData = $pvStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } else {
            $sessStmt = $pdo->prepare("
                SELECT DATE(first_seen_at) as date_key, COUNT(*) as cnt
                FROM site_visitor_sessions
                WHERE first_seen_at BETWEEN ? AND ?
                GROUP BY DATE(first_seen_at)
                ORDER BY date_key ASC
            ");
            $sessStmt->execute([$currFromStr, $currToStr]);
            $sessData = $sessStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $pvStmt = $pdo->prepare("
                SELECT DATE(viewed_at) as date_key, COUNT(*) as cnt
                FROM site_visitor_pageviews
                WHERE viewed_at BETWEEN ? AND ?
                GROUP BY DATE(viewed_at)
                ORDER BY date_key ASC
            ");
            $pvStmt->execute([$currFromStr, $currToStr]);
            $pvData = $pvStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }

        // Populate every single day in the date range so there are no empty gaps in charts
        $labels = [];
        $visits = [];
        $pageviews = [];

        $step = clone $fromDt;
        while ($step <= $toDt) {
            $key = $step->format('Y-m-d');
            $label = $step->format('M j');
            $labels[] = $label;
            $visits[] = (int)($sessData[$key] ?? 0);
            $pageviews[] = (int)($pvData[$key] ?? 0);
            $step->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'visits' => $visits,
            'pageviews' => $pageviews,
        ];
    }

    /**
     * Top visited pages.
     *
     * @return list<array{page_path: string, page_title: string, views: int, unique_visitors: int, percentage: float}>
     */
    public static function getTopPages(PDO $pdo, string $fromDate, string $toDate, int $limit = 10): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $limit = max(1, min(100, $limit));

        $stmt = $pdo->prepare("
            SELECT 
                page_path,
                COALESCE(MAX(page_title), page_path) as page_title,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_id) as unique_visitors
            FROM site_visitor_pageviews
            WHERE viewed_at BETWEEN ? AND ?
            GROUP BY page_path
            ORDER BY views DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalViewsStmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitor_pageviews WHERE viewed_at BETWEEN ? AND ?");
        $totalViewsStmt->execute([$from, $to]);
        $totalViews = (int)$totalViewsStmt->fetchColumn();

        $result = [];
        foreach ($rows as $row) {
            $views = (int)$row['views'];
            $pct = $totalViews > 0 ? round(($views / $totalViews) * 100, 1) : 0.0;
            $result[] = [
                'page_path' => (string)$row['page_path'],
                'page_title' => (string)($row['page_title'] ?: $row['page_path']),
                'views' => $views,
                'unique_visitors' => (int)$row['unique_visitors'],
                'percentage' => $pct,
            ];
        }

        return $result;
    }

    /**
     * Top entry pages (where visitor sessions began).
     *
     * @return list<array{entry_page: string, count: int, percentage: float}>
     */
    public static function getTopEntryPages(PDO $pdo, string $fromDate, string $toDate, int $limit = 10): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $limit = max(1, min(50, $limit));

        $stmt = $pdo->prepare("
            SELECT entry_page, COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY entry_page
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitor_sessions WHERE first_seen_at BETWEEN ? AND ?");
        $totalStmt->execute([$from, $to]);
        $total = (int)$totalStmt->fetchColumn();

        $result = [];
        foreach ($rows as $row) {
            $c = (int)$row['count'];
            $pct = $total > 0 ? round(($c / $total) * 100, 1) : 0.0;
            $result[] = [
                'entry_page' => (string)$row['entry_page'],
                'count' => $c,
                'percentage' => $pct,
            ];
        }

        return $result;
    }

    /**
     * Top exit pages (where visitors left the site).
     *
     * @return list<array{exit_page: string, count: int, percentage: float}>
     */
    public static function getTopExitPages(PDO $pdo, string $fromDate, string $toDate, int $limit = 10): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $limit = max(1, min(50, $limit));

        $stmt = $pdo->prepare("
            SELECT COALESCE(exit_page, entry_page) as exit_page, COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY exit_page
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitor_sessions WHERE first_seen_at BETWEEN ? AND ?");
        $totalStmt->execute([$from, $to]);
        $total = (int)$totalStmt->fetchColumn();

        $result = [];
        foreach ($rows as $row) {
            $c = (int)$row['count'];
            $pct = $total > 0 ? round(($c / $total) * 100, 1) : 0.0;
            $result[] = [
                'exit_page' => (string)$row['exit_page'],
                'count' => $c,
                'percentage' => $pct,
            ];
        }

        return $result;
    }

    /**
     * Device breakdown (Desktop, Mobile, Tablet).
     *
     * @return array<string, array{count: int, percentage: float}>
     */
    public static function getDeviceBreakdown(PDO $pdo, string $fromDate, string $toDate): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';

        $stmt = $pdo->prepare("
            SELECT device_type, COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY device_type
        ");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = 0;
        $map = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
        foreach ($rows as $row) {
            $k = strtolower((string)$row['device_type']);
            $cnt = (int)$row['count'];
            $map[$k] = ($map[$k] ?? 0) + $cnt;
            $total += $cnt;
        }

        $result = [];
        foreach (['desktop', 'mobile', 'tablet'] as $dev) {
            $cnt = $map[$dev] ?? 0;
            $result[$dev] = [
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Browser and Operating System distributions.
     *
     * @return array{browsers: list<array{name: string, count: int, percentage: float}>, os: list<array{name: string, count: int, percentage: float}>}
     */
    public static function getBrowserAndOsStats(PDO $pdo, string $fromDate, string $toDate, int $limit = 8): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitor_sessions WHERE first_seen_at BETWEEN ? AND ?");
        $totalStmt->execute([$from, $to]);
        $total = (int)$totalStmt->fetchColumn();

        // Browsers
        $bStmt = $pdo->prepare("
            SELECT COALESCE(browser, 'Unknown') as name, COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY browser
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        $bStmt->execute([$from, $to]);
        $bRows = $bStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $browsers = [];
        foreach ($bRows as $r) {
            $cnt = (int)$r['count'];
            $browsers[] = [
                'name' => (string)$r['name'],
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        // OS
        $osStmt = $pdo->prepare("
            SELECT COALESCE(os, 'Unknown') as name, COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY os
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        $osStmt->execute([$from, $to]);
        $osRows = $osStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $os = [];
        foreach ($osRows as $r) {
            $cnt = (int)$r['count'];
            $os[] = [
                'name' => (string)$r['name'],
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        return [
            'browsers' => $browsers,
            'os' => $os,
        ];
    }

    /**
     * Location statistics (Top countries and cities).
     *
     * @return array{
     *   countries: list<array{code: string, name: string, flag: string, count: int, percentage: float}>,
     *   cities: list<array{city: string, country_name: string, count: int, percentage: float}>
     * }
     */
    public static function getLocationStats(PDO $pdo, string $fromDate, string $toDate, int $limit = 10): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitor_sessions WHERE first_seen_at BETWEEN ? AND ?");
        $totalStmt->execute([$from, $to]);
        $total = (int)$totalStmt->fetchColumn();

        // Countries
        $cStmt = $pdo->prepare("
            SELECT 
                country_code, 
                COALESCE(MAX(country_name), country_code) as name, 
                COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY country_code
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        $cStmt->execute([$from, $to]);
        $cRows = $cStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $countries = [];
        foreach ($cRows as $r) {
            $code = strtoupper((string)($r['country_code'] ?? ''));
            $cnt = (int)$r['count'];
            $countries[] = [
                'code' => $code ?: 'XX',
                'name' => self::countryNameFromCode($code),
                'flag' => self::countryFlagEmoji($code),
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        // Cities
        $cityStmt = $pdo->prepare("
            SELECT 
                COALESCE(city, 'Unknown') as city,
                COALESCE(MAX(country_name), 'Unknown') as country_name,
                COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ? AND city IS NOT NULL AND city != ''
            GROUP BY city
            ORDER BY count DESC
            LIMIT {$limit}
        ");
        $cityStmt->execute([$from, $to]);
        $cityRows = $cityStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cities = [];
        foreach ($cityRows as $r) {
            $cnt = (int)$r['count'];
            $cities[] = [
                'city' => (string)$r['city'],
                'country_name' => (string)$r['country_name'],
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        return [
            'countries' => $countries,
            'cities' => $cities,
        ];
    }

    /**
     * Traffic Sources breakdown (Direct, Google, Social, Referral, Search).
     *
     * @return list<array{source: string, label: string, count: int, percentage: float, icon: string}>
     */
    public static function getTrafficSources(PDO $pdo, string $fromDate, string $toDate): array
    {
        self::ensureSchema($pdo);
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitor_sessions WHERE first_seen_at BETWEEN ? AND ?");
        $totalStmt->execute([$from, $to]);
        $total = (int)$totalStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT traffic_source, COUNT(*) as count
            FROM site_visitor_sessions
            WHERE first_seen_at BETWEEN ? AND ?
            GROUP BY traffic_source
            ORDER BY count DESC
        ");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $meta = [
            'direct' => ['label' => 'Direct Visits', 'icon' => 'bi-box-arrow-in-right'],
            'google' => ['label' => 'Google Search', 'icon' => 'bi-google'],
            'search' => ['label' => 'Other Search Engines', 'icon' => 'bi-search'],
            'social' => ['label' => 'Social Media', 'icon' => 'bi-share-fill'],
            'referral' => ['label' => 'Referral Websites', 'icon' => 'bi-link-45deg'],
            'campaign' => ['label' => 'Campaign / Ads', 'icon' => 'bi-megaphone'],
        ];

        $result = [];
        foreach ($rows as $row) {
            $src = strtolower((string)$row['traffic_source']);
            $cnt = (int)$row['count'];
            $cfg = $meta[$src] ?? ['label' => ucfirst($src), 'icon' => 'bi-globe'];
            $result[] = [
                'source' => $src,
                'label' => $cfg['label'],
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
                'icon' => $cfg['icon'],
            ];
        }

        // If empty, return standard template
        if (empty($result)) {
            foreach ($meta as $src => $cfg) {
                $result[] = [
                    'source' => $src,
                    'label' => $cfg['label'],
                    'count' => 0,
                    'percentage' => 0.0,
                    'icon' => $cfg['icon'],
                ];
            }
        }

        return $result;
    }

    /**
     * Detailed Visitor Sessions explorer with search, filter, and pagination.
     * Incorporates registered user association (Admin, Teacher, Student, Parent).
     *
     * @param array<string,mixed> $filters
     * @return array{
     *   records: list<array<string,mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   total_pages: int
     * }
     */
    public static function getSessions(PDO $pdo, array $filters, int $page = 1, int $perPage = 25): array
    {
        self::ensureSchema($pdo);

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['from']) && !empty($filters['to'])) {
            $where[] = "s.first_seen_at BETWEEN ? AND ?";
            $params[] = $filters['from'] . ' 00:00:00';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        if (!empty($filters['device_type'])) {
            $where[] = "s.device_type = ?";
            $params[] = (string)$filters['device_type'];
        }

        if (!empty($filters['traffic_source'])) {
            $where[] = "s.traffic_source = ?";
            $params[] = (string)$filters['traffic_source'];
        }

        if (!empty($filters['country_code'])) {
            $where[] = "s.country_code = ?";
            $params[] = strtoupper((string)$filters['country_code']);
        }

        if (!empty($filters['q'])) {
            $q = '%' . trim((string)$filters['q']) . '%';
            $where[] = "(
                s.session_id LIKE ?
                OR s.visitor_id LIKE ?
                OR s.entry_page LIKE ? 
                OR s.exit_page LIKE ? 
                OR s.country_name LIKE ? 
                OR s.city LIKE ? 
                OR s.browser LIKE ? 
                OR s.os LIKE ? 
                OR u.username LIKE ?
                OR u.google_email LIKE ?
                OR sp.full_name LIKE ?
                OR sp.email LIKE ?
                OR t.name LIKE ?
                OR t.email LIKE ?
                OR pa.name LIKE ?
                OR pa.email LIKE ?
                OR pa.phone LIKE ?
                OR s.user_type LIKE ?
            )";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Base table joins
        $fromJoin = "
            FROM site_visitor_sessions s
            LEFT JOIN users u ON (s.user_id = u.id AND (s.user_type IS NULL OR s.user_type != 'parent'))
            LEFT JOIN student_profiles sp ON (sp.user_id = u.id)
            LEFT JOIN teachers t ON (t.id = u.teacher_id)
            LEFT JOIN parent_accounts pa ON (s.user_id = pa.id AND s.user_type = 'parent')
        ";

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) {$fromJoin} {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = (int)ceil($total / $perPage);

        // Fetch records
        $sql = "
            SELECT 
                s.id, s.visitor_id, s.session_id, s.user_id, s.user_type, s.ip_hash, s.country_code, s.country_name, s.city,
                s.device_type, s.os, s.browser, s.traffic_source, s.referrer_host, s.entry_page,
                s.exit_page, s.pageviews_count, s.duration_seconds, s.is_bounce, s.first_seen_at, s.last_seen_at,
                u.username, u.role as user_role, u.google_email, u.teacher_id,
                sp.full_name as student_name, sp.email as student_email,
                t.name as teacher_name, t.email as teacher_email,
                pa.name as parent_name, pa.email as parent_email, pa.phone as parent_phone
            {$fromJoin}
            {$whereSql}
            ORDER BY s.first_seen_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $nowTs = time();

        foreach ($records as &$r) {
            $code = strtoupper((string)($r['country_code'] ?? ''));
            $r['country_flag'] = self::countryFlagEmoji($code);
            $r['country_name'] = self::countryNameFromCode($code);

            // Online / activity calculations
            $lastSeenTs = strtotime((string)$r['last_seen_at']);
            $firstSeenTs = strtotime((string)$r['first_seen_at']);
            $secondsAgo = max(0, $nowTs - $lastSeenTs);
            $r['seconds_ago'] = $secondsAgo;
            $r['is_online'] = ($secondsAgo <= 300); // Active within 5 minutes
            $r['activity_status'] = $r['is_online'] ? 'Online now' : self::formatSecondsAgo($secondsAgo);
            $r['first_seen_time'] = date('M j, H:i', $firstSeenTs);
            $r['last_seen_time'] = date('M j, H:i', $lastSeenTs);

            // Registered User identification
            $isRegistered = !empty($r['user_id']) && (int)$r['user_id'] > 0;
            $userType = strtolower(trim((string)($r['user_type'] ?? '')));

            $displayName = 'Guest Visitor';
            $displayRole = 'Guest';
            $roleBadgeClass = 'bg-secondary-subtle text-secondary border';
            $displayEmail = '';
            $profileUrl = '';

            if ($isRegistered) {
                if ($userType === 'parent') {
                    $displayName = !empty($r['parent_name']) ? (string)$r['parent_name'] : (!empty($r['parent_phone']) ? (string)$r['parent_phone'] : 'Parent #' . $r['user_id']);
                    $displayRole = 'Parent';
                    $roleBadgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                    $displayEmail = (string)($r['parent_email'] ?? '');
                    $profileUrl = 'parent_requests.php';
                } elseif ($userType === 'student' || ($r['user_role'] ?? '') === 'student') {
                    $displayName = !empty($r['student_name']) ? (string)$r['student_name'] : (string)($r['username'] ?? 'Student #' . $r['user_id']);
                    $displayRole = 'Student';
                    $roleBadgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                    $displayEmail = !empty($r['student_email']) ? (string)$r['student_email'] : (string)($r['google_email'] ?? '');
                    $profileUrl = 'students.php?edit=' . (int)$r['user_id'];
                } elseif ($userType === 'teacher' || ($r['user_role'] ?? '') === 'teacher') {
                    $displayName = !empty($r['teacher_name']) ? (string)$r['teacher_name'] : (string)($r['username'] ?? 'Teacher #' . $r['user_id']);
                    $displayRole = 'Teacher';
                    $roleBadgeClass = 'bg-primary-subtle text-primary-emphasis border border-primary-subtle';
                    $displayEmail = !empty($r['teacher_email']) ? (string)$r['teacher_email'] : (string)($r['google_email'] ?? '');
                    $profileUrl = !empty($r['teacher_id']) ? 'teacher_analytics.php?teacher_id=' . (int)$r['teacher_id'] : 'users.php';
                } elseif ($userType === 'admin' || ($r['user_role'] ?? '') === 'admin') {
                    $displayName = !empty($r['username']) ? (string)$r['username'] : 'Administrator';
                    $displayRole = 'Administrator';
                    $roleBadgeClass = 'bg-danger-subtle text-danger-emphasis border border-danger-subtle';
                    $displayEmail = (string)($r['google_email'] ?? '');
                    $profileUrl = 'users.php';
                } else {
                    $displayName = !empty($r['username']) ? (string)$r['username'] : 'User #' . $r['user_id'];
                    $displayRole = ucfirst($userType ?: ($r['user_role'] ?? 'Registered'));
                    $roleBadgeClass = 'bg-secondary-subtle text-secondary-emphasis border';
                    $displayEmail = (string)($r['google_email'] ?? '');
                    $profileUrl = 'users.php';
                }
            }

            $r['is_registered'] = $isRegistered;
            $r['display_name'] = $displayName;
            $r['display_role'] = $displayRole;
            $r['role_badge_class'] = $roleBadgeClass;
            $r['display_email'] = $displayEmail;
            $r['profile_url'] = $profileUrl;
        }
        unset($r);

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Format seconds into a friendly human-readable time elapsed (e.g. "Just now", "5m ago", "2h ago").
     */
    public static function formatSecondsAgo(int $seconds): string
    {
        if ($seconds < 60) {
            return 'Just now';
        }
        $mins = (int)floor($seconds / 60);
        if ($mins < 60) {
            return $mins . 'm ago';
        }
        $hours = (int)floor($mins / 60);
        if ($hours < 24) {
            return $hours . 'h ago';
        }
        $days = (int)floor($hours / 24);
        if ($days < 30) {
            return $days . 'd ago';
        }
        $months = (int)floor($days / 30);
        return $months . 'mo ago';
    }

    /**
     * Format duration seconds into a clean human-readable string (e.g. "2m 45s" or "35s").
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '< 10s';
        }
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $mins = floor($seconds / 60);
        $remSec = $seconds % 60;
        if ($mins < 60) {
            return $mins . 'm ' . ($remSec > 0 ? $remSec . 's' : '');
        }
        $hours = floor($mins / 60);
        $remMins = $mins % 60;
        return $hours . 'h ' . ($remMins > 0 ? $remMins . 'm' : '');
    }
}
