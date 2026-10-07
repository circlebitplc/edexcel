<?php
declare(strict_types=1);

namespace Edexcel\Services;

use DateTime;
use PDO;
use Throwable;

/**
 * ClassPageAnalyticsService
 *
 * Privacy-first visitor + conversion analytics dedicated ONLY to /class.
 * Stored separately from the site-wide visitor analytics (site_visitor_*),
 * so /class numbers never mix with other pages.
 *
 * Privacy: no raw IPs, no names/phones, no form contents, no message text.
 * Visitors are identified only by random client-generated hex tokens.
 */
class ClassPageAnalyticsService
{
    /** Events the public beacon is allowed to record. */
    public const CLIENT_EVENTS = [
        'whatsapp_click', 'cta_click', 'class_select',
        'video_play', 'video_autoplay',
        'pdf_open', 'pdf_page_view', 'pdf_zoom',
        'registration_open', 'registration_start', 'registration_submit',
    ];

    /** Events only the server may record (cannot be forged via the beacon). */
    public const SERVER_EVENTS = ['registration_success', 'registration_fail'];

    /** Events that count as a conversion. */
    public const CONVERSION_EVENTS = ['whatsapp_click', 'registration_success'];

    private static bool $schemaReady = false;

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------
    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS class_page_analytics (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                session_hash VARCHAR(64) NOT NULL,
                visitor_hash VARCHAR(64) NOT NULL,
                visit_date DATE NOT NULL,
                visit_time TIME NOT NULL,
                device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
                os VARCHAR(50) NULL,
                browser VARCHAR(50) NULL,
                referrer_host VARCHAR(255) NULL,
                landing_url VARCHAR(500) NULL,
                traffic_source VARCHAR(50) NOT NULL DEFAULT 'direct',
                utm_source VARCHAR(100) NULL,
                utm_medium VARCHAR(100) NULL,
                utm_campaign VARCHAR(100) NULL,
                utm_content VARCHAR(100) NULL,
                utm_term VARCHAR(100) NULL,
                pageviews_count INT UNSIGNED NOT NULL DEFAULT 1,
                duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cpa_session (session_hash),
                KEY idx_cpa_date (visit_date),
                KEY idx_cpa_visitor_date (visitor_hash, visit_date),
                KEY idx_cpa_source (traffic_source),
                KEY idx_cpa_device (device_type),
                KEY idx_cpa_campaign (utm_campaign)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS class_page_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                session_hash VARCHAR(64) NOT NULL,
                visitor_hash VARCHAR(64) NOT NULL,
                event_name VARCHAR(64) NOT NULL,
                event_target VARCHAR(128) NULL,
                event_value VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cpe_name_created (event_name, created_at),
                KEY idx_cpe_session (session_hash),
                KEY idx_cpe_visitor (visitor_hash),
                KEY idx_cpe_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        self::$schemaReady = true;

        // Dynamic column alignment for live schema compatibility
        try {
            $existingCols = $pdo->query("SHOW COLUMNS FROM class_page_analytics")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('ip_address', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN ip_address VARCHAR(45) NULL AFTER visitor_hash");
            }
            if (!in_array('country_code', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN country_code VARCHAR(8) NULL AFTER ip_address");
            }
            if (!in_array('country_name', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN country_name VARCHAR(64) NULL AFTER country_code");
            }
            if (!in_array('city', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN city VARCHAR(64) NULL AFTER country_name");
            }
            if (!in_array('referrer_host', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN referrer_host VARCHAR(255) NULL AFTER browser");
            }
            if (!in_array('referrer', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN referrer VARCHAR(1000) NULL AFTER browser");
            }
            if (!in_array('user_agent', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN user_agent VARCHAR(500) NULL AFTER browser");
            }
            if (!in_array('ip_hash', $existingCols, true)) {
                $pdo->exec("ALTER TABLE class_page_analytics ADD COLUMN ip_hash VARCHAR(64) NULL AFTER visitor_hash");
            }
        } catch (Throwable $e) {}

        try {
            $evCols = $pdo->query("SHOW COLUMNS FROM class_page_events")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('page_url', $evCols, true)) {
                $pdo->exec("ALTER TABLE class_page_events ADD COLUMN page_url VARCHAR(500) NULL AFTER event_value");
            }
        } catch (Throwable $e) {}
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public static function isValidHash(string $h): bool
    {
        return (bool)preg_match('/^[a-f0-9]{32}$/', $h);
    }

    public static function isBot(string $ua): bool
    {
        if (trim($ua) === '') {
            return true;
        }
        return (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp\/|(?:skypeuri|page|link|slack|discord|url)preview|headless|lighthouse|pagespeed|curl|wget|python-requests|httpclient|monitor/i', $ua);
    }

    public static function detectDevice(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle/i', $ua) || (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua))) {
            return 'tablet';
        }
        if (preg_match('/Mobile|iPhone|iPod|Android|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function detectOs(string $ua): string
    {
        if (preg_match('/Windows NT 10\.0/i', $ua)) return 'Windows 10/11';
        if (preg_match('/Windows NT 6\.3/i', $ua)) return 'Windows 8.1';
        if (preg_match('/Windows NT 6\.1/i', $ua)) return 'Windows 7';
        if (preg_match('/Windows/i', $ua)) return 'Windows';
        if (preg_match('/iPhone.*?OS (\d+[_.]\d+)/i', $ua, $m)) return 'iOS ' . str_replace('_', '.', $m[1]);
        if (preg_match('/iPad.*?OS (\d+[_.]\d+)/i', $ua, $m)) return 'iPadOS ' . str_replace('_', '.', $m[1]);
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'iOS';
        if (preg_match('/Android (\d+(\.\d+)?)/i', $ua, $m)) return 'Android ' . $m[1];
        if (preg_match('/Android/i', $ua)) return 'Android';
        if (preg_match('/Mac OS X (\d+[_.]\d+)/i', $ua, $m)) return 'macOS ' . str_replace('_', '.', $m[1]);
        if (preg_match('/Macintosh|Mac OS X/i', $ua)) return 'macOS';
        if (preg_match('/CrOS/i', $ua)) return 'Chrome OS';
        if (preg_match('/Linux/i', $ua)) return 'Linux';
        return 'Other';
    }

    public static function detectBrowser(string $ua): string
    {
        // Order matters: Edge/Opera/Samsung UAs also contain "Chrome" and "Safari".
        if (preg_match('/Brave/i', $ua)) return 'Brave';
        if (preg_match('/Edg(e|A|iOS)?\/(\d+)/i', $ua, $m)) return 'Edge ' . $m[2];
        if (preg_match('/OPR\/(\d+)|Opera/i', $ua, $m)) return 'Opera ' . ($m[1] ?? '');
        if (preg_match('/SamsungBrowser\/(\d+(\.\d+)?)/i', $ua, $m)) return 'Samsung Internet ' . $m[1];
        if (preg_match('/Firefox\/(\d+)|FxiOS\/(\d+)/i', $ua, $m)) return 'Firefox ' . ($m[1] ?: $m[2]);
        if (preg_match('/Chrome\/(\d+)|CriOS\/(\d+)/i', $ua, $m)) return 'Chrome ' . ($m[1] ?: $m[2]);
        if (preg_match('/Version\/(\d+(\.\d+)?).*?Safari/i', $ua, $m)) return 'Safari ' . $m[1];
        if (preg_match('/Safari/i', $ua)) return 'Safari';
        return 'Other';
    }

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

    public static function countryName(?string $code): string
    {
        $code = strtoupper(trim((string)$code));
        $names = [
            'LK' => 'Sri Lanka', 'GB' => 'United Kingdom', 'US' => 'United States',
            'AU' => 'Australia', 'CA' => 'Canada', 'NZ' => 'New Zealand',
            'AE' => 'United Arab Emirates', 'QA' => 'Qatar', 'SA' => 'Saudi Arabia',
            'KW' => 'Kuwait', 'OM' => 'Oman', 'BH' => 'Bahrain',
            'SG' => 'Singapore', 'MY' => 'Malaysia', 'MV' => 'Maldives',
            'IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
            'IT' => 'Italy', 'FR' => 'France', 'DE' => 'Germany',
            'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China',
        ];
        return $names[$code] ?? ($code !== '' ? $code : 'Unknown Location');
    }

    /** Aggregate traffic source. UTM wins over referrer; exact token matching. */
    public static function detectSource(?string $referrerHost, ?string $utmSource): string
    {
        $map = [
            'google' => 'google', 'facebook' => 'facebook', 'fb' => 'facebook', 'meta' => 'facebook',
            'instagram' => 'instagram', 'ig' => 'instagram', 'whatsapp' => 'whatsapp', 'wa' => 'whatsapp',
            'youtube' => 'youtube', 'tiktok' => 'tiktok', 'sms' => 'sms', 'email' => 'email',
        ];
        if ($utmSource !== null && $utmSource !== '') {
            $s = strtolower(trim($utmSource));
            return $map[$s] ?? 'campaign';
        }
        if ($referrerHost === null || $referrerHost === '') {
            return 'direct';
        }
        $h = strtolower($referrerHost);
        if (str_contains($h, 'edexcel.college')) return 'internal';
        if (preg_match('/(^|\.)google\./', $h)) return 'google';
        if (preg_match('/(^|\.)(facebook\.com|fb\.com|fb\.me|m\.facebook\.com|l\.facebook\.com)$/', $h)) return 'facebook';
        if (preg_match('/(^|\.)instagram\.com$/', $h)) return 'instagram';
        if (preg_match('/(^|\.)(whatsapp\.com|wa\.me)$/', $h)) return 'whatsapp';
        if (preg_match('/(^|\.)(youtube\.com|youtu\.be)$/', $h)) return 'youtube';
        if (preg_match('/(^|\.)tiktok\.com$/', $h)) return 'tiktok';
        if (preg_match('/(^|\.)(bing\.com|yahoo\.com|duckduckgo\.com)$/', $h)) return 'search';
        return 'referral';
    }

    private static function clean(?string $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(strip_tags($v));
        $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /** Convert inclusive Y-m-d range to [start, endExclusive) datetimes (index friendly). */
    private static function dtRange(string $from, string $to): array
    {
        return [$from . ' 00:00:00', (new DateTime($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
    }

    // ------------------------------------------------------------------
    // Recording
    // ------------------------------------------------------------------
    public static function recordVisit(PDO $pdo, array $p, string $ua = ''): array
    {
        if ($ua === '') {
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        $session = (string)($p['session_hash'] ?? ($_COOKIE['_cls_sid'] ?? ''));
        $visitor = (string)($p['visitor_hash'] ?? ($_COOKIE['_cls_vid'] ?? ''));
        if (!self::isValidHash($session) || !self::isValidHash($visitor)) {
            return ['ok' => false, 'error' => 'invalid_id'];
        }
        if (self::isBot($ua)) {
            return ['ok' => true, 'skipped' => 'bot'];
        }
        self::ensureSchema($pdo);

        $referrer = (string)($p['referrer'] ?? '');
        $refHost = $referrer !== '' ? (string)(parse_url($referrer, PHP_URL_HOST) ?: '') : '';
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
            $utm[$k] = self::clean(isset($p[$k]) ? (string)$p[$k] : null, 100);
        }
        // Store only the path (+utm query) of the landing URL; never other query params.
        $landing = self::clean((string)parse_url((string)($p['page_url'] ?? '/class'), PHP_URL_PATH), 500) ?? '/class';

        $clientIp = '';
        if (function_exists('eck_client_ip')) {
            $clientIp = eck_client_ip();
        } elseif (class_exists(\Edexcel\Services\GeoIpService::class)) {
            $clientIp = \Edexcel\Services\GeoIpService::clientIp();
        } else {
            $clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        }
        if ($clientIp !== '' && !filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $clientIp = '';
        }

        $countryCode = '';
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $countryCode = strtoupper(trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']));
        } elseif (class_exists(\Edexcel\Services\GeoIpService::class)) {
            $countryCode = \Edexcel\Services\GeoIpService::countryCode();
        }
        $countryName = self::countryName($countryCode);
        $city = trim((string)($_SERVER['HTTP_CF_IPCITY'] ?? ''));

        // One row per session. Reloads inside the same session increment pageviews.
        $stmt = $pdo->prepare("
            INSERT INTO class_page_analytics
                (session_hash, visitor_hash, ip_address, ip_hash, country_code, country_name, city,
                 visit_date, visit_time, device_type, os, browser, user_agent,
                 referrer, referrer_host, landing_url, traffic_source,
                 utm_source, utm_medium, utm_campaign, utm_content, utm_term, pageviews_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                pageviews_count = pageviews_count + 1,
                ip_address = COALESCE(ip_address, VALUES(ip_address)),
                country_code = COALESCE(country_code, VALUES(country_code)),
                country_name = COALESCE(country_name, VALUES(country_name)),
                city = COALESCE(city, VALUES(city)),
                user_agent = COALESCE(user_agent, VALUES(user_agent))
        ");
        $ipHash = $clientIp !== '' ? hash('sha256', $clientIp . 'edexcel_class_salt') : null;
        $stmt->execute([
            $session, $visitor,
            $clientIp !== '' ? mb_substr($clientIp, 0, 45) : null,
            $ipHash,
            $countryCode !== '' ? mb_substr($countryCode, 0, 8) : null,
            $countryName !== '' ? mb_substr($countryName, 0, 64) : null,
            $city !== '' ? mb_substr($city, 0, 64) : null,
            self::detectDevice($ua),
            self::detectOs($ua),
            self::detectBrowser($ua),
            $ua !== '' ? mb_substr($ua, 0, 500) : null,
            $referrer !== '' ? mb_substr($referrer, 0, 1000) : null,
            $refHost !== '' ? mb_substr(strtolower($refHost), 0, 255) : null,
            $landing,
            self::detectSource($refHost, $utm['utm_source']),
            $utm['utm_source'], $utm['utm_medium'], $utm['utm_campaign'], $utm['utm_content'], $utm['utm_term'],
        ]);
        return ['ok' => true];
    }

    public static function recordHeartbeat(PDO $pdo, string $session, int $seconds): bool
    {
        if (!self::isValidHash($session)) {
            return false;
        }
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare("UPDATE class_page_analytics SET duration_seconds = GREATEST(duration_seconds, ?) WHERE session_hash = ?");
        return $stmt->execute([max(0, min(14400, $seconds)), $session]);
    }

    /**
     * @param bool $fromServer true only for trusted server-side calls (registration result).
     */
    public static function recordEvent(PDO $pdo, array $p, bool $fromServer = false, string $ua = ''): bool
    {
        $name = (string)($p['event_name'] ?? '');
        $allowed = $fromServer ? array_merge(self::CLIENT_EVENTS, self::SERVER_EVENTS) : self::CLIENT_EVENTS;
        if (!in_array($name, $allowed, true)) {
            return false;
        }
        if ($ua === '') {
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        $session = (string)($p['session_hash'] ?? ($_COOKIE['_cls_sid'] ?? ''));
        $visitor = (string)($p['visitor_hash'] ?? ($_COOKIE['_cls_vid'] ?? ''));
        if (!self::isValidHash($session) || !self::isValidHash($visitor)) {
            return false;
        }
        if (!$fromServer && self::isBot($ua)) {
            return true;
        }
        self::ensureSchema($pdo);

        // De-duplicate double-fires (same session, event, target within 3 seconds).
        $target = self::clean(isset($p['event_target']) ? (string)$p['event_target'] : null, 128);
        $dup = $pdo->prepare("
            SELECT 1 FROM class_page_events
            WHERE session_hash = ? AND event_name = ? AND (event_target <=> ?)
              AND created_at >= (NOW() - INTERVAL 3 SECOND)
            LIMIT 1
        ");
        $dup->execute([$session, $name, $target]);
        if ($dup->fetchColumn()) {
            return true;
        }

        $pageUrl = self::clean(isset($p['page_url']) ? (string)$p['page_url'] : null, 500);
        $stmt = $pdo->prepare("
            INSERT INTO class_page_events (session_hash, visitor_hash, event_name, event_target, event_value, page_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $session, $visitor, $name, $target,
            self::clean(isset($p['event_value']) ? (string)$p['event_value'] : null, 255),
            $pageUrl,
        ]);
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------
    public static function getDashboardStats(PDO $pdo, string $from, string $to): array
    {
        self::ensureSchema($pdo);
        [$dtFrom, $dtTo] = self::dtRange($from, $to);

        $s = $pdo->prepare("
            SELECT COALESCE(SUM(pageviews_count),0) AS views,
                   COUNT(DISTINCT visitor_hash) AS visitors,
                   COUNT(*) AS sessions,
                   COALESCE(AVG(NULLIF(duration_seconds,0)),0) AS avg_duration
            FROM class_page_analytics WHERE visit_date BETWEEN ? AND ?
        ");
        $s->execute([$from, $to]);
        $r = $s->fetch(PDO::FETCH_ASSOC) ?: [];

        // Returning = visitor active in range who has more than one session up to range end.
        $ret = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT visitor_hash FROM class_page_analytics
                WHERE visit_date <= ?
                GROUP BY visitor_hash
                HAVING COUNT(*) > 1 AND MAX(visit_date) >= ?
            ) t
        ");
        $ret->execute([$to, $from]);
        $returning = (int)$ret->fetchColumn();

        $sumViews = function (string $a, string $b) use ($pdo): int {
            $q = $pdo->prepare("SELECT COALESCE(SUM(pageviews_count),0) FROM class_page_analytics WHERE visit_date BETWEEN ? AND ?");
            $q->execute([$a, $b]);
            return (int)$q->fetchColumn();
        };
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $e = $pdo->prepare("
            SELECT
              SUM(event_name = 'whatsapp_click') AS wa_clicks,
              SUM(event_name = 'registration_success') AS registrations,
              SUM(event_name = 'registration_fail') AS reg_fails,
              SUM(event_name IN ('video_play')) AS video_plays,
              COUNT(DISTINCT CASE WHEN event_name = 'pdf_open' THEN session_hash END) AS pdf_opens,
              SUM(event_name = 'cta_click') AS cta_clicks,
              COUNT(DISTINCT CASE WHEN event_name IN ('whatsapp_click','registration_success') THEN visitor_hash END) AS converted_visitors
            FROM class_page_events WHERE created_at >= ? AND created_at < ?
        ");
        $e->execute([$dtFrom, $dtTo]);
        $ev = $e->fetch(PDO::FETCH_ASSOC) ?: [];

        $visitors = (int)($r['visitors'] ?? 0);
        $converted = (int)($ev['converted_visitors'] ?? 0);

        $ipStmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM class_page_analytics WHERE visit_date BETWEEN ? AND ? AND ip_address IS NOT NULL AND ip_address != ''");
        $ipStmt->execute([$from, $to]);
        $uniqueIps = (int)$ipStmt->fetchColumn();

        return [
            'total_views' => (int)($r['views'] ?? 0),
            'unique_visitors' => $visitors,
            'unique_ips' => $uniqueIps,
            'returning_visitors' => $returning,
            'total_sessions' => (int)($r['sessions'] ?? 0),
            'avg_duration' => (int)round((float)($r['avg_duration'] ?? 0)),
            'views_today' => $sumViews($today, $today),
            'views_yesterday' => $sumViews($yesterday, $yesterday),
            'views_week' => $sumViews(date('Y-m-d', strtotime('monday this week')), $today),
            'views_month' => $sumViews(date('Y-m-01'), $today),
            'views_year' => $sumViews(date('Y-01-01'), $today),
            'whatsapp_clicks' => (int)($ev['wa_clicks'] ?? 0),
            'registrations' => (int)($ev['registrations'] ?? 0),
            'registration_fails' => (int)($ev['reg_fails'] ?? 0),
            'video_plays' => (int)($ev['video_plays'] ?? 0),
            'pdf_opens' => (int)($ev['pdf_opens'] ?? 0),
            'cta_clicks' => (int)($ev['cta_clicks'] ?? 0),
            'converted_visitors' => $converted,
            // Unique visitors who joined WhatsApp or registered ÷ unique visitors. Never > 100%.
            'conversion_rate' => $visitors > 0 ? round(min(100, $converted / $visitors * 100), 1) : 0.0,
        ];
    }

    public static function getLocationStats(PDO $pdo, string $from, string $to, int $limit = 8): array
    {
        $stmt = $pdo->prepare("
            SELECT COALESCE(country_code,'XX') AS code,
                   COALESCE(country_name,'Unknown Location') AS name,
                   COUNT(*) AS count
            FROM class_page_analytics
            WHERE visit_date BETWEEN ? AND ? AND country_name IS NOT NULL AND country_name != ''
            GROUP BY code, name
            ORDER BY count DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['flag'] = self::countryFlagEmoji($row['code']);
            $row['country_name'] = (string)$row['name'];
            $row['sessions'] = (int)$row['count'];
        }
        unset($row);
        return $rows;
    }

    public static function getVisitorSessions(PDO $pdo, array $filter = [], int $page = 1, int $perPage = 25): array
    {
        self::ensureSchema($pdo);
        $from = (string)($filter['from'] ?? date('Y-m-d', strtotime('-6 days')));
        $to = (string)($filter['to'] ?? date('Y-m-d'));

        $where = ["a.visit_date BETWEEN ? AND ?"];
        $params = [$from, $to];

        $q = trim((string)($filter['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(a.ip_address LIKE ? OR a.visitor_hash LIKE ? OR a.session_hash LIKE ? OR a.country_name LIKE ? OR a.city LIKE ? OR a.browser LIKE ? OR a.os LIKE ? OR a.utm_campaign LIKE ? OR a.referrer LIKE ?)";
            $searchTerm = '%' . $q . '%';
            for ($i = 0; $i < 9; $i++) {
                $params[] = $searchTerm;
            }
        }

        $dev = trim((string)($filter['device_type'] ?? ''));
        if ($dev !== '') {
            $where[] = "a.device_type = ?";
            $params[] = $dev;
        }

        $src = trim((string)($filter['traffic_source'] ?? ''));
        if ($src !== '') {
            $where[] = "a.traffic_source = ?";
            $params[] = $src;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM class_page_analytics a WHERE {$whereSql}");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $perPage = max(5, min(100, $perPage));
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($totalPages, $page));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT a.*,
                   (
                       SELECT COUNT(*) FROM class_page_analytics a2 
                       WHERE a2.visitor_hash = a.visitor_hash AND a2.id < a.id
                   ) AS prev_sessions_count,
                   (
                       SELECT GROUP_CONCAT(CONCAT(e.event_name, '::', COALESCE(e.event_target,''), '::', COALESCE(e.event_value,''), '::', DATE_FORMAT(e.created_at, '%H:%i:%s'), '::', COALESCE(e.page_url,'')) ORDER BY e.id ASC SEPARATOR '||')
                       FROM class_page_events e
                       WHERE e.session_hash = a.session_hash
                   ) AS events_detail
            FROM class_page_analytics a
            WHERE {$whereSql}
            ORDER BY a.id DESC
            LIMIT {$offset}, {$perPage}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as &$r) {
            $evDetail = (string)($r['events_detail'] ?? '');
            $eventsList = [];
            if ($evDetail !== '') {
                foreach (explode('||', $evDetail) as $part) {
                    $p = explode('::', $part, 5);
                    $eventsList[] = [
                        'name' => $p[0] ?? '',
                        'target' => $p[1] ?? '',
                        'value' => $p[2] ?? '',
                        'time' => $p[3] ?? '',
                        'page_url' => $p[4] ?? '',
                        'label' => self::describe($p[0] ?? '', $p[1] ?? '', $p[2] ?? '')
                    ];
                }
            }
            $r['events_list'] = $eventsList;
            $r['actions'] = $eventsList;
            $r['is_new'] = ((int)($r['prev_sessions_count'] ?? 0) === 0);
            $r['country_flag'] = self::countryFlagEmoji($r['country_code'] ?? '');
            $r['country_name'] = !empty($r['country_name']) ? $r['country_name'] : (!empty($r['country_code']) ? self::countryName($r['country_code']) : 'Unknown');
            $r['country_display'] = $r['country_name'];
            $r['ip_address'] = !empty($r['ip_address']) ? $r['ip_address'] : 'Unknown';
            $r['display_ip'] = $r['ip_address'];
            $r['visitor_id'] = (string)($r['visitor_hash'] ?? '');
            $r['short_visitor_id'] = substr($r['visitor_id'], 0, 8) . '...';
            $r['campaign'] = (string)($r['utm_campaign'] ?? '');
            $r['pageviews'] = (int)($r['pageviews_count'] ?? 1);
            $r['first_seen_formatted'] = !empty($r['first_seen_at']) ? date('M j, g:i a', strtotime((string)$r['first_seen_at'])) : ($r['visit_date'] ?? '');
            $dur = (int)($r['duration_seconds'] ?? 0);
            $r['dwell_time_formatted'] = $dur >= 60 ? floor($dur / 60) . 'm ' . ($dur % 60) . 's' : $dur . 's';
        }
        unset($r);

        return [
            'total' => $total,
            'page' => $page,
            'pages' => $totalPages,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'items' => $records,
            'records' => $records,
        ];
    }

    public static function getTimeline(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare("
            SELECT visit_date, SUM(pageviews_count) AS views, COUNT(DISTINCT visitor_hash) AS visitors
            FROM class_page_analytics WHERE visit_date BETWEEN ? AND ?
            GROUP BY visit_date
        ");
        $stmt->execute([$from, $to]);
        $lookup = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lookup[$row['visit_date']] = $row;
        }
        $labels = $views = $visitors = [];
        $cur = new DateTime($from);
        $end = new DateTime($to);
        while ($cur <= $end) {
            $d = $cur->format('Y-m-d');
            $labels[] = $cur->format('M j');
            $views[] = (int)($lookup[$d]['views'] ?? 0);
            $visitors[] = (int)($lookup[$d]['visitors'] ?? 0);
            $cur->modify('+1 day');
        }
        return ['labels' => $labels, 'views' => $views, 'visitors' => $visitors];
    }

    /** Last 12 calendar months including empty months. */
    public static function getMonthlyTrend(PDO $pdo): array
    {
        $start = (new DateTime('first day of this month'))->modify('-11 months');
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(visit_date,'%Y-%m') ym, SUM(pageviews_count) views, COUNT(DISTINCT visitor_hash) visitors
            FROM class_page_analytics WHERE visit_date >= ?
            GROUP BY ym
        ");
        $stmt->execute([$start->format('Y-m-d')]);
        $lookup = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lookup[$row['ym']] = $row;
        }
        $labels = $views = $visitors = [];
        for ($i = 0; $i < 12; $i++) {
            $ym = $start->format('Y-m');
            $labels[] = $start->format('M Y');
            $views[] = (int)($lookup[$ym]['views'] ?? 0);
            $visitors[] = (int)($lookup[$ym]['visitors'] ?? 0);
            $start->modify('+1 month');
        }
        return ['labels' => $labels, 'views' => $views, 'visitors' => $visitors];
    }

    private static function breakdown(PDO $pdo, string $col, string $from, string $to, int $limit = 10): array
    {
        $allowed = ['traffic_source', 'device_type', 'browser', 'os'];
        if (!in_array($col, $allowed, true)) {
            return [];
        }
        $stmt = $pdo->prepare("
            SELECT COALESCE($col,'Other') AS k, COUNT(*) AS c
            FROM class_page_analytics WHERE visit_date BETWEEN ? AND ?
            GROUP BY k ORDER BY c DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = max(1, array_sum(array_map('intval', array_column($rows, 'c'))));
        $out = [];
        foreach ($rows as $row) {
            $val = (string)$row['k'];
            $out[] = [
                'key' => $val,
                'label' => ucfirst($val),
                $col => $val,
                'count' => (int)$row['c'],
                'percentage' => round((int)$row['c'] / $total * 100, 1),
            ];
        }
        return $out;
    }

    public static function getTrafficSources(PDO $pdo, string $from, string $to): array
    {
        return self::breakdown($pdo, 'traffic_source', $from, $to);
    }

    public static function getDeviceBreakdown(PDO $pdo, string $from, string $to): array
    {
        return self::breakdown($pdo, 'device_type', $from, $to);
    }

    public static function getBrowserAndOsStats(PDO $pdo, string $from, string $to): array
    {
        return [
            'browsers' => self::breakdown($pdo, 'browser', $from, $to, 6),
            'os' => self::breakdown($pdo, 'os', $from, $to, 6),
        ];
    }

    public static function getCampaignStats(PDO $pdo, string $from, string $to): array
    {
        [$dtFrom, $dtTo] = self::dtRange($from, $to);
        $stmt = $pdo->prepare("
            SELECT a.utm_campaign AS campaign,
                   COALESCE(a.utm_source,'-') AS source,
                   COALESCE(a.utm_medium,'-') AS medium,
                   COUNT(*) AS visits,
                   COUNT(DISTINCT a.visitor_hash) AS unique_visitors,
                   COUNT(DISTINCT conv.visitor_hash) AS conversions
            FROM class_page_analytics a
            LEFT JOIN (
                SELECT DISTINCT session_hash, visitor_hash FROM class_page_events
                WHERE event_name IN ('whatsapp_click','registration_success')
                  AND created_at >= ? AND created_at < ?
            ) conv ON conv.session_hash = a.session_hash
            WHERE a.visit_date BETWEEN ? AND ? AND a.utm_campaign IS NOT NULL
            GROUP BY a.utm_campaign, a.utm_source, a.utm_medium
            ORDER BY visits DESC LIMIT 25
        ");
        $stmt->execute([$dtFrom, $dtTo, $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$c) {
            $u = (int)$c['unique_visitors'];
            $c['conversion_rate'] = $u > 0 ? round(min(100, (int)$c['conversions'] / $u * 100), 1) : 0.0;
        }
        unset($c);
        return $rows;
    }

    private static function eventCounts(PDO $pdo, array $names, string $from, string $to): array
    {
        [$dtFrom, $dtTo] = self::dtRange($from, $to);
        $in = implode(',', array_fill(0, count($names), '?'));
        $stmt = $pdo->prepare("
            SELECT event_name, COALESCE(event_target,'(none)') AS event_target,
                   COUNT(*) AS count, COUNT(DISTINCT visitor_hash) AS visitors
            FROM class_page_events
            WHERE event_name IN ($in) AND created_at >= ? AND created_at < ?
            GROUP BY event_name, event_target
            ORDER BY count DESC
        ");
        $stmt->execute(array_merge($names, [$dtFrom, $dtTo]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getConversionBreakdown(PDO $pdo, string $from, string $to): array
    {
        [$dtFrom, $dtTo] = self::dtRange($from, $to);

        $wa = self::eventCounts($pdo, ['whatsapp_click'], $from, $to);

        // Interest derived from WhatsApp targets ("kandy_ict", "online_cs", ...)
        $subject = ['ICT' => 0, 'Computer Science' => 0];
        $location = ['Kandy' => 0, 'Kurunegala' => 0, 'Online' => 0];
        foreach ($wa as $row) {
            $t = (string)$row['event_target'];
            $n = (int)$row['count'];
            if (str_ends_with($t, '_ict')) $subject['ICT'] += $n;
            if (str_ends_with($t, '_cs')) $subject['Computer Science'] += $n;
            if (str_starts_with($t, 'kandy')) $location['Kandy'] += $n;
            if (str_starts_with($t, 'kurunegala')) $location['Kurunegala'] += $n;
            if (str_starts_with($t, 'online')) $location['Online'] += $n;
        }

        $funnelStmt = $pdo->prepare("
            SELECT
              (SELECT COUNT(*) FROM class_page_analytics WHERE visit_date BETWEEN ? AND ?) AS step_visits,
              (SELECT COUNT(DISTINCT session_hash) FROM class_page_events
                 WHERE event_name IN ('class_select','cta_click','whatsapp_click','registration_open') AND created_at >= ? AND created_at < ?) AS step_class_selection,
              (SELECT COUNT(DISTINCT session_hash) FROM class_page_events
                 WHERE event_name IN ('whatsapp_click','registration_start') AND created_at >= ? AND created_at < ?) AS step_wa_or_form_start,
              (SELECT COUNT(DISTINCT session_hash) FROM class_page_events
                 WHERE event_name = 'registration_success' AND created_at >= ? AND created_at < ?) AS step_reg_submitted
        ");
        $funnelStmt->execute([$from, $to, $dtFrom, $dtTo, $dtFrom, $dtTo, $dtFrom, $dtTo]);

        return [
            'whatsapp_by_class' => $wa,
            'subject_interest' => $subject,
            'location_interest' => $location,
            'video_engagement' => self::eventCounts($pdo, ['video_play', 'video_autoplay'], $from, $to),
            'pdf_engagement' => self::eventCounts($pdo, ['pdf_open', 'pdf_page_view', 'pdf_zoom'], $from, $to),
            'cta_clicks' => self::eventCounts($pdo, ['cta_click'], $from, $to),
            'registration' => self::eventCounts($pdo, ['registration_open', 'registration_start', 'registration_submit', 'registration_success', 'registration_fail'], $from, $to),
            'funnel' => $funnelStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'step_visits' => 0, 'step_class_selection' => 0, 'step_wa_or_form_start' => 0, 'step_reg_submitted' => 0,
            ],
        ];
    }

    public static function getRecentActivity(PDO $pdo, int $limit = 25): array
    {
        $stmt = $pdo->prepare("
            SELECT e.event_name, e.event_target, e.event_value, e.created_at, a.device_type, a.traffic_source
            FROM class_page_events e
            LEFT JOIN class_page_analytics a ON a.session_hash = e.session_hash
            ORDER BY e.id DESC LIMIT " . max(1, min(100, $limit))
        );
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ts = strtotime((string)$r['created_at']);
            $out[] = [
                'date' => date('M j', $ts),
                'time' => date('h:i A', $ts),
                'desc' => self::describe((string)$r['event_name'], (string)($r['event_target'] ?? ''), (string)($r['event_value'] ?? '')),
                'device' => ucfirst((string)($r['device_type'] ?? 'unknown')),
                'source' => ucfirst((string)($r['traffic_source'] ?? 'unknown')),
            ];
        }
        return $out;
    }

    public static function targetLabel(string $t): string
    {
        $map = [
            'kandy_ict' => 'Kandy ICT', 'kandy_cs' => 'Kandy Computer Science',
            'kurunegala_ict' => 'Kurunegala ICT', 'kurunegala_cs' => 'Kurunegala Computer Science',
            'online_ict' => 'Online ICT', 'online_cs' => 'Online Computer Science',
            'floating_wa' => 'Floating WhatsApp button',
            'video_1' => 'Video 1', 'video_2' => 'Video 2', 'video_3' => 'Video 3',
        ];
        if (isset($map[$t])) return $map[$t];
        if (preg_match('/^page_(\d)$/', $t, $m)) return 'Page ' . $m[1];
        return ucwords(str_replace('_', ' ', $t));
    }

    private static function describe(string $name, string $target, string $value): string
    {
        $label = self::targetLabel($target);
        return match ($name) {
            'whatsapp_click' => "{$label} WhatsApp clicked",
            'video_play' => "{$label} played",
            'video_autoplay' => "{$label} autoplay started",
            'pdf_open' => 'Mock paper preview opened',
            'pdf_page_view' => "PDF {$label} viewed",
            'pdf_zoom' => 'PDF zoom used',
            'cta_click' => "CTA clicked: {$label}",
            'class_select' => "Class option selected: {$label}",
            'registration_open' => 'Registration form viewed',
            'registration_start' => 'Registration form started',
            'registration_submit' => 'Registration form submitted',
            'registration_success' => 'Registration successful' . ($value !== '' ? " ({$value})" : ''),
            'registration_fail' => 'Registration failed (validation)',
            default => $name,
        };
    }

    /** Aggregated CSV only — no visitor-level rows, no personal data. */
    public static function exportCsv(PDO $pdo, string $from, string $to): void
    {
        $stats = self::getDashboardStats($pdo, $from, $to);
        $timeline = self::getTimeline($pdo, $from, $to);
        $conv = self::getConversionBreakdown($pdo, $from, $to);

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="class_page_analytics_' . $from . '_to_' . $to . '.csv"');
            header('Cache-Control: no-store');
        }
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $row = static function (array $r) use ($out): void {
            // Neutralise spreadsheet formula injection.
            fputcsv($out, array_map(static fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));
        };

        $row(['/class Page Analytics', $from, $to]);
        $row([]);
        $row(['SUMMARY']);
        foreach ([
            'Total views' => $stats['total_views'], 'Unique visitors' => $stats['unique_visitors'],
            'Returning visitors' => $stats['returning_visitors'], 'Sessions' => $stats['total_sessions'],
            'Avg time on page (s)' => $stats['avg_duration'], 'WhatsApp clicks' => $stats['whatsapp_clicks'],
            'Registrations' => $stats['registrations'], 'Registration failures' => $stats['registration_fails'],
            'Video plays' => $stats['video_plays'], 'PDF preview opens' => $stats['pdf_opens'],
            'Converted visitors' => $stats['converted_visitors'], 'Conversion rate %' => $stats['conversion_rate'],
        ] as $k => $v) {
            $row([$k, $v]);
        }
        $row([]);
        $row(['DAILY', 'Views', 'Unique visitors']);
        foreach ($timeline['labels'] as $i => $l) {
            $row([$l, $timeline['views'][$i], $timeline['visitors'][$i]]);
        }
        $sections = [
            'TRAFFIC SOURCES' => self::getTrafficSources($pdo, $from, $to),
            'DEVICES' => self::getDeviceBreakdown($pdo, $from, $to),
        ];
        foreach ($sections as $title => $rows) {
            $row([]);
            $row([$title, 'Sessions', '%']);
            foreach ($rows as $r) {
                $row([$r['label'], $r['count'], $r['percentage']]);
            }
        }
        foreach (['WHATSAPP BY CLASS' => 'whatsapp_by_class', 'VIDEO' => 'video_engagement', 'PDF' => 'pdf_engagement', 'REGISTRATION' => 'registration', 'CTA' => 'cta_clicks'] as $title => $key) {
            $row([]);
            $row([$title, 'Event', 'Count', 'Unique visitors']);
            foreach ($conv[$key] as $r) {
                $row([self::targetLabel((string)$r['event_target']), $r['event_name'], $r['count'], $r['visitors']]);
            }
        }
        $row([]);
        $row(['CAMPAIGNS', 'Source', 'Medium', 'Visits', 'Unique', 'Conversions', 'Rate %']);
        foreach (self::getCampaignStats($pdo, $from, $to) as $c) {
            $row([$c['campaign'], $c['source'], $c['medium'], $c['visits'], $c['unique_visitors'], $c['conversions'], $c['conversion_rate']]);
        }

        $row([]);
        $row(['INDIVIDUAL VISITOR SESSIONS (FULL USER DETAIL)']);
        $row(['Date', 'Time', 'IP Address', 'Visitor ID', 'Country', 'City', 'Device', 'OS', 'Browser', 'Traffic Source', 'Referrer Host', 'Landing URL', 'Campaign', 'Pageviews', 'Duration (s)', 'Actions Performed']);
        $sessions = self::getVisitorSessions($pdo, ['from' => $from, 'to' => $to], 1, 1000);
        foreach ($sessions['records'] as $s) {
            $actionsStr = implode('; ', array_column($s['events_list'] ?? [], 'label'));
            $row([
                $s['visit_date'],
                $s['visit_time'],
                $s['ip_address'] ?? 'Hidden',
                $s['visitor_hash'],
                $s['country_display'],
                $s['city'] ?? '',
                ucfirst($s['device_type']),
                $s['os'] ?? 'Unknown',
                $s['browser'] ?? 'Unknown',
                ucfirst($s['traffic_source']),
                $s['referrer_host'] ?? '',
                $s['landing_url'] ?? '',
                $s['utm_campaign'] ?? '',
                $s['pageviews_count'],
                $s['duration_seconds'],
                $actionsStr,
            ]);
        }
        fclose($out);
        exit;
    }
}
