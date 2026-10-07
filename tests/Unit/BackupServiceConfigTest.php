<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\AppLogger;
use Edexcel\Services\BackupService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class BackupServiceConfigTest extends TestCase
{
    public function testConfigDefaultsWithoutOpsSetting(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $svc = new BackupService($pdo);
        $cfg = $svc->config();

        $this->assertTrue($cfg['enabled']);
        $this->assertTrue($cfg['database_enabled']);
        $this->assertTrue($cfg['files_enabled']);
        $this->assertSame('storage/backups', $cfg['location']);
        $this->assertSame('daily', $cfg['frequency']);
        $this->assertSame(7, $cfg['retention_daily']);
        $this->assertSame(4, $cfg['retention_weekly']);
        $this->assertSame(3, $cfg['retention_monthly']);
        $this->assertFalse($cfg['offsite_enabled']);
        $this->assertFalse($cfg['encryption_enabled']);
        $this->assertArrayHasKey('offsite_path', $cfg);
    }

    public function testExcludeListsBlockSecretsAndVendor(): void
    {
        $ref = new ReflectionClass(BackupService::class);
        $names = $ref->getConstant('EXCLUDE_NAMES');
        $dirs = $ref->getConstant('EXCLUDE_DIR_NAMES');

        $this->assertIsArray($names);
        $this->assertContains('.env', $names);
        $this->assertContains('sftp.json', $names);
        $this->assertIsArray($dirs);
        $this->assertContains('vendor', $dirs);
        $this->assertContains('storage/backups', $dirs);
    }

    public function testAppLoggerRedactsSecretsViaReflection(): void
    {
        $logger = new AppLogger(null);
        $method = new ReflectionMethod(AppLogger::class, 'redact');
        $method->setAccessible(true);

        $safe = $method->invoke($logger, [
            'user_id' => 42,
            'password' => 'secret123',
            'api_key' => 'abc',
            'nested' => ['otp' => '999999', 'lesson_id' => 7],
            'note' => 'ok',
        ]);

        $this->assertSame(42, $safe['user_id']);
        $this->assertSame('[REDACTED]', $safe['password']);
        $this->assertSame('[REDACTED]', $safe['api_key']);
        $this->assertSame('[REDACTED]', $safe['nested']['otp']);
        $this->assertSame(7, $safe['nested']['lesson_id']);
        $this->assertSame('ok', $safe['note']);
    }
}
