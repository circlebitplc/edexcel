<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Http\AbuseGuard;
use Edexcel\Http\EdgeProtectionStatus;
use PHPUnit\Framework\TestCase;

final class AbuseProtectionTest extends TestCase
{
    private string $store;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/config/client_ip.php';
        $this->store = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eck-abuse-' . bin2hex(random_bytes(4));
        AbuseGuard::useStore($this->store);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_CF_VISITOR']);
    }

    protected function tearDown(): void
    {
        AbuseGuard::useStore(null);
        $this->removeDir($this->store);
    }

    public function testSpoofedForwardedForIsIgnoredFromUntrustedPeer(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.21';
        $this->assertFalse(eck_proxy_is_trusted());
        $this->assertSame('203.0.113.10', eck_client_ip());
        $this->assertFalse(eck_request_is_https());
    }

    public function testCloudflareHeaderIsUsedOnlyFromCloudflare(): void
    {
        $_SERVER['REMOTE_ADDR'] = '104.16.1.20';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.44';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(eck_proxy_is_trusted());
        $this->assertSame('203.0.113.44', eck_client_ip());
        $this->assertTrue(eck_request_is_https());
    }

    public function testPrivateProxyMayUseForwardedFor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.1.2.3';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(eck_proxy_is_trusted());
        $this->assertSame('198.51.100.9', eck_client_ip());
        $this->assertTrue(eck_request_is_https());
    }

    public function testLoopbackProxyMayUseForwardedFor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.8, 127.0.0.1';
        $this->assertSame('198.51.100.8', eck_client_ip());
    }

    public function testRateLimitTripsThenExpires(): void
    {
        $this->assertTrue(AbuseGuard::allow('login', 'ip:203.0.113.10', 2, 1, 0));
        $this->assertTrue(AbuseGuard::allow('login', 'ip:203.0.113.10', 2, 1, 0));
        $this->assertFalse(AbuseGuard::allow('login', 'ip:203.0.113.10', 2, 1, 0));
        sleep(1);
        $this->assertTrue(AbuseGuard::allow('login', 'ip:203.0.113.10', 2, 1, 0));
    }

    public function testManualBlockIsTemporaryAndClearable(): void
    {
        $this->assertTrue(AbuseGuard::block('198.51.100.50', 120, 'Test flood'));
        $block = AbuseGuard::manualBlock('198.51.100.50');
        $this->assertNotNull($block);
        $this->assertSame('Test flood', $block['reason']);
        $this->assertLessThanOrEqual(time() + 120, $block['until']);
        $this->assertGreaterThan(time(), $block['until']);
        AbuseGuard::clearIp('198.51.100.50');
        $this->assertNull(AbuseGuard::manualBlock('198.51.100.50'));
    }

    public function testThresholdFloorsPreventLockingEveryone(): void
    {
        AbuseGuard::saveThresholds([
            'global_anon' => ['max' => 1, 'window' => 1, 'block' => 999999],
            'classroom_user' => ['max' => 1, 'window' => 60, 'block' => 20],
        ]);
        $rules = AbuseGuard::thresholds();
        $this->assertGreaterThanOrEqual(60, $rules['global_anon']['max']);
        $this->assertGreaterThanOrEqual(60, $rules['classroom_user']['max']);
        $this->assertLessThanOrEqual(86400, $rules['global_anon']['block']);
    }

    public function testEventLayerDistinguishesOriginLockFromRateLimit(): void
    {
        AbuseGuard::noteRejection('login', 'Rate limit reached', 30);
        AbuseGuard::noteRejection('origin', 'Direct origin request rejected', 30);
        $events = AbuseGuard::recentEvents(5);
        $layers = array_column($events, 'layer');
        $this->assertContains('application-rate-limit', $layers);
        $this->assertContains('origin-lock', $layers);
    }

    public function testEdgeStatusDoesNotClaimCloudflareOrFirewall(): void
    {
        unset($_SERVER['HTTP_CF_RAY']);
        putenv('ORIGIN_LOCK_CLOUDFLARE');
        $items = [];
        foreach (EdgeProtectionStatus::items() as $item) {
            $items[$item['label']] = $item['state'];
        }
        $this->assertSame('NO', $items['Cloudflare']);
        $this->assertSame('INACTIVE', $items['WAF']);
        $this->assertSame('INACTIVE', $items['Bot protection']);
        $this->assertSame('INACTIVE', $items['Edge rate limiting']);
        $this->assertSame('DISABLED', $items['Origin lock']);
        $this->assertSame('OK', $items['Trusted proxy']);
        $this->assertSame('NOT VERIFIED', $items['Admin SSH access']);
        $this->assertNotSame('PROTECTED', $items['IPv4']);
        $this->assertNotSame('PROTECTED', $items['IPv6']);
        $this->assertNotSame('RESTRICTED', $items['8888']);
        $this->assertNotSame('RESTRICTED', $items['7080']);
        $this->assertNotSame('RESTRICTED', $items['8090']);
        $this->assertNotSame('PROTECTED', $items['SSH']);
        $this->assertContains($items['Firewall'], ['ACTIVE', 'UNKNOWN']);
        $this->assertContains($items['Firewall policy'], ['CONFIRMED', 'NOT CONFIRMED']);
        $this->assertSame('ACTIVE', $items['AbuseGuard']);
        $this->assertSame('ACTIVE', $items['API Guard']);
    }

    public function testLoginAndPaymentRulesAreSeparateFromClassroomBudget(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/student/pay_lesson.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = 42;
        $names = array_column(AbuseGuard::rulesFor('/student/pay_lesson.php'), 'bucket');
        $this->assertContains('payment_create', $names);
        $this->assertNotContains('classroom_user', $names);
        $_SESSION = [];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $path = $item->getPathname();
            $item->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
