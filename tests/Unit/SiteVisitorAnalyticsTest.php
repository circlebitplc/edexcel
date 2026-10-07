<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\SiteVisitorAnalyticsService;
use PDO;
use PHPUnit\Framework\TestCase;

final class SiteVisitorAnalyticsTest extends TestCase
{
    private function sqlite(): PDO
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('sqlite PDO driver is not available');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public function testUserAgentParsingDesktop(): void
    {
        $chromeWin = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $res = SiteVisitorAnalyticsService::parseUserAgent($chromeWin);

        $this->assertSame('desktop', $res['device_type']);
        $this->assertSame('Windows 10/11', $res['os']);
        $this->assertSame('Chrome', $res['browser']);
        $this->assertFalse($res['is_bot']);
    }

    public function testUserAgentParsingMobile(): void
    {
        $iphoneSafari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $res = SiteVisitorAnalyticsService::parseUserAgent($iphoneSafari);

        $this->assertSame('mobile', $res['device_type']);
        $this->assertSame('iOS', $res['os']);
        $this->assertSame('Safari', $res['browser']);
        $this->assertFalse($res['is_bot']);
    }

    public function testUserAgentParsingTablet(): void
    {
        $ipad = 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1';
        $res = SiteVisitorAnalyticsService::parseUserAgent($ipad);

        $this->assertSame('tablet', $res['device_type']);
        $this->assertSame('iOS', $res['os']);
        $this->assertSame('Safari', $res['browser']);
        $this->assertFalse($res['is_bot']);
    }

    public function testBotDetection(): void
    {
        $googlebot = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $res = SiteVisitorAnalyticsService::parseUserAgent($googlebot);
        $this->assertTrue($res['is_bot']);

        $bingbot = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
        $res = SiteVisitorAnalyticsService::parseUserAgent($bingbot);
        $this->assertTrue($res['is_bot']);
    }

    public function testTrafficSourceParsing(): void
    {
        // Direct
        $res = SiteVisitorAnalyticsService::parseTrafficSource('');
        $this->assertSame('direct', $res['source']);

        // Google
        $res = SiteVisitorAnalyticsService::parseTrafficSource('https://www.google.com/search?q=edexcel+classes');
        $this->assertSame('google', $res['source']);

        // Other search
        $res = SiteVisitorAnalyticsService::parseTrafficSource('https://www.bing.com/search?q=edexcel');
        $this->assertSame('search', $res['source']);

        // Social
        $res = SiteVisitorAnalyticsService::parseTrafficSource('https://m.facebook.com/');
        $this->assertSame('social', $res['source']);

        $res = SiteVisitorAnalyticsService::parseTrafficSource('https://l.instagram.com/');
        $this->assertSame('social', $res['source']);

        // Referral
        $res = SiteVisitorAnalyticsService::parseTrafficSource('https://tuitionguide.lk/listing');
        $this->assertSame('referral', $res['source']);

        // UTM param override
        $res = SiteVisitorAnalyticsService::parseTrafficSource('', 'https://edexcel.college/?utm_source=facebook&utm_medium=social');
        $this->assertSame('social', $res['source']);
    }

    public function testUrlSanitizationStripsSensitiveData(): void
    {
        $dirty = '/student/portal.php?token=xyz123&password=secretpass&page=2&utm_source=google';
        $clean = SiteVisitorAnalyticsService::cleanUrl($dirty);

        $this->assertStringNotContainsString('password', $clean);
        $this->assertStringNotContainsString('xyz123', $clean);
        $this->assertStringContainsString('page=2', $clean);
        $this->assertStringContainsString('utm_source=google', $clean);
        $this->assertStringStartsWith('/student/portal.php', $clean);
    }

    public function testIpAnonymization(): void
    {
        $ip1 = '192.168.1.100';
        $hash1 = SiteVisitorAnalyticsService::anonymizeIp($ip1);
        $hash2 = SiteVisitorAnalyticsService::anonymizeIp($ip1);
        $this->assertSame($hash1, $hash2);
        $this->assertNotSame($ip1, $hash1);
        $this->assertSame(32, strlen($hash1));

        $ip2 = '203.0.113.195';
        $hash3 = SiteVisitorAnalyticsService::anonymizeIp($ip2);
        $this->assertNotSame($hash1, $hash3);

        $this->assertSame('', SiteVisitorAnalyticsService::anonymizeIp(''));
    }

    public function testCountryNameAndFlag(): void
    {
        $this->assertSame('Sri Lanka', SiteVisitorAnalyticsService::countryNameFromCode('LK'));
        $this->assertSame('United Kingdom', SiteVisitorAnalyticsService::countryNameFromCode('GB'));
        $this->assertSame('United States', SiteVisitorAnalyticsService::countryNameFromCode('US'));
        $this->assertSame('Unknown', SiteVisitorAnalyticsService::countryNameFromCode(''));

        $this->assertNotEmpty(SiteVisitorAnalyticsService::countryFlagEmoji('LK'));
        $this->assertSame('🌐', SiteVisitorAnalyticsService::countryFlagEmoji(''));
    }

    public function testDurationFormatting(): void
    {
        $this->assertSame('< 10s', SiteVisitorAnalyticsService::formatDuration(0));
        $this->assertSame('45s', SiteVisitorAnalyticsService::formatDuration(45));
        $this->assertSame('2m 15s', SiteVisitorAnalyticsService::formatDuration(135));
        $this->assertSame('1h 5m', SiteVisitorAnalyticsService::formatDuration(3900));
    }

    public function testDatabaseOperations(): void
    {
        $pdo = $this->sqlite();
        SiteVisitorAnalyticsService::ensureSchema($pdo);

        // 1. Record session 1, page 1
        $r1 = SiteVisitorAnalyticsService::recordVisit($pdo, [
            'visitor_id' => 'v_test_visitor_1',
            'session_id' => 's_test_session_1',
            'page_url' => '/about',
            'page_title' => 'About Edexcel College',
            'referrer_url' => 'https://www.google.com/search',
            'country_code' => 'LK',
            'city' => 'Kandy',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
        ]);
        $this->assertTrue($r1['ok']);
        $this->assertTrue($r1['is_new']);

        // 2. Record session 1, page 2 (same session, second pageview)
        $r2 = SiteVisitorAnalyticsService::recordVisit($pdo, [
            'visitor_id' => 'v_test_visitor_1',
            'session_id' => 's_test_session_1',
            'page_url' => '/edexcel-classes',
            'page_title' => 'Classes - Edexcel College',
            'referrer_url' => '/about',
            'country_code' => 'LK',
            'city' => 'Kandy',
        ]);
        $this->assertTrue($r2['ok']);
        $this->assertFalse($r2['is_new']);

        // 3. Record session 2 for different visitor
        $r3 = SiteVisitorAnalyticsService::recordVisit($pdo, [
            'visitor_id' => 'v_test_visitor_2',
            'session_id' => 's_test_session_2',
            'page_url' => '/contact',
            'page_title' => 'Contact Us',
            'referrer_url' => 'https://facebook.com/edexcel',
            'country_code' => 'GB',
            'city' => 'London',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1',
        ]);
        $this->assertTrue($r3['ok']);

        // 4. Update Heartbeat
        $hb = SiteVisitorAnalyticsService::updateHeartbeat($pdo, 's_test_session_1', 120, '/edexcel-classes');
        $this->assertTrue($hb);

        // 5. Query Dashboard Stats
        $today = date('Y-m-d');
        $stats = SiteVisitorAnalyticsService::getDashboardStats($pdo, $today, $today);

        $this->assertSame(2, $stats['current']['visits']);
        $this->assertSame(2, $stats['current']['unique_visitors']);
        $this->assertSame(3, $stats['current']['pageviews']);
        $this->assertGreaterThan(0, $stats['current']['avg_duration']);

        // 6. Query Top Pages
        $topPages = SiteVisitorAnalyticsService::getTopPages($pdo, $today, $today);
        $this->assertNotEmpty($topPages);
        $paths = array_column($topPages, 'page_path');
        $this->assertContains('/about', $paths);
        $this->assertContains('/edexcel-classes', $paths);
        $this->assertContains('/contact', $paths);

        // 7. Query Top Entry & Exit Pages
        $entryPages = SiteVisitorAnalyticsService::getTopEntryPages($pdo, $today, $today);
        $entryPaths = array_column($entryPages, 'entry_page');
        $this->assertContains('/about', $entryPaths);
        $this->assertContains('/contact', $entryPaths);

        // 8. Device Breakdown
        $devices = SiteVisitorAnalyticsService::getDeviceBreakdown($pdo, $today, $today);
        $this->assertSame(1, $devices['desktop']['count']);
        $this->assertSame(1, $devices['mobile']['count']);

        // 9. Traffic Sources
        $sources = SiteVisitorAnalyticsService::getTrafficSources($pdo, $today, $today);
        $srcMap = array_column($sources, 'count', 'source');
        $this->assertSame(1, $srcMap['google'] ?? 0);
        $this->assertSame(1, $srcMap['social'] ?? 0);

        // 10. Location Stats
        $locs = SiteVisitorAnalyticsService::getLocationStats($pdo, $today, $today);
        $this->assertNotEmpty($locs['countries']);
        $countryCodes = array_column($locs['countries'], 'code');
        $this->assertContains('LK', $countryCodes);
        $this->assertContains('GB', $countryCodes);

        // 11. Timeline
        $timeline = SiteVisitorAnalyticsService::getTimeline($pdo, $today, $today);
        $this->assertNotEmpty($timeline['labels']);
        $this->assertSame([2], $timeline['visits']);
        $this->assertSame([3], $timeline['pageviews']);

        // 12. Sessions Explorer & Search
        $sessResult = SiteVisitorAnalyticsService::getSessions($pdo, ['from' => $today, 'to' => $today], 1, 10);
        $this->assertSame(2, $sessResult['total']);
        $this->assertCount(2, $sessResult['records']);

        // Filter by device
        $mobileSess = SiteVisitorAnalyticsService::getSessions($pdo, ['from' => $today, 'to' => $today, 'device_type' => 'mobile']);
        $this->assertSame(1, $mobileSess['total']);
        $this->assertSame('mobile', $mobileSess['records'][0]['device_type']);

        // Search by query
        $searchSess = SiteVisitorAnalyticsService::getSessions($pdo, ['from' => $today, 'to' => $today, 'q' => 'London']);
        $this->assertSame(1, $searchSess['total']);
        $this->assertSame('London', $searchSess['records'][0]['city']);

        // 13. Real-time active visitors
        $active = SiteVisitorAnalyticsService::getRealtimeActiveCount($pdo, 10);
        $this->assertSame(2, $active);
    }
}
