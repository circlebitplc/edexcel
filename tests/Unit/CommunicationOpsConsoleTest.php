<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\ParentCommunicationTimelineService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommunicationOpsConsoleTest extends TestCase
{
    public function testMaxRetriesConstant(): void
    {
        $this->assertSame(3, CommunicationHubService::MAX_RETRIES);
    }

    public function testRetryFailedRejectsNonFailed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['id' => 1, 'status' => 'sent', 'retry_count' => 0, 'channel' => 'sms', 'phone' => '94771234567', 'message_id' => 9]);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('exec')->willReturn(0);
        $hub = new CommunicationHubService($pdo);
        $this->expectException(RuntimeException::class);
        $hub->retryFailed(1, 1);
    }

    public function testRetryFailedRejectsMaxRetries(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 2,
            'status' => 'failed',
            'retry_count' => 3,
            'channel' => 'whatsapp',
            'phone' => '94771234567',
            'message_id' => 9,
        ]);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('exec')->willReturn(0);
        $hub = new CommunicationHubService($pdo);
        $this->expectException(RuntimeException::class);
        $hub->retryFailed(2, 1);
    }

    public function testParentTimelineServiceExists(): void
    {
        $this->assertTrue(class_exists(ParentCommunicationTimelineService::class));
        $this->assertFileExists(dirname(__DIR__, 2) . '/parent/history.php');
        $this->assertFileExists(dirname(__DIR__, 2) . '/admin/communication_ops.php');
        $this->assertFileExists(dirname(__DIR__, 2) . '/database/migrations/036_communication_ops_retries.sql');
    }
}
