<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\CommunicationRulePackService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CommunicationReliabilityTest extends TestCase
{
    public function testProviderStatusMapsDeliveredAndRead(): void
    {
        $pdo = $this->createMock(PDO::class);
        $select = $this->createMock(PDOStatement::class);
        $select->method('execute')->willReturn(true);
        $select->method('fetch')->willReturn(['id' => 42, 'status' => 'sent']);

        $update = $this->createMock(PDOStatement::class);
        $update->method('execute')->willReturn(true);

        $event = $this->createMock(PDOStatement::class);
        $event->method('execute')->willReturn(true);

        $webhook = $this->createMock(PDOStatement::class);
        $webhook->method('execute')->willReturn(true);

        $pdo->method('prepare')->willReturnCallback(static function (string $sql) use ($select, $update, $event, $webhook) {
            if (str_contains($sql, 'FROM communication_recipients WHERE provider_message_id')) {
                return $select;
            }
            if (str_contains($sql, 'UPDATE communication_recipients SET status')) {
                return $update;
            }
            if (str_contains($sql, 'INSERT INTO communication_delivery_events')) {
                return $event;
            }
            return $webhook;
        });
        $pdo->method('exec')->willReturn(0);

        $hub = new CommunicationHubService($pdo);
        $out = $hub->recordProviderStatus('wamid.ABC', 'delivered', 'whatsapp', 'Meta status delivered');
        $this->assertTrue($out['matched']);
        $this->assertSame(42, $out['recipient_id']);
        $this->assertSame('delivered', $out['mapped_status']);

        $outRead = $hub->recordProviderStatus('wamid.ABC', 'read', 'whatsapp');
        $this->assertSame('read', $outRead['mapped_status']);
    }

    public function testProviderStatusMapsFailed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $select = $this->createMock(PDOStatement::class);
        $select->method('execute')->willReturn(true);
        $select->method('fetch')->willReturn(['id' => 7, 'status' => 'sent']);
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $pdo->method('prepare')->willReturnCallback(static function (string $sql) use ($select, $stmt) {
            return str_contains($sql, 'FROM communication_recipients') ? $select : $stmt;
        });
        $pdo->method('exec')->willReturn(0);
        $hub = new CommunicationHubService($pdo);
        $out = $hub->recordProviderStatus('sms-1', 'failed', 'sms', 'undelivered');
        $this->assertSame('failed', $out['mapped_status']);
        $this->assertTrue($out['matched']);
    }

    public function testEmptyProviderIdDoesNotMatch(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('exec')->willReturn(0);
        $hub = new CommunicationHubService($pdo);
        $out = $hub->recordProviderStatus('', 'delivered');
        $this->assertFalse($out['matched']);
        $this->assertSame(0, $out['recipient_id']);
    }

    public function testRulePackServiceExists(): void
    {
        $this->assertTrue(class_exists(CommunicationRulePackService::class));
        $this->assertTrue(method_exists(CommunicationHubService::class, 'recordProviderStatus'));
    }

    public function testSmsWebhookFileExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/api/sms/webhook.php');
        $this->assertFileExists(dirname(__DIR__, 2) . '/database/migrations/035_communication_channel_reliability.sql');
    }
}
