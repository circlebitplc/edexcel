<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\HealthAlertService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class HealthAlertThrottleTest extends TestCase
{
    public function testBuildChecksMarksCriticalWhenRed(): void
    {
        $svc = new HealthAlertService(new PDO('sqlite::memory:'));
        $method = new ReflectionMethod(HealthAlertService::class, 'buildChecks');
        $method->setAccessible(true);

        $checks = $method->invoke($svc, [
            'database' => ['status' => 'red', 'message' => 'down'],
            'cron' => ['status' => 'green', 'stale_names' => []],
            'backup' => ['status' => 'green', 'label' => 'ok'],
            'disk' => ['status' => 'green', 'used_percent' => 40],
            'whatsapp' => ['status' => 'green'],
            'onepay' => ['status' => 'green'],
            'bunny' => ['status' => 'green', 'stuck_processing' => 0],
            'livekit' => ['status' => 'green'],
            'payments' => ['failed' => 2],
            'outbox' => ['pending' => 1, 'failed_today' => 0],
            'migrations' => ['status' => 'green'],
        ]);

        $byKey = [];
        foreach ($checks as $c) {
            $byKey[$c['key']] = $c;
        }

        $this->assertTrue($byKey['database_unavailable']['open']);
        $this->assertSame('critical', $byKey['database_unavailable']['severity']);
        $this->assertFalse($byKey['cron_stale']['open']);
        $this->assertFalse($byKey['failed_payments_spike']['open']);
    }

    public function testBuildChecksOpensFailedPaymentsAndOutboxThresholds(): void
    {
        $svc = new HealthAlertService(new PDO('sqlite::memory:'));
        $method = new ReflectionMethod(HealthAlertService::class, 'buildChecks');
        $method->setAccessible(true);

        $checks = $method->invoke($svc, [
            'database' => ['status' => 'green'],
            'cron' => ['status' => 'green', 'stale_names' => []],
            'backup' => ['status' => 'green'],
            'disk' => ['status' => 'yellow', 'used_percent' => 88],
            'whatsapp' => ['status' => 'green'],
            'onepay' => ['status' => 'red', 'message' => 'auth'],
            'bunny' => ['status' => 'green', 'stuck_processing' => 5],
            'livekit' => ['status' => 'red', 'message' => 'timeout'],
            'payments' => ['failed' => 12],
            'outbox' => ['pending' => 60, 'failed_today' => 0],
            'migrations' => ['status' => 'green'],
        ]);

        $byKey = [];
        foreach ($checks as $c) {
            $byKey[$c['key']] = $c;
        }

        $this->assertTrue($byKey['disk_low']['open']);
        $this->assertSame('warning', $byKey['disk_low']['severity']);
        $this->assertTrue($byKey['onepay_fail']['open']);
        $this->assertTrue($byKey['bunny_stuck']['open']);
        $this->assertTrue($byKey['livekit_down']['open']);
        $this->assertTrue($byKey['failed_payments_spike']['open']);
        $this->assertTrue($byKey['whatsapp_outbox_stuck']['open']);
    }

    public function testBuildChecksAlwaysReturnsKnownKeys(): void
    {
        $svc = new HealthAlertService(new PDO('sqlite::memory:'));
        $method = new ReflectionMethod(HealthAlertService::class, 'buildChecks');
        $method->setAccessible(true);
        $checks = $method->invoke($svc, []);
        $keys = array_column($checks, 'key');

        $this->assertContains('database_unavailable', $keys);
        $this->assertContains('backup_failed', $keys);
        $this->assertContains('schema_migration_fail', $keys);
        $this->assertCount(11, $checks);
    }
}
